<?php
namespace app\service\push;

use app\model\User;
use Throwable;

class PushService
{
    /**
     * 按渠道发送，返回 [channel => ['ok' => bool, 'error' => string]]
     */
    public static function send(User $user, array $message, array $channels): array
    {
        $drivers = (array) config('push.channels');
        $results = [];
        foreach (array_unique($channels) as $name) {
            $class = $drivers[$name] ?? null;
            if (!$class) {
                $results[$name] = ['ok' => false, 'error' => '未知渠道'];
                continue;
            }
            try {
                /** @var ChannelInterface $driver */
                $driver = new $class();
                $driver->send($user, $message);
                $results[$name] = ['ok' => true, 'error' => ''];
            } catch (Throwable $e) {
                $results[$name] = ['ok' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    /** 用户已配置可用的渠道列表 */
    public static function available(User $user): array
    {
        $out = [];
        foreach ((array) config('push.channels') as $name => $class) {
            try {
                $out[$name] = (new $class())->configured($user);
            } catch (Throwable $e) {
                $out[$name] = false;
            }
        }
        return $out;
    }
}
