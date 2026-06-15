<?php

namespace App\Http\Controllers;

use App\Models\Waitlist;
use App\Models\User;
use App\Mail\WaitlistConfirmation;
use App\Mail\WaitlistApproved;
use App\Mail\WaitlistWelcome;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class WaitlistController extends Controller
{
    // ── PUBLIC ─────────────────────────────────────────────────────────────────

    /**
     * POST /api/waitlist
     * Join the waitlist (public — no auth)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:100',
            'email'        => 'required|email|max:255',
            'organization' => 'nullable|string|max:200',
            'source'       => 'nullable|string|max:50',
        ]);

        // Already on waitlist?
        $existing = Waitlist::where('email', $validated['email'])->first();
        if ($existing) {
            return response()->json([
                'success'        => false,
                'already_exists' => true,
                'message'        => 'This email is already on our waitlist.',
                'status'         => $existing->status,
            ], 409);
        }

        // Already a full user?
        if (User::where('email', $validated['email'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'An account with this email already exists. Please sign in.',
            ], 409);
        }

        $entry = Waitlist::create([
            'name'         => $validated['name'],
            'email'        => $validated['email'],
            'organization' => $validated['organization'] ?? null,
            'source'       => $validated['source'] ?? 'website',
            'status'       => 'pending',
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
        ]);

        // Queue confirmation email
        try {
            Mail::to($entry->email)->send(new WaitlistConfirmation($entry));
        } catch (\Exception $e) {
            \Log::warning('WaitlistConfirmation email failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'You have been added to the waitlist.',
            'id'      => $entry->id,
        ], 201);
    }

    /**
     * GET /api/waitlist/count
     * Public waitlist count for the website counter
     */
    public function count()
    {
        $count = Waitlist::whereIn('status', ['pending', 'approved', 'registered'])->count();
        return response()->json(['count' => $count]);
    }

    /**
     * GET /api/waitlist/setup/{token}
     * Validate invite token — called when beta user clicks link
     */
    public function validateToken($token)
    {
        $entry = Waitlist::where('invite_token', $token)
            ->where('status', 'approved')
            ->first();

        if (!$entry) {
            return response()->json([
                'valid'   => false,
                'message' => 'This invite link is invalid or has already been used.',
            ], 404);
        }

        // Record first click and set 48h expiry window
        if (!$entry->invite_clicked_at) {
            $entry->update([
                'invite_clicked_at' => now(),
                'invite_expires_at' => now()->addHours(48),
            ]);
        }

        // Check if expired
        if ($entry->isExpired()) {
            return response()->json([
                'valid'   => false,
                'expired' => true,
                'message' => 'This invite link has expired. Please contact support for a new one.',
            ], 410);
        }

        return response()->json([
            'valid'        => true,
            'name'         => $entry->name,
            'email'        => $entry->email,
            'organization' => $entry->organization,
        ]);
    }

    /**
     * POST /api/waitlist/setup/{token}
     * Complete account setup — set password and create user
     */
    public function completeSetup(Request $request, $token)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $entry = Waitlist::where('invite_token', $token)
            ->where('status', 'approved')
            ->first();

        if (!$entry) {
            return response()->json(['message' => 'Invalid or expired invite.'], 404);
        }

        if ($entry->isExpired()) {
            return response()->json(['message' => 'This invite has expired.'], 410);
        }

        // Create the full user account
        $user = User::create([
            'name'              => $entry->name,
            'email'             => $entry->email,
            'password'          => Hash::make($request->password),
            'church_name'       => $entry->organization,
            'organization'      => $entry->organization,
            'is_beta_tester'    => true,
            'beta_source'       => $entry->source,
            'beta_approved_at'  => $entry->invite_sent_at,
            'plan'              => 'pro',
            'status'            => 'active',
            'email_verified_at' => now(), // email confirmed via waitlist flow
        ]);

        // Generate beta license key
        $licenseKey = 'WCL-BETA-'
            . strtoupper(Str::random(4)) . '-'
            . strtoupper(Str::random(4)) . '-'
            . strtoupper(Str::random(4));

        // Create Pro license — 1 seat, 1 month
        $user->license()->create([
            'license_key' => $licenseKey,
            'plan'        => 'pro',
            'seat_limit'  => 1,
            'expires_at'  => now()->addMonth(),
            'is_active'   => true,
        ]);

        // DELETE waitlist entry — they are now a user
        $entry->delete();

        // Queue welcome email
        try {
            Mail::to($user->email)->send(new WaitlistWelcome($user, $licenseKey));
        } catch (\Exception $e) {
            \Log::warning('WaitlistWelcome email failed: ' . $e->getMessage());
        }

        // Issue Sanctum token for immediate login
        $authToken = $user->createToken('beta-signup')->plainTextToken;

        return response()->json([
            'success'       => true,
            'message'       => 'Account created successfully.',
            'token'         => $authToken,
            'user'          => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role ?? 'user',
                'plan'  => 'pro',
            ],
            'license_key'   => $licenseKey,
            'dashboard_url' => config('app.dashboard_url', env('APP_DASHBOARD_URL', 'https://dashboard.wordcastlive.site')),
        ]);
    }

    // ── ADMIN ──────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/waitlist
     */
    public function adminIndex(Request $request)
    {
        $query = Waitlist::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name',         'like', "%{$search}%")
                  ->orWhere('email',        'like', "%{$search}%")
                  ->orWhere('organization', 'like', "%{$search}%");
            });
        }

        $entries = $query->orderByDesc('created_at')->paginate(50);

        return response()->json([
            'status' => 'success',
            'data'   => $entries->items(),
            'meta'   => [
                'total'        => $entries->total(),
                'current_page' => $entries->currentPage(),
                'last_page'    => $entries->lastPage(),
            ],
            'stats'  => [
                'total'      => Waitlist::count(),
                'pending'    => Waitlist::where('status', 'pending')->count(),
                'approved'   => Waitlist::where('status', 'approved')->count(),
                'registered' => Waitlist::where('status', 'registered')->count(),
                'rejected'   => Waitlist::where('status', 'rejected')->count(),
            ],
        ]);
    }

    /**
     * POST /api/admin/waitlist/{id}/approve
     */
    public function approve($id)
    {
        $entry = Waitlist::findOrFail($id);

        if ($entry->status !== 'pending') {
            return response()->json(['message' => 'Entry is not in pending status.'], 422);
        }

        $token = Str::random(64);

        $entry->update([
            'status'         => 'approved',
            'invite_token'   => $token,
            'invite_sent_at' => now(),
        ]);

        try {
            Mail::to($entry->email)->send(new WaitlistApproved($entry, $token));
        } catch (\Exception $e) {
            \Log::warning('WaitlistApproved email failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => "Invite sent to {$entry->email}",
            'entry'   => $entry->fresh(),
        ]);
    }

    /**
     * POST /api/admin/waitlist/approve-bulk
     */
    public function approveBulk(Request $request)
    {
        $request->validate(['ids' => 'required|array|min:1']);

        $count = 0;
        foreach ($request->ids as $id) {
            $entry = Waitlist::find($id);
            if ($entry && $entry->status === 'pending') {
                $token = Str::random(64);
                $entry->update([
                    'status'         => 'approved',
                    'invite_token'   => $token,
                    'invite_sent_at' => now(),
                ]);
                try {
                    Mail::to($entry->email)->send(new WaitlistApproved($entry, $token));
                } catch (\Exception $e) {
                    \Log::warning('WaitlistApproved bulk email failed: ' . $e->getMessage());
                }
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$count} invite" . ($count !== 1 ? 's' : '') . " sent.",
            'count'   => $count,
        ]);
    }

    /**
     * POST /api/admin/waitlist/{id}/resend-invite
     */
    public function resendInvite($id)
    {
        $entry = Waitlist::findOrFail($id);

        if ($entry->status !== 'approved') {
            return response()->json(['message' => 'Can only resend to approved entries.'], 422);
        }

        $token = Str::random(64);
        $entry->update([
            'invite_token'      => $token,
            'invite_sent_at'    => now(),
            'invite_clicked_at' => null,
            'invite_expires_at' => null,
        ]);

        try {
            Mail::to($entry->email)->send(new WaitlistApproved($entry, $token));
        } catch (\Exception $e) {
            \Log::warning('WaitlistApproved resend failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Invite resent successfully.',
            'entry'   => $entry->fresh(),
        ]);
    }

    /**
     * POST /api/admin/waitlist/{id}/reject
     */
    public function reject($id)
    {
        $entry = Waitlist::findOrFail($id);
        $entry->update([
            'status'       => 'rejected',
            'invite_token' => null,
        ]);
        return response()->json(['success' => true, 'entry' => $entry->fresh()]);
    }

    /**
     * DELETE /api/admin/waitlist/{id}
     */
    public function destroy($id)
    {
        Waitlist::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    /**
     * GET /api/admin/waitlist/export
     * Returns CSV download
     */
    public function export()
    {
        $entries = Waitlist::orderByDesc('created_at')->get([
            'name', 'email', 'organization', 'status', 'source', 'created_at',
        ]);

        $csv = "Name,Email,Organization,Status,Source,Joined\n";
        foreach ($entries as $e) {
            $joined = $e->created_at ? $e->created_at->format('Y-m-d H:i:s') : '';
            $csv   .= sprintf(
                '"%s","%s","%s",%s,%s,"%s"' . "\n",
                str_replace('"', '""', $e->name ?? ''),
                str_replace('"', '""', $e->email ?? ''),
                str_replace('"', '""', $e->organization ?? ''),
                $e->status,
                $e->source,
                $joined
            );
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="waitlist-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }
}
