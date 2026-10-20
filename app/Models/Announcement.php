<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnnouncementTargetType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 管理者お知らせの配信履歴。配信と同時に作成し、以後は更新しない。
 *
 * 関連: Certification(資格指定の対象) / User(ユーザー指定の対象、配信者)
 */
class Announcement extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'title',
        'body',
        'target_type',
        'target_certification_id',
        'target_user_id',
        'dispatched_count',
        'dispatched_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'target_type' => AnnouncementTargetType::class,
        'dispatched_count' => 'integer',
        'dispatched_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Certification, $this>
     */
    public function targetCertification(): BelongsTo
    {
        return $this->belongsTo(Certification::class, 'target_certification_id');
    }

    /**
     * 配信履歴は監査用のため、退会(論理削除)したユーザーも表示する。
     *
     * @return BelongsTo<User, $this>
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->withTrashed();
    }
}
