<?php
namespace app\model;

use think\Model;

class Project extends Model
{
    public const STATUS_ACTIVE   = 1;
    public const STATUS_ARCHIVED = 2;

    protected $name = 'project';
    protected $hidden = ['customer_id', 'type', 'color', 'icon'];

    protected $type = ['id' => 'integer', 'user_id' => 'integer'];

    public function notes()
    {
        return $this->hasMany(Note::class, 'project_id');
    }
}
