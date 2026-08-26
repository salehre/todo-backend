<?php

namespace App\Http\Controllers;

use App\Events\InviteSent;
use App\Events\MessageSent;
use App\Models\GroupMessage;
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
        abort_unless($member && in_array($member->role, ['admin','owner']), 403, 'فقط مدیر و مالک گروه اجازه‌ی دعوت داره');

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

        $message = GroupMessage::create([
            'group_id' => $invite->group_id,
            'sender_id' => $invite->user_id,
            'text' => "{$request->user()->name} به گروه اضافه شد",
            'type' => 'system',
        ])->load(['reactions', 'task', 'attachments']);

        event(new MessageSent($invite->group_id, $this->formatMessageForNotice($message)));


        return response()->json(['success' => true, 'groupId' => $invite->group_id]);
    }

    private function formatMessageForNotice(GroupMessage $m): array
    {
        return [
            'id' => $m->id,
            'senderId' => $m->sender_id,
            'text' => $m->text,
            'timestamp' => $m->created_at,
            'type' => $m->type,
            'pinned' => false,
            'edited' => false,
            'replyTo' => null,
            'reactions' => (object) [],
            'readBy' => [],
            'mentions' => [],
            'todoRef' => null,
            'attachments' => [],
        ];
    }

    // PUT /invites/{invite}/decline
    public function decline(Request $request, GroupInvite $invite)
    {
        abort_unless($invite->user_id === $request->user()->id, 403);
        $invite->update(['status' => 'declined']);

        return response()->json(['success' => true]);
    }
}
