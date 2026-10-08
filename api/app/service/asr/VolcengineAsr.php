<?php
namespace app\service\asr;

use app\support\Http;
use RuntimeException;

/**
 * 火山引擎 豆包 大模型录音文件极速版识别（一次请求直接返回，无需轮询）
 * 文档：https://www.volcengine.com/docs/6561/1631584
 * 按量后付费，资源 ID 默认 volc.bigasr.auc_turbo
 */
class VolcengineAsr implements AsrDriver
{
    private array $cfg;

    public function __construct()
    {
        $this->cfg = (array) config('asr.volcengine');
    }

    public function submit(string $filePath, string $format, ?string $publicUrl): array
    {
        $appKey    = trim((string) $this->cfg['app_key']);
        $accessKey = trim((string) $this->cfg['access_key']);
        if ($appKey === '') {
            return ['status' => self::FAILED, 'error' => '未配置火山引擎 App Key'];
        }

        $size = filesize($filePath);
        if ($size <= (int) config('asr.inline_max_bytes')) {
            $audio = ['data' => base64_encode((string) file_get_contents($filePath))];
        } elseif ($publicUrl) {
            $audio = ['url' => $publicUrl];
        } else {
            return ['status' => self::FAILED, 'error' => '音频过大且没有公网访问地址'];
        }
        $fmt = strtolower($format);
        if (in_array($fmt, ['mp3', 'wav', 'ogg'], true)) {
            $audio['format'] = $fmt;
        }

        // 新版控制台只有 App Key（作为 X-Api-Key）；旧版控制台是 App ID + Access Token
        $headers = [
            'X-Api-Resource-Id: ' . ($this->cfg['resource_id'] ?: 'volc.bigasr.auc_turbo'),
            'X-Api-Request-Id: ' . $this->uuid(),
            'X-Api-Sequence: -1',
        ];
        if ($accessKey !== '') {
            $headers[] = 'X-Api-App-Key: ' . $appKey;
            $headers[] = 'X-Api-Access-Key: ' . $accessKey;
        } else {
            $headers[] = 'X-Api-Key: ' . $appKey;
        }

        try {
            $res = Http::postJson((string) $this->cfg['endpoint'], [
                'user'    => ['uid' => $appKey],
                'audio'   => $audio,
                'request' => [
                    'model_name'  => 'bigmodel',
                    'enable_itn'  => true,
                    'enable_punc' => true,
                    'enable_ddc'  => true,
                ],
            ], $headers, 180);
        } catch (RuntimeException $e) {
            return ['status' => self::FAILED, 'error' => $e->getMessage()];
        }

        $code = (string) ($res['headers']['x-api-status-code'] ?? '');
        $msg  = (string) ($res['headers']['x-api-message'] ?? '');
        if ($code === '20000000') {
            return ['status' => self::DONE, 'text' => trim((string) ($res['json']['result']['text'] ?? ''))];
        }
        if ($code === '20000003') {
            return ['status' => self::DONE, 'text' => ''];
        }
        if ($code === '20000001' || $code === '20000002') {
            // 极速版理论上同步返回；万一进入排队，按失败处理并提示重试
            return ['status' => self::FAILED, 'error' => '服务排队中，请稍后重试'];
        }
        $detail = $msg ?: ($res['json']['message'] ?? ('HTTP ' . $res['status']));
        return ['status' => self::FAILED, 'error' => '火山引擎返回 ' . ($code ?: '-') . ': ' . $detail];
    }

    public function query(string $taskId): array
    {
        return ['status' => self::FAILED, 'error' => '极速版不支持异步查询'];
    }

    private function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
