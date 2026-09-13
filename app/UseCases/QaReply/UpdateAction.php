<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;

final class UpdateAction
{
    /**
     * @param array{body: string} $data
     */
    public function __invoke(QaReply $reply, array $data): QaReply
    {
        $reply->update([
            'body' => $data['body'],
        ]);

        return $reply;
    }
}
