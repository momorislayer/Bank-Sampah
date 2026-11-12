<?php

namespace App\Contracts;

interface NotificationServiceInterface
{
    /**
     * Kirim notifikasi via WhatsApp/SMS ke nomor tujuan.
     */
    public function send(string $targetPhone, string $message): bool;
}

