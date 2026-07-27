<?php

namespace App\Http\Controllers;

use App\Models\FeedbackMessage;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'message' => 'required|string|max:2000',
        ]);

        FeedbackMessage::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'] ?? null,
            'message' => $data['message'],
        ]);

        return response()->json(['success' => true]);
    }
}
