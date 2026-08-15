<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupInvite extends Model
{
    protected $fillable = ['group_id', 'user_id', 'invited_by', 'status'];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
