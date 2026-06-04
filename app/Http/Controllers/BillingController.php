<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\License;
use App\Models\Coupon;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BillingController extends Controller
{
    // ── Initiate Paystack payment ──────────────────────────────────
    public function initiate(Request $request)
    {
        $request->validate([
            'plan'         => 'required|string',
            'coupon_code'  => 'nullable|string|max:50',
        ]);

        $planSlug = $request->plan;

        // Look up the plan from the database
        $plan = SubscriptionPlan::where('slug', $planSlug)->where('is_active', true)->first();

        if (!$plan) {
            return response()->json(['message' => 'Invalid plan.'], 422);
        }

        // Custom / contact-sales plans (price_monthly is null)
        if ($plan->price_monthly === null) {
            return response()->json([
                'message'  => 'Please contact sales for this plan.',
                'redirect' => env('FRONTEND_URL') . '/contact',
            ]);
        }

        $user       = $request->user();
        $amountNGN  = (float) $plan->price_monthly;

        // ── Apply coupon if provided ───────────────────────────────
        $couponId = null;
        if ($request->coupon_code) {
            $coupon = $this->validateCoupon($request->coupon_code, $planSlug);
            if (!$coupon['valid']) {
                return response()->json(['message' => $coupon['message']], 422);
            }
            $couponModel = $coupon['coupon'];
            $couponId    = $couponModel->id;

            if ($couponModel->type === 'percent') {
                $amountNGN = $amountNGN * (1 - ($couponModel->value / 100));
            } else {
                $amountNGN = max(0, $amountNGN - $couponModel->value);
            }
        }

        // Convert to kobo (Paystack requires smallest currency unit)
        $amountKobo = (int) round($amountNGN * 100);

        if ($amountKobo === 0) {
            // Fully discounted — upgrade directly without payment
            $this->applyPlanToUser($user, $plan, 1);
            if ($couponId) {
                Coupon::where('id', $couponId)->increment('uses_count');
            }
            return response()->json([
                'message'    => 'Plan upgraded successfully with full coupon discount.',
                'free_upgrade' => true,
                'plan'       => $planSlug,
            ]);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('PAYSTACK_SECRET_KEY'),
            'Content-Type'  => 'application/json',
        ])->post('https://api.paystack.co/transaction/initialize', [
            'email'        => $user->email,
            'amount'       => $amountKobo,
            'currency'     => 'NGN',
            'reference'    => 'WC-' . strtoupper(uniqid()),
            'callback_url' => env('FRONTEND_URL') . '/dashboard/billing/callback',
            'metadata'     => [
                'user_id'    => $user->id,
                'plan'       => $planSlug,
                'plan_id'    => $plan->id,
                'coupon_id'  => $couponId,
                'months'     => 1,
                'cancel_action' => env('FRONTEND_URL') . '/dashboard',
            ],
        ]);

        if (!$response->successful()) {
            Log::error('Paystack initiation failed', ['response' => $response->json()]);
            return response()->json(['message' => 'Payment initiation failed. Please try again.'], 502);
        }

        $data = $response->json('data');
        return response()->json([
            'message'           => 'Payment initialized',
            'authorization_url' => $data['authorization_url'],
            'reference'         => $data['reference'],
        ]);
    }

    // ── Validate coupon code (public endpoint) ─────────────────────
    public function applyCoupon(Request $request)
    {
        $request->validate([
            'code'      => 'required|string',
            'plan_slug' => 'required|string',
        ]);

        $plan = SubscriptionPlan::where('slug', $request->plan_slug)->where('is_active', true)->first();
        if (!$plan) {
            return response()->json(['valid' => false, 'message' => 'Invalid plan.'], 422);
        }

        $result = $this->validateCoupon($request->code, $request->plan_slug);

        if (!$result['valid']) {
            return response()->json(['valid' => false, 'message' => $result['message']], 422);
        }

        $coupon    = $result['coupon'];
        $original  = (float) $plan->price_monthly;
        $discounted = $coupon->type === 'percent'
            ? $original * (1 - ($coupon->value / 100))
            : max(0, $original - $coupon->value);

        return response()->json([
            'valid'           => true,
            'discount_type'   => $coupon->type,
            'discount_value'  => (float) $coupon->value,
            'original_price'  => $original,
            'discounted_price' => round($discounted, 2),
            'saving'          => round($original - $discounted, 2),
        ]);
    }

    // ── Cancel subscription ────────────────────────────────────────
    public function cancel(Request $request)
    {
        $user    = $request->user();
        $license = $user->license;

        if (!$license || $user->plan === 'free') {
            return response()->json(['message' => 'No active paid subscription found.'], 422);
        }

        // Downgrade to free — license stays but plan reverts
        $this->applyPlanToUser($user, null, 1); // null = free plan

        Log::info("User #{$user->id} cancelled subscription. Downgraded to free.");

        return response()->json([
            'status'  => 'success',
            'message' => 'Subscription cancelled. You have been moved to the Free plan.',
        ]);
    }

    // ── Paystack webhook ───────────────────────────────────────────
    public function webhook(Request $request)
    {
        $signature = $request->header('x-paystack-signature');
        $secret    = env('PAYSTACK_SECRET_KEY');
        $body      = $request->getContent();

        if ($signature !== hash_hmac('sha512', $body, $secret)) {
            Log::warning('Invalid Paystack webhook signature received.');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $event = $request->json('event');
        $data  = $request->json('data');

        match ($event) {
            'charge.success'               => $this->handleChargeSuccess($data),
            'subscription.disable'         => $this->handleSubscriptionDisabled($data),
            'invoice.payment_failed'       => $this->handlePaymentFailed($data),
            default                        => null,
        };

        return response()->json(['status' => 'ok']);
    }

    // ── Verify after redirect ──────────────────────────────────────
    public function verify(Request $request)
    {
        $request->validate(['reference' => 'required|string']);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('PAYSTACK_SECRET_KEY'),
        ])->get("https://api.paystack.co/transaction/verify/{$request->reference}");

        if (!$response->successful()) {
            return response()->json(['message' => 'Verification failed.'], 502);
        }

        $data   = $response->json('data');
        $status = $data['status'] ?? 'failed';

        if ($status !== 'success') {
            return response()->json(['message' => 'Payment was not successful.'], 402);
        }

        $userId  = $data['metadata']['user_id'] ?? null;
        $planSlug = $data['metadata']['plan']    ?? null;
        $months  = $data['metadata']['months']   ?? 1;
        $couponId = $data['metadata']['coupon_id'] ?? null;
        $user    = $userId ? User::find($userId) : null;

        if ($user && $planSlug) {
            $plan = SubscriptionPlan::where('slug', $planSlug)->first();
            $this->applyPlanToUser($user, $plan, $months);

            // Increment coupon usage
            if ($couponId) {
                Coupon::where('id', $couponId)->increment('uses_count');
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Payment verified. Plan upgraded.',
            'plan'    => $planSlug,
        ]);
    }

    // ── Private: apply plan to user + license ──────────────────────
    private function applyPlanToUser(User $user, ?SubscriptionPlan $plan, int $months = 1): void
    {
        $isFree    = ($plan === null);
        $planSlug  = $isFree ? 'free' : $plan->slug;
        $seatLimit = $isFree ? 1 : (int)($plan->seat_limit ?? 1);
        $expiresAt = $isFree ? null : now()->addMonths($months);

        $license = $user->license;

        if (!$license) {
            $license = $user->license()->create([
                'license_key' => 'WCL-' . strtoupper(\Illuminate\Support\Str::uuid()),
                'plan'        => $planSlug,
                'seat_limit'  => $seatLimit,
                'expires_at'  => $expiresAt,
                'is_active'   => true,
            ]);
        } else {
            $currentExpiry = (!$isFree && $license->expires_at && $license->expires_at->isFuture())
                ? $license->expires_at
                : ($isFree ? null : now());

            $license->update([
                'plan'       => $planSlug,
                'seat_limit' => $seatLimit,
                'expires_at' => $isFree ? null : $currentExpiry->addMonths($months),
                'is_active'  => true,
            ]);
        }

        $user->update(['plan' => $planSlug]);
    }

    // ── Private: webhook charge.success ───────────────────────────
    private function handleChargeSuccess(array $data): void
    {
        $userId    = $data['metadata']['user_id'] ?? null;
        $planSlug  = $data['metadata']['plan']    ?? null;
        $months    = $data['metadata']['months']  ?? 1;
        $couponId  = $data['metadata']['coupon_id'] ?? null;

        if (!$userId || !$planSlug) return;

        $user = User::find($userId);
        if (!$user) return;

        $plan = SubscriptionPlan::where('slug', $planSlug)->first();
        $this->applyPlanToUser($user, $plan, $months);

        if ($couponId) {
            Coupon::where('id', $couponId)->increment('uses_count');
        }

        // Record payment
        Payment::create([
            'user_id'            => $user->id,
            'paystack_reference' => $data['reference'],
            'amount'             => $data['amount'] / 100,
            'currency'           => $data['currency'] ?? 'NGN',
            'status'             => 'success',
            'metadata'           => json_encode($data['metadata'] ?? []),
            'paid_at'            => now(),
        ]);

        Log::info("User #{$userId} upgraded to plan: {$planSlug}");
    }

    private function handleSubscriptionDisabled(array $data): void
    {
        $customerEmail = $data['customer']['email'] ?? null;
        if (!$customerEmail) return;

        $user = User::where('email', $customerEmail)->first();
        if (!$user) return;

        $this->applyPlanToUser($user, null, 0); // downgrade to free
        Log::info("Subscription disabled for user: {$customerEmail}");
    }

    private function handlePaymentFailed(array $data): void
    {
        $customerEmail = $data['customer']['email'] ?? null;
        Log::warning("Payment failed for: {$customerEmail}");
    }

    // ── Private: validate coupon ───────────────────────────────────
    private function validateCoupon(string $code, string $planSlug): array
    {
        $coupon = Coupon::where('code', strtoupper(trim($code)))
                        ->where('is_active', true)
                        ->first();

        if (!$coupon) {
            return ['valid' => false, 'message' => 'Invalid or inactive coupon code.'];
        }

        if ($coupon->expires_at && Carbon::parse($coupon->expires_at)->isPast()) {
            return ['valid' => false, 'message' => 'This coupon has expired.'];
        }

        if ($coupon->max_uses !== null && $coupon->uses_count >= $coupon->max_uses) {
            return ['valid' => false, 'message' => 'This coupon has reached its usage limit.'];
        }

        // Check plan restrictions
        if ($coupon->applicable_plans && !in_array($planSlug, $coupon->applicable_plans)) {
            return ['valid' => false, 'message' => 'This coupon is not valid for the selected plan.'];
        }

        return ['valid' => true, 'coupon' => $coupon];
    }
}
