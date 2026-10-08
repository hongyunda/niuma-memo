<?php
namespace app\controller\api;

use app\model\Attachment;
use app\model\Note;
use app\service\NoteService;

class Search extends Base
{
    public function index()
    {
        $q = trim((string) $this->request->get('q', ''));
        if ($q === '') {
            return $this->success(['notes' => ['list' => [], 'total' => 0], 'attachments' => []]);
        }
        [$page, $limit] = $this->paging();
        $svc = new NoteService();
        $filters = array_merge($this->request->get(), ['keyword' => $q, 'archived' => 'all']);
        $paginator = $svc->query($this->uid(), $filters)->paginate(['list_rows' => $limit, 'page' => $page]);
        $notes = $this->paginate($paginator);
        $notes['list'] = array_map([NoteService::class, 'toListItem'], $notes['list']);

        $attachments = [];
        if ($page === 1) {
            $aq = Attachment::where('user_id', $this->uid())->where('original_name|transcript', 'like', '%' . $q . '%');
            if ($pid = $this->request->get('project_id')) {
                $aq->where('project_id', (int) $pid);
            }
            $attachments = $aq->with(['note' => fn($n) => $n->field('id,title')])->order('id', 'desc')->limit(20)->select()->toArray();
        }
        return $this->success(['notes' => $notes, 'attachments' => $attachments, 'q' => $q]);
    }
}
