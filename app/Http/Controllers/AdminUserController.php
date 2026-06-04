<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\SubscriptionPlan;


class AdminUserController extends Controller
{
    // ── List / Search ─────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $users = User::with('license')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name',  'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($status, fn($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15);

        return response()->json(['status' => 'success', 'data' => $users]);
    }

    // ── Single user profile (with sessions + licenses) ─────────────────────────

    public function show($id)
    {
        $user = User::with(['license.devices'])->findOrFail($id);

        // Last 10 DB sessions for the user
        $sessions = DB::table('sessions')
            ->where('user_id', $id)
            ->orderByDesc('last_activity')
            ->limit(10)
            ->get()
            ->map(function ($s) {
                return [
                    'ip_address'    => $s->ip_address,
                    'user_agent'    => $s->user_agent,
                    'last_activity' => date('Y-m-d H:i:s', $s->last_activity),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data'   => [
                'user'     => $user,
                'sessions' => $sessions,
            ],
        ]);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role'     => 'required|in:admin,user',
            'plan'     => 'required|in:free,pro,campus',
            'status'   => 'required|in:active,suspended,banned',
        ]);

        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'User created successfully.',
            'user'    => $user,
        ]);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users,email,'.$id,
            'password' => 'nullable|string|min:8',
            'role'     => 'required|in:admin,user',
            'plan'     => 'required|in:free,pro,campus',
            'status'   => 'required|in:active,suspended,banned',
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if (auth()->id() === $user->id && isset($data['role']) && $data['role'] !== $user->role) {
             return response()->json([
                 'status'  => 'error',
                 'message' => 'You cannot change your own role.',
             ], 403);
        }
        
        if (auth()->id() === $user->id && isset($data['status']) && $data['status'] !== $user->status) {
             return response()->json([
                 'status'  => 'error',
                 'message' => 'You cannot change your own status.',
             ], 403);
        }

        $user->update($data);

        // Sync license status when suspending/banning
        $newStatus = $data['status'] ?? $user->status;
        if (in_array($newStatus, ['suspended', 'banned'])) {
            $user->tokens()->delete();
            $user->license?->update(['is_active' => false]);
        } elseif ($newStatus === 'active') {
            $user->license?->update(['is_active' => true]);
        }

        // Sync plan to license if plan changed
        $this->syncLicensePlan($user, $data['plan'] ?? $user->plan);

        return response()->json([
            'status'  => 'success',
            'message' => 'User updated successfully.',
            'user'    => $user->fresh('license'),
        ]);
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if (auth()->id() === $user->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'You cannot delete your own account.',
            ], 403);
        }

        // Revoke all Sanctum tokens (kills sessions)
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['status' => 'success', 'message' => 'User deleted successfully.']);
    }

    // ── Role ──────────────────────────────────────────────────────────────────

    public function updateRole(Request $request, $id)
    {
        $request->validate(['role' => 'required|in:admin,user']);

        $user = User::findOrFail($id);

        if (auth()->id() === $user->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'You cannot change your own role.',
            ], 403);
        }

        $user->role = $request->role;
        $user->save();

        return response()->json(['status' => 'success', 'message' => 'Role updated.', 'user' => $user]);
    }

    // ── Status: suspend / ban / activate ─────────────────────────────────────

    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:active,suspended,banned']);

        $user = User::findOrFail($id);

        if (auth()->id() === $user->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'You cannot change your own account status.',
            ], 403);
        }

        $user->status = $request->status;
        $user->save();

        // If suspending or banning, immediately revoke all Sanctum tokens + deactivate license
        if (in_array($request->status, ['suspended', 'banned'])) {
            $user->tokens()->delete();
            $user->license?->update(['is_active' => false]);
        } elseif ($request->status === 'active') {
            // Re-activate license when restoring
            $user->license?->update(['is_active' => true]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => "User status set to {$request->status}.",
            'user'    => $user->fresh('license'),
        ]);
    }

    // ── Change Plan (admin) ───────────────────────────────────────────────────

    public function changePlan(Request $request, $id)
    {
        $request->validate(['plan' => 'required|string']);

        $user    = User::with('license')->findOrFail($id);
        $planSlug = $request->plan;

        $this->syncLicensePlan($user, $planSlug);
        $user->update(['plan' => $planSlug]);

        return response()->json([
            'status'  => 'success',
            'message' => "Plan changed to {$planSlug}.",
            'user'    => $user->fresh('license'),
        ]);
    }

    // ── Reset password ────────────────────────────────────────────────────────

    public function resetPassword($id)
    {
        $user = User::findOrFail($id);

        $newPassword = Str::random(12);
        $user->password = Hash::make($newPassword);
        $user->save();

        // Revoke all tokens so they must re-login with new password
        $user->tokens()->delete();

        return response()->json([
            'status'       => 'success',
            'message'      => 'Password has been reset.',
            'new_password' => $newPassword,
        ]);
    }

    // ── Private: sync license plan & seat_limit ───────────────────────────────

    private function syncLicensePlan(User $user, string $planSlug): void
    {
        // Look up seat_limit from SubscriptionPlan table, fallback to hardcoded
        $subPlan   = SubscriptionPlan::where('slug', $planSlug)->first();
        $seatLimit = $subPlan ? (int)($subPlan->seat_limit ?? 1) : match($planSlug) {
            'pro'    => 5,
            'campus' => 999,
            default  => 1,
        };

        $license = $user->license;
        if ($license) {
            $license->update([
                'plan'       => $planSlug,
                'seat_limit' => $seatLimit,
                // Keep expires_at and is_active as-is, admin can manage separately
            ]);
        } else {
            // Create license if missing
            $user->license()->create([
                'license_key' => 'WCL-' . strtoupper(\Illuminate\Support\Str::uuid()),
                'plan'        => $planSlug,
                'seat_limit'  => $seatLimit,
                'is_active'   => true,
                'expires_at'  => null,
            ]);
        }
    }

    public function subscribers(Request $request)
    {
        $search = $request->input('search');
        $plan = $request->input('plan');

        $subscribers = DB::table('users')
            ->leftJoin('subscriptions', 'users.id', '=', 'subscriptions.user_id')
            ->leftJoin('licenses', 'users.id', '=', 'licenses.user_id')
            ->select(
                'users.id as user_id',
                'users.name as user_name',
                'users.email as user_email',
                'users.plan as user_plan',
                'users.status as user_status',
                'subscriptions.id as subscription_id',
                'subscriptions.paystack_subscription_code',
                'subscriptions.status as subscription_status',
                'subscriptions.amount',
                'subscriptions.currency',
                'subscriptions.next_payment_date',
                'licenses.license_key',
                'licenses.expires_at as license_expires_at'
            )
            ->whereIn('users.plan', ['pro', 'campus'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('users.name', 'like', "%{$search}%")
                      ->orWhere('users.email', 'like', "%{$search}%")
                      ->orWhere('licenses.license_key', 'like', "%{$search}%");
                });
            })
            ->when($plan, fn($q) => $q->where('users.plan', $plan))
            ->orderByDesc('users.created_at')
            ->paginate(15);

        $subscribers->getCollection()->transform(function ($sub) {
            if (is_null($sub->amount)) {
                if ($sub->user_plan === 'pro') {
                    $sub->amount = 5000.00;
                    $sub->currency = 'NGN';
                } else {
                    $sub->amount = null; // Custom
                    $sub->currency = 'NGN';
                }
            }
            if (is_null($sub->next_payment_date)) {
                $sub->next_payment_date = $sub->license_expires_at;
            }
            return $sub;
        });

        return response()->json(['status' => 'success', 'data' => $subscribers]);
    }

    // ── Settings & System Operations ──────────────────────────────────────────

    /**
     * Returns the admin-configured OpenAI API key to authenticated subscribers.
     * Only users with plan 'pro' or 'campus' may access this.
     * Free-tier users receive a 403 Forbidden response.
     */
    public function getOpenAiKeyForSubscriber(\Illuminate\Http\Request $request)
    {
        $user = $request->user();

        $subscriberPlans = ['pro', 'campus'];
        if (!in_array($user->plan ?? 'free', $subscriberPlans)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This feature is available to Pro and Campus subscribers only.',
            ], 403);
        }

        // Load the admin-configured key from settings.json or env fallback
        $path     = storage_path('app/settings.json');
        $settings = file_exists($path) ? json_decode(file_get_contents($path), true) : [];
        $key      = $settings['openai_api_key'] ?? env('OPENAI_API_KEY', '');

        if (!$key) {
            return response()->json([
                'status'  => 'error',
                'message' => 'AI features are not configured yet. Please contact support.',
            ], 503);
        }

        return response()->json([
            'status' => 'success',
            'key'    => $key,
        ]);
    }

    public function getSettings()
    {
        $path = storage_path('app/settings.json');
        $settings = [];
        if (file_exists($path)) {
            $settings = json_decode(file_get_contents($path), true);
        }

        $defaults = [
            'app_name'                  => env('APP_NAME', 'WordCast LIVE'),
            'support_email'             => 'billing@wordcast.live',
            'default_seat_limit_pro'    => 3,
            'default_seat_limit_campus' => 10,
            'pro_plan_price'            => 5000,
            'campus_plan_price'         => 15000,
            'pro_plan_features'         => 'Advanced Streaming, HD Quality, 3 Seats',
            'campus_plan_features'      => 'Multi-campus support, 4K Quality, 10 Seats, Dedicated Support',
            'discount_percentage'       => 0,
            'default_trial_days'        => 7,
            'paystack_public_key'       => env('PAYSTACK_PUBLIC_KEY', ''),
            'paystack_secret_key'       => env('PAYSTACK_SECRET_KEY', ''),
            'openai_api_key'            => env('OPENAI_API_KEY', ''),
            'gemini_api_key'            => env('GEMINI_API_KEY', ''),
            'active_speech_engine'      => 'whisper_api',
            'active_nlp_engine'         => 'openai',
            'maintenance_mode'          => false,
        ];

        $settings = array_merge($defaults, $settings ?: []);
        return response()->json(['status' => 'success', 'data' => $settings]);
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'app_name'                  => 'string|max:255',
            'support_email'             => 'email|max:255',
            'default_seat_limit_pro'    => 'integer|min:1',
            'default_seat_limit_campus' => 'integer|min:1',
            'pro_plan_price'            => 'numeric|min:0',
            'campus_plan_price'         => 'numeric|min:0',
            'pro_plan_features'         => 'string|nullable',
            'campus_plan_features'      => 'string|nullable',
            'discount_percentage'       => 'numeric|min:0|max:100',
            'default_trial_days'        => 'integer|min:0',
            'paystack_public_key'       => 'string|nullable',
            'paystack_secret_key'       => 'string|nullable',
            'openai_api_key'            => 'string|nullable',
            'gemini_api_key'            => 'string|nullable',
            'active_speech_engine'      => 'string|in:whisper_api,local_whisper,google_speech',
            'active_nlp_engine'         => 'string|in:openai,gemini',
            'maintenance_mode'          => 'boolean',
        ]);

        $path = storage_path('app/settings.json');
        $settings = $request->only([
            'app_name', 'support_email', 'default_seat_limit_pro', 'default_seat_limit_campus',
            'pro_plan_price', 'campus_plan_price', 'pro_plan_features', 'campus_plan_features',
            'discount_percentage', 'default_trial_days',
            'paystack_public_key', 'paystack_secret_key', 'openai_api_key', 'gemini_api_key',
            'active_speech_engine', 'active_nlp_engine', 'maintenance_mode'
        ]);

        file_put_contents($path, json_encode($settings, JSON_PRETTY_PRINT));

        // Try updating .env values
        $this->updateEnvFile([
            'APP_NAME'            => '"' . $settings['app_name'] . '"',
            'PAYSTACK_PUBLIC_KEY' => $settings['paystack_public_key'],
            'PAYSTACK_SECRET_KEY' => $settings['paystack_secret_key'],
            'OPENAI_API_KEY'      => $settings['openai_api_key'],
            'GEMINI_API_KEY'      => $settings['gemini_api_key'],
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'System settings updated successfully.',
            'data'    => $settings
        ]);
    }

    private function updateEnvFile($data)
    {
        $envPath = base_path('.env');
        if (!file_exists($envPath)) {
            return;
        }

        $envContent = file_get_contents($envPath);
        foreach ($data as $key => $value) {
            $value = $value ?? '';
            if (preg_match("/^{$key}=/m", $envContent)) {
                $envContent = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $envContent);
            } else {
                $envContent .= "\n{$key}={$value}";
            }
        }

        file_put_contents($envPath, $envContent);
    }

    public function clearCache()
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
            \Illuminate\Support\Facades\Artisan::call('config:clear');
            return response()->json(['status' => 'success', 'message' => 'Application cache cleared successfully.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to clear cache: ' . $e->getMessage()], 500);
        }
    }

    public function backupDatabase()
    {
        try {
            $dbName = env('DB_DATABASE', 'wordcast');
            $filename = "backup-{$dbName}-" . date('Y-m-d-H-i-s') . ".sql";
            $storageDir = storage_path('app/backups');
            if (!file_exists($storageDir)) {
                mkdir($storageDir, 0755, true);
            }
            $filePath = $storageDir . '/' . $filename;
            
            $sqlContent = "-- WordCast LIVE Database Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n\n";
            
            $tablesResult = DB::select("SHOW TABLES");
            foreach ($tablesResult as $tableRow) {
                $tableRowArr = (array)$tableRow;
                $table = reset($tableRowArr);
                
                $sqlContent .= "-- Table: {$table}\n";
                try {
                    $create = DB::select("SHOW CREATE TABLE `{$table}`");
                    $createArr = (array)$create[0];
                    $sqlContent .= $createArr['Create Table'] . ";\n\n";
                    
                    $rows = DB::table($table)->get();
                    if ($rows->count() > 0) {
                        $sqlContent .= "-- Data for table: {$table}\n";
                        foreach ($rows as $row) {
                            $rowArr = (array)$row;
                            $keys = array_keys($rowArr);
                            $values = array_map(function($v) {
                                if (is_null($v)) return 'NULL';
                                return "'" . addslashes($v) . "'";
                            }, array_values($rowArr));
                            $sqlContent .= "INSERT INTO `{$table}` (`" . implode("`, `", $keys) . "`) VALUES (" . implode(", ", $values) . ");\n";
                        }
                        $sqlContent .= "\n";
                    }
                } catch (\Exception $ex) {
                    $sqlContent .= "-- Failed to export table {$table}: " . $ex->getMessage() . "\n\n";
                }
            }
            
            file_put_contents($filePath, $sqlContent);
            return response()->download($filePath, $filename);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Backup failed: ' . $e->getMessage()], 500);
        }
    }
}
