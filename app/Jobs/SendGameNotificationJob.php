<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendGameNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $type,
        public readonly string $message,
        public readonly array $payload = [],
    ) {}

    public function handle(NotificationService $notifications): void
    {
        if ($this->userId <= 0 || $this->message === '') {
            return;
        }

        if (method_exists($notifications, 'notifyUser')) {
            $notifications->notifyUser($this->userId, $this->type, $this->message, $this->payload);
        }
    }
}
