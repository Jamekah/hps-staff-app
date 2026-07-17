<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceTokenController extends Controller
{
    /**
     * Register (or re-associate) a device token for the current user.
     * A token moving between users re-associates to whoever is logged in.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', Rule::in(['web', 'android'])],
        ]);

        DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $validated['platform'],
                'updated_at' => now(),
            ]
        )->touch();

        return response()->json(['status' => 'registered']);
    }

    /**
     * Remove a device token (called on logout so a shared or handed-over
     * device stops receiving the previous user's pushes).
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        DeviceToken::where('token', $validated['token'])->delete();

        return response()->json(['status' => 'removed']);
    }
}
