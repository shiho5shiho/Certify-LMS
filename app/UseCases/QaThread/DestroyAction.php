<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Models\QaThread;

/**
 * 質問の削除。配下の回答は外部キーの cascadeOnDelete で連動削除される。
 */
final class DestroyAction
{
    public function __invoke(QaThread $thread): void
    {
        $thread->delete();
    }
}
