<?php
namespace app\model;

use think\Model;
use think\model\concern\SoftDelete;

class Note extends Model
{
    use SoftDelete;

    protected $name = 'note';
    protected $hidden = ['title_input_hash', 'title_started_at', 'title_error', 'type', 'status', 'priority', 'source', 'done_at'];
    protected $deleteTime = 'deleted_at';
    protected $defaultSoftDelete = null;

    protected $type = [
        'id'          => 'integer',
        'user_id'     => 'integer',
        'project_id'  => 'integer',
        'is_pinned'   => 'integer',
        'is_archived' => 'integer',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'note_tag', 'tag_id', 'note_id');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'note_id')->order('id', 'asc');
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class, 'note_id')->order('remind_at', 'asc');
    }
}
