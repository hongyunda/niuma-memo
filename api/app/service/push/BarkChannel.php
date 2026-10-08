<?php
namespace app\service\push;

use app\model\User;
use app\support\Http;
use RuntimeException;

/**
 * Bark（iOS）：App Store 安装 Bark，把 Key 填到设置里即可
 */
class BarkChannel implements ChannelInterface
{
    public function configured(User $user): bool
    {
        return !empty($user->pushConfig()['bark_key']);
    }

    public function send(User $user, array $message): void
    {
        $cfg = $user->pushConfig();
        $key = trim((string) ($cfg['bark_key'] ?? ''));
        if ($key === '') {
            throw new RuntimeException('未配置 Bark Key');
        }
        $server = rtrim((string) ($cfg['bark_server'] ?? '') ?: 'https://api.day.app', '/');
        $res = Http::postJson($server . '/push', [
            'device_key' => $key,
            'title'      => $message['title'] ?? '提醒',
            'body'       => $message['body'] ?? '',
            'url'        => $message['url'] ?? '',
            'group'      => '牛马备忘录',
            'sound'      => 'minuet',
            'level'      => 'timeSensitive',
            'icon'       => rtrim((string) config('push.app_url'), '/') . '/icons/icon-192.png',
        ], [], 15);
        $code = $res['json']['code'] ?? $res['status'];
        if ((int) $code !== 200) {
            throw new RuntimeException('Bark 返回：' . ($res['json']['message'] ?? $res['body']));
        }
    }
}
