<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Plan;
use App\Models\User;
use App\Policies\PlanPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PlanPolicy の ability × Role マトリクス検証。
 * 全 ability（viewAny/view/create/update/delete/publish/archive/unarchive）が admin のみ true を返すことを網羅する。
 */
class PlanPolicyTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('adminOnlyAbilityMatrix')]
    public function test_admin_only_abilities_match_role_expectation(
        string $actingRole,
        string $policyMethod,
        bool $expected,
    ): void {
        // Arrange
        $actor = User::factory()->{$actingRole}()->create();
        $plan = Plan::factory()->published()->create();
        $policy = new PlanPolicy;

        // Act
        $result = in_array($policyMethod, ['create', 'viewAny'], true)
            ? $policy->{$policyMethod}($actor)
            : $policy->{$policyMethod}($actor, $plan);

        // Assert
        $this->assertSame(
            $expected,
            $result,
            "{$actingRole} が {$policyMethod} で " . ($expected ? 'true' : 'false') . ' を返すはず',
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function adminOnlyAbilityMatrix(): array
    {
        $abilities = ['viewAny', 'view', 'create', 'update', 'delete', 'publish', 'archive', 'unarchive'];
        $roles = ['admin' => true, 'coach' => false, 'student' => false];

        $cases = [];
        foreach ($roles as $role => $expected) {
            foreach ($abilities as $ability) {
                $caseKey = $expected
                    ? "{$role} は {$ability} を実行できる"
                    : "{$role} は {$ability} を実行できない";
                $cases[$caseKey] = [$role, $ability, $expected];
            }
        }

        return $cases;
    }
}
