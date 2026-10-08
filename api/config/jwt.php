<?php
// JWT 鉴权配置
return [
    'secret' => env('JWT_SECRET', ''),
    // 有效期（秒），默认 7 天
    'ttl'    => (int) env('JWT_TTL', 604800),
    // 同时写入 httpOnly Cookie，供 <img>/<audio> 直接请求私有文件时携带
    'cookie' => env('JWT_COOKIE', 'memo_token'),
    'issuer' => 'niuma-memo',
];
