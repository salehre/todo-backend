<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageAttachment extends Model
{
    public $timestamps = false; // فقط created_at داریم، updated_at لازم نیست
    protected $fillable = ['message_id', 'type', 'path', 'name', 'size', 'voice_duration', 'position'];

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->path);
    }
}
