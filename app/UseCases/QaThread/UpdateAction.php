<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Models\QaThread;

/**
 * 質問の編集。資格(certification_id)は変更対象に含めない(仕様上、編集時変更不可)。
 */
final class UpdateAction
{
    /**
     * @param array{title: string, body: string} $data
     */
    public function __invoke(QaThread $thread, array $data): QaThread
    {
        $thread->update([
            'title' => $data['title'],
            'body' => $data['body'],
        ]);

        return $thread;
    }
}
