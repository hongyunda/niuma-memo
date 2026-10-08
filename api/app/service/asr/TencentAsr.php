<?php
namespace app\service\asr;

use app\support\Http;
use RuntimeException;

/**
 * 腾讯云 录音文件识别（CreateRecTask / DescribeTaskStatus）
 * 每月 10 小时免费额度，默认后付费，不用买套餐
 */
class TencentAsr implements AsrDriver
{
    private array $cfg;

    public function __construct()
    {
        $this->cfg = (array) config('asr.tencent');
    }

    public function submit(string $filePath, string $format, ?string $publicUrl): array
    {
        $params = [
            'EngineModelType' => $this->cfg['engine'] ?: '16k_zh',
            'ChannelNum'      => 1,
            'ResTextFormat'   => 0,
            'FilterModal'     => 1,
            'ConvertNumMode'  => 1,
        ];
        $size = filesize($filePath);
        if ($size <= (int) config('asr.inline_max_bytes')) {
            $params['SourceType'] = 1;
            $params['Data']       = base64_encode((string) file_get_contents($filePath));
            $params['DataLen']    = $size;
        } elseif ($publicUrl) {
            $params['SourceType'] = 0;
            $params['Url']        = $publicUrl;
        } else {
            return ['status' => self::FAILED, 'error' => '音频过大且没有公网访问地址'];
        }

        try {
            $data = $this->call('CreateRecTask', $params);
        } catch (RuntimeException $e) {
            return ['status' => self::FAILED, 'error' => $e->getMessage()];
        }
        $taskId = $data['Data']['TaskId'] ?? null;
        if (!$taskId) {
            return ['status' => self::FAILED, 'error' => '未返回 TaskId'];
        }
        return ['status' => self::PROCESSING, 'task_id' => (string) $taskId];
    }

    public function query(string $taskId): array
    {
        try {
            $data = $this->call('DescribeTaskStatus', ['TaskId' => (int) $taskId]);
        } catch (RuntimeException $e) {
            return ['status' => self::FAILED, 'error' => $e->getMessage()];
        }
        $d = $data['Data'] ?? [];
        $statusStr = (string) ($d['StatusStr'] ?? '');
        if ($statusStr === 'success') {
            return ['status' => self::DONE, 'text' => $this->cleanResult((string) ($d['Result'] ?? ''))];
        }
        if ($statusStr === 'failed') {
            return ['status' => self::FAILED, 'error' => (string) ($d['ErrorMsg'] ?? '识别失败')];
        }
        return ['status' => self::PROCESSING];
    }

    /** ResTextFormat=0 的结果每行形如 "[0:0.000,0:2.500]  文本"，去掉时间戳 */
    private function cleanResult(string $result): string
    {
        $lines = preg_split('/\r?\n/', trim($result)) ?: [];
        $lines = array_map(fn($l) => trim((string) preg_replace('/^\[[^\]]*\]\s*/', '', $l)), $lines);
        return trim(implode("\n", array_filter($lines, fn($l) => $l !== '')));
    }

    /** TC3-HMAC-SHA256 签名调用 */
    private function call(string $action, array $params): array
    {
        $secretId  = (string) $this->cfg['secret_id'];
        $secretKey = (string) $this->cfg['secret_key'];
        if ($secretId === '' || $secretKey === '') {
            throw new RuntimeException('未配置腾讯云 SecretId / SecretKey');
        }
        $host      = (string) ($this->cfg['endpoint'] ?: 'asr.tencentcloudapi.com');
        $service   = 'asr';
        $version   = '2019-06-14';
        $timestamp = time();
        $date      = gmdate('Y-m-d', $timestamp);
        $payload   = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $canonicalHeaders = "content-type:application/json; charset=utf-8\nhost:{$host}\nx-tc-action:" . strtolower($action) . "\n";
        $signedHeaders    = 'content-type;host;x-tc-action';
        $canonicalRequest = "POST\n/\n\n{$canonicalHeaders}\n{$signedHeaders}\n" . hash('sha256', $payload);
        $credentialScope  = "{$date}/{$service}/tc3_request";
        $stringToSign     = "TC3-HMAC-SHA256\n{$timestamp}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        $secretDate    = hash_hmac('sha256', $date, 'TC3' . $secretKey, true);
        $secretService = hash_hmac('sha256', $service, $secretDate, true);
        $secretSigning = hash_hmac('sha256', 'tc3_request', $secretService, true);
        $signature     = hash_hmac('sha256', $stringToSign, $secretSigning);

        $authorization = "TC3-HMAC-SHA256 Credential={$secretId}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";
        $headers = [
            'Authorization: ' . $authorization,
            'Content-Type: application/json; charset=utf-8',
            'Host: ' . $host,
            'X-TC-Action: ' . $action,
            'X-TC-Timestamp: ' . $timestamp,
            'X-TC-Version: ' . $version,
        ];

        $res  = Http::request('POST', 'https://' . $host . '/', $payload, $headers, 60);
        $json = json_decode($res['body'], true) ?: [];
        $resp = $json['Response'] ?? [];
        if (!empty($resp['Error'])) {
            throw new RuntimeException(($resp['Error']['Code'] ?? 'Error') . ': ' . ($resp['Error']['Message'] ?? ''));
        }
        if ($res['status'] >= 400) {
            throw new RuntimeException('腾讯云接口 HTTP ' . $res['status']);
        }
        return $resp;
    }
}
