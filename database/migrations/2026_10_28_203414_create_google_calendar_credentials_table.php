<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * コーチの Google カレンダー連携情報テーブル(1 コーチ : 1 連携)。
 *
 * OAuth で取得したトークンを保持し、空き枠判定・予定の登録 / 削除で利用する。
 * calendar_id は連携時に Google API から取得したメインカレンダーの ID を保存する。
 * トークンの暗号化保存は本チケットのスコープ外(本番運用時の暗号化は README に記載)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_calendar_credentials', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('calendar_id');
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('token_expires_at');
            $table->timestamp('connected_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendar_credentials');
    }
};
