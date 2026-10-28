<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * コーチの Google カレンダー連携情報を表す Model。
 *
 * 面談設定タブの連携状態表示(calendar_id / connected_at)と、Google API 呼び出し時のトークン管理に使う。
 * レコードの有無がそのまま「連携中 / 未連携」を表す。
 *
 * 関連: User(belongsTo)
 */
class GoogleCalendarCredential extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'calendar_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'connected_at',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'connected_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
