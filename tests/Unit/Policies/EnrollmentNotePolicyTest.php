<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use App\Policies\EnrollmentNotePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnrollmentNotePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_any_and_create_allowed_for_assigned_coach_and_admin(): void
    {
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();
        $this->attachCoach($enrollment, $coach);
        $policy = app(EnrollmentNotePolicy::class);
        $admin = User::factory()->admin()->create();

        $this->assertTrue($policy->viewAny($coach, $enrollment));
        $this->assertTrue($policy->create($coach, $enrollment));
        $this->assertTrue($policy->viewAny($admin, $enrollment));
        $this->assertTrue($policy->create($admin, $enrollment));
    }

    public function test_view_any_and_create_denied_for_unassigned_coach_and_owner_student(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $coach = User::factory()->coach()->create();
        $policy = app(EnrollmentNotePolicy::class);

        $this->assertFalse($policy->viewAny($coach, $enrollment));
        $this->assertFalse($policy->create($coach, $enrollment));
        $this->assertFalse($policy->viewAny($student, $enrollment));
        $this->assertFalse($policy->create($student, $enrollment));
    }

    public function test_view_any_and_create_denied_for_trashed_enrollment(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->create();
        $enrollment->delete();
        $policy = app(EnrollmentNotePolicy::class);

        $this->assertFalse($policy->viewAny($admin, $enrollment));
        $this->assertFalse($policy->create($admin, $enrollment));
    }

    public function test_update_and_delete_allowed_only_for_author_and_admin(): void
    {
        $author = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();
        $this->attachCoach($enrollment, $author);
        $this->attachCoach($enrollment, $otherCoach);
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->byAuthor($author)->create();
        $admin = User::factory()->admin()->create();
        $policy = app(EnrollmentNotePolicy::class);

        $this->assertTrue($policy->update($author, $note));
        $this->assertTrue($policy->delete($author, $note));
        $this->assertFalse($policy->update($otherCoach, $note));
        $this->assertFalse($policy->delete($otherCoach, $note));
        $this->assertTrue($policy->update($admin, $note));
        $this->assertTrue($policy->delete($admin, $note));
    }

    private function attachCoach(Enrollment $enrollment, User $coach): void
    {
        $enrollment->certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }
}
