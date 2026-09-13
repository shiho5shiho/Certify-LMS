<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;

final class StoreAction
{
    /**
     * @param array{body: string} $data
     */
    public function __invoke(User $author, QaThread $thread, array $data): QaReply
    {
        return $thread->replies()->create([
            'user_id' => $author->id,
            'body' => $data['body'],
        ]);
    }
}
