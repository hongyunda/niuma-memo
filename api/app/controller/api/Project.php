<?php
namespace app\controller\api;

use app\exception\ApiException;
use app\model\Attachment;
use app\model\Note;
use app\model\Project as ProjectModel;
use app\service\ProjectResourceService;
use think\facade\Db;

class Project extends Base
{
    public function index()
    {
        $uid = $this->uid();
        $q = ProjectModel::where('user_id', $uid);
        $status = $this->request->get('status', '');
        if ($status !== '' && $status !== 'all') {
            $q->where('status', (int) $status);
        }
        $list = $q->order('status', 'asc')->order('sort', 'asc')->order('id', 'desc')->select()->toArray();

        $noteCounts = Note::where('user_id', $uid)->where('is_archived', 0)->group('project_id')->column('COUNT(*)', 'project_id');
        $updated = Note::where('user_id', $uid)->group('project_id')->column('MAX(updated_at)', 'project_id');

        foreach ($list as &$p) {
            $p['note_count']    = (int) ($noteCounts[$p['id']] ?? 0);
            $p['last_note_at']  = $updated[$p['id']] ?? null;
        }
        unset($p);

        return $this->success(['list' => $list]);
    }

    public function save()
    {
        $data = $this->request->post();
        $this->validate($data, ['name' => 'require|max:100'], ['name.require' => '请输入项目名称']);

        $p = new ProjectModel();
        $p->user_id = $this->uid();
        $p->status  = ProjectModel::STATUS_ACTIVE;
        $p->sort    = (int) (ProjectModel::where('user_id', $this->uid())->max('sort') ?? 0) + 1;
        $this->fill($p, $data);
        $p->save();
        return $this->success($p);
    }

    public function read(int $id)
    {
        $uid = $this->uid();
        $p = ProjectModel::where('user_id', $uid)->find($id);
        if (!$p) {
            throw new ApiException('项目不存在', 404);
        }
        $arr = $p->toArray();
        $arr['note_count'] = Note::where('user_id', $uid)->where('project_id', $id)->where('is_archived', 0)->count();
        return $this->success($arr);
    }

    public function update(int $id)
    {
        $p = $this->find($id);
        $data = $this->request->put();
        if (isset($data['name'])) {
            $this->validate($data, ['name' => 'require|max:100']);
        }
        $this->fill($p, $data);
        $p->save();
        return $this->success($p);
    }

    public function resources(int $id)
    {
        $this->find($id);
        $category = (string) $this->request->get('category', 'files');
        if (!in_array($category, ['files', 'image', 'video', 'link', 'table', 'pdf', 'other'], true)) {
            throw new ApiException('资源分类不正确', 422);
        }
        [$page, $limit] = $this->paging(50);
        $keyword = mb_substr(trim((string) $this->request->get('keyword', '')), 0, 200);
        return $this->success((new ProjectResourceService())->list($this->uid(), $id, $category, $keyword, $page, $limit));
    }

    public function sort()
    {
        $ids = (array) $this->request->put('ids', []);
        Db::transaction(function () use ($ids) {
            foreach (array_values($ids) as $i => $id) {
                ProjectModel::where('user_id', $this->uid())->where('id', (int) $id)->update(['sort' => $i + 1]);
            }
        });
        return $this->success();
    }

    public function delete(int $id)
    {
        $p = $this->find($id);
        $withNotes = (int) $this->request->param('with_notes', 0) === 1;
        Db::transaction(function () use ($p, $withNotes) {
            $notes = Note::where('user_id', $this->uid())->where('project_id', $p->id);
            if ($withNotes) {
                $notes->select()->each(fn($n) => $n->delete()); // 软删除进回收站
            } else {
                $notes->update(['project_id' => null]);         // 保留记录，可从最近编辑或搜索打开
            }
            Attachment::where('user_id', $this->uid())->where('project_id', $p->id)->update(['project_id' => null]);
            $p->delete();
        });
        return $this->success();
    }

    private function find(int $id): ProjectModel
    {
        $p = ProjectModel::where('user_id', $this->uid())->find($id);
        if (!$p) {
            throw new ApiException('项目不存在', 404);
        }
        return $p;
    }

    private function fill(ProjectModel $p, array $data): void
    {
        if (isset($data['name'])) {
            $p->name = mb_substr(trim((string) $data['name']), 0, 100);
        }
        if (array_key_exists('description', $data)) {
            $p->description = (string) $data['description'];
        }
        if (isset($data['status'])) {
            $p->status = (int) $data['status'] === ProjectModel::STATUS_ARCHIVED ? ProjectModel::STATUS_ARCHIVED : ProjectModel::STATUS_ACTIVE;
        }
        if (isset($data['sort'])) {
            $p->sort = (int) $data['sort'];
        }
    }
}
