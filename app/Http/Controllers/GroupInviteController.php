<?php

namespace App\Http\Controllers;

use App\Events\InviteSent;
use App\Models\Group;
use App\Models\GroupInvite;
use App\Models\GroupMember;
use Illuminate\Http\Request;

class GroupInviteController extends Controller
{
    // GET /invites — دعوت‌نامه‌های در انتظار خودِ من
    public function index(Request $request)
    {
        $invites = GroupInvite::where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->with(['group:id,name,avatar', 'inviter:id,name'])
            ->latest()
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'groupId' => $i->group_id,
                'groupName' => $i->group->name,
                'groupAvatarUrl' => $i->group->avatar ? asset('storage/' . $i->group->avatar) : null,
                'invitedByName' => $i->inviter->name,
                'createdAt' => $i->created_at,
            ]);

        return response()->json($invites);
    }

    // POST /groups/{group}/invites — فقط مدیر
    public function store(Request $request, Group $group)
    {
        $member = GroupMember::where('group_id', $group->id)->where('user_id', $request->user()->id)->first();
        abort_unless($member && $member->role === 'admin', 403, 'فقط مدیر گروه اجازه‌ی دعوت داره');

        $data = $request->validate(['user_id' => 'required|integer|exists:users,id']);

        abort_if(
            GroupMember::where('group_id', $group->id)->where('user_id', $data['user_id'])->exists(),
            422,
            'این کاربر از قبل عضو گروهه'
        );

        $invite = GroupInvite::updateOrCreate(
            ['group_id' => $group->id, 'user_id' => $data['user_id']],
            ['invited_by' => $request->user()->id, 'status' => 'pending']
        );

        event(new InviteSent($data['user_id'], [
            'id' => $invite->id,
            'groupId' => $group->id,
            'groupName' => $group->name,
            'groupAvatarUrl' => $group->avatar ? asset('storage/' . $group->avatar) : null,
            'invitedByName' => $request->user()->name,
            'createdAt' => $invite->created_at,
        ]));

        return response()->json(['success' => true]);
    }

    // PUT /invites/{invite}/accept
    public function accept(Request $request, GroupInvite $invite)
    {
        abort_unless($invite->user_id === $request->user()->id, 403);
        abort_unless($invite->status === 'pending', 422, 'این دعوت دیگه معتبر نیست');

        GroupMember::firstOrCreate(
            ['group_id' => $invite->group_id, 'user_id' => $invite->user_id],
            ['role' => 'member']
        );
        $invite->update(['status' => 'accepted']);

        return response()->json(['success' => true, 'groupId' => $invite->group_id]);
    }

    // PUT /invites/{invite}/decline
    public function decline(Request $request, GroupInvite $invite)
    {
        abort_unless($invite->user_id === $request->user()->id, 403);
        $invite->update(['status' => 'declined']);

        return response()->json(['success' => true]);
    }
}
