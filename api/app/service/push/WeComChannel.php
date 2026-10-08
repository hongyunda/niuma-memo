<?php
namespace app\service\push;

use app\model\User;
use app\support\Http;
use RuntimeException;

/**
 * 企业微信群机器人 Webhook
 */
class WeComChannel implements ChannelInterface
{
    public function configured(User $user): bool
    {
        return !empty($user->pushConfig()['wecom_webhook']);
    }

    public function send(User $user, array $message): void
    {
        $hook = trim((string) ($user->pushConfig()['wecom_webhook'] ?? ''));
        if ($hook === '' || !str_starts_with($hook, 'https://qyapi.weixin.qq.com/')) {
            throw new RuntimeException('未配置有效的企业微信 Webhook');
        }
        $title = $message['title'] ?? '提醒';
        $body  = $message['body'] ?? '';
        $url   = $message['url'] ?? '';
        $content = "### ⏰ {$title}\n{$body}";
        if ($url) {
            $content .= "\n[打开备忘录]({$url})";
        }
        $res = Http::postJson($hook, ['msgtype' => 'markdown', 'markdown' => ['content' => $content]], [], 15);
        if ((int) ($res['json']['errcode'] ?? -1) !== 0) {
            throw new RuntimeException('企业微信返回：' . ($res['json']['errmsg'] ?? $res['body']));
        }
    }
}
