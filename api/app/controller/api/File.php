<?php
namespace app\controller\api;

use app\exception\ApiException;
use app\model\Attachment;
use app\service\AttachmentService;
use app\support\CosStreamResponse;
use app\service\JwtService;
use app\support\FileStreamResponse;

/**
 * 私有文件下发：登录态 或 临时签名 二选一
 * 生产环境建议开启 UPLOAD_ACCEL，由 Nginx internal location 输出文件
 */
class File extends Base
{
    public function show(int $id)
    {
        $att   = $this->authorize($id);
        $files = new AttachmentService();
        $variant = (string) $this->request->get('variant', '');
        if ($att->storage === 'cos') {
            $path = (string) $att->getData('path') . ($variant === 'asr' ? '.asr.mp3' : '');
            return $this->serveCos($path, $variant === 'asr' ? 'audio/mpeg' : (string) $att->mime, $variant === 'asr' ? 'audio.mp3' : (string) $att->original_name, (bool) $this->request->get('download', false));
        }
        $path = $variant === 'asr' ? $files->asrVariantOf($att) : $files->absoluteOf($att);
        $mime = $variant === 'asr' ? 'audio/mpeg' : (string) $att->mime;
        $name = $variant === 'asr' ? pathinfo($att->original_name, PATHINFO_FILENAME) . '.mp3' : (string) $att->original_name;
        return $this->serve($path, $mime, $name, (bool) $this->request->get('download', false));
    }

    public function thumb(int $id)
    {
        $att   = $this->authorize($id);
        $files = new AttachmentService();
        if ($att->storage === 'cos') {
            $thumb = (string) $att->getData('thumb_path');
            return $this->serveCos($thumb ?: (string) $att->getData('path'), $thumb ? 'image/jpeg' : (string) $att->mime, (string) $att->original_name, false);
        }
        $thumb = $files->thumbAbsoluteOf($att);
        if (!$thumb || !is_file($thumb)) {
            // 没有缩略图（如 HEIC）退回原图
            $thumb = $files->absoluteOf($att);
            return $this->serve($thumb, (string) $att->mime, (string) $att->original_name, false);
        }
        return $this->serve($thumb, 'image/jpeg', 'thumb.jpg', false);
    }

    private function authorize(int $id): Attachment
    {
        $att = Attachment::find($id);
        if (!$att) {
            throw new ApiException('文件不存在', 404);
        }
        $user = JwtService::currentUser($this->request);
        if ($user && (int) $user->id === (int) $att->user_id) {
            return $att;
        }
        $exp = (int) $this->request->get('exp', 0);
        $sig = (string) $this->request->get('sig', '');
        $variant = (string) $this->request->get('variant', '');
        if ($exp && $sig && (new AttachmentService())->verifySignature($id, $exp, $sig, $variant)) {
            return $att;
        }
        throw new ApiException('无权访问该文件', 401);
    }

    private function serveCos(string $path, string $mime, string $name, bool $download)
    {
        return new CosStreamResponse($path, $mime, $name, $download, $this->request->header('range'), $this->request->isHead());
    }

    private function serve(string $path, string $mime, string $name, bool $forceDownload)
    {
        if (!is_file($path)) {
            throw new ApiException('文件已不存在', 404);
        }
        $disposition = ($forceDownload ? 'attachment' : 'inline') . "; filename*=UTF-8''" . rawurlencode($name);
        $headers = [
            'Content-Type'        => $mime ?: 'application/octet-stream',
            'Content-Disposition' => $disposition,
            'Cache-Control'       => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if (filter_var(env('UPLOAD_ACCEL', false), FILTER_VALIDATE_BOOLEAN)) {
            $root = rtrim((string) config('filesystem.disks.uploads.root'), '/');
            $rel  = ltrim(substr($path, strlen($root)), '/');
            $headers['X-Accel-Redirect'] = rtrim((string) env('UPLOAD_ACCEL_PREFIX', '/_protected'), '/') . '/' . $rel;
            return response('', 200, $headers);
        }

        // 无 Nginx 时由 PHP 流式输出，支持 Range（音视频拖动进度 / iOS Safari 播放）
        return new FileStreamResponse($path, $mime, $name, !$forceDownload, $this->request->header('range'), $this->request->isHead());
    }
}
