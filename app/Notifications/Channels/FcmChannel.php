<?php

namespace App\Notifications\Channels;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Kreait\Firebase\Messaging\WebPushConfig;

class FcmChannel
{
    /**
     * Send a multicast push to every device token the user has registered.
     * Tokens FCM reports as invalid/unregistered are pruned.
     */
    public function send(User $notifiable, Notification $notification): void
    {
        $tokens = $notifiable->deviceTokens()->pluck('token')->all();

        if ($tokens === []) {
            return;
        }

        /** @var array{title: string, body: string, link: string} $payload */
        $payload = $notification->toFcm($notifiable);

        $message = CloudMessage::new()
            ->withNotification(FcmNotification::create($payload['title'], $payload['body']))
            ->withData(['link' => $payload['link']]);

        // Click-through target for browser notifications (FCM requires https).
        $absoluteLink = url($payload['link']);

        if (str_starts_with($absoluteLink, 'https://')) {
            $message = $message->withWebPushConfig(WebPushConfig::fromArray([
                'fcm_options' => ['link' => $absoluteLink],
            ]));
        }

        try {
            // Resolved lazily so environments without Firebase credentials
            // (e.g. tests, which also never have device tokens) never boot it.
            $report = app(Messaging::class)->sendMulticast($message, $tokens);
        } catch (\Throwable $e) {
            Log::error('FCM multicast failed: '.$e->getMessage());

            return;
        }

        $stale = [...$report->unknownTokens(), ...$report->invalidTokens()];

        if ($stale !== []) {
            DeviceToken::whereIn('token', $stale)->delete();
        }
    }
}
