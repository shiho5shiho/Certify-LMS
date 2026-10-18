<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\EnrollmentNote;

/**
 * 受講生メモの編集。本文のみ更新し、作成者は変えない。
 */
final class UpdateAction
{
    /**
     * @param array{body: string} $data
     */
    public function __invoke(EnrollmentNote $note, array $data): EnrollmentNote
    {
        $note->update([
            'body' => $data['body'],
        ]);

        return $note;
    }
}
