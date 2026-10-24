<?php

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
            // null = 未送信 / 日時あり = 送信済み(二重配信防止に使う)
            $table->dateTime('eve_reminded_at')->nullable()->after('completed_at');
            $table->dateTime('one_hour_reminded_at')->nullable()->after('eve_reminded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['eve_reminded_at', 'one_hour_reminded_at']);
        });
    }
};
