<?php

namespace App\Services\Notification;

use App\Contracts\NotificationServiceInterface;
use Illuminate\Support\Facades\Log;

class LogNotificationService implements NotificationServiceInterface
{
    public function send(string $targetPhone, string $message): bool
    {
        Log::info("[Notification Log] Sending SMS/WhatsApp to {$targetPhone}: {$message}");
        return true;
    }
}

