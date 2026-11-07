<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 追加面談パックの購入(Stripe 決済)の記録。
 *
 * 決済画面(Checkout Session)の作成時に処理中で作成し、Stripe からの Webhook で完了 / 失敗に更新する。
 * amount / quantity は購入時点の価格・面談回数の控えで、後から面談パックを変更しても書き換えない。
 * 返金はステータスとしてのみ用意し、Stripe で返金しても本機能では自動で変更しない。
 *
 * 関連: User(購入者) / MeetingPack(購入した面談パック)
 */
class Payment extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'meeting_pack_id',
        'stripe_checkout_session_id',
        'amount',
        'quantity',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'status' => PaymentStatus::class,
        'amount' => 'integer',
        'quantity' => 'integer',
        'paid_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<MeetingPack, $this>
     */
    public function meetingPack(): BelongsTo
    {
        return $this->belongsTo(MeetingPack::class);
    }
}
