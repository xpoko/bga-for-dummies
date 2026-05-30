# Active-Hex Activation

**One-liner.** One water hex is "active" at any moment. Players place penguins only on ice hexes adjacent to it. When the round ends, the marker advances to the next water hex (or pool). A pool's fish VP is awarded the instant the last empty ice hex adjacent to it is filled — that may happen mid-round on a placement, or at round-end when the marker advances.

## Source files

- [TokenManager.php:42](../modules/php/TokenManager.php#L42) — initial seed: `active_hex` at `token_state = 1`
- [BoardManager.php:119-129](../modules/php/BoardManager.php#L119-L129) — `getActiveHexData` (resolves marker → hex row)
- [BoardManager.php:159-216](../modules/php/BoardManager.php#L159-L216) — `getPoolContaining` (BFS over water-water adjacency) + `getEmptyIceAdjacentToPool`
- [waddle.ts:1107-1137](../src/waddle.ts#L1107-L1137) — `notif_roundEnd` (marker moves, view re-centers, marbles re-render)
- [waddle.ts:704-706](../src/waddle.ts#L704-L706) — `placeTokens` resolves `active_hex` via `waterHexKeyForNumber`
- [dbmodel.sql:59-70](../dbmodel.sql#L59-L70) — `hex` table; note the `activated` column

## Rulebook → code

The rulebook ships activation numbers printed on every water hex (1, 2, 3, …). Play starts with the lowest-numbered water hex active. Once the round ends (no ice left adjacent to the active hex), activation advances to the next water hex with empty adjacent ice. A pool scores its fish VP the moment its closure condition holds — when no empty ice hex remains adjacent to any water hex in the pool — even if some of the pool's numbered water hexes have not yet had their round.

That made "pool closure" a first-class concept, and the `activated` flag took on a dual role. Two activation models we considered:

1. **Per-water-hex activation.** `active_hex` walks `1 → 2 → 3 → ...` blindly. The "pool finished" trigger is recomputed by querying which water hexes in a pool have had penguins placed adjacent to them.
2. **Per-pool activation.** `active_hex` still walks by number, but every water hex carries an `activated` flag, flipped pool-wide the moment that pool is scored. The next active hex is "smallest `hex_number` where `activated = 0`".

We picked (2). [`PoolScorer::markPoolActivated`](../modules/php/PoolScorer.php) flips every water hex in a pool to `activated=1` the moment that pool scores, regardless of which entry point (mid-round closure in PlayerTurn or marker advance in RoundEnd) fired. The flag now means two things at once: *"the activation walker should skip this hex"* and *"this hex's pool has already been scored, so don't score it twice"*. That second meaning is what makes the two entry points idempotent — whichever closes the pool first scores it, and the other no-ops. The flag isn't strictly necessary for the walker — you could derive it from a BFS — but it's cheap, and it's load-bearing for the idempotency guard.

The active hex is stored in the universal token schema: `token_key = 'active_hex'`, `token_state = <hex_number>`. The client renders it as a marker pinned inside the corresponding hex div, resolved via `waterHexKeyForNumber` ([waddle.ts:782-790](../src/waddle.ts#L782-L790)).

## Design choices

- **`hex_number` is the activation key, not a generic identifier.** Water hex_keys are `w<number>` ([dbmodel.sql:50-51](../dbmodel.sql#L50-L51)); since `tile_id` is reused as `hex_number` at setup ([BoardManager.php:71-95](../modules/php/BoardManager.php#L71-L95)), every water hex's key, number, and tile are the same integer. The constraint is enforced by the layout: `Material::LAYOUTS` is one tile per slot.
- **Pools are computed, not stored.** A pool is the connected component of water hexes containing the active hex. We don't materialize pools in the DB; [`getPoolContaining`](../modules/php/BoardManager.php#L159-L200) BFSes them on demand. They're stable for the whole game (layout doesn't change), but recomputing is fast and avoids a denormalized table that has to be kept in sync with `hex`.
- **The marker travels by moving the same DOM node.** When the round ends, `notif_roundEnd` re-parents `#active_hex` into the destination hex div and re-applies the `.active-hex` glow ([waddle.ts:1117-1124](../src/waddle.ts#L1117-L1124)). CSS animations make the marker slide to its new location. The client also calls `centerOnActiveHex` to scroll the new hex into view.
- **Mirror Match flips the activation direction.** In half 2, water hexes activate in *descending* order (highest hex_number first). The `tile_direction` game-state value is `+1` in half 1, `-1` in half 2; round-end uses it to pick the next hex. `MidGameReset` resets `activated=0` across the board and seeds the marker at the highest hex_number ([waddle.ts:1267-1270](../src/waddle.ts#L1267-L1270)).

## Snags & refinements

- **`getPoolContaining` had to handle non-adjacent water hexes.** Some boards have isolated single-water-hex tiles. The BFS correctly returns just `[seed]` for those; the round-end logic doesn't special-case lone pools.
- **`hex_number` integer-vs-string drift.** The marker token state is an int in the DB but a string-typed JSON value in some BGA framework callbacks. The `placeTokens` lookup coerces via `Object.values(hexes).find(h => h.hex_number === num)`, which means `num` has to already be a number when called — wrap in `Number()` if you're passing through a notification payload.
- **`active_track` is separate from `active_hex`.** Two unrelated "active" values: `active_track` (0 or 1 — which turn track is draining this round) and `active_hex` (1..N — which water hex is current). They both get re-seeded on Mirror Match but neither derives from the other. Kept as distinct game-state values so the wire format for `notif_roundEnd` can carry one or the other without ambiguity ([waddle.ts:1107-1137](../src/waddle.ts#L1107-L1137)).
- **The marker has its own tooltip.** "Active hex marker — penguins must be placed on ice hexes adjacent to this." Surfaced in [`setupTooltips`](../src/waddle.ts#L916-L924) so a new player who hovers it gets the constraint without reading the rules.

## Cross-refs

- [[hex-grid]] — water vs ice, hex_key naming.
- [[token-placement]] — `getEmptyAdjacentIceHexes` reads from the active hex.
- [[turn-order]] — round-end can also flip the active track; both fire from `notif_roundEnd`.
