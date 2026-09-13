<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use App\Models\User;

final class StoreAction
{
    /**
     * @param array{certification_id: string, title: string, body: string} $data
     */
    public function __invoke(User $student, array $data): QaThread
    {
        return QaThread::create([
            'certification_id' => $data['certification_id'],
            'user_id' => $student->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'status' => QaThreadStatus::Unresolved,
        ]);
    }
}
