<?php
namespace app\service;

use app\exception\ApiException;
use app\model\Note;
use app\model\Project;
use app\model\Tag;
use HTMLPurifier;
use HTMLPurifier_Config;

class NoteService
{
    private static ?HTMLPurifier $purifier = null;

    public static function purifier(): HTMLPurifier
    {
        if (self::$purifier) {
            return self::$purifier;
        }
        $cacheDir = runtime_path('purifier');
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('Cache.SerializerPath', $cacheDir);
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set('HTML.DefinitionID', 'niuma-memo-editor');
        $config->set('HTML.DefinitionRev', 3);
        $config->set('HTML.Allowed', implode(',', [
            'p', 'br', 'hr', 'h1', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'code', 'pre[data-language]',
            'blockquote', 'ul[data-type]', 'ol', 'li[data-type|data-checked]', 'a[href|title|target|rel]',
            'img[src|alt|title|width|height|data-id]', 'span[style]', 'mark', 'div', 'table', 'thead', 'tbody', 'tr', 'th[colspan|rowspan]', 'td[colspan|rowspan]', 'sup', 'sub',
        ]));
        $config->set('CSS.AllowedProperties', 'color,background-color,text-align');
        $config->set('AutoFormat.RemoveEmpty', false);
        $config->set('HTML.TargetBlank', true);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);

        $def = $config->maybeGetRawHTMLDefinition();
        if ($def) {
            $def->addElement('mark', 'Inline', 'Inline', 'Common');
            $def->addAttribute('ul', 'data-type', 'Text');
            $def->addAttribute('li', 'data-type', 'Text');
            $def->addAttribute('li', 'data-checked', 'Text');
            $def->addAttribute('img', 'data-id', 'Text');
            $def->addAttribute('pre', 'data-language', 'Text');
        }
        return self::$purifier = new HTMLPurifier($config);
    }

    public static function purify(string $html): string
    {
        if ($html === '') {
            return '';
        }
        $clean = self::purifier()->purify($html);
        // 任务列表里 <label><input> 被过滤后留下的空 span
        return (string) preg_replace('#<span>\s*</span>#', '', $clean);
    }

    public static function toText(string $html): string
    {
        if ($html === '') {
            return '';
        }
        $html = preg_replace('#<(br|/p|/li|/h[1-6]|/div|/tr|/blockquote|/pre)[^>]*>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = preg_replace('/\s*\n\s*/u', "\n", $text);
        return trim((string) $text);
    }

    public function save(int $uid, array $data, ?Note $note = null): Note
    {
        $isNew = $note === null;
        if ($isNew) {
            $note = new Note();
            $note->user_id = $uid;
        }

        if (array_key_exists('project_id', $data)) {
            $pid = (int) $data['project_id'];
            if ($pid > 0) {
                if (!Project::where('user_id', $uid)->where('id', $pid)->find()) {
                    throw new ApiException('项目不存在', 404);
                }
                $note->project_id = $pid;
            } else {
                $note->project_id = null;
            }
        }
        if (array_key_exists('content', $data)) {
            $html = self::purify((string) $data['content']);
            $note->content      = $html;
            $note->content_text = self::toText($html);
        }
        if (array_key_exists('title', $data)) {
            $note->title = mb_substr(trim((string) $data['title']), 0, 200);
        }
        if ($note->title === '' || $note->title === null) {
            // 快速记录不填标题：取正文第一行
            $first = strtok((string) ($note->content_text ?? ''), "\n");
            $note->title = mb_substr(trim((string) $first), 0, 60);
        }
        foreach (['is_pinned', 'is_archived'] as $f) {
            if (array_key_exists($f, $data)) {
                $note->$f = (int) $data[$f];
            }
        }
        $note->save();

        if (array_key_exists('tags', $data) && is_array($data['tags'])) {
            $this->syncTags($note, $data['tags'], $uid);
        }
        if (!empty($data['reminder']) && is_array($data['reminder'])) {
            (new ReminderService())->create($uid, $data['reminder'] + ['note_id' => $note->id]);
        }

        return $note;
    }

    /** @param array<int|string> $tags 标签名或标签 ID */
    public function syncTags(Note $note, array $tags, int $uid): void
    {
        $ids = [];
        foreach ($tags as $t) {
            if (is_int($t) || ctype_digit((string) $t)) {
                $tag = Tag::where('user_id', $uid)->where('id', (int) $t)->find();
            } else {
                $name = mb_substr(trim((string) $t), 0, 30);
                if ($name === '') {
                    continue;
                }
                $tag = Tag::where('user_id', $uid)->where('name', $name)->find();
                if (!$tag) {
                    $tag = Tag::create(['user_id' => $uid, 'name' => $name, 'color' => '']);
                }
            }
            if ($tag) {
                $ids[(int) $tag->id] = (int) $tag->id;
            }
        }
        $note->tags()->detach();
        if ($ids) {
            $note->tags()->attach(array_values($ids));
        }
    }

    /**
     * 列表查询构造
     * filters: project_id, tag_id, keyword, archived(0/1/all), pinned, date_from, date_to
     */
    public function query(int $uid, array $f)
    {
        $q = Note::where('note.user_id', $uid);

        if (isset($f['project_id']) && $f['project_id'] !== '' && $f['project_id'] !== null) {
            $pid = (int) $f['project_id'];
            if ($pid > 0) $q->where('note.project_id', $pid);
        }
        if (!empty($f['tag_id'])) {
            $noteIds = \think\facade\Db::name('note_tag')->where('tag_id', (int) $f['tag_id'])->column('note_id');
            $q->whereIn('note.id', $noteIds ?: [0]);
        }
        $archived = $f['archived'] ?? '0';
        if ($archived !== 'all') {
            $q->where('note.is_archived', (int) $archived);
        }
        if (isset($f['pinned']) && $f['pinned'] !== '') {
            $q->where('note.is_pinned', (int) $f['pinned']);
        }
        if (!empty($f['date_from'])) {
            $q->where('note.created_at', '>=', $f['date_from'] . ' 00:00:00');
        }
        if (!empty($f['date_to'])) {
            $q->where('note.created_at', '<=', $f['date_to'] . ' 23:59:59');
        }
        if (!empty($f['keyword'])) {
            $this->applyKeyword($q, trim((string) $f['keyword']));
        }

        $q->with([
            'tags',
            'attachments' => function ($aq) {
                $aq->field('id,note_id,file_type,original_name,thumb_path,duration,asr_status,size');
            },
            'reminders' => function ($rq) {
                $rq->whereIn('status', [1, 2])->order('remind_at', 'asc');
            },
        ]);

        return $q->order('note.is_pinned', 'desc')->order('note.updated_at', 'desc');
    }

    public function applyKeyword($q, string $kw): void
    {
        if ($kw === '') {
            return;
        }
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $kw) . '%';
        if (mb_strlen($kw) >= 2) {
            $phrase = '"' . str_replace('"', '', $kw) . '"';
            $q->where(function ($w) use ($phrase, $like) {
                $w->whereRaw('MATCH(note.title, note.content_text) AGAINST (? IN BOOLEAN MODE)', [$phrase])
                  ->whereOr('note.title', 'like', $like);
            });
        } else {
            $q->where(function ($w) use ($like) {
                $w->where('note.title', 'like', $like)->whereOr('note.content_text', 'like', $like);
            });
        }
    }

    /** 列表项瘦身：去掉正文，补摘要 */
    public static function toListItem(array $row): array
    {
        $text = (string) ($row['content_text'] ?? '');
        $row['summary'] = mb_substr(preg_replace('/\s+/u', ' ', $text), 0, 140);
        $row['next_reminder'] = $row['reminders'][0] ?? null;
        $row['attachment_count'] = count($row['attachments'] ?? []);
        $row['images'] = array_values(array_filter(array_map(function ($a) {
            return ($a['file_type'] === 'image' && !empty($a['thumb_url'])) ? $a['thumb_url'] : null;
        }, $row['attachments'] ?? [])));
        $row['has_audio'] = (bool) array_filter($row['attachments'] ?? [], fn($a) => $a['file_type'] === 'audio');
        unset($row['content'], $row['content_text'], $row['reminders']);
        return $row;
    }
}
