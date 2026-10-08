<?php
namespace app\model;

use think\Model;

class Tag extends Model
{
    protected $name = 'tag';

    protected $type = ['id' => 'integer', 'user_id' => 'integer'];

    public function notes()
    {
        return $this->belongsToMany(Note::class, 'note_tag', 'note_id', 'tag_id');
    }
}
