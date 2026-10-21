<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->merge([
            'username' => strtolower(trim((string) $request->input('username'))),
        ]);

        $result = DB::transaction(function () use ($request) {
            $data = $request->validate([
                'first_name' => 'required|string|max:80',
                'last_name' => 'required|string|max:80',
                'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[A-Za-z0-9_]+$/', 'unique:users,username'],
                'email' => 'required|email|unique:users',
                'password' => 'required|min:8',
                'sex' => 'required|in:female,male',
                'consent' => 'accepted',
            ]);

            $user = User::create([
                'name' => trim($data['first_name'].' '.$data['last_name']),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => $data['password'],
                'sex' => $data['sex'],
                'reminders_enabled' => true,
            ]);

            $user->forceFill(['consented_at' => now()])->save();

            return [
                'user' => $user,
                'token' => $request->hasSession()
                    ? null
                    : $user->createToken('medibyte')->plainTextToken,
            ];
        });

        if ($request->hasSession()) {
            Auth::login($result['user']);
            $request->session()->regenerate();
        }

        return response()->json($result, 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|string',
            'password' => 'required',
        ]);

        $login = trim($data['email']);
        $field = str_contains($login, '@') ? 'email' : 'username';
        $login = $field === 'username' ? strtolower($login) : $login;

        $user = User::where($field, $login)->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if ($request->hasSession()) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        return response()->json([
            'user' => $user,
            'token' => $request->hasSession()
                ? null
                : $user->createToken('medibyte')->plainTextToken,
        ]);
    }

    public function checkUsername(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[A-Za-z0-9_]+$/'],
        ]);

        $available = ! User::whereRaw('LOWER(username) = ?', [strtolower($data['username'])])->exists();

        return response()->json([
            'username' => strtolower($data['username']),
            'available' => $available,
            'message' => $available ? 'Username is available.' : 'That username is already taken.',
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink($request->only('email'));

        // Always return the same response so account existence is not exposed.
        return response()->json([
            'message' => 'If that email is registered, we have sent a password reset link.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => __($status),
                'errors' => ['email' => [__($status)]],
            ], 422);
        }

        return response()->json(['message' => 'Your password has been reset. You can log in now.']);
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Logged out']);
    }
}
