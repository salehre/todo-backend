<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // GET /users/search?q=...
    public function search(Request $request)
    {
        $q = trim($request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $users = User::where('id', '!=', $request->user()->id)
            ->where(function ($query) use ($q) {
                $query->where('username', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%");
            })
            ->limit(10)
            ->get(['id', 'name', 'username', 'avatar']);

        return response()->json($users->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'username' => $u->username,
            'avatarUrl' => $u->avatar ? asset('storage/' . $u->avatar) : null,
        ]));
    }

    // GET /users/{id}/profile
    public function showProfile(int $id)
    {
        $user = \App\Models\User::findOrFail($id);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'avatarUrl' => $user->avatar ? asset('storage/' . $user->avatar) : null,
            'coverUrl' => $user->cover ? asset('storage/' . $user->cover) : null,
            'bio' => $user->bio,
            'social_links' => $user->social_links ?? [],
        ]);
    }

    // GET /users/me/activity
    public function activity(Request $request)
    {
        $userId = $request->user()->id;

        // ۷ روز اخیر رو پایه بذاریم؛ برای هر روز سه‌تا شمارنده حساب می‌کنیم
        $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));

        $personalTasks = \App\Models\Task::where('user_id', $userId)
            ->whereNull('group_id')
            ->whereDate('created_at', '>=', now()->subDays(6))
            ->get()
            ->groupBy(fn ($t) => $t->created_at->format('Y-m-d'));

        $groupTasks = \App\Models\Task::whereNotNull('group_id')
            ->whereHas('assignees', fn ($q) => $q->where('users.id', $userId))
            ->whereDate('created_at', '>=', now()->subDays(6))
            ->get()
            ->groupBy(fn ($t) => $t->created_at->format('Y-m-d'));

        $completedTasks = \App\Models\Task::where(function ($q) use ($userId) {
            $q->where(function ($iq) use ($userId) {
                $iq->where('user_id', $userId)->whereNull('group_id');
            })->orWhere(function ($iq) use ($userId) {
                $iq->whereNotNull('group_id')->whereHas('assignees', fn ($aq) => $aq->where('users.id', $userId));
            });
        })
            ->where('is_completed', true)
            ->whereDate('updated_at', '>=', now()->subDays(6))
            ->get()
            ->groupBy(fn ($t) => $t->updated_at->format('Y-m-d'));

        return response()->json([
            'labels' => $days->values(),
            'personal' => $days->map(fn ($d) => $personalTasks->get($d, collect())->count())->values(),
            'group' => $days->map(fn ($d) => $groupTasks->get($d, collect())->count())->values(),
            'completed' => $days->map(fn ($d) => $completedTasks->get($d, collect())->count())->values(),
        ]);
    }
}
