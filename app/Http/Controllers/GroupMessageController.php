<?php

namespace App\Http\Controllers;

use App\Events\MessageDeleted;
use App\Events\MessagePinned;
use App\Events\MessageReacted;
use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Events\UserTyping;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMessage;
use App\Models\MessageReaction;
use Illuminate\Http\Request;
use App\Models\PinnedMessage;
use App\Models\MessageMention;

class GroupMessageController extends Controller
{
    private function member(Request $request, Group $group): GroupMember
    {
        $member = GroupMember::where('group_id', $group->id)
            ->where('user_id', $request->user()->id)
            ->first();

        abort_unless($member, 403);

        return $member;
    }

    // GET /groups/{group}/messages
    public function index(Request $request, Group $group)
    {
        $this->member($request, $group);

        $messages = $group->messages()->with(['reactions', 'task', 'attachments'])->get();
        $memberships = GroupMember::where('group_id', $group->id)->get(['user_id', 'last_read_message_id']);
        $pinnedIds = PinnedMessage::where('group_id', $group->id)->pluck('message_id');

        return response()->json(
            $messages->map(fn ($m) => $this->formatMessage($m, $memberships, $pinnedIds))
        );
    }

    // POST /groups/{group}/messages
    public function store(Request $request, Group $group)
    {
        $this->member($request, $group);

        $data = $request->validate([
            'text' => 'nullable|string|max:5000',
            'reply_to' => 'nullable|integer|exists:group_messages,id',
            'task_id' => 'nullable|integer|exists:tasks,id',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|max:10240',
            'voice_duration' => 'nullable|integer',
            'mentions' => 'nullable|array',
            'mentions.*' => 'integer|exists:users,id',
        ]);

        $message = GroupMessage::create([
            'group_id' => $group->id,
            'sender_id' => $request->user()->id,
            'text' => $data['text'] ?? null,
            'reply_to' => $data['reply_to'] ?? null,
            'task_id' => $data['task_id'] ?? null,
        ]);
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $index => $file) {
                $isImage = in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg', 'png', 'webp', 'gif']);
                $message->attachments()->create([
                    'type' => !empty($data['voice_duration']) ? 'voice' : ($isImage ? 'image' : 'file'),
                    'path' => $file->store('chat-attachments', 'public'),
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'voice_duration' => $data['voice_duration'] ?? null,
                    'position' => $index,
                ]);
            }}
       $message->load(['reactions', 'task', 'attachments']);

        if (!empty($data['mentions'])) {
            $validMentions = $group->members()->whereIn('users.id', $data['mentions'])->pluck('users.id');
            foreach ($validMentions as $userId) {
                MessageMention::create(['message_id' => $message->id, 'user_id' => $userId]);
            }
            $message->load('mentions');
        }

        $formatted = $this->formatMessage($message);
        \Log::info('Broadcasting to group', ['group_id' => $group->id]);
        event(new MessageSent($group->id, $formatted));

        return response()->json($formatted);
    }

    // PUT /groups/{group}/messages/{message}
    public function update(Request $request, Group $group, GroupMessage $message)
    {
        $this->member($request, $group);
        abort_unless($message->sender_id === $request->user()->id, 403);

        $data = $request->validate(['text' => 'required|string|max:5000']);
        $message->update(['text' => $data['text'], 'edited' => true]);

        $formatted = $this->formatMessage($message->load(['reactions', 'task']));
        event(new MessageUpdated($group->id, $formatted));

        return response()->json($formatted);
    }

    // DELETE /groups/{group}/messages/{message}
    public function destroy(Request $request, Group $group, GroupMessage $message)
    {
        $this->member($request, $group);
        abort_unless($message->sender_id === $request->user()->id, 403);

        $messageId = $message->id;
        $message->delete();

        event(new MessageDeleted($group->id, $messageId));

        return response()->json(['success' => true]);
    }

    // PUT /groups/{group}/messages/{message}/pin
    public function togglePin(Request $request, Group $group, GroupMessage $message)
    {
        $this->member($request, $group);
        $existing = PinnedMessage::where('group_id', $group->id)->where('message_id', $message->id)->first();

        if ($existing) {
            $existing->delete();
            $pinned = false;
        } else {
            PinnedMessage::create([
                'group_id' => $group->id,
                'message_id' => $message->id,
                'pinned_by' => $request->user()->id,
            ]);
            $pinned = true;
        }

        event(new MessagePinned($group->id, $message->id, $pinned));
        return response()->json(['success' => true, 'pinned' => $pinned]);
    }

    public function pinnedMessages(Request $request, Group $group)
    {
        $this->member($request, $group);
        $pinned = PinnedMessage::where('group_id', $group->id)
            ->with(['message.reactions', 'message.task'])
            ->latest()
            ->get();

        return response()->json(
            $pinned->map(fn ($p) => $this->formatMessage($p->message))
        );
    }


    // POST /groups/{group}/messages/{message}/react
    public function react(Request $request, Group $group, GroupMessage $message)
    {
        $this->member($request, $group);
        $data = $request->validate(['emoji' => 'required|string|max:10']);
        $userId = $request->user()->id;

        $existing = MessageReaction::where('message_id', $message->id)->where('user_id', $userId)->first();

        if ($existing && $existing->emoji === $data['emoji']) {
            $existing->delete();
        } else {
            MessageReaction::updateOrCreate(
                ['message_id' => $message->id, 'user_id' => $userId],
                ['emoji' => $data['emoji']]
            );
        }

        $formatted = $this->formatMessage($message->fresh()->load(['reactions', 'task']));
        event(new MessageReacted($group->id, $formatted));

        return response()->json($formatted);
    }

    // PUT /groups/{group}/read   body: { message_id }
    public function markRead(Request $request, Group $group)
    {
        $member = $this->member($request, $group);
        $data = $request->validate(['message_id' => 'required|integer|exists:group_messages,id']);

        // فقط اگه پیام جدیدتر از آخرین‌خوانده‌شده باشه آپدیت کن (عقب نره)
        if (! $member->last_read_message_id || $data['message_id'] > $member->last_read_message_id) {
            $member->update(['last_read_message_id' => $data['message_id']]);
            event(new MessageRead($group->id, $request->user()->id, $data['message_id']));
        }

        return response()->json(['success' => true]);
    }

    // POST /groups/{group}/typing  (بدون هیچ ذخیره‌ای، فقط broadcast لحظه‌ای)
    public function typing(Request $request, Group $group)
    {
        $this->member($request, $group);
        event(new UserTyping($group->id, $request->user()->id, $request->user()->name));

        return response()->json(['success' => true]);
    }

    private function formatMessage(GroupMessage $m, $memberships = null, $pinnedIds = null): array
    {
        $reactions = [];
        foreach ($m->reactions as $r) {
            $reactions[$r->emoji][] = $r->user_id;
        }

        $isPinned = $pinnedIds ? $pinnedIds->contains($m->id) : PinnedMessage::where('message_id', $m->id)->exists();

        $readBy = [];
        if ($memberships) {
            $readBy = $memberships
                ->filter(fn ($mem) => $mem->last_read_message_id >= $m->id)
                ->pluck('user_id')
                ->values();
        }

        return [
            'id' => $m->id,
            'senderId' => $m->sender_id,
            'text' => $m->text,
            'timestamp' => $m->created_at,
            'type' => $m->type,
            'pinned' => $isPinned,
            'edited' => (bool) $m->edited,
            'replyTo' => $m->reply_to,
            'mentions' => $m->mentions?->pluck('user_id') ?? [],
            'reactions' => (object) $reactions,
            'readBy' => $readBy,
            'todoRef' => $m->task ? ['id' => $m->task->id, 'text' => $m->task->title, 'priority' => $m->task->priority] : null,
            'attachments' => $m->attachments->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'size' => $a->size,
                'type' => $a->type,
                'url' => $a->url,
                'voiceDuration' => $a->voice_duration,
            ]),
        ];
    }
}
