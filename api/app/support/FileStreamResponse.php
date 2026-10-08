<?php
namespace app\support;

use think\Response;

/**
 * 流式输出本地文件，支持 HTTP Range（iOS Safari 播放音视频必须要有 206 分段响应）
 */
class FileStreamResponse extends Response
{
    protected string $path;
    protected int $start = 0;
    protected int $end = -1;
    protected int $size = 0;
    protected bool $isHead = false;

    public function __construct(string $path, string $mime, string $name, bool $inline = true, ?string $range = null, bool $isHead = false, int $maxAge = 86400)
    {
        $this->path   = $path;
        $this->size   = (int) filesize($path);
        $this->isHead = $isHead;
        $this->start  = 0;
        $this->end    = $this->size - 1;
        $code = 200;
        $extra = [];

        if ($range && preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $m)) {
            $start = $m[1] !== '' ? (int) $m[1] : null;
            $end   = $m[2] !== '' ? (int) $m[2] : null;
            if ($start === null && $end !== null) {          // bytes=-500 最后 500 字节
                $start = max(0, $this->size - $end);
                $end   = $this->size - 1;
            } elseif ($end === null || $end >= $this->size) {
                $end = $this->size - 1;
            }
            if ($start === null || $start > $end || $start >= $this->size) {
                $code = 416;
                $this->start = 0;
                $this->end   = -1;
                $extra['Content-Range'] = 'bytes */' . $this->size;
            } else {
                $code = 206;
                $this->start = $start;
                $this->end   = $end;
                $extra['Content-Range'] = "bytes {$start}-{$end}/{$this->size}";
            }
        }

        $this->init('', $code);

        $encoded = rawurlencode($name);
        $this->header = array_merge($this->header, $extra, [
            'Content-Type'           => $mime ?: 'application/octet-stream',
            'Accept-Ranges'          => 'bytes',
            'Content-Length'         => (string) max(0, $this->end - $this->start + 1),
            'Content-Disposition'    => ($inline ? 'inline' : 'attachment') . "; filename=\"{$encoded}\"; filename*=UTF-8''{$encoded}",
            'Cache-Control'          => 'private, max-age=' . $maxAge,
            'Last-Modified'          => gmdate('D, d M Y H:i:s', (int) filemtime($path)) . ' GMT',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    protected function sendData(string $data): void
    {
        if ($this->isHead || $this->end < $this->start) {
            return;
        }
        $fp = fopen($this->path, 'rb');
        if (!$fp) {
            return;
        }
        fseek($fp, $this->start);
        $remaining = $this->end - $this->start + 1;
        while ($remaining > 0 && !feof($fp) && !connection_aborted()) {
            $chunk = fread($fp, (int) min(65536, $remaining));
            if ($chunk === false || $chunk === '') {
                break;
            }
            echo $chunk;
            $remaining -= strlen($chunk);
            flush();
        }
        fclose($fp);
    }
}
