<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\License;
use App\Models\LicenseDevice;
use App\Models\User;

class AdminLicenseController extends Controller
{
    // ── List all licenses ─────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $search = $request->input('search');
        $plan   = $request->input('plan');
        $status = $request->input('status'); // "active" | "inactive"

        $licenses = License::with('user')
            ->withCount('devices')
            ->when($search, function ($q) use ($search) {
                $q->where('license_key', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($u) =>
                      $u->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                  );
            })
            ->when($plan, fn($q) => $q->where('plan', $plan))
            ->when($status === 'active',   fn($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn($q) => $q->where('is_active', false))
            ->latest()
            ->paginate(20);

        return response()->json(['status' => 'success', 'data' => $licenses]);
    }

    // ── Single license detail (with devices) ─────────────────────────────────

    public function show($id)
    {
        $license = License::with(['user', 'devices'])->findOrFail($id);
        return response()->json(['status' => 'success', 'data' => $license]);
    }

    // ── Generate a new license for a user ─────────────────────────────────────

    public function generate(Request $request)
    {
        $request->validate([
            'user_id'    => 'required|exists:users,id',
            'plan'       => 'required|in:free,pro,campus,beta',
            'seat_limit' => 'required|integer|min:1|max:50',
            'expires_at' => 'nullable|date|after:today',
        ]);

        $plan = $request->plan;
        $key  = $this->generateKey($plan);

        $license = License::create([
            'user_id'     => $request->user_id,
            'license_key' => $key,
            'plan'        => $plan,
            'seat_limit'  => $request->seat_limit,
            'expires_at'  => $request->expires_at ?? null,
            'is_active'   => true,
        ]);

        // Sync user.plan — beta users get 'pro' tier access
        $appPlan = $plan === 'beta' ? 'pro' : $plan;
        User::where('id', $request->user_id)->update(['plan' => $appPlan]);

        return response()->json([
            'status'  => 'success',
            'message' => 'License generated successfully.',
            'data'    => $license->load('user'),
        ], 201);
    }

    // ── Update an existing license (plan, seats, expiry, status) ─────────────

    public function update(Request $request, $id)
    {
        $request->validate([
            'plan'       => 'sometimes|in:free,pro,campus,beta',
            'seat_limit' => 'sometimes|integer|min:1|max:50',
            'expires_at' => 'nullable|date',
            'is_active'  => 'sometimes|boolean',
        ]);

        $license = License::with('user')->findOrFail($id);

        if ($request->has('plan'))       $license->plan       = $request->plan;
        if ($request->has('seat_limit')) $license->seat_limit = $request->seat_limit;
        if ($request->has('is_active'))  $license->is_active  = $request->is_active;
        if ($request->has('expires_at')) $license->expires_at = $request->expires_at ?: null;

        $license->save();

        // Sync user.plan when plan changes
        if ($request->has('plan') && $license->user_id) {
            $appPlan = $request->plan === 'beta' ? 'pro' : $request->plan;
            User::where('id', $license->user_id)->update(['plan' => $appPlan]);
        }

        // Sync user.plan when active status changes
        if ($request->has('is_active') && $license->user_id) {
            if ($request->is_active) {
                $appPlan = ($license->plan === 'beta') ? 'pro' : $license->plan;
                User::where('id', $license->user_id)->update(['plan' => $appPlan]);
            } else {
                User::where('id', $license->user_id)->update(['plan' => 'free']);
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'License updated.',
            'data'    => $license->fresh(['user', 'devices']),
        ]);
    }

    // ── Admin regenerate — preserves key format prefix ────────────────────────

    public function regenerate($id)
    {
        $license = License::with('user')->findOrFail($id);

        // Preserve the key format prefix: WCL-BETA- for beta plans, etc.
        $newKey = $this->generateKey($license->plan);

        // Wipe all devices — they must re-activate with the new key
        $license->devices()->delete();
        $license->update(['license_key' => $newKey]);

        return response()->json([
            'status'      => 'success',
            'message'     => 'License key regenerated. All devices must re-activate.',
            'license_key' => $newKey,
            'data'        => $license->fresh(['user']),
        ]);
    }

    // ── Revoke (permanently delete) a license ────────────────────────────────

    public function destroy($id)
    {
        $license = License::with('user')->findOrFail($id);

        // Downgrade user to free immediately
        if ($license->user_id) {
            User::where('id', $license->user_id)->update(['plan' => 'free']);
        }

        // Cascade deletes devices via DB foreign key constraint
        $license->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'License revoked and deleted. User downgraded to free.',
        ]);
    }

    // ── Suspend / Re-activate (toggle) ───────────────────────────────────────

    public function toggleStatus($id)
    {
        $license = License::with('user')->findOrFail($id);
        $license->is_active = !$license->is_active;
        $license->save();

        // Sync user.plan immediately — no more waiting for 24h validate cycle
        if ($license->user_id) {
            if ($license->is_active) {
                $appPlan = ($license->plan === 'beta') ? 'pro' : $license->plan;
                User::where('id', $license->user_id)->update(['plan' => $appPlan]);
            } else {
                // Suspended: downgrade user to free right now
                User::where('id', $license->user_id)->update(['plan' => 'free']);
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => $license->is_active
                ? 'License re-activated. User plan restored.'
                : 'License suspended. User downgraded to free.',
            'data'    => $license,
        ]);
    }

    // ── Kick a specific device off a license ──────────────────────────────────

    public function kickDevice($licenseId, $deviceId)
    {
        $device = LicenseDevice::where('id', $deviceId)
                               ->where('license_id', $licenseId)
                               ->firstOrFail();
        $device->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Device removed from license.',
        ]);
    }

    // ── Wipe ALL devices from a license ───────────────────────────────────────

    public function wipeDevices($id)
    {
        $license = License::findOrFail($id);
        $count = $license->devices()->delete();

        return response()->json([
            'status'  => 'success',
            'message' => "{$count} device(s) removed.",
        ]);
    }

    // ── Search users for the generate-modal autocomplete ──────────────────────

    public function searchUsers(Request $request)
    {
        $q = $request->input('q', '');

        $users = User::where('name', 'like', "%{$q}%")
                     ->orWhere('email', 'like', "%{$q}%")
                     ->select('id', 'name', 'email', 'plan')
                     ->limit(10)
                     ->get();

        return response()->json(['status' => 'success', 'data' => $users]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Generate a license key that preserves the plan-specific prefix.
     *   beta   → WCL-BETA-XXXX-XXXX-XXXX-XXXX
     *   campus → WCL-CAMPUS-XXXX-XXXX-XXXX-XXXX
     *   pro    → WCL-XXXX-XXXX-XXXX-XXXX
     *   free   → WCL-FREE-XXXX-XXXX-XXXX-XXXX
     */
    private function generateKey(string $plan): string
    {
        $segments = strtoupper(substr(str_replace('-', '', Str::uuid()), 0, 16));
        $parts    = str_split($segments, 4);

        return match ($plan) {
            'beta'   => 'WCL-BETA-'   . implode('-', $parts),
            'campus' => 'WCL-CAMPUS-' . implode('-', $parts),
            'free'   => 'WCL-FREE-'   . implode('-', $parts),
            default  => 'WCL-'        . implode('-', $parts),
        };
    }
}
