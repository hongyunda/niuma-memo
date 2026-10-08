<?php
namespace app\model;

use think\Model;

class Attachment extends Model
{
    public const ASR_NONE       = 0;
    public const ASR_QUEUED     = 1;
    public const ASR_PROCESSING = 2;
    public const ASR_DONE       = 3;
    public const ASR_FAILED     = 4;

    protected $name = 'attachment';
    protected $hidden = ['path', 'thumb_path', 'hash', 'storage'];
    protected $append = ['url', 'thumb_url'];

    protected $type = [
        'id'          => 'integer',
        'user_id'     => 'integer',
        'note_id'    => 'integer',
        'project_id' => 'integer',
        'size'       => 'integer',
        'duration'   => 'integer',
        'width'      => 'integer',
        'height'     => 'integer',
        'asr_status' => 'integer',
    ];

    public function getUrlAttr($value, $data): string
    {
        return '/api/files/' . $data['id'];
    }

    public function getThumbUrlAttr($value, $data): string
    {
        return !empty($data['thumb_path']) ? '/api/files/' . $data['id'] . '/thumb' : '';
    }

    public function note()
    {
        return $this->belongsTo(Note::class, 'note_id');
    }

    public function isMedia(): bool
    {
        return in_array($this->file_type, ['audio', 'video'], true);
    }
}
