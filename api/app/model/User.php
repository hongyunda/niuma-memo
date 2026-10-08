<?php
namespace app\model;

use think\Model;

class User extends Model
{
    protected $name = 'user';

    protected $type = ['id' => 'integer'];
    protected $hidden = ['password'];
    protected $json = ['push_config'];
    protected $jsonAssoc = true;

    public function pushConfig(): array
    {
        $cfg = $this->push_config;
        return is_array($cfg) ? $cfg : [];
    }
}
