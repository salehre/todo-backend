<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageMention extends Model
{
    protected $fillable = ['message_id', 'user_id'];
    public $timestamps = false;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->created_at = now();
        });
    }
}
