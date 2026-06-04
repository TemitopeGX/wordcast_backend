<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Coupon;

class CouponController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'coupons' => Coupon::orderBy('created_at', 'desc')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'             => 'required|string|unique:coupons,code|alpha_dash|max:50',
            'type'             => 'required|in:percent,fixed',
            'value'            => 'required|numeric|min:0',
            'max_uses'         => 'nullable|integer|min:1',
            'expires_at'       => 'nullable|date|after:today',
            'applicable_plans' => 'nullable|array',
        ]);

        if ($validated['type'] === 'percent' && $validated['value'] > 100) {
            return response()->json(['status' => 'error', 'message' => 'Percent discount cannot exceed 100%.'], 422);
        }

        $coupon = Coupon::create($validated);

        return response()->json(['status' => 'success', 'coupon' => $coupon, 'message' => 'Coupon created.']);
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $validated = $request->validate([
            'code'             => 'required|string|alpha_dash|max:50|unique:coupons,code,' . $coupon->id,
            'type'             => 'required|in:percent,fixed',
            'value'            => 'required|numeric|min:0',
            'max_uses'         => 'nullable|integer|min:1',
            'expires_at'       => 'nullable|date',
            'applicable_plans' => 'nullable|array',
            'is_active'        => 'sometimes|boolean',
        ]);

        $coupon->update($validated);

        return response()->json(['status' => 'success', 'coupon' => $coupon->fresh(), 'message' => 'Coupon updated.']);
    }

    public function toggle(Coupon $coupon): JsonResponse
    {
        $coupon->update(['is_active' => !$coupon->is_active]);
        return response()->json(['status' => 'success', 'is_active' => $coupon->is_active]);
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $coupon->delete();
        return response()->json(['status' => 'success']);
    }
}
