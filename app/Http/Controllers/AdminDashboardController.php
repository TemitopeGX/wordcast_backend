<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function overview(Request $request)
    {
        $now = now();
        $startOfThisMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        // Users
        $totalUsers = User::count();
        $lastMonthUsers = User::where('created_at', '<', $startOfThisMonth)->count();
        $userGrowth = $lastMonthUsers > 0 ? (($totalUsers - $lastMonthUsers) / $lastMonthUsers) * 100 : 0;

        // Licenses
        $totalLicensesThisMonth = DB::table('licenses')->where('created_at', '>=', $startOfThisMonth)->count();
        $totalLicensesLastMonth = DB::table('licenses')->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();
        $licensesGrowth = $totalLicensesLastMonth > 0 ? (($totalLicensesThisMonth - $totalLicensesLastMonth) / $totalLicensesLastMonth) * 100 : 0;

        $inactiveLicenses = DB::table('licenses')->where('is_active', false)->count();
        $inactiveLastMonth = DB::table('licenses')->where('is_active', false)->where('updated_at', '<', $startOfThisMonth)->count();
        $inactiveGrowth = $inactiveLastMonth > 0 ? (($inactiveLicenses - $inactiveLastMonth) / $inactiveLastMonth) * 100 : 0;

        // Revenue
        $totalRevenue = DB::table('payments')->where('status', 'success')->sum('amount');
        $lastMonthRevenue = DB::table('payments')->where('status', 'success')->where('created_at', '<', $startOfThisMonth)->sum('amount');
        $revenueGrowth = $lastMonthRevenue > 0 ? (($totalRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 : 0;

        // Plans
        $licensesByPlan = DB::table('licenses')
            ->select('plan', DB::raw('count(*) as count'))
            ->groupBy('plan')
            ->get()
            ->pluck('count', 'plan')
            ->toArray();

        // Performance Chart (last 6 months)
        $performance = collect();
        for ($i = 5; $i >= 0; $i--) {
            $start = $now->copy()->subMonths($i)->startOfMonth();
            $end = $now->copy()->subMonths($i)->endOfMonth();
            
            $sales = DB::table('licenses')->whereBetween('created_at', [$start, $end])->count();
            $revenue = DB::table('payments')->where('status', 'success')->whereBetween('created_at', [$start, $end])->sum('amount');
            
            $performance->push([
                'date' => $start->format('M'),
                'sales' => $sales,
                'revenue' => round($revenue / 1000, 1) // in K
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'users' => [
                    'total' => $totalUsers,
                    'last_month' => $lastMonthUsers,
                    'growth' => round($userGrowth, 1)
                ],
                'licenses' => [
                    'new_this_month' => $totalLicensesThisMonth,
                    'new_last_month' => $totalLicensesLastMonth,
                    'new_growth' => round($licensesGrowth, 1),
                    'inactive' => $inactiveLicenses,
                    'inactive_last_month' => $inactiveLastMonth,
                    'inactive_growth' => round($inactiveGrowth, 1)
                ],
                'revenue' => [
                    'total' => $totalRevenue,
                    'last_month' => $lastMonthRevenue,
                    'growth' => round($revenueGrowth, 1)
                ],
                'plans' => [
                    'free' => $licensesByPlan['free'] ?? 0,
                    'pro' => $licensesByPlan['pro'] ?? 0,
                    'campus' => $licensesByPlan['campus'] ?? 0,
                ],
                'performance' => $performance
            ]
        ]);
    }
}
