<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\GroupMember;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    // GET /tasks — تسک‌های شخصی + تسک‌های گروهی که به خودِ کاربر اساین شده
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $tasks = Task::where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->whereNull('group_id');
        })
            ->orWhere(function ($q) use ($userId) {
                $q->where('assigned_to', $userId)->whereNotNull('group_id');
            })
            ->with(['steps', 'group:id,name'])
            ->orderByDesc('id')
            ->get();

        return response()->json($tasks->map(fn ($t) => $this->formatTask($t)));
    }

    // GET /groups/{group}/tasks — همه‌ی تسک‌های گروه (برای همه‌ی اعضا قابل دیدنه)
    public function groupTasks(Request $request, \App\Models\Group $group)
    {
        $this->requireMember($request, $group->id);

        $tasks = Task::where('group_id', $group->id)
            ->with(['steps', 'assignee:id,name,username'])
            ->orderByDesc('id')
            ->get();

        return response()->json($tasks->map(fn ($t) => $this->formatTask($t)));
    }

    // POST /tasks/create — ساخت تسک شخصی (بدون تغییر نسبت به قبل)
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:low,medium,high',
        ]);

        $task = Task::create([
            'user_id' => $request->user()->id,
            'assigned_to' => $request->user()->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'],
            'is_completed' => false,
        ]);

        $task->load('steps');
        return response()->json($this->formatTask($task));
    }

    // POST /groups/{group}/tasks — ساخت تسک برای یه عضو گروه
    public function storeGroupTask(Request $request, \App\Models\Group $group)
    {
        $this->requireMember($request, $group->id);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:low,medium,high',
            'assigned_to' => 'required|integer|exists:users,id',
        ]);

        // مطمئن شو کسی که داره بهش assign می‌شه، خودش عضو همین گروهه
        abort_unless(
            GroupMember::where('group_id', $group->id)->where('user_id', $data['assigned_to'])->exists(),
            422,
            'کاربر انتخاب‌شده عضو این گروه نیست'
        );

        $task = Task::create([
            'user_id' => $request->user()->id, // سازنده
            'group_id' => $group->id,
            'assigned_to' => $data['assigned_to'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'],
            'is_completed' => false,
        ]);

        $task->load(['steps', 'assignee:id,name,username', 'group:id,name']);
        return response()->json($this->formatTask($task));
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
        ]);

        $task = Task::findOrFail($data['id']);
        $userId = $request->user()->id;

        if ($task->group_id) {
            // تسک گروهیه: فقط سازنده یا assignee اجازه‌ی ویرایش عنوان/توضیح/اولویت دارن
            abort_unless(in_array($userId, [$task->user_id, $task->assigned_to]), 403);

            // ولی تیک‌زدنِ "تمام‌شده" فقط دست خودِ assignee‌ست
            if (array_key_exists('is_completed', $data) && $userId !== $task->assigned_to) {
                abort(403, 'فقط کسی که این تسک بهش محول شده می‌تونه تکمیلش کنه');
            }
        } else {
            // تسک شخصیه: فقط صاحبش
            abort_unless($task->user_id === $userId, 403);
        }

        $task->update(collect($data)->except('id')->toArray());
        $task->load(['steps', 'group:id,name']);

        return response()->json($this->formatTask($task));
    }

    // PUT /tasks/updateStep — فقط assignee (یا صاحب تسک شخصی) اجازه داره استپ‌ها رو مدیریت کنه
    public function updateStep(Request $request)
    {
        $data = $request->validate([
            'task_id' => 'required|integer',
            'steps' => 'array',
            'steps.*.id' => 'nullable|integer',
            'steps.*.text' => 'required|string',
            'steps.*.completed' => 'required|boolean',
        ]);

        $task = Task::findOrFail($data['task_id']);
        $userId = $request->user()->id;
        $allowedUser = $task->group_id ? $task->assigned_to : $task->user_id;

        abort_unless($userId === $allowedUser, 403, 'فقط کسی که این تسک بهش محول شده می‌تونه استپ‌هاش رو تغییر بده');

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

    // DELETE /tasks/delete — فقط سازنده
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

    private function formatTask(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority,
            'is_completed' => $task->is_completed,
            'created_at' => $task->created_at,
            'steps' => $task->steps,
            'group_id' => $task->group_id,
            'group_name' => $task->group?->name,
            'assigned_to' => $task->assigned_to,
            'assignee_name' => $task->assignee?->name,
            'can_complete' => $task->group_id
                ? $task->assigned_to === auth()->id()
                : $task->user_id === auth()->id(),
        ];
    }
}
