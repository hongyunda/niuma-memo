<?php
namespace app\model;

use think\Model;

class ReminderLog extends Model
{
    protected $name = 'reminder_log';

    protected $type = ['id' => 'integer', 'user_id' => 'integer'];
    protected $autoWriteTimestamp = false;
}
