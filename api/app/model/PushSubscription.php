<?php
namespace app\model;

use think\Model;

class PushSubscription extends Model
{
    protected $name = 'push_subscription';

    protected $type = ['id' => 'integer', 'user_id' => 'integer'];
}
