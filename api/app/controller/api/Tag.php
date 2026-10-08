<?php
namespace app\controller\api;

use app\exception\ApiException;
use app\model\Tag as TagModel;
use think\facade\Db;

class Tag extends Base
{
    public function index()
    {
        $uid  = $this->uid();
        $list = TagModel::where('user_id', $uid)->order('id', 'asc')->select()->toArray();
        $counts = Db::name('note_tag')->alias('nt')
            ->join('note n', 'n.id = nt.note_id')
            ->where('n.user_id', $uid)->whereNull('n.deleted_at')
            ->group('nt.tag_id')->column('COUNT(*)', 'nt.tag_id');
        foreach ($list as &$t) {
            $t['note_count'] = (int) ($counts[$t['id']] ?? 0);
        }
        return $this->success(['list' => $list]);
    }

    public function save()
    {
        $data = $this->request->post();
        $this->validate($data, ['name' => 'require|max:30']);
        $name = trim((string) $data['name']);
        $tag  = TagModel::where('user_id', $this->uid())->where('name', $name)->find();
        if (!$tag) {
            $tag = TagModel::create([
                'user_id' => $this->uid(),
                'name'    => $name,
                'color'   => $this->color($data['color'] ?? ''),
            ]);
        }
        return $this->success($tag);
    }

    public function update(int $id)
    {
        $tag  = $this->find($id);
        $data = $this->request->put();
        if (isset($data['name'])) {
            $name = mb_substr(trim((string) $data['name']), 0, 30);
            $dup  = TagModel::where('user_id', $this->uid())->where('name', $name)->where('id', '<>', $id)->find();
            if ($dup) {
                throw new ApiException('标签名已存在', 409);
            }
            $tag->name = $name;
        }
        if (isset($data['color'])) {
            $tag->color = $this->color($data['color']);
        }
        $tag->save();
        return $this->success($tag);
    }

    public function delete(int $id)
    {
        $tag = $this->find($id);
        Db::name('note_tag')->where('tag_id', $tag->id)->delete();
        $tag->delete();
        return $this->success();
    }

    private function find(int $id): TagModel
    {
        $tag = TagModel::where('user_id', $this->uid())->find($id);
        if (!$tag) {
            throw new ApiException('标签不存在', 404);
        }
        return $tag;
    }

    private function color(string $c): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : '';
    }
}
