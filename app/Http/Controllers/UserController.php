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
        ]);
    }
}
