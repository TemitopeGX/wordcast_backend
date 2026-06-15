<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\ProContentController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\BlogController;
use App\Models\PlatformSettings;
use App\Models\SubscriptionPlan;
use App\Http\Controllers\WaitlistController;

// ── Public API ────────────────────────────────────────────────────────────────
Route::get('/plans', function() {
    return response()->json([
        'status' => 'success',
        'plans' => SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get()
    ]);
});

// Public app settings (used by website pricing page)
Route::get('/app/settings/public', [SettingsController::class, 'publicAppSettings']);

// ── Waitlist (public) ─────────────────────────────────────────────────────────
Route::post('/waitlist',                       [WaitlistController::class, 'store']);
Route::get('/waitlist/count',                  [WaitlistController::class, 'count']);
Route::get('/waitlist/validate/{token}',       [WaitlistController::class, 'validateToken']);
Route::post('/waitlist/complete/{token}',      [WaitlistController::class, 'completeSetup']);

// ── Public Blog routes ────────────────────────────────────────────────────────
Route::prefix('blog')->group(function () {
    Route::get('/posts',         [BlogController::class, 'index']);
    Route::get('/posts/latest',  [BlogController::class, 'latest']);
    Route::get('/posts/{slug}',  [BlogController::class, 'show']);
});

// ── Public auth routes ────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// ── Paystack webhook (no auth — Paystack hits this directly) ──────────────────
Route::post('/billing/webhook', [BillingController::class, 'webhook']);

// ── Public license endpoints (Electron) ───────────────────────────────────────
Route::prefix('license')->middleware('throttle:60,1')->group(function () {
    Route::post('/validate',    [LicenseController::class, 'validate']);
    Route::post('/activate',    [LicenseController::class, 'activate']);
    Route::post('/deactivate',  [LicenseController::class, 'deactivate']);
    Route::post('/oauth-token', [LicenseController::class, 'oauthToken']);
});

// ── ProContent public catalog ─────────────────────────────────────────────────
Route::prefix('procontent')->group(function () {
    Route::get('/catalog',            [ProContentController::class, 'catalog']);
    Route::get('/catalog/{category}', [ProContentController::class, 'byCategory']);
});

// ── All authenticated routes ──────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout',         [AuthController::class, 'logout']);
    Route::get('/auth/me',              [AuthController::class, 'me']);
    Route::put('/auth/profile',         [AuthController::class, 'updateProfile']);
    Route::post('/auth/profile/avatar', [AuthController::class, 'updateAvatar']);
    Route::put('/auth/password',        [AuthController::class, 'updatePassword']);
    Route::post('/auth/app-token',      [AuthController::class, 'generateAppToken']);
    Route::get('/auth/dashboard',       [DashboardController::class, 'index']);

    // Billing
    Route::post('/billing/initiate',     [BillingController::class, 'initiate']);
    Route::get('/billing/verify',        [BillingController::class, 'verify']);
    Route::post('/billing/apply-coupon', [BillingController::class, 'applyCoupon']);
    Route::post('/billing/cancel',       [BillingController::class, 'cancel']);


    // License dashboard
    Route::prefix('license')->group(function () {
        Route::get('/',                         [LicenseController::class, 'show']);
        Route::post('/regenerate',              [LicenseController::class, 'regenerate']);
        Route::get('/devices',                  [LicenseController::class, 'devices']);
        Route::delete('/devices/{machineId}',   [LicenseController::class, 'deactivateByMachineId']);
    });

    // Onboarding
    Route::get('/onboarding',   [OnboardingController::class, 'show']);
    Route::patch('/onboarding', [OnboardingController::class, 'update']);

    // Desktop App — AI config (never exposes keys)
    Route::get('/app/ai-config', function () {
        $features = PlatformSettings::get('ai_features', [
            'sermon_studio'    => true,
            'phrase_detection' => true,
            'voice_commands'   => true,
            'lyrics_import'    => true,
        ]);
        return response()->json([
            'active_provider'  => PlatformSettings::get('active_ai_provider', 'openai'),
            'openai_model'     => PlatformSettings::get('openai_model', 'gpt-4o-mini'),
            'gemini_model'     => PlatformSettings::get('gemini_model', 'gemini-1.5-flash'),
            'features_enabled' => $features,
        ]);
    });

    // Desktop App — AI proxy (keys never leave server)
    Route::post('/app/ai/complete', function (Request $request) {
        $provider = PlatformSettings::get('active_ai_provider', 'openai');
        $today    = date('Y-m-d');
        $month    = date('Y-m');
        PlatformSettings::set('ai_calls_today_' . $today, (PlatformSettings::get('ai_calls_today_' . $today, 0) + 1), 'ai_engine', 'integer');
        PlatformSettings::set('ai_calls_month_' . $month,  (PlatformSettings::get('ai_calls_month_' . $month,  0) + 1), 'ai_engine', 'integer');

        if ($provider === 'openai') {
            $key   = PlatformSettings::get('openai_api_key');
            $model = PlatformSettings::get('openai_model', 'gpt-4o-mini');
            return Http::withToken($key)->post('https://api.openai.com/v1/chat/completions', [
                'model'    => $model,
                'messages' => $request->messages,
            ])->json();
        }

        if ($provider === 'gemini') {
            $key   = PlatformSettings::get('gemini_api_key');
            $model = PlatformSettings::get('gemini_model', 'gemini-1.5-flash');
            return Http::post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}",
                ['contents' => $request->contents]
            )->json();
        }

        return response()->json(['error' => 'No AI provider configured'], 503);
    });

    // ProContent admin
    Route::prefix('procontent')->group(function () {
        Route::post('/upload',          [ProContentController::class, 'upload'])->middleware('can:admin');
        Route::put('/{id}',             [ProContentController::class, 'update'])->middleware('can:admin');
        Route::patch('/{id}/toggle',    [ProContentController::class, 'toggleActive'])->middleware('can:admin');
        Route::delete('/{id}',          [ProContentController::class, 'destroy'])->middleware('can:admin');
        Route::get('/admin/all',        [ProContentController::class, 'adminList'])->middleware('can:admin');
    });

    // ── Admin-only routes ─────────────────────────────────────────────────────
    Route::prefix('admin')->middleware('can:admin')->group(function () {

        Route::get('/overview', [\App\Http\Controllers\AdminDashboardController::class, 'overview']);

        // Users
        Route::get('/users',                        [\App\Http\Controllers\AdminUserController::class, 'index']);
        Route::get('/subscribers',                  [\App\Http\Controllers\AdminUserController::class, 'subscribers']);
        Route::post('/users',                       [\App\Http\Controllers\AdminUserController::class, 'store']);
        Route::get('/users/{id}',                   [\App\Http\Controllers\AdminUserController::class, 'show']);
        Route::put('/users/{id}',                   [\App\Http\Controllers\AdminUserController::class, 'update']);
        Route::delete('/users/{id}',                [\App\Http\Controllers\AdminUserController::class, 'destroy']);
        Route::patch('/users/{id}/role',            [\App\Http\Controllers\AdminUserController::class, 'updateRole']);
        Route::patch('/users/{id}/status',          [\App\Http\Controllers\AdminUserController::class, 'updateStatus']);
        Route::patch('/users/{id}/plan',            [\App\Http\Controllers\AdminUserController::class, 'changePlan']);
        Route::post('/users/{id}/reset-password',   [\App\Http\Controllers\AdminUserController::class, 'resetPassword']);


        // Licenses
        Route::get('/licenses',                             [\App\Http\Controllers\AdminLicenseController::class, 'index']);
        Route::get('/licenses/search-users',                [\App\Http\Controllers\AdminLicenseController::class, 'searchUsers']);
        Route::post('/licenses',                            [\App\Http\Controllers\AdminLicenseController::class, 'generate']);
        Route::get('/licenses/{id}',                        [\App\Http\Controllers\AdminLicenseController::class, 'show']);
        Route::delete('/licenses/{id}',                     [\App\Http\Controllers\AdminLicenseController::class, 'destroy']);
        Route::patch('/licenses/{id}/toggle',               [\App\Http\Controllers\AdminLicenseController::class, 'toggleStatus']);
        Route::delete('/licenses/{id}/devices',             [\App\Http\Controllers\AdminLicenseController::class, 'wipeDevices']);
        Route::delete('/licenses/{id}/devices/{deviceId}',  [\App\Http\Controllers\AdminLicenseController::class, 'kickDevice']);

        // Analytics
        Route::get('/analytics', [\App\Http\Controllers\AdminAnalyticsController::class, 'index']);

        // Settings
        Route::prefix('settings')->group(function () {
            Route::get('/',                         [SettingsController::class, 'index']);
            Route::post('/general',                 [SettingsController::class, 'saveGeneral']);
            Route::post('/billing',                 [SettingsController::class, 'saveBilling']);
            Route::post('/ai-engine',               [SettingsController::class, 'saveAiEngine']);
            Route::post('/ai-engine/switch',        [SettingsController::class, 'switchAiProvider']);
            Route::post('/ai-engine/test',          [SettingsController::class, 'testAiConnection']);
            Route::post('/ai-engine/models',        [SettingsController::class, 'listAiModels']);
            Route::post('/system',                  [SettingsController::class, 'saveSystem']);
            Route::post('/system/toggle',           [SettingsController::class, 'toggleSetting']);
            Route::post('/system/clear-cache',      [SettingsController::class, 'clearCache']);
            Route::get('/system/backup',            [SettingsController::class, 'backupDatabase']);
            Route::post('/billing/test-webhook',    [SettingsController::class, 'testWebhook']);
            Route::post('/app-control',              [SettingsController::class, 'saveAppControl']);
            Route::post('/app-control/toggle',       [SettingsController::class, 'toggleAppControl']);
        });

        // Subscription plans + coupons
        Route::prefix('subscription')->group(function () {
            Route::get('/plans',                    [SubscriptionPlanController::class, 'index']);
            Route::post('/plans',                   [SubscriptionPlanController::class, 'store']);
            Route::post('/plans/reorder',           [SubscriptionPlanController::class, 'reorder']);
            Route::put('/plans/{plan}',             [SubscriptionPlanController::class, 'update']);
            Route::delete('/plans/{plan}',          [SubscriptionPlanController::class, 'destroy']);
            Route::post('/plans/{plan}/toggle',     [SubscriptionPlanController::class, 'toggle']);

            Route::get('/coupons',                  [CouponController::class, 'index']);
            Route::post('/coupons',                 [CouponController::class, 'store']);
            Route::put('/coupons/{coupon}',         [CouponController::class, 'update']);
            Route::delete('/coupons/{coupon}',      [CouponController::class, 'destroy']);
            Route::post('/coupons/{coupon}/toggle', [CouponController::class, 'toggle']);
        });

        // ProContent (admin prefix)
        Route::prefix('procontent')->group(function () {
            Route::post('/upload',          [ProContentController::class, 'upload']);
            Route::put('/{id}',             [ProContentController::class, 'update']);
            Route::patch('/{id}/toggle',    [ProContentController::class, 'toggleActive']);
            Route::delete('/{id}',          [ProContentController::class, 'destroy']);
            Route::get('/all',              [ProContentController::class, 'adminList']);
        });

        // Blog CMS (admin)
        Route::prefix('blog')->group(function () {
            Route::get('/posts',              [BlogController::class, 'adminIndex']);
            Route::post('/posts',             [BlogController::class, 'store']);
            Route::put('/posts/{id}',         [BlogController::class, 'update']);
            Route::delete('/posts/{id}',      [BlogController::class, 'destroy']);
            Route::patch('/posts/{id}/toggle',[BlogController::class, 'toggleStatus']);
        });

        // Waitlist (admin)
        Route::prefix('waitlist')->group(function () {
            Route::get('/',                    [WaitlistController::class, 'adminIndex']);
            Route::post('/approve-bulk',       [WaitlistController::class, 'approveBulk']);
            Route::get('/export',              [WaitlistController::class, 'export']);
            Route::post('/{id}/approve',       [WaitlistController::class, 'approve']);
            Route::post('/{id}/resend-invite', [WaitlistController::class, 'resendInvite']);
            Route::post('/{id}/reject',        [WaitlistController::class, 'reject']);
            Route::delete('/{id}',             [WaitlistController::class, 'destroy']);
        });
    });
});
