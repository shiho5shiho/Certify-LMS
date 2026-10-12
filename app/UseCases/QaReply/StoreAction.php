<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\QaBoard\QaReplyReceivedNotification;
use App\Services\NotificationDispatchService;

final class StoreAction
{
    public function __construct(
        private readonly NotificationDispatchService $notifier,
    ) {}

    /**
     * @param array{body: string} $data
     */
    public function __invoke(User $author, QaThread $thread, array $data): QaReply
    {
        $reply = $thread->replies()->create([
            'user_id' => $author->id,
            'body' => $data['body'],
        ]);

        $reply->setRelation('thread', $thread->loadMissing('user'));
        $reply->load('user');
        $this->notifier->send(
            [$thread->user],
            $author,
            new QaReplyReceivedNotification($reply),
        );

        return $reply;
    }
}
