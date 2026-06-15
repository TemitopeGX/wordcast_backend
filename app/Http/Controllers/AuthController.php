<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\License;
use App\Models\AppToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;


class AuthController extends Controller
{
    // ── Register ──────────────────────────────────────────────
    public function register(Request $request)
    {
        $request->validate([
            'firstName'  => 'required|string|max:100|regex:/^[\pL\s\-]+$/u',
            'lastName'   => 'required|string|max:100|regex:/^[\pL\s\-]+$/u',
            'churchName' => 'required|string|max:255',
            'email'      => 'required|string|email|max:255|unique:users',
            'password'   => ['required', Password::min(8)->letters()->mixedCase()->numbers()],
        ]);

        $user = User::create([
            'name'        => trim($request->firstName . ' ' . $request->lastName),
            'email'       => strtolower(trim($request->email)),
            'password'    => Hash::make($request->password),
            'church_name' => trim($request->churchName),
            'role'        => 'user',
            'plan'        => 'free',
            'status'      => 'active',
        ]);

        // Auto-create a free license for every new user
        $user->license()->create([
            'license_key' => 'WCL-' . strtoupper(Str::uuid()),
            'plan'        => 'free',
            'seat_limit'  => 1,
            'is_active'   => true,
            'expires_at'  => null, // free plan never expires
        ]);

        $token = $user->createToken('auth_token', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'message'      => 'Registration successful',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $this->formatUser($user),
        ], 201);
    }

    // ── Login ─────────────────────────────────────────────────
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|string|email|max:255',
            'password' => 'required|string|max:255',
        ]);

        $throttleKey = 'login:' . Str::lower($request->email) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'message' => "Too many login attempts. Please wait {$seconds} seconds before retrying.",
            ], 429);
        }

        $user = User::where('email', strtolower(trim($request->email)))->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        RateLimiter::clear($throttleKey);
        $user->tokens()->delete();
        $token = $user->createToken('auth_token', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'message'      => 'Login successful',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $this->formatUser($user),
            'app_login'    => $request->boolean('app'), // flag: redirecting to desktop app?
        ]);
    }

    // ── Generate short-lived desktop app token ────────────────
    public function generateAppToken(Request $request)
    {
        // Called after web login when ?app=1 — returns a 5-minute token
        // the desktop Electron app exchanges for license data.
        $user  = $request->user();
        $token = Str::random(64);

        // Delete any existing app tokens for this user
        AppToken::where('user_id', $user->id)->delete();

        AppToken::create([
            'user_id'    => $user->id,
            'token'      => $token,
            'expires_at' => now()->addMinutes(5),
        ]);

        return response()->json(['token' => $token]);
    }

    // ── Logout ────────────────────────────────────────────────
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    // ── Get current user ──────────────────────────────────────
    public function me(Request $request)
    {
        return response()->json($this->formatUser($request->user()));
    }

    // ── Update Profile ────────────────────────────────────────
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name'        => 'required|string|max:200|regex:/^[\pL\s\-]+$/u',
            'email'       => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'church_name' => 'nullable|string|max:255',
        ]);

        $user->update([
            'name'        => trim($request->name),
            'email'       => strtolower(trim($request->email)),
            'church_name' => $request->church_name ? trim($request->church_name) : null,
        ]);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user'    => $this->formatUser($user),
        ]);
    }

    // ── Update Profile Picture (Avatar) ────────────────────────
    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $user = $request->user();

        // Delete old avatar if exists
        if ($user->avatar_url) {
            $oldPath = public_path($user->avatar_url);
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }

        $file = $request->file('avatar');
        $filename = 'avatar_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
        
        // Ensure destination directory exists
        $destinationPath = public_path('uploads/avatars');
        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        $file->move($destinationPath, $filename);

        $user->update([
            'avatar_url' => '/uploads/avatars/' . $filename,
        ]);

        return response()->json([
            'message' => 'Profile picture updated successfully',
            'avatar_url' => url('/uploads/avatars/' . $filename),
            'user' => $this->formatUser($user),
        ]);
    }

    // ── Update Password ───────────────────────────────────────
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password does not match your current password.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json(['message' => 'Password updated successfully']);
    }

    // ── Private: sanitised user data ─────────────────────────
    private function formatUser(User $user): array
    {
        // ── Grace period / auto-downgrade logic ───────────────────
        $license = $user->license;
        $expiresAt = $license?->expires_at;
        $now = now();

        // Determine subscription status
        $subscriptionStatus = 'active';
        $graceUntil = null;

        if ($user->plan !== 'free' && $license) {
            if ($expiresAt && $expiresAt->isPast()) {
                // License is expired — check if within grace period
                $graceEnd = $expiresAt->copy()->addDays(3);
                $graceUntil = $graceEnd->toIso8601String();

                if ($now->lte($graceEnd)) {
                    // Still in grace period
                    $subscriptionStatus = 'grace';
                } else {
                    // Grace period over — auto-downgrade to free
                    $subscriptionStatus = 'expired';
                    if ($user->plan !== 'free') {
                        $user->update(['plan' => 'free']);
                        $license->update([
                            'plan'       => 'free',
                            'seat_limit' => 1,
                            'is_active'  => true,
                        ]);
                        $user->refresh();
                        $license->refresh();
                    }
                }
            }
        }

        return [
            'id'                   => $user->id,
            'name'                 => $user->name,
            'email'                => $user->email,
            'church_name'          => $user->church_name,
            'avatar_url'           => $user->avatar_url ? (str_starts_with($user->avatar_url, 'http') ? $user->avatar_url : url($user->avatar_url)) : 'https://res.cloudinary.com/dg9elcrcw/image/upload/v1781202901/PROFILE_LOGO_2x_vpvu6w.jpg',
            'role'                 => $user->role,
            'plan'                 => $user->plan,
            'subscription_status'  => $subscriptionStatus, // active | grace | expired
            'plan_grace_until'     => $graceUntil,
            'status'               => $user->status ?? 'active',
            'license_is_active'    => $license?->is_active ?? false,
            'license_expires_at'   => $expiresAt?->toIso8601String(),
            'license_plan'         => $license?->plan ?? 'free',
        ];
    }
}
