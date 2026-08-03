<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    // GET /groups  — لیست گروه‌های خودِ یوزر (الان همیشه یکی، بعداً می‌تونه چندتا باشه)
    public function index(Request $request)
    {
        $groups = $request->user()->groups()->withCount('members')->get();
        return response()->json($groups);
    }

    // POST /groups — ساخت گروه جدید
    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255']);

        $group = Group::create([
            'name' => $data['name'],
            'created_by' => $request->user()->id,
        ]);
        $group->members()->attach($request->user()->id);

        return response()->json($group);
    }
}
