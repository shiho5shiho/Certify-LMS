<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            // 同一コーチ × 同一時刻の二重予約を DB レベルで禁止する(status を問わず効かせる)
            $table->unique(['coach_id', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            // 外部キー coach_id 用の INDEX を先に作り直してから UNIQUE を削除する
            $table->index('coach_id');
            $table->dropUnique(['coach_id', 'scheduled_at']);
        });
    }
};
