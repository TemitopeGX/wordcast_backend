<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\SubscriptionPlan;

class SubscriptionPlanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'plans'  => SubscriptionPlan::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:100',
            'slug'          => 'required|string|unique:subscription_plans,slug|alpha_dash',
            'price_monthly' => 'nullable|numeric|min:0',
            'price_yearly'  => 'nullable|numeric|min:0',
            'currency'      => 'required|string|size:3',
            'seat_limit'    => 'required|integer|min:1',
            'trial_days'    => 'integer|min:0',
            'features'      => 'required|array',
            'features.*'    => 'string',
            'sort_order'    => 'integer|min:0',
        ]);

        $plan = SubscriptionPlan::create($validated);

        return response()->json(['status' => 'success', 'plan' => $plan, 'message' => 'Plan created.']);
    }

    public function update(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:100',
            'price_monthly' => 'nullable|numeric|min:0',
            'price_yearly'  => 'nullable|numeric|min:0',
            'currency'      => 'required|string|size:3',
            'seat_limit'    => 'required|integer|min:1',
            'trial_days'    => 'integer|min:0',
            'features'      => 'required|array',
            'features.*'    => 'string',
            'is_active'     => 'sometimes|boolean',
        ]);

        $plan->update($validated);

        return response()->json(['status' => 'success', 'plan' => $plan->fresh(), 'message' => 'Plan updated.']);
    }

    public function toggle(SubscriptionPlan $plan): JsonResponse
    {
        $plan->update(['is_active' => !$plan->is_active]);
        return response()->json(['status' => 'success', 'is_active' => $plan->is_active]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate(['order' => 'required|array', 'order.*' => 'integer']);
        foreach ($request->order as $sortOrder => $id) {
            SubscriptionPlan::where('id', $id)->update(['sort_order' => $sortOrder]);
        }
        return response()->json(['status' => 'success']);
    }

    public function destroy(SubscriptionPlan $plan): JsonResponse
    {
        if ($plan->slug === 'free') {
            return response()->json(['status' => 'error', 'message' => 'Cannot delete the Free plan.'], 422);
        }
        $plan->delete();
        return response()->json(['status' => 'success']);
    }
}
