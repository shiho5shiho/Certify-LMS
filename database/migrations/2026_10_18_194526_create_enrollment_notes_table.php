<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 受講登録単位のコーチメモ(業務記録)。削除は物理削除で履歴は残さない。
 * 受講解除(論理削除)ではメモを残し、画面での表示・操作を Policy で止める(PM 確認済)。
 * 親 Enrollment が物理削除された場合は連動して削除する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_notes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('enrollment_id')
                ->constrained('enrollments')
                ->cascadeOnDelete();
            $table->foreignUlid('author_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['enrollment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_notes');
    }
};
