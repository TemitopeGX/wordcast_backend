<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\PlatformSettings;
use App\Models\SubscriptionPlan;
use App\Models\Coupon;

class SettingsController extends Controller
{
    // ── GET /admin/settings/all ─────────────────────────────────────────────────
    public function index(): JsonResponse
    {
        $settings = PlatformSettings::all()->keyBy('key')->map(function ($s) {
            // Never return encrypted raw values — return masked or empty
            if ($s->type === 'encrypted') {
                return ['value' => PlatformSettings::masked($s->key), 'type' => 'encrypted', 'group' => $s->group];
            }
            return ['value' => PlatformSettings::get($s->key), 'type' => $s->type, 'group' => $s->group];
        });

        $plans = SubscriptionPlan::orderBy('sort_order')->get();
        $coupons = Coupon::orderBy('created_at', 'desc')->get();

        return response()->json([
            'status'   => 'success',
            'settings' => $settings,
            'plans'    => $plans,
            'coupons'  => $coupons,
        ]);
    }

    // ── POST /admin/settings/general ───────────────────────────────────────────
    public function saveGeneral(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform_name'    => 'required|string|max:100',
            'support_email'    => 'required|email',
            'app_description'  => 'nullable|string|max:500',
            'maintenance_mode' => 'sometimes|boolean',
        ]);

        PlatformSettings::set('platform_name',   $validated['platform_name'],   'general');
        PlatformSettings::set('support_email',   $validated['support_email'],   'general');
        PlatformSettings::set('app_description', $validated['app_description'] ?? '', 'general');
        PlatformSettings::set('maintenance_mode', $request->boolean('maintenance_mode'), 'general', 'boolean');

        return response()->json(['status' => 'success', 'message' => 'General settings saved.']);
    }

    // ── POST /admin/settings/billing ───────────────────────────────────────────
    public function saveBilling(Request $request): JsonResponse
    {
        PlatformSettings::set('billing_currency',    $request->billing_currency ?? 'NGN',   'billing');
        PlatformSettings::set('billing_test_mode',   $request->boolean('billing_test_mode'), 'billing', 'boolean');
        PlatformSettings::set('paystack_public_key', $request->paystack_public_key ?? '',    'billing');
        PlatformSettings::set('invoice_prefix',      $request->invoice_prefix ?? 'WCL-',    'billing');
        PlatformSettings::set('vat_percentage',      $request->vat_percentage ?? 0,         'billing', 'integer');
        PlatformSettings::set('company_name',        $request->company_name ?? '',           'billing');
        PlatformSettings::set('company_address',     $request->company_address ?? '',        'billing');

        // Save secret encrypted only if a real new value is provided
        if ($request->filled('paystack_secret_key') && !str_starts_with($request->paystack_secret_key, '****')) {
            PlatformSettings::set('paystack_secret_key', $request->paystack_secret_key, 'billing', 'encrypted');
        }

        return response()->json(['status' => 'success', 'message' => 'Billing settings saved.']);
    }

    // ── POST /admin/settings/ai-engine ─────────────────────────────────────────
    public function saveAiEngine(Request $request): JsonResponse
    {
        if ($request->filled('openai_api_key') && !str_starts_with($request->openai_api_key, '****')) {
            PlatformSettings::set('openai_api_key', $request->openai_api_key, 'ai_engine', 'encrypted');
        }

        if ($request->filled('gemini_api_key') && !str_starts_with($request->gemini_api_key, '****')) {
            PlatformSettings::set('gemini_api_key', $request->gemini_api_key, 'ai_engine', 'encrypted');
        }

        PlatformSettings::set('openai_model', $request->openai_model ?? 'gpt-4o-mini',      'ai_engine');
        PlatformSettings::set('gemini_model', $request->gemini_model ?? 'gemini-2.5-flash',  'ai_engine');

        // Save feature toggles
        $aiFeatures = $request->input('ai_features', []);
        PlatformSettings::set('ai_features', $aiFeatures, 'ai_engine', 'json');

        return response()->json(['status' => 'success', 'message' => 'AI Engine settings saved.']);
    }

    // ── POST /admin/settings/ai-engine/switch ──────────────────────────────────
    public function switchAiProvider(Request $request): JsonResponse
    {
        $request->validate(['provider' => 'required|in:openai,gemini']);

        PlatformSettings::set('active_ai_provider', $request->provider, 'ai_engine');

        return response()->json([
            'status'   => 'success',
            'provider' => $request->provider,
            'message'  => 'AI provider switched to ' . ucfirst($request->provider),
        ]);
    }

    // ── POST /admin/settings/ai-engine/test ────────────────────────────────────
    public function testAiConnection(Request $request): JsonResponse
    {
        $provider = $request->input('provider', PlatformSettings::get('active_ai_provider', 'openai'));

        try {
            $start = microtime(true);

            if ($provider === 'openai') {
                $key   = PlatformSettings::get('openai_api_key');
                $model = PlatformSettings::get('openai_model', 'gpt-4o-mini');
                if (!$key) return response()->json(['status' => 'error', 'message' => 'OpenAI API key not configured.']);

                $response = \Illuminate\Support\Facades\Http::withToken($key)
                    ->timeout(10)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model'      => $model,
                        'messages'   => [['role' => 'user', 'content' => 'Say "OK" in one word.']],
                        'max_tokens' => 5,
                    ]);
            } else {
                $key   = PlatformSettings::get('gemini_api_key');
                $model = PlatformSettings::get('gemini_model', 'gemini-2.5-flash');
                if (!$key) return response()->json(['status' => 'error', 'message' => 'Gemini API key not configured.']);

                $response = \Illuminate\Support\Facades\Http::timeout(10)
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}", [
                        'contents' => [['parts' => [['text' => 'Say OK in one word.']]]],
                    ]);
            }

            $latency = round((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                // Increment usage counter
                $today = date('Y-m-d');
                $month = date('Y-m');
                PlatformSettings::set('ai_calls_today_' . $today, (PlatformSettings::get('ai_calls_today_' . $today, 0) + 1), 'ai_engine', 'integer');
                PlatformSettings::set('ai_calls_month_' . $month, (PlatformSettings::get('ai_calls_month_' . $month, 0) + 1), 'ai_engine', 'integer');

                return response()->json(['status' => 'success', 'latency_ms' => $latency, 'message' => 'Connection successful.']);
            }

            return response()->json(['status' => 'error', 'latency_ms' => $latency, 'message' => $response->json('error.message') ?? 'Request failed.']);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'Connection failed: ' . $e->getMessage()]);
        }
    }

    // ── POST /admin/settings/ai-engine/models ──────────────────────────────────
    public function listAiModels(Request $request): JsonResponse
    {
        $provider = $request->input('provider', 'openai');
        try {
            if ($provider === 'openai') {
                $key = PlatformSettings::get('openai_api_key');
                if (!$key) return response()->json(['status' => 'error', 'message' => 'OpenAI API key not configured.']);
                $res = \Illuminate\Support\Facades\Http::withToken($key)->get('https://api.openai.com/v1/models');
                if ($res->successful()) {
                    $models = collect($res->json('data'))->pluck('id')->sort()->values();
                    return response()->json(['status' => 'success', 'models' => $models]);
                }
                return response()->json(['status' => 'error', 'message' => 'Failed to fetch OpenAI models.']);
            } else {
                $key = PlatformSettings::get('gemini_api_key');
                if (!$key) return response()->json(['status' => 'error', 'message' => 'Gemini API key not configured.']);
                $res = \Illuminate\Support\Facades\Http::get("https://generativelanguage.googleapis.com/v1beta/models?key={$key}");
                if ($res->successful()) {
                    $models = collect($res->json('models'))
                        ->filter(fn($m) => str_contains(strtolower($m['name']), 'gemini'))
                        ->pluck('name')->map(fn($n) => str_replace('models/', '', $n))->values();
                    return response()->json(['status' => 'success', 'models' => $models]);
                }
                return response()->json(['status' => 'error', 'message' => 'Failed to fetch Gemini models.']);
            }
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ── POST /admin/settings/system ────────────────────────────────────────────
    public function saveSystem(Request $request): JsonResponse
    {
        PlatformSettings::set('grace_period_days',           $request->grace_period_days ?? 3,           'system', 'integer');
        PlatformSettings::set('license_check_interval_hours',$request->license_check_interval_hours ?? 24,'system', 'integer');
        PlatformSettings::set('max_devices_free',            $request->max_devices_free ?? 1,            'system', 'integer');
        PlatformSettings::set('session_retention_days',      $request->session_retention_days ?? 30,     'system', 'integer');
        PlatformSettings::set('force_update_version',        $request->force_update_version ?? '',       'system');
        PlatformSettings::set('update_channel',              $request->update_channel ?? 'stable',       'system');
        PlatformSettings::set('session_recording_enabled',   $request->boolean('session_recording_enabled'), 'system', 'boolean');
        PlatformSettings::set('crash_reports_enabled',       $request->boolean('crash_reports_enabled'),     'system', 'boolean');
        PlatformSettings::set('analytics_enabled',           $request->boolean('analytics_enabled'),         'system', 'boolean');

        return response()->json(['status' => 'success', 'message' => 'System settings saved.']);
    }

    // ── POST /admin/settings/system/toggle ─────────────────────────────────────
    public function toggleSetting(Request $request): JsonResponse
    {
        $request->validate(['key' => 'required|string', 'value' => 'required|boolean']);

        $setting = \App\Models\PlatformSettings::where('key', $request->key)->first();
        $group   = $setting?->group ?? 'system';

        PlatformSettings::set($request->key, $request->boolean('value'), $group, 'boolean');

        return response()->json(['status' => 'success']);
    }

    // ── POST /admin/settings/system/clear-cache ────────────────────────────────
    public function clearCache(): JsonResponse
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
            \Illuminate\Support\Facades\Artisan::call('config:clear');
            \Illuminate\Support\Facades\Artisan::call('view:clear');
            return response()->json(['status' => 'success', 'message' => 'Application cache cleared.']);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // ── GET /admin/settings/system/backup ──────────────────────────────────────
    public function backupDatabase()
    {
        $dbPath = database_path('database.sqlite');
        if (!file_exists($dbPath)) {
            return response()->json(['status' => 'error', 'message' => 'SQLite database not found.'], 404);
        }
        $filename = 'wcl-backup-' . date('Y-m-d-His') . '.sqlite';
        return response()->download($dbPath, $filename);
    }

    // ── POST /admin/settings/billing/test-webhook ──────────────────────────────
    public function testWebhook(): JsonResponse
    {
        try {
            $webhookUrl = url('/api/billing/webhook');
            $response = \Illuminate\Support\Facades\Http::timeout(8)->post($webhookUrl, [
                'event' => 'test.webhook',
                'data'  => ['id' => 'test_' . uniqid()],
            ]);
            return response()->json([
                'status'  => $response->successful() ? 'success' : 'error',
                'code'    => $response->status(),
                'message' => $response->successful() ? 'Webhook endpoint is reachable.' : 'Webhook returned HTTP ' . $response->status(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ── POST /admin/settings/app-control ───────────────────────────────────────
    public function saveAppControl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'show_pricing'              => 'required|boolean',
            'pricing_coming_soon_text'  => 'nullable|string|max:200',
        ]);

        PlatformSettings::set('app_control_show_pricing', $validated['show_pricing'], 'app_control', 'boolean');
        PlatformSettings::set('app_control_pricing_coming_soon_text', $validated['pricing_coming_soon_text'] ?? 'Pricing plans coming soon', 'app_control');

        return response()->json(['status' => 'success', 'message' => 'App control settings saved.']);
    }

    // ── POST /admin/settings/app-control/toggle ────────────────────────────────
    // Called immediately on toggle click (no save button)
    public function toggleAppControl(Request $request): JsonResponse
    {
        $request->validate([
            'key'   => 'required|string|in:app_control_show_pricing',
            'value' => 'required|boolean',
        ]);

        PlatformSettings::set($request->key, $request->boolean('value'), 'app_control', 'boolean');

        return response()->json(['status' => 'success']);
    }

    // ── GET /api/app/settings/public ───────────────────────────────────────────
    // Public — used by the website pricing page
    public function publicAppSettings(): JsonResponse
    {
        return response()->json([
            'show_pricing'             => PlatformSettings::get('app_control_show_pricing', true),
            'pricing_coming_soon_text' => PlatformSettings::get('app_control_pricing_coming_soon_text', 'Pricing plans coming soon'),
        ]);
    }
}
