<?php

declare(strict_types=1);

namespace App\UseCases\GoogleCalendar;

use App\Exceptions\Mentoring\GoogleCalendarRequestFailedException;
use App\Models\GoogleCalendarCredential;
use App\Models\User;
use App\Services\Contracts\GoogleCalendarServiceContract;

/**
 * コーチ本人の Google カレンダー連携を保存するユースケース。
 *
 * 認可コードをトークンに交換し、連携情報を保存する。既に連携済みなら上書きする(1 コーチ : 1 連携)。
 * user_id は常に認証ユーザー本人で固定する(他人の連携を作るリスクを排除)。
 */
final class ConnectAction
{
    public function __construct(
        private readonly GoogleCalendarServiceContract $googleCalendar,
    ) {}

    /**
     * @throws GoogleCalendarRequestFailedException
     */
    public function __invoke(User $coach, string $code): GoogleCalendarCredential
    {
        $token = $this->googleCalendar->exchangeCode($code);

        return GoogleCalendarCredential::updateOrCreate(
            ['user_id' => $coach->id],
            [
                'calendar_id' => $token['calendar_id'],
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'],
                'token_expires_at' => $token['token_expires_at'],
                'connected_at' => now(),
            ],
        );
    }
}
