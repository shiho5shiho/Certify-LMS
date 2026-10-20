<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 管理者が受講生に一斉配信したお知らせの配信履歴。配信後の編集・取消はしない(要件: 配信の不可逆性)。
 * target_certification_id / target_user_id は、配信対象タイプが資格指定 / ユーザー指定のときだけ入る。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title', 200);
            $table->text('body');
            $table->string('target_type', 20);
            $table->foreignUlid('target_certification_id')->nullable()->constrained('certifications')->restrictOnDelete();
            $table->foreignUlid('target_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('dispatched_count');
            $table->timestamp('dispatched_at');
            $table->foreignUlid('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('dispatched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
