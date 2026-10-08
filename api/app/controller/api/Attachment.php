<?php
namespace app\controller\api;

use app\exception\ApiException;
use app\model\Attachment as AttachmentModel;
use app\model\Note;
use app\model\Project;
use app\service\AsrService;
use app\service\AttachmentService;
use app\service\NoteTitleService;

class Attachment extends Base
{
    public function upload()
    {
        $file = $this->request->file('file');
        if (!$file) {
            throw new ApiException('没有收到文件', 422);
        }
        $uid    = $this->uid();
        $noteId = (int) $this->request->param('note_id', 0);
        $pid    = (int) $this->request->param('project_id', 0);
        if ($noteId > 0) {
            $note = Note::where('user_id', $uid)->find($noteId);
            if (!$note) {
                throw new ApiException('记录不存在', 404);
            }
            $pid = (int) $note->project_id;
        } elseif ($pid > 0 && !Project::where('user_id', $uid)->where('id', $pid)->find()) {
            throw new ApiException('项目不存在', 404);
        }

        $att = (new AttachmentService())->store($file, $uid, $noteId ?: null, $pid ?: null);
        if ($noteId > 0) {
            Note::where('id', $noteId)->update(['updated_at' => date('Y-m-d H:i:s')]);
            (new NoteTitleService())->queue($noteId);
        }
        return $this->success($att);
    }

    public function update(int $id)
    {
        $att  = $this->find($id);
        $data = $this->request->put();
        if (array_key_exists('note_id', $data)) {
            $nid = (int) $data['note_id'];
            if ($nid > 0) {
                $note = Note::where('user_id', $this->uid())->find($nid);
                if (!$note) {
                    throw new ApiException('记录不存在', 404);
                }
                $att->note_id    = $nid;
                $att->project_id = $note->project_id;
            } else {
                $att->note_id = null;
            }
        }
        if (array_key_exists('project_id', $data) && !$att->note_id) {
            $pid = (int) $data['project_id'];
            $att->project_id = $pid > 0 ? $pid : null;
        }
        if (isset($data['original_name'])) {
            $att->original_name = mb_substr(trim((string) $data['original_name']), 0, 250);
        }
        if (array_key_exists('transcript', $data)) {
            $att->transcript = (string) $data['transcript'];
        }
        $att->save();
        if ($att->note_id) (new NoteTitleService())->queue((int) $att->note_id);
        return $this->success($att);
    }

    public function delete(int $id)
    {
        $att = $this->find($id);
        $noteId = (int) $att->note_id;
        (new AttachmentService())->delete($att);
        if ($noteId) (new NoteTitleService())->queue($noteId);
        return $this->success();
    }

    /** 触发/推进语音转文字，前端轮询直到 asr_status 为 3 或 4 */
    public function transcribe(int $id)
    {
        $att = $this->find($id);
        if ((int) $att->asr_status === AttachmentModel::ASR_DONE && !$this->request->param('force')) {
            return $this->success($att);
        }
        if ((int) $this->request->param('force', 0) === 1) {
            $att->asr_status  = AttachmentModel::ASR_QUEUED;
            $att->asr_task_id = '';
        }
        set_time_limit(200);
        $att = (new AsrService())->process($att);
        return $this->success($att);
    }

    private function find(int $id): AttachmentModel
    {
        $att = AttachmentModel::where('user_id', $this->uid())->find($id);
        if (!$att) {
            throw new ApiException('附件不存在', 404);
        }
        return $att;
    }
}
