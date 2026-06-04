<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Onboarding;

class OnboardingController extends Controller
{
    /**
     * GET /onboarding
     * Returns the current user's onboarding progress.
     * Creates a row automatically if one doesn't exist yet.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        $progress = Onboarding::firstOrCreate(
            ['user_id' => $user->id],
            [
                'account_created'  => true,
                'app_downloaded'   => false,
                'app_logged_in'    => false,
                'cloud_sync_setup' => false,
            ]
        );

        return response()->json($progress);
    }

    /**
     * PATCH /onboarding
     * Marks one or more steps as complete.
     * Body: { "app_downloaded": true }  (only send the fields you want to update)
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'app_downloaded'   => 'sometimes|boolean',
            'app_logged_in'    => 'sometimes|boolean',
            'cloud_sync_setup' => 'sometimes|boolean',
        ]);

        $progress = Onboarding::firstOrCreate(
            ['user_id' => $user->id],
            ['account_created' => true]
        );

        // Only allow marking steps as done, never unmark them
        foreach ($data as $key => $value) {
            if ($value === true) {
                $progress->$key = true;
            }
        }

        $progress->save();

        return response()->json($progress);
    }
}
