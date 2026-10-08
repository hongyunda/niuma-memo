<?php
namespace app\controller\api;

use app\exception\ApiException;
use app\model\PushSubscription;
use app\service\push\PushService;

class Push extends Base
{
    public function subscribe()
    {
        $data     = $this->request->post();
        $endpoint = (string) ($data['endpoint'] ?? '');
        $p256dh   = (string) ($data['keys']['p256dh'] ?? '');
        $auth     = (string) ($data['keys']['auth'] ?? '');
        if ($endpoint === '' || $p256dh === '' || $auth === '' || !str_starts_with($endpoint, 'https://')) {
            throw new ApiException('订阅信息不完整', 422);
        }
        $hash = md5($endpoint);
        $sub  = PushSubscription::where('endpoint_hash', $hash)->find() ?: new PushSubscription();
        $sub->user_id       = $this->uid();
        $sub->endpoint      = $endpoint;
        $sub->endpoint_hash = $hash;
        $sub->p256dh        = $p256dh;
        $sub->auth          = $auth;
        $sub->user_agent    = mb_substr((string) $this->request->header('user-agent', ''), 0, 255);
        $sub->save();
        return $this->success(['id' => $sub->id]);
    }

    public function unsubscribe()
    {
        $endpoint = (string) $this->request->param('endpoint', '');
        if ($endpoint !== '') {
            PushSubscription::where('user_id', $this->uid())->where('endpoint_hash', md5($endpoint))->delete();
        }
        return $this->success();
    }

    public function test()
    {
        $channel = (string) $this->request->post('channel', 'webpush');
        if (!array_key_exists($channel, (array) config('push.channels'))) {
            throw new ApiException('未知渠道', 422);
        }
        $results = PushService::send($this->request->user, [
            'title' => '测试推送',
            'body'  => '如果你看到这条消息，说明「' . $channel . '」渠道已经通了 🎉',
            'url'   => rtrim((string) config('push.app_url'), '/') . '/reminders',
            'tag'   => 'test',
        ], [$channel]);
        $res = $results[$channel];
        if (!$res['ok']) {
            throw new ApiException('发送失败：' . $res['error'], 400);
        }
        return $this->success($res);
    }
}
