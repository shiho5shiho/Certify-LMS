<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;

/**
 * 受講生メモの追加。作成者(author)は操作したコーチ / admin。
 */
final class StoreAction
{
    /**
     * @param array{body: string} $data
     */
    public function __invoke(Enrollment $enrollment, User $author, array $data): EnrollmentNote
    {
        return $enrollment->notes()->create([
            'author_id' => $author->id,
            'body' => $data['body'],
        ]);
    }
}
