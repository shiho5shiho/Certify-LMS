<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\QaThread;
use App\Models\User;
use App\Notifications\QaBoard\QaThreadPostedNotification;
use App\Services\NotificationDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * NotificationDispatchService の受信対象の絞り込みを検証する。
 */
class NotificationDispatchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_only_to_in_progress_students_and_coaches_excluding_actor_and_admin(): void
    {
        Notification::fake();

        $actor = User::factory()->student()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $admin = User::factory()->admin()->create();
        $invited = User::factory()->student()->invited()->create();
        $graduated = User::factory()->student()->graduated()->create();
        $withdrawn = User::factory()->student()->withdrawn()->create();
        $notification = new QaThreadPostedNotification(QaThread::factory()->create(['user_id' => $actor->id]));

        app(NotificationDispatchService::class)->send(
            [$actor, $student, $coach, $admin, $invited, $graduated, $withdrawn, null],
            $actor,
            $notification,
        );

        Notification::assertSentTo($student, QaThreadPostedNotification::class);
        Notification::assertSentTo($coach, QaThreadPostedNotification::class);
        foreach ([$actor, $admin, $invited, $graduated, $withdrawn] as $excluded) {
            Notification::assertNotSentTo($excluded, QaThreadPostedNotification::class);
        }
    }

    public function test_sends_via_database_and_mail(): void
    {
        Notification::fake();

        $actor = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $notification = new QaThreadPostedNotification(QaThread::factory()->create(['user_id' => $actor->id]));

        app(NotificationDispatchService::class)->send([$coach], $actor, $notification);

        Notification::assertSentTo(
            $coach,
            QaThreadPostedNotification::class,
            fn ($sent, array $channels) => $channels === ['database', 'mail'],
        );
    }

    public function test_sends_nothing_when_only_actor_is_candidate(): void
    {
        Notification::fake();

        $actor = User::factory()->student()->inProgress()->create();
        $notification = new QaThreadPostedNotification(QaThread::factory()->create(['user_id' => $actor->id]));

        app(NotificationDispatchService::class)->send([$actor], $actor, $notification);

        Notification::assertNothingSent();
    }

    public function test_sends_to_all_valid_candidates_when_actor_is_null(): void
    {
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $notification = new QaThreadPostedNotification(QaThread::factory()->create(['user_id' => $student->id]));

        app(NotificationDispatchService::class)->send([$student, $coach], null, $notification);

        Notification::assertSentTo($student, QaThreadPostedNotification::class);
        Notification::assertSentTo($coach, QaThreadPostedNotification::class);
    }
}
