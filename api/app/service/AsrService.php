<?php
namespace app\service;

use app\model\Attachment;
use app\service\asr\AsrDriver;
use app\service\asr\TencentAsr;
use app\service\asr\VolcengineAsr;
use Throwable;

/**
 * 语音转文字调度：准备音频 → 提交 → （轮询）→ 写回 transcript
 */
class AsrService
{
    public static function provider(): string
    {
        return strtolower((string) config('asr.provider', 'none'));
    }

    public static function enabled(): bool
    {
        return match (self::provider()) {
            'tencent'    => (string) config('asr.tencent.secret_id') !== '' && (string) config('asr.tencent.secret_key') !== '',
            'volcengine' => (string) config('asr.volcengine.app_key') !== '',
            default      => false,
        };
    }

    public function driver(): AsrDriver
    {
        return match (self::provider()) {
            'tencent'    => new TencentAsr(),
            'volcengine' => new VolcengineAsr(),
            default      => throw new \RuntimeException('未启用语音转文字'),
        };
    }

    /** 推进一步：排队→提交，处理中→查询。返回更新后的附件 */
    public function process(Attachment $att): Attachment
    {
        if (!in_array($att->file_type, ['audio', 'video'], true)) {
            return $this->fail($att, '只有音频 / 视频可以转写');
        }
        if (!self::enabled()) {
            return $this->fail($att, '未配置语音转文字服务');
        }
        if ((int) $att->duration > (int) config('asr.max_duration')) {
            return $this->fail($att, '音频超过 ' . round(config('asr.max_duration') / 3600, 1) . ' 小时上限');
        }

        $driver = $this->driver();
        try {
            if ((int) $att->asr_status === Attachment::ASR_PROCESSING && $att->asr_task_id !== '') {
                $result = $driver->query((string) $att->asr_task_id);
            } else {
                $result = $this->submit($driver, $att);
            }
        } catch (Throwable $e) {
            return $this->fail($att, $e->getMessage());
        }

        return $this->apply($att, $result);
    }

    private function submit(AsrDriver $driver, Attachment $att): array
    {
        $files    = new AttachmentService();
        $source   = $files->localSource($att);
        try {
            if (!is_file($source)) {
                return ['status' => AsrDriver::FAILED, 'error' => '源文件不存在'];
            }
            $format = strtolower(pathinfo($source, PATHINFO_EXTENSION));
            $path   = $source;
            $variant = '';

            // 统一转成 16k 单声道 mp3：体积小、两家服务商都支持；视频顺带抽音轨
            if (MediaService::hasFfmpeg() && ($format !== 'mp3' || $att->file_type === 'video')) {
                $asrFile = $files->asrVariantOf($att);
                if (is_file($asrFile) || MediaService::toMp3($source, $asrFile)) {
                    $path    = $asrFile;
                    $format  = 'mp3';
                    $variant = 'asr';
                }
            } elseif (!in_array($format, ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'amr', 'flac', 'mp4'], true)) {
                return ['status' => AsrDriver::FAILED, 'error' => '服务器未安装 ffmpeg，无法转换 ' . $format . ' 格式'];
            }
            if ($format === 'aac' || $format === 'mp4') {
                $format = 'm4a';
            }

            $publicUrl = null;
            if (filesize($path) > (int) config('asr.inline_max_bytes')) {
                if ($att->storage === 'cos') {
                    $remotePath = (string) $att->getData('path') . ($variant === 'asr' ? '.asr.mp3' : '');
                    $cos = new CosStorage();
                    if ($variant === 'asr') $cos->put($remotePath, $path, 'audio/mpeg');
                    $publicUrl = $cos->url($remotePath, '', false, (int) config('asr.signed_url_ttl', 1800));
                } else {
                    $publicUrl = $files->signedUrl($att, $variant);
                }
            }
            $result = $driver->submit($path, $format, $publicUrl);

            // 小文件的转码副本用完即删；大文件保留给服务商拉取
            if ($variant === 'asr' && $publicUrl === null && $result['status'] !== AsrDriver::PROCESSING) {
                @unlink($path);
            }
            return $result;
        } finally {
            if ($att->storage === 'cos') {
                @unlink($source);
                @unlink($files->asrVariantOf($att));
            }
        }
    }

    private function apply(Attachment $att, array $result): Attachment
    {
        switch ($result['status'] ?? AsrDriver::FAILED) {
            case AsrDriver::DONE:
                $att->transcript  = (string) ($result['text'] ?? '');
                $att->asr_status  = Attachment::ASR_DONE;
                $att->asr_error   = $att->transcript === '' ? '未识别到有效语音' : '';
                $this->cleanupVariant($att);
                break;
            case AsrDriver::PROCESSING:
                $att->asr_status  = Attachment::ASR_PROCESSING;
                if (!empty($result['task_id'])) {
                    $att->asr_task_id = (string) $result['task_id'];
                }
                $att->asr_error = '';
                break;
            default:
                $att->asr_status = Attachment::ASR_FAILED;
                $att->asr_error  = mb_substr((string) ($result['error'] ?? '识别失败'), 0, 500);
                $this->cleanupVariant($att);
        }
        $att->save();
        if ($att->note_id && (int) $att->asr_status === Attachment::ASR_DONE) {
            (new NoteTitleService())->queue((int) $att->note_id);
        }
        return $att;
    }

    private function fail(Attachment $att, string $error): Attachment
    {
        return $this->apply($att, ['status' => AsrDriver::FAILED, 'error' => $error]);
    }

    private function cleanupVariant(Attachment $att): void
    {
        if ($att->storage === 'cos') {
            try { (new CosStorage())->delete($att->getData('path') . '.asr.mp3'); }
            catch (Throwable $e) { trace('COS 转码副本清理失败', 'warning'); }
        }
        $f = (new AttachmentService())->asrVariantOf($att);
        if (is_file($f)) {
            @unlink($f);
        }
    }
}
