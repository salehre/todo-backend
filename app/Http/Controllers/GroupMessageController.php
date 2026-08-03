<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\MessageReaction;
use Illuminate\Http\Request;

class GroupMessageController extends Controller
{
    // یه چک کمکی: فقط عضو گروه بتونه چیزی ببینه/بفرسته
    private function authorizeMember(Request $request, Group $group): void
    {
        abort_unless($group->members()->where('user_id', $request->user()->id)->exists(), 403);
    }

    // GET /groups/{group}/messages
    public function index(Request $request, Group $group)
    {
        $this->authorizeMember($request, $group);

        $messages = $group->messages()
            ->with(['reactions', 'task'])
            ->get()
            ->map(fn ($m) => $this->formatMessage($m));

        return response()->json($messages);
    }

    // POST /groups/{group}/messages
    public function store(Request $request, Group $group)
    {
        $this->authorizeMember($request, $group);

        $data = $request->validate([
            'text' => 'nullable|string|max:5000',
            'reply_to' => 'nullable|integer|exists:group_messages,id',
            'task_id' => 'nullable|integer|exists:tasks,id',
            'attachment' => 'nullable|file|max:10240', // 10MB
            'voice_duration' => 'nullable|integer',
        ]);

        $attachmentData = [];
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $isImage = in_array($file->getClientOriginalExtension(), ['jpg', 'jpeg', 'png', 'webp', 'gif']);
            $attachmentData = [
                'attachment_type' => $data['voice_duration'] ? 'voice' : ($isImage ? 'image' : 'file'),
                'attachment_path' => $file->store('chat-attachments', 'public'),
                'attachment_name' => $file->getClientOriginalName(),
                'attachment_size' => $file->getSize(),
                'voice_duration' => $data['voice_duration'] ?? null,
            ];
        }

        $message = GroupMessage::create([
            'group_id' => $group->id,
            'sender_id' => $request->user()->id,
            'text' => $data['text'] ?? null,
            'reply_to' => $data['reply_to'] ?? null,
            'task_id' => $data['task_id'] ?? null,
            ...$attachmentData,
        ]);

        return response()->json($this->formatMessage($message->load(['reactions', 'task'])));
    }

    // PUT /groups/{group}/messages/{message}
    public function update(Request $request, Group $group, GroupMessage $message)
    {
        $this->authorizeMember($request, $group);
        abort_unless($message->sender_id === $request->user()->id, 403);

        $data = $request->validate(['text' => 'required|string|max:5000']);
        $message->update(['text' => $data['text'], 'edited' => true]);

        return response()->json($this->formatMessage($message->load(['reactions', 'task'])));
    }

    // DELETE /groups/{group}/messages/{message}
    public function destroy(Request $request, Group $group, GroupMessage $message)
    {
        $this->authorizeMember($request, $group);
        abort_unless($message->sender_id === $request->user()->id, 403);

        $message->delete();
        return response()->json(['success' => true]);
    }

    // PUT /groups/{group}/messages/{message}/pin
    public function togglePin(Request $request, Group $group, GroupMessage $message)
    {
        $this->authorizeMember($request, $group);
        $message->update(['pinned' => ! $message->pinned]);

        return response()->json(['success' => true, 'pinned' => $message->pinned]);
    }

    // POST /groups/{group}/messages/{message}/react
    public function react(Request $request, Group $group, GroupMessage $message)
    {
        $this->authorizeMember($request, $group);
        $data = $request->validate(['emoji' => 'required|string|max:10']);
        $userId = $request->user()->id;

        // یوزر فقط یه ری‌اکشن رو هر پیام می‌تونه داشته باشه
        $existing = MessageReaction::where('message_id', $message->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing && $existing->emoji === $data['emoji']) {
            $existing->delete(); // toggle: همون ایموجی رو دوباره زد -> حذفش کن
        } else {
            MessageReaction::updateOrCreate(
                ['message_id' => $message->id, 'user_id' => $userId],
                ['emoji' => $data['emoji']]
            );
        }

        return response()->json($this->formatMessage($message->fresh()->load(['reactions', 'task'])));
    }

    // ─── تبدیل مدل به همون شکلی که فرانت (interface Message) انتظار داره ───
    private function formatMessage(GroupMessage $m): array
    {
        $reactions = [];
        foreach ($m->reactions as $r) {
            $reactions[$r->emoji][] = $r->user_id;
        }

        return [
            'id' => $m->id,
            'senderId' => $m->sender_id,
            'text' => $m->text,
            'timestamp' => $m->created_at,
            'type' => $m->type,
            'pinned' => (bool) $m->pinned,
            'edited' => (bool) $m->edited,
            'replyTo' => $m->reply_to,
            'reactions' => $reactions,
            'todoRef' => $m->task ? ['id' => $m->task->id, 'text' => $m->task->title, 'priority' => $m->task->priority] : null,
            'attachment' => $m->attachment_path ? [
                'name' => $m->attachment_name,
                'size' => $m->attachment_size,
                'type' => $m->attachment_type,
                'url' => asset('storage/' . $m->attachment_path),
            ] : null,
        ];
    }
}
