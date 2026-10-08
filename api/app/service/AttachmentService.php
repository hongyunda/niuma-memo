<?php
namespace app\service;

use app\exception\ApiException;
use app\model\Attachment;
use Intervention\Image\ImageManager;
use think\file\UploadedFile;
use Throwable;

class AttachmentService
{
    public function root(): string
    {
        return rtrim((string) config('filesystem.disks.uploads.root'), '/');
    }

    public function absolute(string $relative): string
    {
        return $this->root() . '/' . ltrim($relative, '/');
    }

    public function absoluteOf(Attachment $att): string
    {
        return $this->absolute($att->getData('path'));
    }

    public function thumbAbsoluteOf(Attachment $att): ?string
    {
        $thumb = (string) $att->getData('thumb_path');
        return $thumb !== '' ? $this->absolute($thumb) : null;
    }

    /** ASR 用的转码副本（大文件走临时链接时用） */
    public function asrVariantOf(Attachment $att): string
    {
        return $this->absoluteOf($att) . '.asr.mp3';
    }

    public function detectType(string $ext, string $mime, string $clientMime = ''): string
    {
        $ext        = strtolower($ext);
        $mime       = strtolower($mime);
        $clientMime = strtolower($clientMime);
        $types      = (array) config('upload.types');

        // webm / mp4 这类音视频共用的容器：libmagic 一律报 video/*，以浏览器声明的 MIME 为准
        $isAudioExt = in_array($ext, $types['audio'] ?? [], true);
        $isVideoExt = in_array($ext, $types['video'] ?? [], true);
        if (str_starts_with($clientMime, 'audio/') && ($isAudioExt || $isVideoExt)) {
            return 'audio';
        }
        if ($isAudioExt && $isVideoExt) {
            return str_starts_with($mime, 'audio/') ? 'audio' : 'video';
        }
        foreach ($types as $type => $exts) {
            if (in_array($ext, $exts, true)) {
                return $type;
            }
        }
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        return 'other';
    }

    public function store(UploadedFile $file, int $uid, ?int $noteId = null, ?int $projectId = null): Attachment
    {
        $storage = strtolower((string) config('upload.storage', 'cos'));
        if (!in_array($storage, ['cos', 'local'], true)) {
            throw new ApiException('附件存储配置不正确', 500);
        }
        if ($storage === 'cos' && !CosStorage::configured()) {
            throw new ApiException('COS 尚未配置，文件暂时无法上传', 503);
        }
        $originalName = $file->getOriginalName();
        $ext = strtolower($file->getOriginalExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION));
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';

        if (in_array($ext, (array) config('upload.deny'), true)) {
            throw new ApiException('不允许上传该类型文件：' . $ext, 422);
        }
        $maxBytes = (int) config('upload.max_mb') * 1024 * 1024;
        if ($file->getSize() > $maxBytes) {
            throw new ApiException('文件超过大小限制 ' . config('upload.max_mb') . 'MB', 422);
        }

        $clientMime = (string) $file->getOriginalMime();
        $dir  = date('Y/m/d');
        $name = date('His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $absDir = $this->absolute($dir);
        if (!is_dir($absDir) && !mkdir($absDir, 0755, true) && !is_dir($absDir)) {
            throw new ApiException('存储目录不可写', 500);
        }
        $file->move($absDir, $name);
        $full = $absDir . '/' . $name;
        $path = $dir . '/' . $name;

        $mime = $this->detectMime($full, $clientMime);
        // 服务器端 MIME 二次校验，阻断改后缀上传脚本
        if (preg_match('#(php|x-httpd|javascript|html|xml)#i', $mime) && !in_array($ext, ['txt', 'md', 'json', 'xml'], true)) {
            @unlink($full);
            throw new ApiException('文件内容类型不被允许', 422);
        }
        $type = $this->detectType($ext, $mime, $clientMime);
        if ($type === 'audio' && !str_starts_with($mime, 'audio/')) {
            $mime = $clientMime && str_starts_with($clientMime, 'audio/') ? $clientMime : 'audio/webm';
        }

        $att = new Attachment();
        $att->user_id       = $uid;
        $att->note_id       = $noteId ?: null;
        $att->project_id    = $projectId ?: null;
        $att->file_type     = $type;
        $att->original_name = mb_substr($originalName, 0, 250);
        $att->path          = $path;
        $att->thumb_path    = '';
        $att->mime          = $mime;
        $att->size          = filesize($full);
        $att->storage       = 'local';
        $att->hash          = '';
        $att->duration      = 0;
        $att->width         = 0;
        $att->height        = 0;
        $att->transcript    = null;
        $att->asr_status    = Attachment::ASR_NONE;
        $att->asr_task_id   = '';
        $att->asr_error     = '';

        try {
            if ($type === 'image') {
                $this->processImage($att, $full, $path);
            } elseif ($type === 'audio') {
                $this->processAudio($att, $full, $path, $ext);
            } elseif ($type === 'video') {
                $this->processVideo($att, $full, $path);
            }
        } catch (Throwable $e) {
            trace('附件后处理失败: ' . $e->getMessage(), 'error');
        }

        $att->hash = md5_file($this->absolute($att->path)) ?: '';
        if (in_array($att->file_type, ['audio', 'video'], true) && AsrService::enabled()) {
            $att->asr_status = Attachment::ASR_QUEUED;
        }
        if ($storage === 'cos') {
            $this->moveToCos($att, false);
        } else {
            $att->save();
        }

        return Attachment::find($att->id) ?: $att;
    }

    private function detectMime(string $full, string $fallback): string
    {
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = $finfo ? (string) finfo_file($finfo, $full) : '';
        }
        if ($mime === '' || $mime === 'application/octet-stream') {
            $mime = $fallback ?: 'application/octet-stream';
        }
        return $mime;
    }

    private function processImage(Attachment $att, string $full, string $path): void
    {
        $info = @getimagesize($full);
        if ($info) {
            $att->width  = (int) $info[0];
            $att->height = (int) $info[1];
        }
        $thumbRel = dirname($path) . '/thumb_' . pathinfo($path, PATHINFO_FILENAME) . '.jpg';
        $thumbAbs = $this->absolute($thumbRel);
        $size = (int) config('upload.thumb_size', 480);
        try {
            $image = ImageManager::gd()->read($full);
            if (!$att->width) {
                $att->width  = $image->width();
                $att->height = $image->height();
            }
            $image->scaleDown($size, $size)->toJpeg(82)->save($thumbAbs);
            $att->thumb_path = $thumbRel;
        } catch (Throwable $e) {
            // HEIC 等 GD 不支持的格式：不生成缩略图
            trace('缩略图生成失败: ' . $e->getMessage(), 'warning');
        }
    }

    private function processAudio(Attachment $att, string $full, string $path, string $ext): void
    {
        // 手机录音（webm/ogg/opus）统一转 mp3，保证 iOS/Android/桌面都能播放
        if (in_array($ext, ['webm', 'weba', 'ogg', 'opus', 'amr'], true) && MediaService::hasFfmpeg()) {
            $mp3Rel = preg_replace('/\.[a-z0-9]+$/i', '', $path) . '.mp3';
            $mp3Abs = $this->absolute($mp3Rel);
            if (MediaService::toMp3($full, $mp3Abs, 16000, '64k')) {
                @unlink($full);
                $att->path = $mp3Rel;
                $att->mime = 'audio/mpeg';
                $att->size = filesize($mp3Abs);
                $full = $mp3Abs;
                if (!preg_match('/\.mp3$/i', $att->original_name)) {
                    $att->original_name = preg_replace('/\.[a-z0-9]+$/i', '', $att->original_name) . '.mp3';
                }
            }
        }
        $att->duration = MediaService::duration($full);
    }

    private function processVideo(Attachment $att, string $full, string $path): void
    {
        $att->duration = MediaService::duration($full);
        $posterRel = dirname($path) . '/thumb_' . pathinfo($path, PATHINFO_FILENAME) . '.jpg';
        if (MediaService::poster($full, $this->absolute($posterRel))) {
            $att->thumb_path = $posterRel;
            $info = @getimagesize($this->absolute($posterRel));
            if ($info) {
                $att->width  = (int) $info[0];
                $att->height = (int) $info[1];
            }
        }
    }

    public function delete(Attachment $att): void
    {
        if ($att->storage === 'cos') {
            $cos = new CosStorage();
            foreach ([$att->getData('path'), $att->getData('thumb_path'), $att->getData('path') . '.asr.mp3'] as $path) {
                if ($path) $cos->delete((string) $path);
            }
        }
        foreach ([$this->absoluteOf($att), $this->thumbAbsoluteOf($att), $this->asrVariantOf($att)] as $file) {
            if ($file && is_file($file)) {
                @unlink($file);
            }
        }
        $att->delete();
    }

    /** 原件和缩略图全部上传、校验后再切换存储；迁移旧附件时保留本地备份。 */
    public function moveToCos(Attachment $att, bool $keepLocal = true): void
    {
        if ($att->storage === 'cos') return;
        $cos = new CosStorage();
        $objects = [(string) $att->getData('path') => (string) $att->mime];
        if ($att->getData('thumb_path')) $objects[(string) $att->getData('thumb_path')] = 'image/jpeg';
        if (is_file($this->asrVariantOf($att))) $objects[$att->getData('path') . '.asr.mp3'] = 'audio/mpeg';
        $uploaded = [];
        try {
            foreach ($objects as $path => $mime) {
                $uploaded[] = $path;
                $cos->put($path, $this->absolute($path), $mime);
            }
            $att->storage = 'cos';
            $att->save();
        } catch (Throwable $e) {
            $att->storage = 'local';
            foreach ($uploaded as $path) {
                try { $cos->delete($path); } catch (Throwable $cleanup) { trace('COS 上传回滚失败', 'error'); }
            }
            // 未入库的新上传失败后清理临时文件；旧附件迁移失败保留原件。
            if (!$keepLocal) foreach (array_keys($objects) as $path) @unlink($this->absolute($path));
            trace('COS 上传失败: ' . $e->getMessage(), 'error');
            throw new ApiException('COS 上传失败，请检查存储配置后重试', 502);
        }
        if (!$keepLocal) foreach (array_keys($objects) as $path) @unlink($this->absolute($path));
    }

    /** 转写时只下载临时副本，处理完由调用方删除。 */
    public function localSource(Attachment $att): string
    {
        if ($att->storage !== 'cos') return $this->absoluteOf($att);
        $dir = runtime_path('cos-asr');
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $path = $dir . bin2hex(random_bytes(12)) . '.' . pathinfo((string) $att->getData('path'), PATHINFO_EXTENSION);
        try {
            (new CosStorage())->download((string) $att->getData('path'), $path);
        } catch (Throwable $e) {
            @unlink($path);
            throw $e;
        }
        return $path;
    }

    /** 给第三方（ASR 服务商）用的临时下载链接 */
    public function signedUrl(Attachment $att, string $variant = '', ?int $ttl = null): string
    {
        $ttl = $ttl ?? (int) config('asr.signed_url_ttl', 1800);
        $exp = time() + $ttl;
        $sig = $this->signature((int) $att->id, $exp, $variant);
        $query = http_build_query(array_filter(['exp' => $exp, 'sig' => $sig, 'variant' => $variant]));
        return rtrim((string) config('push.app_url'), '/') . '/api/files/' . $att->id . '?' . $query;
    }

    public function signature(int $id, int $exp, string $variant = ''): string
    {
        return hash_hmac('sha256', $id . '|' . $exp . '|' . $variant, (string) config('jwt.secret'));
    }

    public function verifySignature(int $id, int $exp, string $sig, string $variant = ''): bool
    {
        if ($exp < time() || $sig === '') {
            return false;
        }
        return hash_equals($this->signature($id, $exp, $variant), $sig);
    }

    /** @return array{used:int,count:int} */
    public function usage(int $uid): array
    {
        $row = Attachment::where('user_id', $uid)->field('COALESCE(SUM(size),0) AS used, COUNT(*) AS cnt')->find();
        return ['used' => (int) ($row['used'] ?? 0), 'count' => (int) ($row['cnt'] ?? 0)];
    }
}
