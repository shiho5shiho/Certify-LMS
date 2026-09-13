<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QaReplyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 質問掲示板の回答 1 件。投稿は受講生・コーチ(管理者は投稿不可)。
 */
class QaReply extends Model
{
    /** @use HasFactory<QaReplyFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'qa_thread_id',
        'user_id',
        'body',
    ];

    /**
     * @return BelongsTo<QaThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(QaThread::class, 'qa_thread_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
