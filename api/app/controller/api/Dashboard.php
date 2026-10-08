<?php
namespace app\controller\api;

use app\model\Reminder;
use app\service\NoteService;

class Dashboard extends Base
{
    public function index()
    {
        $today = date('Y-m-d');
        $reminders = Reminder::where('user_id', $this->uid())
            ->with(['note' => fn($q) => $q->field('id,title,project_id')])
            ->whereIn('status', [Reminder::STATUS_PENDING, Reminder::STATUS_SENT])
            ->whereBetween('remind_at', [$today . ' 00:00:00', $today . ' 23:59:59'])
            ->order('remind_at', 'asc')->select();
        $recent = (new NoteService())->query($this->uid(), [])
            ->removeOption('order')->order('note.updated_at', 'desc')->order('note.id', 'desc')
            ->limit(20)->select()->toArray();

        return $this->success([
            'today_reminders' => $reminders,
            'recent_notes' => array_map([NoteService::class, 'toListItem'], $recent),
        ]);
    }
}
