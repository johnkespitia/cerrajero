<?php
namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class WebhookNotificationService
{
    public function sendNotification($notificationData)
    {
        $webhookUrl =  env('WEBHOOK_NOTIFICATION_URL', ''); // Replace with actual URL

        $response = Http::post($webhookUrl, $notificationData);

        if ($response->successful()) {
            Log::info('Webhook notification sent successfully. Response: ' . $response->getBody());
        } else {
            Log::error('Failed to send notification to webhook. Response: ' . $response->getBody());
            throw new \Exception('Failed to send notification to webhook');
        }
    }
}
