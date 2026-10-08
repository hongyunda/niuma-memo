<?php
namespace app\support;

use RuntimeException;

/**
 * 轻量 cURL 封装（对接腾讯云 / 火山引擎 / Bark / 企业微信）
 */
class Http
{
    /**
     * @return array{status:int, headers:array<string,string>, body:string}
     */
    public static function request(string $method, string $url, ?string $body = null, array $headers = [], int $timeout = 30): array
    {
        $ch = curl_init($url);
        $rawHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$rawHeaders) {
                $len = strlen($line);
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $rawHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return $len;
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $result = curl_exec($ch);
        if ($result === false) {
            $err = curl_error($ch);
            throw new RuntimeException('HTTP 请求失败: ' . $err);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        return ['status' => $status, 'headers' => $rawHeaders, 'body' => (string) $result];
    }

    public static function postJson(string $url, array $payload, array $headers = [], int $timeout = 30): array
    {
        $headers[] = 'Content-Type: application/json; charset=utf-8';
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $res  = self::request('POST', $url, $body, $headers, $timeout);
        $res['json'] = json_decode($res['body'], true) ?: [];
        return $res;
    }

    public static function get(string $url, array $headers = [], int $timeout = 30): array
    {
        return self::request('GET', $url, null, $headers, $timeout);
    }
}
