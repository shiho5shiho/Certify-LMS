<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 質問掲示板のスレッド(質問投稿 1 件)。
 *
 * 投稿者は受講生のみ(user_id)。資格は編集時変更不可のため更新対象カラムから除外する運用とする。
 * status は unresolved / resolved の 2 値(App\Enums\QaThreadStatus)。resolved_at は解決操作時刻の記録用。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_threads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('certification_id')
                ->constrained('certifications')
                ->cascadeOnDelete();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('title', 200);
            $table->text('body');
            $table->string('status')->default('unresolved');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // 一覧の絞り込み(資格 / 解決状態)と新着順ソートの複合インデックス
            $table->index(['certification_id', 'status', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_threads');
    }
};
