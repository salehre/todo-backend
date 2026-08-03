<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupMessage extends Model
{
    protected $fillable = [
        'group_id', 'sender_id', 'text', 'type', 'reply_to', 'task_id',
        'pinned', 'edited', 'attachment_type', 'attachment_path',
        'attachment_name', 'attachment_size', 'voice_duration',
    ];

    protected $casts = [
        'pinned' => 'boolean',
        'edited' => 'boolean',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyToMessage()
    {
        return $this->belongsTo(GroupMessage::class, 'reply_to');
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function reactions()
    {
        return $this->hasMany(MessageReaction::class, 'message_id');
    }
}
