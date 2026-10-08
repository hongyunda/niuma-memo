<?php
namespace app\service;

use app\exception\ApiException;
use Qcloud\Cos\Client;

/** 私有 COS 对象；数据库保留相对路径，前端始终通过 /api/files 鉴权。 */
class CosStorage
{
    private ?Client $client = null;

    public static function configured(): bool
    {
        foreach (['bucket', 'region', 'secret_id', 'secret_key'] as $key) {
            if (trim((string) config('cos.' . $key)) === '') {
                return false;
            }
        }
        return true;
    }

    private function client(): Client
    {
        if (!self::configured()) {
            throw new ApiException('COS 尚未配置，请填写 COS_BUCKET、COS_REGION、COS_SECRET_ID 和 COS_SECRET_KEY', 503);
        }
        return $this->client ??= new Client([
            'region' => (string) config('cos.region'),
            'scheme' => 'https',
            'timeout' => 120,
            'connect_timeout' => 10,
            'retry' => 2,
            'credentials' => [
                'secretId' => (string) config('cos.secret_id'),
                'secretKey' => (string) config('cos.secret_key'),
            ],
        ]);
    }

    public function key(string $path): string
    {
        $prefix = trim((string) config('cos.prefix'), '/');
        return ($prefix === '' ? '' : $prefix . '/') . ltrim($path, '/');
    }

    public function put(string $path, string $local, string $mime): void
    {
        $stream = fopen($local, 'rb');
        if (!$stream) {
            throw new ApiException('附件源文件无法读取', 500);
        }
        try {
            $this->client()->putObject([
                'Bucket' => (string) config('cos.bucket'),
                'Key' => $this->key($path),
                'Body' => $stream,
                'ContentType' => $mime ?: 'application/octet-stream',
                'ACL' => 'private',
                'ContentMD5' => true,
                'CacheControl' => 'private, max-age=900',
            ]);
            $head = $this->client()->headObject([
                'Bucket' => (string) config('cos.bucket'), 'Key' => $this->key($path),
            ]);
            if ((int) $head['ContentLength'] !== filesize($local)) {
                throw new ApiException('COS 文件大小校验失败，请重新上传', 502);
            }
        } finally {
            if (is_resource($stream)) fclose($stream);
        }
    }

    public function download(string $path, string $local): void
    {
        $this->client()->getObject([
            'Bucket' => (string) config('cos.bucket'),
            'Key' => $this->key($path), 'SaveAs' => $local,
        ]);
    }

    public function read(string $path, ?string $range, bool $head): array
    {
        $args = ['Bucket' => (string) config('cos.bucket'), 'Key' => $this->key($path)];
        if ($head) return $this->client()->headObject($args)->toArray();
        if ($range && preg_match('/^bytes=(?:\d+-\d*|-\d+)$/', $range)) $args['Range'] = $range;
        $args['@http'] = ['stream' => true];
        return $this->client()->getObject($args)->toArray();
    }

    public function url(string $path, string $name = '', bool $download = false, ?int $ttl = null): string
    {
        $args = [];
        if ($name !== '') {
            $args['ResponseContentDisposition'] = ($download ? 'attachment' : 'inline') . "; filename*=UTF-8''" . rawurlencode($name);
        }
        return $this->client()->getObjectUrl(
            (string) config('cos.bucket'), $this->key($path),
            '+' . ($ttl ?? (int) config('cos.url_ttl', 900)) . ' seconds', $args,
        );
    }

    public function delete(string $path): void
    {
        $this->client()->deleteObject([
            'Bucket' => (string) config('cos.bucket'), 'Key' => $this->key($path),
        ]);
    }
}
