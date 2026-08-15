<?php

use App\Models\Group;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('group.{groupId}', function ($user, $groupId) {
    return Group::find($groupId)?->members()->where('user_id', $user->id)->exists() ?? false;
});

Broadcast::channel('online-group.{groupId}', function ($user, $groupId) {
    $isMember = Group::find($groupId)?->members()->where('user_id', $user->id)->exists() ?? false;
    return $isMember ? ['id' => $user->id, 'name' => $user->name] : false;
});

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
