<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\Announcement\AdminAnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * お知らせ配信の受信者・配信履歴・バリデーション・認可を検証する。
 * 受信者は PM 確認どおり(受講中の受講生のみ、資格指定は学習中の受講登録のみ)であることを確認する。
 */
class StoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = User::factory()->admin()->inProgress()->create();
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'メンテナンスのお知らせ',
            'body' => "10/20 2:00〜4:00 はご利用いただけません。\nご迷惑をおかけします。",
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ], $overrides);
    }

    public function test_all_students_reaches_only_in_progress_students(): void
    {
        $inProgress = User::factory()->student()->inProgress()->create();
        $graduated = User::factory()->student()->graduated()->create();
        $invited = User::factory()->student()->invited()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.announcements.store'), $this->payload());

        $announcement = Announcement::sole();
        $response->assertRedirect(route('admin.announcements.show', $announcement));
        $this->assertSame(1, $announcement->dispatched_count);
        $this->assertSame($this->admin->id, $announcement->created_by_user_id);
        $this->assertNotNull($announcement->dispatched_at);

        Notification::assertSentTo(
            $inProgress,
            AdminAnnouncementNotification::class,
            fn ($notification) => $notification->toArray($inProgress) === [
                'notification_type' => 'admin_announcement',
                'title' => 'メンテナンスのお知らせ',
                'message' => "10/20 2:00〜4:00 はご利用いただけません。\nご迷惑をおかけします。",
                'url' => route('notifications.show', $notification->id),
            ],
        );
        Notification::assertNotSentTo([$graduated, $invited, $coach, $this->admin], AdminAnnouncementNotification::class);
    }

    public function test_certification_reaches_only_students_learning_that_certification(): void
    {
        $certification = Certification::factory()->published()->create();
        $otherCertification = Certification::factory()->published()->create();

        $learning = User::factory()->student()->inProgress()->create();
        Enrollment::factory()->for($learning)->for($certification)->learning()->create();
        $passed = User::factory()->student()->inProgress()->create();
        Enrollment::factory()->for($passed)->for($certification)->passed()->create();
        $failed = User::factory()->student()->inProgress()->create();
        Enrollment::factory()->for($failed)->for($certification)->failed()->create();
        $unenrolled = User::factory()->student()->inProgress()->create();
        Enrollment::factory()->for($unenrolled)->for($certification)->learning()->create()->delete();
        $other = User::factory()->student()->inProgress()->create();
        Enrollment::factory()->for($other)->for($otherCertification)->learning()->create();

        $this->actingAs($this->admin)->post(route('admin.announcements.store'), $this->payload([
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => $certification->id,
        ]));

        $announcement = Announcement::sole();
        $this->assertSame($certification->id, $announcement->target_certification_id);
        $this->assertSame(1, $announcement->dispatched_count);
        Notification::assertSentTo($learning, AdminAnnouncementNotification::class);
        Notification::assertNotSentTo([$passed, $failed, $unenrolled, $other], AdminAnnouncementNotification::class);
    }

    public function test_user_reaches_only_the_selected_student_and_ignores_other_target_field(): void
    {
        $target = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $this->actingAs($this->admin)->post(route('admin.announcements.store'), $this->payload([
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => $target->id,
            'target_certification_id' => $certification->id,
        ]));

        $announcement = Announcement::sole();
        $this->assertSame($target->id, $announcement->target_user_id);
        $this->assertNull($announcement->target_certification_id);
        Notification::assertSentTo($target, AdminAnnouncementNotification::class);
        Notification::assertNotSentTo($other, AdminAnnouncementNotification::class);
    }

    public function test_no_recipients_is_rejected_without_history(): void
    {
        $certification = Certification::factory()->published()->create();

        $this->actingAs($this->admin)
            ->from(route('admin.announcements.create'))
            ->post(route('admin.announcements.store'), $this->payload([
                'target_type' => AnnouncementTargetType::Certification->value,
                'target_certification_id' => $certification->id,
            ]))
            ->assertRedirect(route('admin.announcements.create'))
            ->assertSessionHas('error', '配信対象の受講生がいないため、配信できませんでした。');

        $this->assertDatabaseCount('announcements', 0);
        Notification::assertNothingSent();
    }

    public function test_required_fields_and_max_length_are_validated(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.announcements.store'), [])
            ->assertSessionHasErrors(['title', 'body', 'target_type']);

        $this->actingAs($this->admin)
            ->post(route('admin.announcements.store'), $this->payload([
                'title' => str_repeat('あ', 201),
                'body' => str_repeat('あ', 5001),
            ]))
            ->assertSessionHasErrors(['title', 'body']);

        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_target_fields_are_required_for_their_type(): void
    {
        $graduated = User::factory()->student()->graduated()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.announcements.store'), $this->payload([
                'target_type' => AnnouncementTargetType::Certification->value,
            ]))
            ->assertSessionHasErrors(['target_certification_id']);

        $this->actingAs($this->admin)
            ->post(route('admin.announcements.store'), $this->payload([
                'target_type' => AnnouncementTargetType::User->value,
                'target_user_id' => $graduated->id,
            ]))
            ->assertSessionHasErrors(['target_user_id']);

        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_non_admin_cannot_dispatch(): void
    {
        User::factory()->student()->inProgress()->create();

        foreach ([User::factory()->coach()->inProgress()->create(), User::factory()->student()->inProgress()->create()] as $user) {
            $this->actingAs($user)
                ->post(route('admin.announcements.store'), $this->payload())
                ->assertForbidden();
        }

        $this->assertDatabaseCount('announcements', 0);
    }
}
