<?php
namespace app\controller\api;

use app\service\AsrService;
use app\service\AttachmentService;
use app\service\MediaService;
use app\service\CosStorage;
use app\service\NoteTitleService;
use app\service\push\PushService;

class Setting extends Base
{
    public function index()
    {
        $user = $this->request->user;
        return $this->success([
            'push_config'  => $user->pushConfig(),
            'channels'     => PushService::available($user),
            'vapid_public' => (string) config('push.vapid.publicKey'),
            'asr'          => ['provider' => AsrService::provider(), 'enabled' => AsrService::enabled()],
            'ffmpeg'       => MediaService::hasFfmpeg(),
            'ai'           => ['enabled' => NoteTitleService::enabled()],
            'attachment_storage' => ['driver' => (string) config('upload.storage'), 'configured' => CosStorage::configured()],
            'storage'      => (new AttachmentService())->usage($this->uid()),
            'upload_max_mb'=> (int) config('upload.max_mb'),
        ]);
    }

    public function push()
    {
        $data = $this->request->put();
        $user = $this->request->user;
        $cfg  = $user->pushConfig();
        foreach (['bark_key', 'bark_server', 'wecom_webhook'] as $k) {
            if (array_key_exists($k, $data)) {
                $cfg[$k] = trim((string) $data[$k]);
            }
        }
        if (array_key_exists('default_channels', $data)) {
            $allowed = array_keys((array) config('push.channels'));
            $cfg['default_channels'] = array_values(array_intersect((array) $data['default_channels'], $allowed));
        }
        $user->push_config = $cfg;
        $user->save();
        return $this->success($cfg);
    }
}
