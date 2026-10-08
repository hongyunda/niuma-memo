<?php
namespace app\controller\api;

use app\exception\ApiException;
use app\model\User;
use app\service\JwtService;
use think\facade\Cache;
use think\facade\Cookie;

class Auth extends Base
{
    public function login()
    {
        $data = $this->request->post();
        $this->validate($data, [
            'username' => 'require|max:50',
            'password' => 'require|max:100',
        ], ['username.require' => '请输入用户名', 'password.require' => '请输入密码']);

        $key   = 'login_fail_' . md5((string) $this->request->ip());
        $fails = (int) Cache::get($key, 0);
        if ($fails >= 5) {
            throw new ApiException('失败次数过多，请 15 分钟后再试', 429);
        }

        $user = User::where('username', $data['username'])->find();
        if (!$user || !password_verify((string) $data['password'], (string) $user->getData('password'))) {
            Cache::set($key, $fails + 1, 900);
            throw new ApiException('用户名或密码错误', 400);
        }
        Cache::delete($key);

        $user->last_login_at = date('Y-m-d H:i:s');
        $user->save();

        $token = JwtService::issue($user);
        Cookie::set((string) config('jwt.cookie'), $token, JwtService::cookieOptions($this->request));

        return $this->success(['token' => $token, 'user' => $user]);
    }

    public function me()
    {
        return $this->success($this->request->user);
    }

    public function logout()
    {
        Cookie::delete((string) config('jwt.cookie'));
        return $this->success();
    }

    public function password()
    {
        $data = $this->request->put();
        $this->validate($data, [
            'old_password' => 'require',
            'new_password' => 'require|min:6|max:100',
        ], ['new_password.min' => '新密码至少 6 位']);

        $user = $this->request->user;
        if (!password_verify((string) $data['old_password'], (string) $user->getData('password'))) {
            throw new ApiException('原密码不正确', 400);
        }
        $user->password = password_hash((string) $data['new_password'], PASSWORD_DEFAULT);
        $user->save();
        return $this->success();
    }
}
