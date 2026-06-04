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
            'plan'       => 'required|in:free,pro,campus',
            'seat_limit' => 'required|integer|min:1|max:50',
            'expires_at' => 'nullable|date|after:today',
        ]);

        $key = 'WCL-' . strtoupper(Str::uuid());

        $license = License::create([
            'user_id'    => $request->user_id,
            'license_key' => $key,
            'plan'       => $request->plan,
            'seat_limit' => $request->seat_limit,
            'expires_at' => $request->expires_at ?? null,
            'is_active'  => true,
        ]);

        // Sync user plan to match license
        User::where('id', $request->user_id)->update(['plan' => $request->plan]);

        return response()->json([
            'status'  => 'success',
            'message' => 'License generated successfully.',
            'data'    => $license->load('user'),
        ], 201);
    }

    // ── Revoke (delete) a license key ─────────────────────────────────────────

    public function destroy($id)
    {
        $license = License::findOrFail($id);
        // Cascade deletes devices via DB foreign key constraint
        $license->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'License revoked and deleted.',
        ]);
    }

    // ── Toggle active / inactive ──────────────────────────────────────────────

    public function toggleStatus($id)
    {
        $license = License::findOrFail($id);
        $license->is_active = !$license->is_active;
        $license->save();

        // If deactivating, optionally wipe devices so they can't use stale sessions
        // Keep devices so they can re-activate if re-enabled.

        return response()->json([
            'status'  => 'success',
            'message' => 'License status updated.',
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
}
