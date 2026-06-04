<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\PlatformSettings;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Default plans
        if (SubscriptionPlan::count() === 0) {
            SubscriptionPlan::create([
                'name'          => 'Free',
                'slug'          => 'free',
                'price_monthly' => 0,
                'price_yearly'  => 0,
                'currency'      => 'NGN',
                'seat_limit'    => 1,
                'trial_days'    => 0,
                'features'      => ['1 device', 'KJV Bible only', 'Basic themes', 'Standard support'],
                'sort_order'    => 0,
                'is_active'     => true,
            ]);

            SubscriptionPlan::create([
                'name'          => 'Pro',
                'slug'          => 'pro',
                'price_monthly' => 5000,
                'price_yearly'  => 50000,
                'currency'      => 'NGN',
                'seat_limit'    => 5,
                'trial_days'    => 14,
                'features'      => ['Up to 5 devices', 'All Bible translations', 'Full theme editor', 'ProContent access', 'NDI output', 'Sermon Studio', 'Priority support'],
                'sort_order'    => 1,
                'is_active'     => true,
            ]);

            SubscriptionPlan::create([
                'name'          => 'Campus',
                'slug'          => 'campus',
                'price_monthly' => 0, // contact sales
                'price_yearly'  => 0,
                'currency'      => 'NGN',
                'seat_limit'    => 999,
                'trial_days'    => 30,
                'features'      => ['Unlimited devices', 'All Pro features', 'Multi-campus management', 'Custom branding', 'Dedicated support', 'SLA guarantee'],
                'sort_order'    => 2,
                'is_active'     => true,
            ]);
        }

        // Default settings
        PlatformSettings::set('platform_name', 'WordCast Live', 'general');
        PlatformSettings::set('support_email', 'support@wordcastlive.site', 'general');
        PlatformSettings::set('app_description', 'Platform for multi-campus live broadcasting and sermon enhancement tools.', 'general');
        PlatformSettings::set('maintenance_mode', false, 'general', 'boolean');

        PlatformSettings::set('active_ai_provider', 'openai', 'ai_engine');
        PlatformSettings::set('openai_model', 'gpt-4o-mini', 'ai_engine');
        PlatformSettings::set('gemini_model', 'gemini-1.5-flash', 'ai_engine');
        PlatformSettings::set('openai_api_key', '', 'ai_engine', 'encrypted');
        PlatformSettings::set('gemini_api_key', '', 'ai_engine', 'encrypted');
        PlatformSettings::set('ai_features', [
            'sermon_studio'    => true,
            'phrase_detection' => true,
            'voice_commands'   => true,
            'lyrics_import'    => true,
        ], 'ai_engine', 'json');

        PlatformSettings::set('billing_currency', 'NGN', 'billing');
        PlatformSettings::set('billing_test_mode', true, 'billing', 'boolean');
        PlatformSettings::set('paystack_public_key', '', 'billing');
        PlatformSettings::set('paystack_secret_key', '', 'billing', 'encrypted');
        PlatformSettings::set('invoice_prefix', 'WCL-', 'billing');
        PlatformSettings::set('vat_percentage', 0, 'billing', 'integer');
        PlatformSettings::set('company_name', 'WordCast Live Ltd', 'billing');
        PlatformSettings::set('company_address', '123 Broadcast Way, Lagos, Nigeria', 'billing');

        PlatformSettings::set('grace_period_days', 3, 'system', 'integer');
        PlatformSettings::set('license_check_interval_hours', 24, 'system', 'integer');
        PlatformSettings::set('max_devices_free', 1, 'system', 'integer');
        PlatformSettings::set('session_retention_days', 30, 'system', 'integer');
        PlatformSettings::set('force_update_version', '1.0.0', 'system');
        PlatformSettings::set('update_channel', 'stable', 'system');
        PlatformSettings::set('session_recording_enabled', true, 'system', 'boolean');
        PlatformSettings::set('crash_reports_enabled', true, 'system', 'boolean');
        PlatformSettings::set('analytics_enabled', true, 'system', 'boolean');
    }
}

