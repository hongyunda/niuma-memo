<?php
namespace app\middleware;

use app\service\JwtService;
use Closure;
use think\Request;

/**
 * JWT 鉴权：Header Authorization: Bearer xxx 或 Cookie
 */
class Auth
{
    public function handle(Request $request, Closure $next)
    {
        $user = JwtService::currentUser($request);
        if (!$user) {
            return json(['code' => 401, 'msg' => '请先登录'], 401);
        }
        $request->user   = $user;
        $request->userId = (int) $user->id;

        return $next($request);
    }
}
