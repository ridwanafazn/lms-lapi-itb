<?php

namespace App\Models\Concerns;

use App\Models\User;

/**
 * Single source of truth for "does this user's membership unlock this item?".
 *
 * Used by Program and Batch so the two never drift apart again.
 */
trait HasMembershipRequirement
{
    /** Higher rank unlocks every lower requirement. */
    public const LEVEL_RANK = [
        'starter' => 0,
        'standard' => 1,
        'pro' => 2,
    ];

    /**
     * Explicit plan requirement (if set) always wins over the level rule.
     * Items with level "none" are paid-only, never included in a membership.
     */
    public function isFreeForUser(?User $user): bool
    {
        if (! $user || ! $user->hasActiveMembership()) {
            return false;
        }

        $explicitPlanId = $this->getAttribute('required_membership_plan_id');
        if ($explicitPlanId) {
            return $user->membership_plan_id === $explicitPlanId;
        }

        return self::userLevelSatisfies($user, (string) $this->getAttribute('min_membership_level'));
    }

    public static function userLevelSatisfies(User $user, string $requiredLevel): bool
    {
        $requiredLevel = strtolower(trim($requiredLevel));

        if ($requiredLevel === '' || $requiredLevel === 'none') {
            return false;
        }

        $userLevel = strtolower((string) ($user->membershipPlan->level ?? 'starter'));

        $requiredRank = self::LEVEL_RANK[$requiredLevel] ?? PHP_INT_MAX;
        $userRank = self::LEVEL_RANK[$userLevel] ?? 0;

        return $userRank >= $requiredRank;
    }

    /**
     * Levels whose plans are included for a given membership level.
     *
     * @return array<int, string>
     */
    public static function levelsIncludedIn(?string $membershipLevel): array
    {
        return match (strtolower((string) $membershipLevel)) {
            'pro' => ['standard', 'pro'],
            'standard' => ['standard'],
            default => [],
        };
    }
}
