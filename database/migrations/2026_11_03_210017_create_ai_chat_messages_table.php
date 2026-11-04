<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI 相談の会話に含まれるメッセージ(受講生の発言 / AI の応答)。
 *
 * model / input_tokens / output_tokens / response_time_ms / error_detail は運用観測用のメタデータで、
 * AI の応答にのみ記録する。受講生の画面や JSON には出さない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ai_chat_conversation_id')
                ->constrained('ai_chat_conversations')
                ->cascadeOnDelete();
            $table->string('role');
            $table->string('status');
            $table->text('content');
            $table->string('model')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->text('error_detail')->nullable();
            $table->timestamps();

            $table->index(['ai_chat_conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
    }
};
