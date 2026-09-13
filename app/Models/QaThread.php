<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use Database\Factories\QaThreadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 質問掲示板のスレッド(質問投稿 1 件)。
 *
 * 投稿は受講生のみ。資格は作成後変更不可。
 * 可視範囲は資格の公開状態とロールで決まる(scopeVisibleTo 参照)。
 */
class QaThread extends Model
{
    /** @use HasFactory<QaThreadFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'certification_id',
        'user_id',
        'title',
        'body',
        'status',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'status' => QaThreadStatus::class,
        'resolved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Certification, $this>
     */
    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 回答は投稿順(古い順)。会話としての読み進めを想定。
     *
     * @return HasMany<QaReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(QaReply::class)->oldest();
    }

    /**
     * ロールに応じた可視範囲の絞り込み。
     *
     * - admin: 全件(公開停止資格のスレッドも含む、モデレーション用)
     * - coach: 担当資格(certification_coach_assignments) かつ資格が公開中のもののみ
     * - student: 資格が公開中のもの(Enrollment の有無は問わない)
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            UserRole::Admin => $query,
            UserRole::Coach => $query->whereHas(
                'certification',
                fn (Builder $q) => $q->where('status', CertificationStatus::Published->value)
                    ->whereHas('coaches', fn (Builder $q2) => $q2->where('users.id', $user->id)),
            ),
            UserRole::Student => $query->whereHas(
                'certification',
                fn (Builder $q) => $q->where('status', CertificationStatus::Published->value),
            ),
        };
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if ($status === null || $status === '') {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function scopeCertification(Builder $query, ?string $certificationId): Builder
    {
        if ($certificationId === null || $certificationId === '') {
            return $query;
        }

        return $query->where('certification_id', $certificationId);
    }

    /**
     * タイトル・スレッド本文・回答本文を対象としたキーワード検索(部分一致)。
     */
    public function scopeKeyword(Builder $query, ?string $keyword): Builder
    {
        if ($keyword === null || trim($keyword) === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($keyword) {
            $q->where('title', 'LIKE', '%'.$keyword.'%')
                ->orWhere('body', 'LIKE', '%'.$keyword.'%')
                ->orWhereHas(
                    'replies',
                    fn (Builder $r) => $r->where('body', 'LIKE', '%'.$keyword.'%'),
                );
        });
    }
}
