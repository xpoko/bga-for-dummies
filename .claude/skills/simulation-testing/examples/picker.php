<?php
// Pure simulation-action picker. Lifted from Waddle's
// modules/php/Simulation.php — adapt the action constants and the
// $choices construction rules to your game.
//
// Key properties to preserve:
//   - Static method; no $this, no DB, no framework dependencies.
//   - $randFn is injected with a default of mt_rand, so unit tests can
//     fix the index and get deterministic output.
//   - The "no legal action" branch throws — don't silently return a
//     placeholder. If your action gathering says nothing is legal,
//     that's a bug worth surfacing.
//   - For non-last players, listing $choices[] = ACTION_PLACE_ONE twice
//     gives placement 2x the probability of scout when both are legal —
//     simpler than weighted-random math and easy to read.
//   - The isLast branch SHORT-CIRCUITS: when a placement is legal we ONLY
//     offer PLACE_ALL (no scout). The rulebook says the last player must
//     place if they can; the picker must enforce this, not roll dice for
//     it. Scout is only ever offered as the fallback when no ice remains.

declare(strict_types=1);

namespace Bga\Games\YourGame;

final class Simulation
{
    public const ACTION_SCOUT      = 'scout';
    public const ACTION_PLACE_ONE  = 'placeOne';
    public const ACTION_PLACE_ALL  = 'placeAll';

    public static function pickAction(
        bool $canScout,
        bool $canPlace,
        bool $isLast,
        ?callable $randFn = null
    ): string {
        $choices = [];
        if ($isLast) {
            // Rule: the last player must place if any ice remains.
            // Don't even offer scout when placement is legal.
            if ($canPlace) {
                $choices[] = self::ACTION_PLACE_ALL;
            } elseif ($canScout) {
                $choices[] = self::ACTION_SCOUT;
            }
        } else {
            if ($canScout) {
                $choices[] = self::ACTION_SCOUT;
            }
            if ($canPlace) {
                // Weight 2x: placement is the more interesting code path
                // to exercise; we'd rather see lots of placements per game.
                $choices[] = self::ACTION_PLACE_ONE;
                $choices[] = self::ACTION_PLACE_ONE;
            }
        }
        if (empty($choices)) {
            throw new \RuntimeException('No legal simulation action');
        }
        $randFn = $randFn ?? static fn(int $min, int $max): int => mt_rand($min, $max);
        $idx = $randFn(0, count($choices) - 1);
        return $choices[$idx];
    }
}
