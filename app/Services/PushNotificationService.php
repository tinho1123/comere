<?php

namespace App\Services;

use App\Models\DriverPushSubscription;
use App\Models\PushSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService
{
    private WebPush $webPush;

    public function __construct()
    {
        $auth = [
            'VAPID' => [
                'subject' => config('app.url'),
                'publicKey' => config('webpush.vapid.public_key'),
                'privateKey' => config('webpush.vapid.private_key'),
            ],
        ];

        $this->webPush = new WebPush($auth);
        $this->webPush->setReuseVAPIDHeaders(true);
    }

    public function notifyCompany(int $companyId, string $title, string $body, string $url = '/admin'): void
    {
        $subscriptions = PushSubscription::where('company_id', $companyId)->get();

        $this->send($subscriptions, $title, $body, $url, function (string $endpoint) {
            PushSubscription::where('endpoint', $endpoint)->delete();
        }, ['company_id' => $companyId]);
    }

    public function notifyDriver(int $driverId, string $title, string $body, string $url = '/motoboy'): void
    {
        $subscriptions = DriverPushSubscription::where('driver_id', $driverId)->get();

        $this->send($subscriptions, $title, $body, $url, function (string $endpoint) {
            DriverPushSubscription::where('endpoint', $endpoint)->delete();
        }, ['driver_id' => $driverId]);
    }

    /**
     * @param  Collection<int, PushSubscription|DriverPushSubscription>  $subscriptions
     */
    private function send($subscriptions, string $title, string $body, string $url, callable $removeInvalid, array $logContext): void
    {
        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'icon' => '/icons/icon-192x192.png',
        ]);

        foreach ($subscriptions as $sub) {
            $subscription = Subscription::create([
                'endpoint' => $sub->endpoint,
                'publicKey' => $sub->public_key,
                'authToken' => $sub->auth_token,
                'contentEncoding' => 'aesgcm',
            ]);

            $this->webPush->queueNotification($subscription, $payload);
        }

        foreach ($this->webPush->flush() as $report) {
            if (! $report->isSuccess()) {
                Log::warning('Push notification failed', [
                    'endpoint' => $report->getEndpoint(),
                    'reason' => $report->getReason(),
                    ...$logContext,
                ]);

                // Remove invalid subscriptions (410 Gone or 404)
                $statusCode = $report->getResponse()?->getStatusCode();
                if (in_array($statusCode, [404, 410])) {
                    $removeInvalid($report->getEndpoint());
                }
            }
        }
    }
}
