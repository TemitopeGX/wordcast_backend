<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Models\LicenseDevice;
use App\Models\AppToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * LicenseController
 *
 * Handles all license operations for the WordCast Live desktop app.
 *
 * Endpoints:
 *   POST /api/license/validate   → validate hash + machine_id, return plan details
 *   POST /api/license/activate   → activate a license key on a device
 *   POST /api/license/deactivate → remove a device from a license
 *   POST /api/license/oauth-token → exchange short-lived browser token for license data
 *
 * Rate limiting is configured in RouteServiceProvider (60 req/min per IP).
 */
class LicenseController extends Controller
{
    // ── Validate ─────────────────────────────────────────────────────────────

    /**
     * POST /api/license/validate
     * Body: { machine_id, license_hash, device_name }
     */
    public function validate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'machine_id'   => 'required|string|max:256',
            'license_hash' => 'required|string|size:64',
            'device_name'  => 'nullable|string|max:128',
        ]);

        $device = LicenseDevice::where('machine_id',    $data['machine_id'])
                               ->where('license_hash',  $data['license_hash'])
                               ->with('license.user')
                               ->first();

        if (!$device) {
            return response()->json(['valid' => false, 'code' => 'not_found'], 200);
        }

        $license = $device->license;

        if (!$license->is_active || $license->isExpired()) {
            return response()->json(['valid' => false, 'code' => 'expired'], 200);
        }

        // Update last active timestamp
        $device->update([
            'last_active_at' => now(),
            'device_name'    => $data['device_name'] ?? $device->device_name,
        ]);

        return response()->json([
            'valid'       => true,
            'plan'        => $license->plan,
            'expires_at'  => $license->expires_at?->toIso8601String(),
            'seat_limit'  => $license->seat_limit,
            'seats_used'  => $license->devices()->count(),
            'device_name' => $device->device_name,
            'user'        => [
                'name'  => $license->user?->name,
                'email' => $license->user?->email,
            ],
            'ai_key'      => $this->resolveAiKey($license->plan),
        ]);
    }

    // ── Activate ─────────────────────────────────────────────────────────────

    /**
     * POST /api/license/activate
     * Body: { license_key, machine_id, device_name }
     */
    public function activate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'license_key' => 'required|string|max:64',
            'machine_id'  => 'required|string|max:256',
            'device_name' => 'nullable|string|max:128',
        ]);

        $license = License::where('license_key', $data['license_key'])
                          ->where('is_active', true)
                          ->with('user', 'devices')
                          ->first();

        if (!$license) {
            return response()->json(['success' => false, 'message' => 'Invalid or inactive license key.'], 422);
        }

        if ($license->isExpired()) {
            return response()->json(['success' => false, 'message' => 'License has expired.'], 422);
        }

        // Check if this machine is already activated
        $existing = $license->devices()->where('machine_id', $data['machine_id'])->first();
        if ($existing) {
            // Already activated — return success with current data
            $hash = hash('sha256', $data['license_key'] . $data['machine_id']);
            $existing->update(['last_active_at' => now(), 'device_name' => $data['device_name'] ?? $existing->device_name]);
            return $this->activationResponse($license, $existing->license_hash);
        }

        // Check seat limit
        if (!$license->seatsAvailable()) {
            return response()->json([
                'success' => false,
                'code'    => 'seat_limit',
                'message' => "Seat limit of {$license->seat_limit} reached. Deactivate another device first.",
            ], 422);
        }

        // Create device record (hash is computed from key + machine_id, same as Electron)
        $hash = hash('sha256', $data['license_key'] . $data['machine_id']);

        $device = LicenseDevice::create([
            'license_id'    => $license->id,
            'machine_id'    => $data['machine_id'],
            'license_hash'  => $hash,
            'device_name'   => $data['device_name'] ?? 'Unknown Device',
            'os'            => $this->guessOS($data['device_name'] ?? ''),
            'activated_at'  => now(),
            'last_active_at' => now(),
        ]);

        return $this->activationResponse($license->fresh(['user', 'devices']), $hash);
    }

    // ── Deactivate ───────────────────────────────────────────────────────────

    /**
     * POST /api/license/deactivate
     * Body: { machine_id, license_hash }
     */
    public function deactivate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'machine_id'   => 'required|string|max:256',
            'license_hash' => 'required|string|size:64',
        ]);

        LicenseDevice::where('machine_id',   $data['machine_id'])
                     ->where('license_hash', $data['license_hash'])
                     ->delete();

        return response()->json(['success' => true]);
    }

    /**
     * DELETE /api/license/devices/{machine_id}  (authenticated — from dashboard)
     */
    public function deactivateByMachineId(Request $request, string $machineId): JsonResponse
    {
        $user = $request->user();
        $deleted = LicenseDevice::whereHas('license', fn($q) => $q->where('user_id', $user->id))
                                ->where('machine_id', $machineId)
                                ->delete();

        return response()->json(['success' => $deleted > 0]);
    }

    // ── OAuth token exchange ──────────────────────────────────────────────────

    /**
     * POST /api/license/oauth-token
     * Body: { token, machine_id, device_name }
     * Called by the Electron app after browser login callback.
     */
    public function oauthToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'       => 'required|string|max:256',
            'machine_id'  => 'required|string|max:256',
            'device_name' => 'nullable|string|max:128',
        ]);

        $appToken = \App\Models\AppToken::where('token', $data['token'])
                                       ->where('expires_at', '>', now())
                                       ->with('user.license')
                                       ->first();

        if (!$appToken) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired token.'], 422);
        }

        $user    = $appToken->user;
        $license = $user->license;

        // Delete used token regardless of plan
        $appToken->delete();

        // Free plan users (no license record) — sign them in with free access
        if (!$license || !$license->is_active || $license->isExpired()) {
            return response()->json([
                'success'     => true,
                'plan'        => 'free',
                'expires_at'  => null,
                'seat_limit'  => 1,
                'seats_used'  => 0,
                'license_key' => null,   // no key for free users
                'device_name' => $data['device_name'] ?? null,
                'user'        => [
                    'name'  => $user->name,
                    'email' => $user->email,
                ],
                'ai_key'      => null,
            ]);
        }

        // Paid plan — auto-activate this device if seats available
        $activateRequest = new Request(array_merge($data, ['license_key' => $license->license_key]));
        return $this->activate($activateRequest);
    }

    // ── Dashboard: list devices ───────────────────────────────────────────────

    /**
     * GET /api/license/devices  (authenticated)
     */
    public function devices(Request $request): JsonResponse
    {
        $user    = $request->user();
        $license = $user->license;
        if (!$license) return response()->json(['devices' => []]);

        $devices = $license->devices()
                           ->orderByDesc('last_active_at')
                           ->get()
                           ->map(fn($d) => [
                               'machine_id'    => $d->machine_id,
                               'device_name'   => $d->device_name,
                               'os'            => $d->os,
                               'activated_at'  => $d->activated_at?->toIso8601String(),
                               'last_active_at' => $d->last_active_at?->toIso8601String(),
                           ]);

        return response()->json([
            'devices'   => $devices,
            'seats_used' => $license->devices()->count(),
            'seat_limit' => $license->seat_limit,
        ]);
    }

    // ── License key info ─────────────────────────────────────────────────────

    /**
     * GET /api/license  (authenticated)
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $license = $user->license;
        if (!$license) {
            $rawPlan = $user->plan ?? 'free';
            if ($rawPlan === 'free' || $rawPlan === 'starter') {
                return response()->json(['license' => null]);
            }
            
            $validPlans = ['pro', 'campus'];
            $plan = in_array($rawPlan, $validPlans) ? $rawPlan : 'pro';
            
            $license = $user->license()->create([
                'license_key' => 'WCL-' . strtoupper(Str::uuid()),
                'plan'        => $plan,
                'seat_limit'  => $plan === 'campus' ? 10 : 3,
                'is_active'   => true,
            ]);
            $license->refresh();
        }

        return response()->json([
            'license' => [
                'license_key' => $license->license_key,
                'plan'        => $license->plan,
                'expires_at'  => $license->expires_at?->toIso8601String(),
                'seat_limit'  => $license->seat_limit,
                'seats_used'  => $license->devices()->count(),
                'is_active'   => $license->is_active,
            ],
        ]);
    }

    /**
     * POST /api/license/regenerate  (authenticated) — invalidate old key, generate new
     * Preserves the plan-specific key prefix (WCL-BETA-, WCL-CAMPUS-, etc.)
     */
    public function regenerate(Request $request): JsonResponse
    {
        $user    = $request->user();
        $license = $user->license;

        if (!$license) {
            $rawPlan = $user->plan ?? 'free';
            if ($rawPlan === 'free' || $rawPlan === 'starter') {
                return response()->json(['success' => false, 'message' => 'Free plans cannot generate a license. Please upgrade.'], 403);
            }

            $validPlans = ['pro', 'campus', 'beta'];
            $plan = in_array($rawPlan, $validPlans) ? $rawPlan : 'pro';

            $license = $user->license()->create([
                'license_key' => $this->makeKey($plan),
                'plan'        => $plan,
                'seat_limit'  => $plan === 'campus' ? 10 : 3,
                'is_active'   => true,
            ]);
            $license->refresh();
        }

        // Deactivate all devices — they must re-activate with the new key
        $license->devices()->delete();

        // Generate new key that preserves the plan prefix
        $license->update(['license_key' => $this->makeKey($license->plan)]);

        return response()->json(['success' => true, 'license_key' => $license->license_key]);
    }

    /**
     * Generate a plan-prefixed license key.
     *   beta   → WCL-BETA-XXXX-XXXX-XXXX-XXXX
     *   campus → WCL-CAMPUS-XXXX-XXXX-XXXX-XXXX
     *   pro    → WCL-XXXX-XXXX-XXXX-XXXX
     */
    private function makeKey(string $plan): string
    {
        $segments = strtoupper(substr(str_replace('-', '', Str::uuid()), 0, 16));
        $parts    = str_split($segments, 4);

        return match ($plan) {
            'beta'   => 'WCL-BETA-'   . implode('-', $parts),
            'campus' => 'WCL-CAMPUS-' . implode('-', $parts),
            default  => 'WCL-'        . implode('-', $parts),
        };
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function activationResponse(License $license, string $hash): JsonResponse
    {
        return response()->json([
            'success'     => true,
            'plan'        => $license->plan,
            'expires_at'  => $license->expires_at?->toIso8601String(),
            'seat_limit'  => $license->seat_limit,
            'seats_used'  => $license->devices()->count(),
            'device_name' => null,
            'license_key' => $license->license_key, // needed for Electron to hash
            'user'        => [
                'name'  => $license->user?->name,
                'email' => $license->user?->email,
            ],
            'ai_key'      => $this->resolveAiKey($license->plan),
        ]);
    }

    /**
     * Returns the admin-configured OpenAI API key for pro/campus plans.
     * Returns null for free plans — Electron stores this alongside license data.
     */
    private function resolveAiKey(string $plan): ?string
    {
        $subscriberPlans = ['pro', 'campus'];
        if (!in_array($plan, $subscriberPlans)) {
            return null;
        }

        $path     = storage_path('app/settings.json');
        $settings = file_exists($path) ? json_decode(file_get_contents($path), true) : [];
        $key      = $settings['openai_api_key'] ?? env('OPENAI_API_KEY', '');

        return $key ?: null;
    }

    private function guessOS(string $deviceName): string
    {
        $lower = strtolower($deviceName);
        if (str_contains($lower, 'windows') || str_contains($lower, 'win32')) return 'windows';
        if (str_contains($lower, 'mac') || str_contains($lower, 'darwin'))   return 'macos';
        if (str_contains($lower, 'linux'))                                    return 'linux';
        return 'unknown';
    }
}
