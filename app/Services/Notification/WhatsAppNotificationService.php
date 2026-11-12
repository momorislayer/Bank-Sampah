<?php

namespace App\Services\Notification;

use App\Contracts\NotificationServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppNotificationService implements NotificationServiceInterface
{
    /**
     * Integrasi provider WhatsApp generic (Fonnte / Wablas / custom gateway).
     */
    public function send(string $targetPhone, string $message): bool
    {
        $endpoint = config('services.whatsapp.endpoint');
        $apiKey = config('services.whatsapp.api_key');

        if (! $endpoint || ! $apiKey) {
            Log::warning("[WhatsApp Service] API Key atau Endpoint belum dikonfigurasi di config/services.php");
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $apiKey,
            ])->post($endpoint, [
                'target' => $targetPhone,
                'message' => $message,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("[WhatsApp Service Error] " . $e->getMessage());
            return false;
        }
    }
}

