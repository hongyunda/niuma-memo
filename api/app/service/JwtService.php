<?php
namespace app\service;

use app\model\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\Request;
use Throwable;

class JwtService
{
    public static function issue(User $user): string
    {
        $now = time();
        $payload = [
            'iss' => config('jwt.issuer'),
            'sub' => (int) $user->id,
            'iat' => $now,
            'exp' => $now + (int) config('jwt.ttl'),
            'jti' => bin2hex(random_bytes(8)),
        ];
        return JWT::encode($payload, (string) config('jwt.secret'), 'HS256');
    }

    public static function verify(string $token): ?User
    {
        try {
            $payload = JWT::decode($token, new Key((string) config('jwt.secret'), 'HS256'));
        } catch (Throwable $e) {
            return null;
        }
        $uid = (int) ($payload->sub ?? 0);
        if ($uid <= 0) {
            return null;
        }
        return User::find($uid);
    }

    /** 从 Header（Bearer）或 Cookie 中取 token */
    public static function extractToken(Request $request): ?string
    {
        $auth = (string) $request->header('authorization', '');
        if ($auth && stripos($auth, 'Bearer ') === 0) {
            $token = trim(substr($auth, 7));
            if ($token !== '') {
                return $token;
            }
        }
        $cookie = (string) $request->cookie((string) config('jwt.cookie'), '');
        return $cookie !== '' ? $cookie : null;
    }

    public static function currentUser(Request $request): ?User
    {
        $token = self::extractToken($request);
        return $token ? self::verify($token) : null;
    }

    /** 登录后写 Cookie 的选项 */
    public static function cookieOptions(Request $request): array
    {
        return [
            'expire'   => (int) config('jwt.ttl'),
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $request->isSsl(),
        ];
    }
}
