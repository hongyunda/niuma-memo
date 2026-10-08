<?php
namespace app\service\asr;

interface AsrDriver
{
    public const DONE       = 'done';
    public const PROCESSING = 'processing';
    public const FAILED     = 'failed';

    /**
     * 提交识别。
     * @param string      $filePath  本地音频文件（已尽量转成 16k 单声道 mp3）
     * @param string      $format    mp3 / wav / m4a / ogg
     * @param string|null $publicUrl 文件过大时可用的临时公网下载链接
     * @return array{status:string, task_id?:string, text?:string, error?:string}
     */
    public function submit(string $filePath, string $format, ?string $publicUrl): array;

    /**
     * 查询异步任务
     * @return array{status:string, text?:string, error?:string}
     */
    public function query(string $taskId): array;
}
