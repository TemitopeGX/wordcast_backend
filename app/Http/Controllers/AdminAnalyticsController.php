<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $now = now();
        
        // 1. Devices by OS
        $osDataRaw = DB::table('license_devices')
            ->select('os', DB::raw('count(*) as count'))
            ->groupBy('os')
            ->get();
            
        // Clean up OS names if they are null or empty
        $osData = $osDataRaw->map(function ($item) {
            $osName = empty($item->os) ? 'Unknown' : $item->os;
            return [
                'os' => $osName,
                'count' => $item->count
            ];
        });
            
        // 2. Subscriptions breakdown
        $subscriptionsRaw = DB::table('subscriptions')
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();
            
        $subscriptions = [
            'active' => 0,
            'cancelled' => 0,
            'expired' => 0
        ];
        foreach ($subscriptionsRaw as $s) {
            if (array_key_exists($s->status, $subscriptions)) {
                $subscriptions[$s->status] = $s->count;
            }
        }
            
        // 3. 12-month Revenue Chart
        $revenueData = collect();
        for ($i = 11; $i >= 0; $i--) {
            $start = $now->copy()->subMonths($i)->startOfMonth();
            $end = $now->copy()->subMonths($i)->endOfMonth();
            
            $rev = DB::table('payments')
                ->where('status', 'success')
                ->whereBetween('created_at', [$start, $end])
                ->sum('amount');
                
            $revenueData->push([
                'month' => $start->format('M Y'),
                'revenue' => round($rev, 2)
            ]);
        }
        
        // 4. Totals
        $totalDevices = DB::table('license_devices')->count();
        $totalSubscriptions = DB::table('subscriptions')->count();
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'devices_by_os' => $osData,
                'subscriptions' => $subscriptions,
                'revenue_trend' => $revenueData,
                'totals' => [
                    'devices' => $totalDevices,
                    'subscriptions' => $totalSubscriptions
                ]
            ]
        ]);
    }
}
