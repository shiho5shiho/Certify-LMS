<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `GET /qa-board` の可視範囲・絞り込みを検証する。
 */
class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_does_not_see_threads_of_unpublished_certification(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $published = Certification::factory()->published()->create();
        $draft = Certification::factory()->draft()->create();
        $visible = QaThread::factory()->for($published)->create(['title' => '公開資格の質問']);
        $hidden = QaThread::factory()->for($draft)->create(['title' => '下書き資格の質問']);

        // Act
        $response = $this->actingAs($student)->get(route('qa-board.index'));

        // Assert
        $response->assertOk();
        $response->assertSee($visible->title);
        $response->assertDontSee($hidden->title);
    }

    public function test_coach_sees_only_assigned_certification_threads(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $assigned = Certification::factory()->published()->create();
        $other = Certification::factory()->published()->create();
        $assigned->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $visible = QaThread::factory()->for($assigned)->create(['title' => '担当資格の質問']);
        $hidden = QaThread::factory()->for($other)->create(['title' => '担当外資格の質問']);

        // Act
        $response = $this->actingAs($coach)->get(route('qa-board.index'));

        // Assert
        $response->assertOk();
        $response->assertSee($visible->title);
        $response->assertDontSee($hidden->title);
    }

    public function test_admin_moderation_index_shows_unpublished_certification_threads(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $draft = Certification::factory()->draft()->create();
        $thread = QaThread::factory()->for($draft)->create(['title' => '下書き資格の質問']);

        // Act
        $response = $this->actingAs($admin)->get(route('admin.qa-board.index'));

        // Assert
        $response->assertOk();
        $response->assertSee($thread->title);
    }

    public function test_keyword_matches_title(): void
    {
        // Arrange: タイトルのみにキーワードを含み、本文・回答には含まない
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $matching = QaThread::factory()->for($certification)->create([
            'title' => '二分探索木について',
            'body' => '無関係な内容です',
        ]);
        $notMatching = QaThread::factory()->for($certification)->create([
            'title' => '別の質問',
            'body' => 'こちらも無関係です',
        ]);

        // Act
        $response = $this->actingAs($student)->get(route('qa-board.index', ['keyword' => '二分探索木']));

        // Assert
        $response->assertOk();
        $response->assertSee($matching->title);
        $response->assertDontSee($notMatching->title);
    }

    public function test_keyword_matches_thread_body(): void
    {
        // Arrange: スレッド本文のみにキーワードを含む
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $matching = QaThread::factory()->for($certification)->create([
            'title' => '無関係なタイトル',
            'body' => '二分探索木の平均比較回数について教えてください',
        ]);
        $notMatching = QaThread::factory()->for($certification)->create([
            'title' => '別の質問',
            'body' => 'こちらも無関係です',
        ]);

        // Act
        $response = $this->actingAs($student)->get(route('qa-board.index', ['keyword' => '二分探索木']));

        // Assert
        $response->assertOk();
        $response->assertSee($matching->title);
        $response->assertDontSee($notMatching->title);
    }

    public function test_keyword_matches_reply_body(): void
    {
        // Arrange: タイトル・スレッド本文には含まれず、回答本文にのみキーワードを含む
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $matching = QaThread::factory()->for($certification)->create([
            'title' => '無関係なタイトル',
            'body' => '無関係な本文です',
        ]);
        QaReply::factory()->for($matching, 'thread')->create([
            'body' => '二分探索木のコードはこちらです',
        ]);
        $notMatching = QaThread::factory()->for($certification)->create([
            'title' => '別の質問',
            'body' => 'こちらも無関係です',
        ]);

        // Act
        $response = $this->actingAs($student)->get(route('qa-board.index', ['keyword' => '二分探索木']));

        // Assert
        $response->assertOk();
        $response->assertSee($matching->title);
        $response->assertDontSee($notMatching->title);
    }

    public function test_status_filter_narrows_to_resolved_threads(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $resolved = QaThread::factory()->for($certification)->resolved()->create(['title' => '解決済の質問']);
        $unresolved = QaThread::factory()->for($certification)->create(['title' => '未解決の質問']);

        // Act
        $response = $this->actingAs($student)->get(route('qa-board.index', ['status' => 'resolved']));

        // Assert
        $response->assertOk();
        $response->assertSee($resolved->title);
        $response->assertDontSee($unresolved->title);
    }

    public function test_certification_filter_narrows_to_selected_certification(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $target = Certification::factory()->published()->create();
        $other = Certification::factory()->published()->create();
        $matching = QaThread::factory()->for($target)->create(['title' => '対象資格の質問']);
        $notMatching = QaThread::factory()->for($other)->create(['title' => '別資格の質問']);

        // Act
        $response = $this->actingAs($student)->get(route('qa-board.index', ['certification_id' => $target->id]));

        // Assert
        $response->assertOk();
        $response->assertSee($matching->title);
        $response->assertDontSee($notMatching->title);
    }
}
