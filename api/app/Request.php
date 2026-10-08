<?php
namespace app;

use app\model\User;

// 应用请求对象类
class Request extends \think\Request
{
    /** 当前登录用户（由 Auth 中间件注入） */
    public ?User $user = null;

    /** 当前登录用户 ID，未登录为 0 */
    public int $userId = 0;
}
