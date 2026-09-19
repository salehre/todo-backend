<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RunbookTask extends Model
{
    protected $fillable = ['runbook_id', 'title', 'description', 'priority', 'order'];

    public function runbook()
    {
        return $this->belongsTo(Runbook::class);
    }
}
