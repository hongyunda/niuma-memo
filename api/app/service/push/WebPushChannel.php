<?php
namespace app\service\push;

use app\model\PushSubscription;
use app\model\User;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use RuntimeException;

class WebPushChannel implements ChannelInterface
{
    public function configured(User $user): bool
    {
        $vapid = (array) config('push.vapid');
        return !empty($vapid['publicKey']) && !empty($vapid['privateKey'])
            && PushSubscription::where('user_id', $user->id)->count() > 0;
    }

    public function send(User $user, array $message): void
    {
        $vapid = (array) config('push.vapid');
        if (empty($vapid['publicKey']) || empty($vapid['privateKey'])) {
            throw new RuntimeException('未配置 VAPID 密钥，请先执行 php think push:vapid');
        }
        $subs = PushSubscription::where('user_id', $user->id)->select();
        if ($subs->isEmpty()) {
            throw new RuntimeException('没有已订阅浏览器通知的设备');
        }

        $webPush = new WebPush(['VAPID' => $vapid], ['TTL' => 3600, 'urgency' => 'high'], 20);
        $payload = json_encode([
            'title' => $message['title'] ?? '提醒',
            'body'  => $message['body'] ?? '',
            'url'   => $message['url'] ?? '/',
            'tag'   => $message['tag'] ?? 'memo',
            'icon'  => '/icons/icon-192.png',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach ($subs as $s) {
            $webPush->queueNotification(Subscription::create([
                'endpoint'        => $s->endpoint,
                'publicKey'       => $s->p256dh,
                'authToken'       => $s->auth,
                'contentEncoding' => 'aes128gcm',
            ]), $payload);
        }

        $ok = 0;
        $errors = [];
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $ok++;
                continue;
            }
            $errors[] = $report->getReason();
            if ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint_hash', md5($report->getEndpoint()))->delete();
            }
        }
        if ($ok === 0) {
            throw new RuntimeException($errors ? implode('; ', array_unique($errors)) : '推送失败');
        }
    }
}
