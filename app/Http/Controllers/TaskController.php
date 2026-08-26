<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\GroupMember;
use App\Models\Group;
use Illuminate\Http\Request;
use App\Events\MessageSent;
use App\Models\GroupMessage;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $tasks = Task::where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->whereNull('group_id');
        })
            ->orWhere(function ($q) use ($userId) {
                $q->whereNotNull('group_id')
                    ->whereHas('assignees', fn ($aq) => $aq->where('users.id', $userId));
            })
            ->with(['steps', 'group:id,name', 'assignees:id,name,username,avatar'])
            ->orderByDesc('id')
            ->get();

        return response()->json($tasks->map(fn ($t) => $this->formatTask($t, $userId)));
    }

    public function groupTasks(Request $request, Group $group)
    {
        $this->requireMember($request, $group->id);

        $tasks = Task::where('group_id', $group->id)
            ->with(['steps', 'assignees:id,name,username,avatar'])
            ->orderByDesc('id')
            ->get();

        return response()->json($tasks->map(fn ($t) => $this->formatTask($t, $request->user()->id)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:low,medium,high',
        ]);

        $task = Task::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'],
            'is_completed' => false,
            'ordered_steps' => $data['ordered_steps'] ?? true,
        ]);
        $task->assignees()->attach($request->user()->id);

        $task->load(['steps', 'assignees:id,name,username,avatar']);
        return response()->json($this->formatTask($task, $request->user()->id));
    }

    public function storeGroupTask(Request $request, Group $group)
    {
        $this->requireMember($request, $group->id);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:low,medium,high',
            'ordered_steps' => 'sometimes|boolean',
            'assigned_to' => 'required|array|min:1',
            'assigned_to.*' => 'integer',
        ]);

        $validAssignees = GroupMember::where('group_id', $group->id)
            ->whereIn('user_id', $data['assigned_to'])
            ->pluck('user_id');

        abort_if($validAssignees->isEmpty(), 422, 'حداقل باید یک عضو معتبر انتخاب بشه');

        $task = Task::create([
            'user_id' => $request->user()->id,
            'group_id' => $group->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'],
            'is_completed' => false,
        ]);
        $task->assignees()->attach($validAssignees);

        $task->load(['steps', 'assignees:id,name,username,avatar', 'group:id,name']);

        $assigneeNames = $task->assignees->pluck('name')->implode('، ');
        $text = "{$request->user()->name} تسک «{$task->title}» رو برای {$assigneeNames} ساخت";
        $message = GroupMessage::create([
            'group_id' => $group->id,
            'sender_id' => $request->user()->id,
            'text' => $text,
            'type' => 'system',
        ])->load(['reactions', 'task', 'attachments']);
        event(new MessageSent($group->id, $this->formatMessageForNotice($message)));
        return response()->json($this->formatTask($task, $request->user()->id));
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

    // PUT /tasks/updateTask
    public function updateTask(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|integer',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'priority' => 'sometimes|in:low,medium,high',
            'is_completed' => 'sometimes|boolean',
            'ordered_steps' => 'sometimes|boolean',
        ]);

        $task = Task::with('assignees')->findOrFail($data['id']);
        $userId = $request->user()->id;
        $assigneeIds = $task->assignees->pluck('id');

        if ($task->group_id) {
            abort_unless($userId === $task->user_id || $assigneeIds->contains($userId), 403);

            if (array_key_exists('is_completed', $data) && ! $assigneeIds->contains($userId)) {
                abort(403, 'فقط کسی که این تسک بهش محول شده می‌تونه تکمیلش کنه');
            }
        } else {
            abort_unless($task->user_id === $userId, 403);
        }

        $task->update(collect($data)->except('id')->toArray());
        $task->load(['steps', 'group:id,name', 'assignees:id,name,username,avatar']);

        return response()->json($this->formatTask($task, $userId));
    }

    public function updateStep(Request $request)
    {
        $data = $request->validate([
            'task_id' => 'required|integer',
            'steps' => 'array',
            'steps.*.id' => 'nullable|integer',
            'steps.*.text' => 'required|string',
            'steps.*.completed' => 'required|boolean',
        ]);

        $task = Task::with('assignees')->findOrFail($data['task_id']);
        $userId = $request->user()->id;
        $allowed = $task->group_id
            ? $task->assignees->pluck('id')->contains($userId)
            : $task->user_id === $userId;

        abort_unless($allowed, 403, 'فقط کسی که این تسک بهش محول شده می‌تونه استپ‌هاش رو تغییر بده');

        $incomingIds = collect($data['steps'])->pluck('id')->filter()->all();
        $task->steps()->whereNotIn('id', $incomingIds)->delete();

        foreach ($data['steps'] as $index => $stepData) {
            $task->steps()->updateOrCreate(
                ['id' => $stepData['id'] ?? null],
                ['text' => $stepData['text'], 'completed' => $stepData['completed'], 'position' => $index]
            );
        }

        return response()->json($task->steps()->orderBy('position')->get());
    }

    public function destroy(Request $request)
    {
        $data = $request->validate(['id' => 'required|integer']);

        $task = Task::findOrFail($data['id']);
        abort_unless($task->user_id === $request->user()->id, 403);

        $task->delete();
        return response()->json(['success' => true]);
    }

    private function requireMember(Request $request, int $groupId): void
    {
        abort_unless(
            GroupMember::where('group_id', $groupId)->where('user_id', $request->user()->id)->exists(),
            403
        );
    }

    private function formatTask(Task $task, int $currentUserId): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority,
            'is_completed' => $task->is_completed,
            'created_at' => $task->created_at,
            'steps' => $task->steps,
            'ordered_steps' => $task->ordered_steps,
            'group_id' => $task->group_id,
            'group_name' => $task->group?->name,
            'assignees' => $task->assignees->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'avatarUrl' => $u->avatar ? asset('storage/' . $u->avatar) : null,
            ]),
            'can_complete' => $task->group_id
                ? $task->assignees->pluck('id')->contains($currentUserId)
                : $task->user_id === $currentUserId,
        ];
    }
}
