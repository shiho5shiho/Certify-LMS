<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI 相談の会話(1 スレッド)。オーナーは受講生本人のみ。
 *
 * enrollment_id は相談時の資格文脈、section_id は教材から開いた場合の教材文脈(どちらも任意)。
 * title_edited_by_user が true の会話は、AI によるタイトルの自動付け直しを行わない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignUlid('enrollment_id')
                ->nullable()
                ->constrained('enrollments')
                ->nullOnDelete();
            $table->foreignUlid('section_id')
                ->nullable()
                ->constrained('sections')
                ->nullOnDelete();
            $table->string('title', 100);
            $table->boolean('title_edited_by_user')->default(false);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            // 履歴サイドバーの新しい順表示 / 教材から開いた時の既存会話の検索
            $table->index(['user_id', 'last_message_at']);
            $table->index(['user_id', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_conversations');
    }
};
