<?php
namespace app\controller\api;

use app\exception\ApiException;
use app\model\Attachment;
use app\model\Note as NoteModel;
use app\model\Project;
use app\service\AttachmentService;
use app\service\NoteService;
use app\service\NoteTitleService;
use think\facade\Db;

class Note extends Base
{
    private NoteService $svc;

    protected function initialize()
    {
        $this->svc = new NoteService();
    }

    public function index()
    {
        [$page, $limit] = $this->paging();
        $paginator = $this->svc->query($this->uid(), $this->request->get())->paginate(['list_rows' => $limit, 'page' => $page]);
        $data = $this->paginate($paginator);
        $data['list'] = array_map([NoteService::class, 'toListItem'], $data['list']);
        return $this->success($data);
    }

    public function save()
    {
        $data = $this->request->post();
        if (trim((string) ($data['title'] ?? '')) === '' && trim(strip_tags((string) ($data['content'] ?? ''))) === '' && empty($data['attachment_ids'])) {
            throw new ApiException('内容不能为空', 422);
        }
        $note = Db::transaction(function () use ($data) {
            $note = $this->svc->save($this->uid(), $data);
            $this->bindAttachments($note, $data);
            return $note;
        });
        (new NoteTitleService())->queue((int) $note->id);
        return $this->success($this->detail($note->id));
    }

    public function read(int $id)
    {
        return $this->success($this->detail($id));
    }

    public function update(int $id)
    {
        $note = $this->find($id);
        $data = $this->request->put();
        Db::transaction(function () use ($note, $data) {
            $this->svc->save($this->uid(), $data, $note);
            $this->bindAttachments($note, $data);
        });
        (new NoteTitleService())->queue($id);
        return $this->success($this->detail($id));
    }

    public function title(int $id)
    {
        $this->find($id);
        $svc = new NoteTitleService();
        $svc->recover();
        $svc->queue($id);
        $svc->process($id);
        return $this->success($this->detail($id));
    }

    public function delete(int $id)
    {
        $note = $this->find($id);
        $note->delete();
        return $this->success();
    }

    public function pin(int $id)
    {
        $note = $this->find($id);
        $note->is_pinned = (int) $this->request->put('is_pinned', !$note->is_pinned);
        $note->save();
        return $this->success($note);
    }

    public function archive(int $id)
    {
        $note = $this->find($id);
        $note->is_archived = (int) $this->request->put('is_archived', !$note->is_archived);
        $note->save();
        return $this->success($note);
    }

    /** 批量移动到项目 */
    public function move()
    {
        $ids = array_map('intval', (array) $this->request->put('ids', []));
        $pid = (int) $this->request->put('project_id', 0);
        if (!$ids) {
            throw new ApiException('请选择记录', 422);
        }
        if ($pid <= 0) throw new ApiException('请选择项目', 422);
        if (!Project::where('user_id', $this->uid())->where('id', $pid)->find()) {
            throw new ApiException('项目不存在', 404);
        }
        $target = $pid > 0 ? $pid : null;
        NoteModel::where('user_id', $this->uid())->whereIn('id', $ids)->update(['project_id' => $target]);
        Attachment::where('user_id', $this->uid())->whereIn('note_id', $ids)->update(['project_id' => $target]);
        return $this->success();
    }

    public function trash()
    {
        [$page, $limit] = $this->paging();
        $paginator = NoteModel::onlyTrashed()->where('user_id', $this->uid())->with(['tags'])
            ->order('deleted_at', 'desc')->paginate(['list_rows' => $limit, 'page' => $page]);
        $data = $this->paginate($paginator);
        $data['list'] = array_map(function ($row) {
            $row['summary'] = mb_substr((string) ($row['content_text'] ?? ''), 0, 140);
            unset($row['content'], $row['content_text']);
            return $row;
        }, $data['list']);
        return $this->success($data);
    }

    public function restore(int $id)
    {
        $note = NoteModel::onlyTrashed()->where('user_id', $this->uid())->find($id);
        if (!$note) {
            throw new ApiException('记录不存在', 404);
        }
        $note->restore();
        return $this->success();
    }

    public function force(int $id)
    {
        $note = NoteModel::onlyTrashed()->where('user_id', $this->uid())->find($id);
        if (!$note) {
            throw new ApiException('记录不在回收站', 404);
        }
        $files = new AttachmentService();
        Db::transaction(function () use ($note, $files) {
            foreach (Attachment::where('note_id', $note->id)->select() as $att) {
                $files->delete($att);
            }
            Db::name('note_tag')->where('note_id', $note->id)->delete();
            Db::name('reminder')->where('note_id', $note->id)->delete();
            $note->force()->delete();
        });
        return $this->success();
    }

    private function find(int $id): NoteModel
    {
        $note = NoteModel::where('user_id', $this->uid())->find($id);
        if (!$note) {
            throw new ApiException('记录不存在', 404);
        }
        return $note;
    }

    private function detail(int $id): array
    {
        $note = NoteModel::where('user_id', $this->uid())
            ->with(['project' => fn($q) => $q->field('id,name'), 'tags', 'attachments', 'reminders'])
            ->find($id);
        if (!$note) {
            throw new ApiException('记录不存在', 404);
        }
        return $note->toArray();
    }

    /** 上传时未关联记录的附件（快速记录先传文件再建记录），在保存时绑定 */
    private function bindAttachments(NoteModel $note, array $data): void
    {
        if (empty($data['attachment_ids']) || !is_array($data['attachment_ids'])) {
            return;
        }
        $ids = array_map('intval', $data['attachment_ids']);
        Attachment::where('user_id', $this->uid())->whereIn('id', $ids)
            ->where(fn($q) => $q->whereNull('note_id')->whereOr('note_id', $note->id))
            ->update(['note_id' => $note->id, 'project_id' => $note->project_id]);
    }
}
