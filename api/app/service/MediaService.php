<?php
namespace app\service;

/**
 * ffmpeg / ffprobe 封装：时长读取、音频转 mp3、视频封面
 */
class MediaService
{
    private static array $binCache = [];

    public static function bin(string $name): ?string
    {
        if (array_key_exists($name, self::$binCache)) {
            return self::$binCache[$name];
        }
        $configured = (string) config('upload.' . $name, '');
        if ($configured !== '' && is_executable($configured)) {
            return self::$binCache[$name] = $configured;
        }
        $paths = array_filter(array_unique(array_merge(
            explode(PATH_SEPARATOR, (string) getenv('PATH')),
            ['/usr/local/bin', '/opt/homebrew/bin', '/usr/bin', '/bin']
        )));
        foreach ($paths as $dir) {
            $candidate = rtrim($dir, '/') . '/' . $name;
            if (is_executable($candidate)) {
                return self::$binCache[$name] = $candidate;
            }
        }
        return self::$binCache[$name] = null;
    }

    public static function hasFfmpeg(): bool
    {
        return self::bin('ffmpeg') !== null;
    }

    /** 音视频时长（秒），失败返回 0 */
    public static function duration(string $file): int
    {
        $ffprobe = self::bin('ffprobe');
        if (!$ffprobe || !is_file($file)) {
            return 0;
        }
        $cmd = sprintf(
            '%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>&1',
            escapeshellarg($ffprobe),
            escapeshellarg($file)
        );
        $out = trim((string) shell_exec($cmd));
        return is_numeric($out) ? (int) round((float) $out) : 0;
    }

    /** 转成 16k 单声道 mp3（体积小、所有浏览器和 ASR 服务商都能吃） */
    public static function toMp3(string $in, string $out, int $rate = 16000, string $bitrate = '48k'): bool
    {
        $ffmpeg = self::bin('ffmpeg');
        if (!$ffmpeg || !is_file($in)) {
            return false;
        }
        $cmd = sprintf(
            '%s -y -loglevel error -i %s -vn -ac 1 -ar %d -b:a %s -f mp3 %s 2>&1',
            escapeshellarg($ffmpeg),
            escapeshellarg($in),
            $rate,
            escapeshellarg($bitrate),
            escapeshellarg($out)
        );
        shell_exec($cmd);
        return is_file($out) && filesize($out) > 0;
    }

    /** 抽取视频第 1 秒作为封面 */
    public static function poster(string $video, string $out, int $size = 480): bool
    {
        $ffmpeg = self::bin('ffmpeg');
        if (!$ffmpeg || !is_file($video)) {
            return false;
        }
        $cmd = sprintf(
            '%s -y -loglevel error -ss 00:00:01 -i %s -frames:v 1 -vf %s %s 2>&1',
            escapeshellarg($ffmpeg),
            escapeshellarg($video),
            escapeshellarg("scale='min({$size},iw)':-2"),
            escapeshellarg($out)
        );
        shell_exec($cmd);
        if (!is_file($out) || filesize($out) === 0) {
            // 视频不足 1 秒时退回第 0 帧
            $cmd = str_replace('-ss 00:00:01 ', '', $cmd);
            shell_exec($cmd);
        }
        return is_file($out) && filesize($out) > 0;
    }
}
