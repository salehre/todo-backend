<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    // GET /push/public-key
    public function publicKey()
    {
        return response()->json(['key' => config('webpush.public_key')]);
    }

    // POST /push/subscribe
    public function store(Request $request)
    {
        $data = $request->validate([
            'endpoint' => 'required|url|max:2048',
            'keys.p256dh' => 'required|string|max:255',
            'keys.auth' => 'required|string|max:255',
            'contentEncoding' => 'nullable|string|in:aesgcm,aes128gcm',
        ]);

        // اگه همین دستگاه قبلاً برای کاربر دیگه‌ای ثبت شده بود، به کاربر فعلی منتقل می‌شه
        PushSubscription::updateOrCreate(
            ['endpoint_hash' => sha1($data['endpoint'])],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $data['endpoint'],
                'p256dh_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]
        );

        return response()->json(['ok' => true]);
    }

    // POST /push/unsubscribe
    public function destroy(Request $request)
    {
        $data = $request->validate(['endpoint' => 'required|url|max:2048']);

        PushSubscription::where('endpoint_hash', sha1($data['endpoint']))
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['ok' => true]);
    }
}
