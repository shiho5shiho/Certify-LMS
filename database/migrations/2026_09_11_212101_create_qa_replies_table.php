<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 質問掲示板の回答。投稿は受講生・コーチ(管理者は投稿不可、モデレーション削除のみ)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_replies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('qa_thread_id')
                ->constrained('qa_threads')
                ->cascadeOnDelete();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['qa_thread_id', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_replies');
    }
};
