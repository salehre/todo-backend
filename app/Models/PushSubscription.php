<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushSubscription extends Model
{
    protected $fillable = [
        'user_id', 'endpoint', 'endpoint_hash', 'p256dh_key', 'auth_token',
        'content_encoding', 'user_agent',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
