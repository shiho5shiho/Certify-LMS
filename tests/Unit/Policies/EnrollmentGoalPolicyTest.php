<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use App\Policies\EnrollmentGoalPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentGoalPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_allowed_for_owning_student(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student)->create();

        $this->assertTrue(app(EnrollmentGoalPolicy::class)->create($student, $enrollment));
    }

    public function test_create_denied_for_trashed_enrollment(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $enrollment->delete();

        $this->assertFalse(app(EnrollmentGoalPolicy::class)->create($student, $enrollment));
    }

    public function test_create_denied_for_other_student(): void
    {
        $student = User::factory()->student()->create();
        $otherEnrollment = Enrollment::factory()->create();

        $this->assertFalse(app(EnrollmentGoalPolicy::class)->create($student, $otherEnrollment));
    }

    public function test_create_denied_for_coach_and_admin(): void
    {
        $enrollment = Enrollment::factory()->create();
        $policy = app(EnrollmentGoalPolicy::class);

        $this->assertFalse($policy->create(User::factory()->coach()->create(), $enrollment));
        $this->assertFalse($policy->create(User::factory()->admin()->create(), $enrollment));
    }

    public function test_goal_operations_allowed_for_owning_student(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();
        $policy = app(EnrollmentGoalPolicy::class);

        $this->assertTrue($policy->update($student, $goal));
        $this->assertTrue($policy->delete($student, $goal));
        $this->assertTrue($policy->markAchieved($student, $goal));
        $this->assertTrue($policy->unmarkAchieved($student, $goal));
    }

    public function test_goal_operations_denied_for_other_student(): void
    {
        $student = User::factory()->student()->create();
        $goal = EnrollmentGoal::factory()->create();
        $policy = app(EnrollmentGoalPolicy::class);

        $this->assertFalse($policy->update($student, $goal));
        $this->assertFalse($policy->delete($student, $goal));
        $this->assertFalse($policy->markAchieved($student, $goal));
        $this->assertFalse($policy->unmarkAchieved($student, $goal));
    }

    public function test_goal_operations_denied_for_coach_and_admin(): void
    {
        $goal = EnrollmentGoal::factory()->create();
        $policy = app(EnrollmentGoalPolicy::class);

        foreach ([User::factory()->coach()->create(), User::factory()->admin()->create()] as $staff) {
            $this->assertFalse($policy->update($staff, $goal));
            $this->assertFalse($policy->delete($staff, $goal));
            $this->assertFalse($policy->markAchieved($staff, $goal));
            $this->assertFalse($policy->unmarkAchieved($staff, $goal));
        }
    }
}
