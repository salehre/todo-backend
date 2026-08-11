<?php
// app/Models/GroupMember.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupMember extends Model
{
    protected $fillable = ['group_id', 'user_id', 'last_read_message_id', 'role'];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
