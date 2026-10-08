<?php
namespace app\model;

use think\Model;

class Reminder extends Model
{
    public const STATUS_PENDING   = 1; // 待提醒
    public const STATUS_SENT      = 2; // 已提醒（未处理）
    public const STATUS_DONE      = 3; // 已完成
    public const STATUS_CANCELLED = 4; // 已取消

    public const REPEAT_NONE    = 0;
    public const REPEAT_DAILY   = 1;
    public const REPEAT_WEEKLY  = 2;
    public const REPEAT_MONTHLY = 3;
    public const REPEAT_YEARLY  = 4;
    public const REPEAT_WORKDAY = 5;

    protected $name = 'reminder';

    protected $type = [
        'id'          => 'integer',
        'user_id'     => 'integer',
        'note_id'         => 'integer',
        'advance_minutes' => 'integer',
        'repeat_type'     => 'integer',
        'status'          => 'integer',
    ];

    public function note()
    {
        return $this->belongsTo(Note::class, 'note_id');
    }

    public function channelList(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->channels))));
    }
}
