<?php

use App\Models\Group;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('group.{groupId}', function ($user, $groupId) {
    return Group::find($groupId)?->members()->where('user_id', $user->id)->exists() ?? false;
});
