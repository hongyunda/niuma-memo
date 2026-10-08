<?php
// 附件上传配置
return [
    'storage'    => env('UPLOAD_STORAGE', 'cos'),
    'max_mb'   => (int) env('UPLOAD_MAX_MB', 200),
    'ffmpeg'   => env('FFMPEG_BIN', ''),
    'ffprobe'  => env('FFPROBE_BIN', ''),
    // 扩展名 → 文件类型
    'types' => [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif', 'bmp'],
        'audio' => ['mp3', 'm4a', 'aac', 'wav', 'ogg', 'opus', 'webm', 'amr', 'flac', 'weba'],
        'video' => ['mp4', 'mov', 'm4v', 'avi', 'mkv', 'webm', '3gp'],
        'pdf'   => ['pdf'],
        'word'  => ['doc', 'docx', 'rtf'],
        'excel' => ['xls', 'xlsx', 'csv'],
        'ppt'   => ['ppt', 'pptx'],
        'other' => ['txt', 'md', 'zip', 'rar', '7z', 'json', 'xml', 'psd', 'ai', 'sketch', 'fig', 'xmind', 'key', 'numbers', 'pages'],
    ],
    // 绝对禁止的扩展名（可执行 / 可脚本）
    'deny' => ['php', 'phtml', 'php3', 'php5', 'phar', 'html', 'htm', 'js', 'mjs', 'svg', 'exe', 'sh', 'bat', 'cmd', 'jsp', 'asp', 'aspx', 'cgi', 'pl', 'py'],
    // 缩略图边长
    'thumb_size' => 480,
];
