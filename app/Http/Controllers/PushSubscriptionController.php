<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PushSubscriptionController extends Controller
{
    /**
     * Store (or update) the browser push subscription for the current user/device.
     */
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string'],
        ]);

        $request->user()->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            // Default to the modern encoding Apple/Chromium require; without it
            // the server library falls back to the deprecated 'aesgcm'.
            $data['contentEncoding'] ?? 'aes128gcm',
        );

        return response()->noContent();
    }

    /**
     * Remove the given push subscription for the current user/device.
     */
    public function destroy(Request $request): Response
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string'],
        ]);

        $request->user()->deletePushSubscription($data['endpoint']);

        return response()->noContent();
    }
}
