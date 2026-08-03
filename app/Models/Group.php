<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = ['name', 'created_by'];

    public function members()
    {
        return $this->belongsToMany(User::class, 'group_members');
    }

    public function messages()
    {
        return $this->hasMany(GroupMessage::class)->orderBy('created_at');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
