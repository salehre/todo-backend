<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'priority',
        'is_completed',
        'group_id',
        'assigned_to',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function assignee()
    {
            return $this->belongsTo(User::class, 'assigned_to');
    }

    public function steps()
    {
        return $this->hasMany(Step::class)->orderBy('position');
    }
}
