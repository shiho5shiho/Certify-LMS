<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 面談に、コーチの Google カレンダーへ登録した予定のイベント ID を追加する。
 *
 * キャンセル時に Google 側の予定を削除するために使う。
 * コーチが未連携のとき、または登録に失敗したときは null。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('google_event_id')->nullable()->after('meeting_url_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('google_event_id');
        });
    }
};
