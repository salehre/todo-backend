<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Step;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    // GET /tasks
    public function index(Request $request)
    {
        $tasks = Task::where('user_id', $request->user()->id)
            ->with('steps')
            ->orderByDesc('id')
            ->get();

        return response()->json($tasks);
    }

    // POST /tasks/create
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
        ]);

        $task->load('steps');

        return response()->json($task);
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

        $task = Task::where('user_id', $request->user()->id)
            ->findOrFail($data['id']);

        $task->update(collect($data)->except('id')->toArray());
        $task->load('steps');

        return response()->json($task);
    }

    // PUT /tasks/updateStep  (جایگزینی کامل لیست استپ‌های یک تسک)
    public function updateStep(Request $request)
    {
        $data = $request->validate([
            'task_id' => 'required|integer',
            'steps' => 'array',
            'steps.*.id' => 'nullable|integer',
            'steps.*.text' => 'required|string',
            'steps.*.completed' => 'required|boolean',
        ]);

        $task = Task::where('user_id', $request->user()->id)
            ->findOrFail($data['task_id']);

        $incomingIds = collect($data['steps'])->pluck('id')->filter()->all();

        // حذف استپ‌هایی که تو لیست جدید نیستن
        $task->steps()->whereNotIn('id', $incomingIds)->delete();

        // آپدیت/ساخت استپ‌ها با حفظ ترتیب
        foreach ($data['steps'] as $index => $stepData) {
            $task->steps()->updateOrCreate(
                ['id' => $stepData['id'] ?? null],
                [
                    'text' => $stepData['text'],
                    'completed' => $stepData['completed'],
                    'position' => $index,
                ]
            );
        }

        return response()->json($task->steps()->orderBy('position')->get());
    }

    // DELETE /tasks/delete
    public function destroy(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|integer',
        ]);

        $task = Task::where('user_id', $request->user()->id)
            ->findOrFail($data['id']);

        $task->delete();

        return response()->json(['success' => true]);
    }
}
