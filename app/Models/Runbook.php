<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Runbook extends Model
{
    protected $fillable = ['name', 'created_by'];

    public function tasks()
    {
        return $this->hasMany(RunbookTask::class)->orderBy('order');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
