<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $credentials['username'])
            ->orWhere('email', $credentials['username'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['success' => false], 422);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'user' => $this->userPayload($user)
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true]);
    }

    public function userInfo(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'user' => $user ? $this->userPayload($user) : null,
        ]);
    }

    // POST /auth/register
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|unique:users,email',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => bcrypt(str()->random(32)), // رمز موقت، تو set-password عوض می‌شه
        ]);

        $this->issueCode($user);

        return response()->json(['success' => true]);
    }

    // POST /auth/resend-code
    public function resendCode(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);
        $user = User::where('email', $data['email'])->firstOrFail();

        $this->issueCode($user);

        return response()->json(['success' => true]);
    }

    // POST /auth/verify-email
    public function verifyEmail(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! $this->codeIsValid($user, $data['code'])) {
            return response()->json(['success' => false], 422);
        }

        $user->update([
            'email_verified_at' => now(),
            'verification_code' => null,
            'verification_code_expires_at' => null,
        ]);

        return response()->json(['success' => true]);
    }

    // POST /auth/set-password
    public function setPassword(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        $user = User::where('email', $data['email'])
            ->whereNotNull('email_verified_at')
            ->firstOrFail();

        $user->update(['password' => bcrypt($data['password'])]);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'user' => $this->userPayload($user)
        ]);
    }

    // POST /auth/forgot-password
    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);
        $user = User::where('email', $data['email'])->firstOrFail();

        $this->issueCode($user);

        return response()->json(['success' => true]);
    }

    // POST /auth/reset-password
    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string',
            'password' => 'required|string|min:8',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! $this->codeIsValid($user, $data['code'])) {
            return response()->json(['success' => false], 422);
        }

        $user->update([
            'password' => bcrypt($data['password']),
            'verification_code' => null,
            'verification_code_expires_at' => null,
        ]);

        return response()->json(['success' => true]);
    }

    // ─── Helper های داخلی ─────────────────────────────────────────────
    private function issueCode(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        $user->update([
            'verification_code' => $code,
            'verification_code_expires_at' => now()->addMinutes(10),
        ]);

        \Illuminate\Support\Facades\Log::info("کد تأیید برای {$user->email}: {$code}");
    }

    private function codeIsValid(User $user, string $code): bool
    {
        return $user->verification_code === $code
            && $user->verification_code_expires_at
            && $user->verification_code_expires_at->isFuture();
    }

    // PUT /auth/preferences
    public function updatePreferences(Request $request)
    {
        $data = $request->validate([
            'theme' => 'sometimes|in:sky,emerald,orange,rose',
            'dark_mode' => 'sometimes|boolean',
        ]);

        $user = $request->user();
        $user->update($data);

        return response()->json(['success' => true]);
    }

    // PUT /auth/profile
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20|regex:/^09\d{9}$/',
            'gender' => 'nullable|in:male,female,company',

        ]);

        $user->update($data);

        return response()->json(['success' => true, 'user' => $this->userPayload($user)]);
    }

    // POST /auth/avatar
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $user = $request->user();

        // عکس قبلی رو پاک کن (اگه بود)
        if ($user->avatar) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return response()->json(['success' => true, 'user' => $this->userPayload($user)]);
    }

    // POST /auth/cover
    public function uploadCover(Request $request)
    {
        $request->validate([
            'cover' => 'required|image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);

        $user = $request->user();

        if ($user->cover) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->cover);
        }

        $path = $request->file('cover')->store('covers', 'public');
        $user->update(['cover' => $path]);

        return response()->json(['success' => true, 'user' => $this->userPayload($user)]);
    }

    // ─── هلپر داخلی: ساخت آرایه‌ی یکسان یوزر برای همه‌ی جواب‌ها ──────────
    private function userPayload(User $user): array
    {
        return [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'theme' => $user->theme,
            'dark_mode' => (bool) $user->dark_mode,
            'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
            'cover_url' => $user->cover ? asset('storage/' . $user->cover) : null,
            'gender' => $user->gender,
        ];
    }
}
