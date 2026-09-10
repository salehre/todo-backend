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
        'ordered_steps',
        'last_edited_by',
    ];

    public function lastEditor()
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    protected $casts = [
        'is_completed' => 'boolean',
        'ordered_steps' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function assignees()
    {
        return $this->belongsToMany(User::class, 'task_assignees');
    }

    public function steps()
    {
        return $this->hasMany(Step::class)->orderBy('position');
    }
}
