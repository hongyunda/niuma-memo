<?php
namespace app\controller\api;

use app\exception\ApiException;
use app\model\Reminder as ReminderModel;
use app\service\ReminderService;

class Reminder extends Base
{
    private ReminderService $svc;

    protected function initialize()
    {
        $this->svc = new ReminderService();
    }

    /** scope: upcoming（默认）| overdue | done | all */
    public function index()
    {
        [$page, $limit] = $this->paging(30);
        $q     = ReminderModel::where('user_id', $this->uid())->with(['note' => fn($n) => $n->field('id,title,project_id')]);
        $scope = (string) $this->request->get('scope', 'upcoming');
        $now   = date('Y-m-d H:i:s');
        switch ($scope) {
            case 'overdue':
                $q->whereIn('status', [1, 2])->where('remind_at', '<', $now)->order('remind_at', 'desc');
                break;
            case 'done':
                $q->whereIn('status', [3, 4])->order('updated_at', 'desc');
                break;
            case 'all':
                $q->order('remind_at', 'desc');
                break;
            default:
                $q->whereIn('status', [1, 2])->where('remind_at', '>=', $now)->order('remind_at', 'asc');
        }
        if ($nid = $this->request->get('note_id')) {
            $q->where('note_id', (int) $nid);
        }
        return $this->success($this->paginate($q->paginate(['list_rows' => $limit, 'page' => $page])));
    }

    /** month=2026-09 → 按日期分组 */
    public function calendar()
    {
        $month = (string) $this->request->get('month', date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new ApiException('月份格式应为 YYYY-MM', 422);
        }
        $start = $month . '-01 00:00:00';
        $end   = date('Y-m-t 23:59:59', strtotime($start));
        $rows  = ReminderModel::where('user_id', $this->uid())->whereBetween('remind_at', [$start, $end])
            ->with(['note' => fn($n) => $n->field('id,title,project_id')])->order('remind_at', 'asc')->select()->toArray();
        $days = [];
        foreach ($rows as $r) {
            $days[substr((string) $r['remind_at'], 0, 10)][] = $r;
        }
        return $this->success(['month' => $month, 'days' => $days]);
    }

    public function save()
    {
        $r = $this->svc->create($this->uid(), $this->request->post());
        return $this->success($r);
    }

    public function update(int $id)
    {
        $r = $this->svc->update($this->find($id), $this->request->put());
        return $this->success($r);
    }

    public function delete(int $id)
    {
        $this->find($id)->delete();
        return $this->success();
    }

    public function snooze(int $id)
    {
        $r = $this->find($id);
        $this->svc->snooze($r, (int) $this->request->put('minutes', 10));
        return $this->success($r);
    }

    public function done(int $id)
    {
        $r = $this->find($id);
        $this->svc->done($r);
        return $this->success($r);
    }

    private function find(int $id): ReminderModel
    {
        $r = ReminderModel::where('user_id', $this->uid())->find($id);
        if (!$r) {
            throw new ApiException('提醒不存在', 404);
        }
        return $r;
    }
}
