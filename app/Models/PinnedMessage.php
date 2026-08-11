<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PinnedMessage extends Model
{
    protected $fillable = ['group_id', 'message_id', 'pinned_by'];

    public $timestamps = false;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->created_at = now();
        });
    }

    public function message()
    {
        return $this->belongsTo(GroupMessage::class, 'message_id');
    }
}
