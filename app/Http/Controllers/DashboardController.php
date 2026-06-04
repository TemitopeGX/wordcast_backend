<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Mock stats for WordCast
        return response()->json([
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'church_name' => $user->church_name,
                'plan' => $user->plan,
            ],
            'stats' => [
                'active_screens' => 2,
                'total_lyrics' => 124,
                'recent_verses' => 45,
                'storage_used' => '1.2 GB',
                'storage_limit' => '10 GB',
            ],
            'recent_activity' => [
                ['id' => 1, 'type' => 'Screen connected', 'target' => 'Main Sanctuary', 'time' => '2 hours ago'],
                ['id' => 2, 'type' => 'Bible import', 'target' => 'KJV (Amplified)', 'time' => 'Yesterday'],
                ['id' => 3, 'type' => 'New song added', 'target' => 'Way Maker', 'time' => '3 days ago'],
            ]
        ]);
    }
}
