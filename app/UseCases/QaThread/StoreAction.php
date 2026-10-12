<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\QaBoard\QaThreadPostedNotification;
use App\Services\NotificationDispatchService;

final class StoreAction
{
    public function __construct(
        private readonly NotificationDispatchService $notifier,
    ) {}

    /**
     * @param array{certification_id: string, title: string, body: string} $data
     */
    public function __invoke(User $student, array $data): QaThread
    {
        $thread = QaThread::create([
            'certification_id' => $data['certification_id'],
            'user_id' => $student->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'status' => QaThreadStatus::Unresolved,
        ]);

        $thread->load(['user', 'certification.coaches']);
        $this->notifier->send(
            $thread->certification->coaches,
            $student,
            new QaThreadPostedNotification($thread),
        );

        return $thread;
    }
}
