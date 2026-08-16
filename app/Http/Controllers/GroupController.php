<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    private function requireAdmin(Request $request, Group $group): GroupMember
    {
        $member = GroupMember::where('group_id', $group->id)
            ->where('user_id', $request->user()->id)
            ->first();

        abort_unless($member, 403);
        abort_unless($member->role === 'admin', 403, 'فقط مدیر گروه اجازه‌ی این کار رو داره');

        return $member;
    }

    // GET /groups
    public function index(Request $request)
    {
        $groups = $request->user()->groups()
                ->withCount('members')
                ->with(['messages' => fn ($q) => $q->latest()->limit(1)])
                ->get()
                ->sortByDesc(fn ($g) => optional($g->messages->first())->created_at ?? $g->created_at)
                ->values()
                ->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'description' => $g->description,
                    'avatar_url' => $g->avatar ? asset('storage/' . $g->avatar) : null,
                    'members_count' => $g->members_count,
                    'last_message_at' => optional($g->messages->first())->created_at,
                    'created_at' => $g->created_at,
                   ]);
        return response()->json($groups);
    }

    // GET /groups/{group}
    public function show(Request $request, Group $group)
    {
        abort_unless($group->members()->where('user_id', $request->user()->id)->exists(), 403);

        return response()->json([
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'avatar_url' => $group->avatar ? asset('storage/' . $group->avatar) : null,
        ]);
    }

    // POST /groups
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $group = Group::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        // سازنده‌ی گروه خودکار مدیرشه
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $request->user()->id,
            'role' => 'admin',
        ]);

        return response()->json($group);
    }

    // PUT /groups/{group} — فقط مدیر
    public function update(Request $request, Group $group)
    {
        $this->requireAdmin($request, $group);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
        ]);

        $group->update($data);
        return response()->json(['success' => true]);
    }

    // POST /groups/{group}/avatar — فقط مدیر
    public function uploadAvatar(Request $request, Group $group)
    {
        $this->requireAdmin($request, $group);

        $request->validate(['avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048']);

        if ($group->avatar) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($group->avatar);
        }

        $path = $request->file('avatar')->store('group-avatars', 'public');
        $group->update(['avatar' => $path]);

        return response()->json(['success' => true, 'avatar_url' => asset('storage/' . $path)]);
    }

    public function destroy(Request $request, Group $group)
    {
        $this->requireAdmin($request, $group);

        $attachmentPaths = \App\Models\MessageAttachment::whereIn(
            'message_id',
            $group->messages()->pluck('id')
        )->pluck('path');
        foreach ($attachmentPaths as $path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
        }
        if ($group->avatar) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($group->avatar);
        }

        $group->delete();

        return response()->json(['success' => true]);
    }

    // GET /groups/{group}/members
    public function members(Request $request, Group $group)
    {
        abort_unless($group->members()->where('user_id', $request->user()->id)->exists(), 403);

        $members = GroupMember::where('group_id', $group->id)
            ->with('user')
            ->get()
            ->map(fn ($m) => [
                'userId' => $m->user_id,
                'name' => $m->user->name,
                'username' => $m->user->username,
                'avatarUrl' => $m->user->avatar ? asset('storage/' . $m->user->avatar) : null,
                'role' => $m->role,
            ]);

        return response()->json($members);
    }

    public function removeMember(Request $request, Group $group, int $userId)
    {
        $requester = GroupMember::where('group_id', $group->id)->where('user_id', $request->user()->id)->first();
        abort_unless($requester, 403);
        abort_unless($requester->role === 'admin' || $requester->user_id === $userId, 403);

        GroupMember::where('group_id', $group->id)->where('user_id', $userId)->delete();

        return response()->json(['success' => true]);
    }

    // PUT /groups/{group}/members/{userId}/role — فقط مدیر، ارتقا/تنزل نقش
    public function updateRole(Request $request, Group $group, int $userId)
    {
        $this->requireAdmin($request, $group);

        $data = $request->validate(['role' => 'required|in:admin,member']);

        GroupMember::where('group_id', $group->id)->where('user_id', $userId)
            ->update(['role' => $data['role']]);

        return response()->json(['success' => true]);
    }
}
