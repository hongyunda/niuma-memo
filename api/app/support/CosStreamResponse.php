<?php
namespace app\support;

use app\exception\ApiException;
use app\service\CosStorage;
use Psr\Http\Message\StreamInterface;
use Qcloud\Cos\Exception\ServiceResponseException;
use think\Response;

/** 鉴权后流式输出 COS，规避新桶默认域名强制下载，支持音视频 Range。 */
class CosStreamResponse extends Response
{
    private ?StreamInterface $stream = null;
    private bool $head;

    public function __construct(string $path, string $mime, string $name, bool $download, ?string $range, bool $head)
    {
        $this->head = $head;
        $extra = [];
        $code = 200;
        try {
            $result = (new CosStorage())->read($path, $range, $head);
            $this->stream = $result['Body'] ?? null;
            if (!empty($result['ContentRange'])) {
                $code = 206;
                $extra['Content-Range'] = $result['ContentRange'];
            }
            $extra['Content-Length'] = (string) ($result['ContentLength'] ?? 0);
        } catch (ServiceResponseException $e) {
            if ($e->getStatusCode() === 416) {
                $result = (new CosStorage())->read($path, null, true);
                $code = 416;
                $extra = ['Content-Range' => 'bytes */' . $result['ContentLength'], 'Content-Length' => '0'];
            } else {
                throw new ApiException($e->getStatusCode() === 404 ? '文件已不存在' : 'COS 文件暂时无法读取', $e->getStatusCode() === 404 ? 404 : 502);
            }
        }
        $this->init('', $code);
        $this->header = array_merge($this->header, $extra, [
            'Content-Type' => $mime ?: 'application/octet-stream',
            'Content-Disposition' => ($download ? 'attachment' : 'inline') . "; filename*=UTF-8''" . rawurlencode($name),
            'Cache-Control' => 'private, max-age=900',
            'Accept-Ranges' => 'bytes',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    protected function sendData(string $data): void
    {
        try {
            if ($this->head || !$this->stream) return;
            while (!$this->stream->eof() && !connection_aborted()) {
                $chunk = $this->stream->read(65536);
                if ($chunk === '') break;
                echo $chunk;
                flush();
            }
        } finally {
            $this->stream?->close();
        }
    }
}
