<?php
namespace app\service\push;

use app\model\User;

interface ChannelInterface
{
    /**
     * @param array{title:string, body:string, url:string, tag?:string} $message
     * @throws \RuntimeException 发送失败时抛出，消息为失败原因
     */
    public function send(User $user, array $message): void;

    /** 该用户是否已配置好此渠道 */
    public function configured(User $user): bool;
}
