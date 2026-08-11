<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupMessage extends Model
{
    protected $fillable = [
        'group_id', 'sender_id', 'text', 'type', 'reply_to', 'task_id',
        'edited',
    ];

    public function attachments()
    {
        return $this->hasMany(MessageAttachment::class, 'message_id')->orderBy('position');
    }

    protected $casts = [
        'edited' => 'boolean',
    ];

    public function mentions()
    {
        return $this->hasMany(MessageMention::class, 'message_id');
    }

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
