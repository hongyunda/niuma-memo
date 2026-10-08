<?php
namespace app\service;

use app\exception\ApiException;
use app\model\Note;
use app\model\Reminder;
use app\model\ReminderLog;
use app\model\User;
use app\service\push\PushService;
use DateTimeImmutable;
use Throwable;

class ReminderService
{
    public function create(int $uid, array $data): Reminder
    {
        $r = new Reminder();
        $r->user_id = $uid;
        $r->status  = Reminder::STATUS_PENDING;
        $this->fill($r, $data, true);
        $r->save();
        return $r;
    }

    public function update(Reminder $r, array $data): Reminder
    {
        $this->fill($r, $data, false);
        // 修改时间后重新进入待提醒
        if (isset($data['remind_at'])) {
            $r->status       = Reminder::STATUS_PENDING;
            $r->snooze_until = null;
        }
        $r->save();
        return $r;
    }

    private function fill(Reminder $r, array $data, bool $isNew): void
    {
        if (isset($data['note_id'])) {
            $nid = (int) $data['note_id'];
            if ($nid > 0) {
                $note = Note::where('user_id', $r->user_id)->find($nid);
                if (!$note) {
                    throw new ApiException('记录不存在', 404);
                }
                $r->note_id = $nid;
                if (empty($data['title']) && !$r->title) {
                    $r->title = $note->title ?: '提醒';
                }
            } else {
                $r->note_id = null;
            }
        }
        if (isset($data['title'])) {
            $r->title = mb_substr(trim((string) $data['title']), 0, 200);
        }
        if ($isNew && !$r->title) {
            $r->title = '提醒';
        }
        if (isset($data['remind_at'])) {
            $ts = strtotime((string) $data['remind_at']);
            if (!$ts) {
                throw new ApiException('提醒时间格式不正确', 422);
            }
            $r->remind_at = date('Y-m-d H:i:00', $ts);
        } elseif ($isNew) {
            throw new ApiException('请选择提醒时间', 422);
        }
        if (isset($data['advance_minutes'])) {
            $r->advance_minutes = max(0, (int) $data['advance_minutes']);
        }
        if (isset($data['repeat_type'])) {
            $rt = (int) $data['repeat_type'];
            if ($rt < 0 || $rt > 5) {
                throw new ApiException('重复类型不正确', 422);
            }
            $r->repeat_type = $rt;
        }
        if (array_key_exists('repeat_until', $data)) {
            $r->repeat_until = $data['repeat_until'] ? date('Y-m-d 23:59:59', strtotime((string) $data['repeat_until'])) : null;
        }
        if (isset($data['channels'])) {
            $channels = is_array($data['channels']) ? $data['channels'] : explode(',', (string) $data['channels']);
            $allowed  = array_keys((array) config('push.channels'));
            $channels = array_values(array_intersect(array_map('trim', $channels), $allowed));
            $r->channels = implode(',', $channels ?: ['webpush']);
        } elseif ($isNew && !$r->channels) {
            $user = User::find($r->user_id);
            $defaults = $user ? ($user->pushConfig()['default_channels'] ?? null) : null;
            $r->channels = is_array($defaults) && $defaults ? implode(',', $defaults) : 'webpush';
        }
    }

    public function snooze(Reminder $r, int $minutes): void
    {
        $minutes = max(1, min(60 * 24 * 30, $minutes));
        $r->snooze_until = date('Y-m-d H:i:00', time() + $minutes * 60);
        $r->status       = Reminder::STATUS_PENDING;
        $r->save();
    }

    public function done(Reminder $r): void
    {
        $r->snooze_until = null;
        if ($r->repeat_type > 0) {
            $next = $this->nextOccurrence($r, new DateTimeImmutable());
            if ($next) {
                $r->remind_at = $next->format('Y-m-d H:i:s');
                $r->status    = Reminder::STATUS_PENDING;
                $r->save();
                return;
            }
        }
        $r->status = Reminder::STATUS_DONE;
        $r->save();
    }

    /** 计算 $after 之后的下一次发生时间；到达 repeat_until 返回 null */
    public function nextOccurrence(Reminder $r, DateTimeImmutable $after): ?DateTimeImmutable
    {
        if ((int) $r->repeat_type === Reminder::REPEAT_NONE) {
            return null;
        }
        $dt   = new DateTimeImmutable((string) $r->remind_at);
        $base = $dt;
        $i    = 0;
        do {
            $i++;
            switch ((int) $r->repeat_type) {
                case Reminder::REPEAT_DAILY:
                    $dt = $base->modify("+{$i} day");
                    break;
                case Reminder::REPEAT_WEEKLY:
                    $dt = $base->modify("+{$i} week");
                    break;
                case Reminder::REPEAT_MONTHLY:
                    $dt = $this->addMonths($base, $i);
                    break;
                case Reminder::REPEAT_YEARLY:
                    $dt = $this->addMonths($base, $i * 12);
                    break;
                case Reminder::REPEAT_WORKDAY:
                    $dt = $base->modify("+{$i} day");
                    while ((int) $dt->format('N') >= 6) {
                        $i++;
                        $dt = $base->modify("+{$i} day");
                    }
                    break;
                default:
                    return null;
            }
            if ($i > 5000) {
                return null;
            }
        } while ($dt <= $after);

        if ($r->repeat_until && $dt > new DateTimeImmutable((string) $r->repeat_until)) {
            return null;
        }
        return $dt;
    }

    private function addMonths(DateTimeImmutable $base, int $months): DateTimeImmutable
    {
        $day   = (int) $base->format('j');
        $first = $base->modify('first day of this month')->modify("+{$months} month");
        $max   = (int) $first->format('t');
        return $first->setDate((int) $first->format('Y'), (int) $first->format('n'), min($day, $max));
    }

    /** 扫描到期提醒并推送，返回处理数量 */
    public function scanDue(): int
    {
        $now = date('Y-m-d H:i:s');
        $due = Reminder::where('status', Reminder::STATUS_PENDING)
            ->whereRaw('COALESCE(snooze_until, DATE_SUB(remind_at, INTERVAL advance_minutes MINUTE)) <= ?', [$now])
            ->order('remind_at', 'asc')
            ->limit(200)
            ->select();

        $count = 0;
        foreach ($due as $r) {
            try {
                $this->dispatch($r);
                $count++;
            } catch (Throwable $e) {
                trace('提醒发送异常 #' . $r->id . ': ' . $e->getMessage(), 'error');
            }
        }
        return $count;
    }

    public function dispatch(Reminder $r): array
    {
        $user = User::find($r->user_id);
        $note = $r->note_id ? Note::find($r->note_id) : null;

        $title = $r->title ?: ($note ? $note->title : '提醒');
        $body  = $note ? mb_substr((string) $note->content_text, 0, 120) : '时间到了';
        if ($body === '') {
            $body = date('m月d日 H:i', strtotime((string) $r->remind_at));
        }
        $url = rtrim((string) config('push.app_url'), '/') . ($note ? '/notes/' . $note->id : '/reminders');

        $results = [];
        if ($user) {
            $results = PushService::send($user, [
                'title'       => $title,
                'body'        => $body,
                'url'         => $url,
                'tag'         => 'reminder-' . $r->id,
                'reminder_id' => (int) $r->id,
                'note_id'     => $note ? (int) $note->id : null,
            ], $r->channelList() ?: ['webpush']);
        }
        $sentAt = date('Y-m-d H:i:s');
        foreach ($results as $channel => $res) {
            ReminderLog::create([
                'reminder_id' => $r->id,
                'channel'     => $channel,
                'success'     => $res['ok'] ? 1 : 0,
                'error'       => mb_substr((string) ($res['error'] ?? ''), 0, 500),
                'sent_at'     => $sentAt,
            ]);
        }

        $r->last_sent_at = $sentAt;
        $r->snooze_until = null;
        $next = $r->repeat_type > 0 ? $this->nextOccurrence($r, new DateTimeImmutable()) : null;
        if ($next) {
            $r->remind_at = $next->format('Y-m-d H:i:s');
            $r->status    = Reminder::STATUS_PENDING;
        } else {
            $r->status = Reminder::STATUS_SENT;
        }
        $r->save();

        return $results;
    }
}
