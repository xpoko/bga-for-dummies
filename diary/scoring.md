# Scoring

**One-liner.** Two scoring streams. *Waddle VP*: every connected same-color penguin group ("waddle") scores triangularly with its size (1→1, 2→3, 3→6, …). *Fish VP*: when a pool of water hexes closes (all its tiles activated), the colors with the most adjacent penguins take its fish in rank order; ties share a slot. Doubles count as 2 penguins. All math lives in a pure, side-effect-free [`Scoring`](../modules/php/Scoring.php) class so tests can drive it with synthetic boards.

## Source files

- [Scoring.php](../modules/php/Scoring.php) — the whole pure-functions module
- [Material.php:30-31, 187-195](../modules/php/Material.php#L30-L195) — `WADDLE_VP` / `WADDLE_VP_2P` lookup tables + `getWaddleVP`
- [BoardManager.php:17-25](../modules/php/BoardManager.php#L17-L25) — `isAdjacent` (the predicate every BFS uses)
- [PoolScorer.php](../modules/php/PoolScorer.php) — owns the live "lake closed" pipeline; called from both PlayerTurn (mid-round closure) and RoundEnd (marker advance)
- [States/PlayerTurn.php](../modules/php/States/PlayerTurn.php) — mid-round entry point: every committed placement asks PoolScorer whether the touched pool(s) just closed
- [States/RoundEnd.php](../modules/php/States/RoundEnd.php) — marker-advance entry point: when a water hex activates, its pool is offered to PoolScorer
- [States/GameEnd.php:213-295](../modules/php/States/GameEnd.php#L213-L295) — end-of-game path; consumes `waddleSizesPerPlayer` + `fishPerLakePerPlayer` for the scoreboard
- [tests/ScoringBreakdownTest.php](../tests/ScoringBreakdownTest.php), [tests/FishingPoolTest.php](../tests/FishingPoolTest.php) — drive every public function with synthetic placements

## Rulebook → code

> *"At the end of the game, each player scores the sum of triangular numbers of the sizes of their **waddles** (groups of same-color penguins connected edge-to-edge). When a pool of water hexes is completely closed, each player ranks their penguins adjacent to that pool; the leader takes the highest-numbered fish, second place takes the next, and so on. Ties share equally."*

Two distinct algorithms drop out of that:

| Rule | Code |
|---|---|
| "connected same-color penguins" | per-color BFS on the cube-adjacency predicate ([`waddleSizesByColor`](../modules/php/Scoring.php#L44-L103)) |
| "triangular score per group" | table lookup ([`Material::getWaddleVP`](../modules/php/Material.php#L187-L195)) — explicit table beats `n*(n+1)/2` because the 2p variant extends past 8 and we want the same call shape everywhere |
| "pool = connected water hexes" | another BFS, same adjacency predicate ([`groupWaterHexesIntoPools`](../modules/php/Scoring.php#L455-L494)) |
| "rank penguins adjacent to the pool" | tally per color (single=1, double=2), `arsort`, walk the sorted fish list ([`scoreFishingPools`](../modules/php/Scoring.php#L148-L266) for totals, [`computeFishingPoolBreakdown`](../modules/php/Scoring.php#L283-L406) for the live-scoring rank groups) |
| "ties share equally" | tied colors get the same `fish_vp` and a *new* fish slot is consumed only on a fresh rank — see the snag below |
| "2-player variant: leader takes the whole pool" | branch on `$playerCount === 2` inside both fish functions |

The waddle BFS counts *weight*, not penguins-as-tokens: each token contributes 1 if it's a single (`penguin_<color>_s_*`) or 2 if it's a double (`penguin_<color>_d_*`), keyed off `str_contains($t['token_key'], '_d_')` ([Scoring.php:63-64](../modules/php/Scoring.php#L63-L64)). The same weight rule applies to the fish-rank tally ([Scoring.php:221](../modules/php/Scoring.php#L221)).

## Design choices

- **Pure functions, plain-array inputs.** Every public method on `Scoring` takes arrays and returns arrays — no DB, no framework, no `$this->game->...`. The two state classes that *use* scoring ([`GameEnd`](../modules/php/States/GameEnd.php) and [`RoundEnd`](../modules/php/States/RoundEnd.php)) own the DB queries and pass results in. This is why [ScoringBreakdownTest](../tests/ScoringBreakdownTest.php) can exercise waddle grouping with literal placements and no test harness. We considered putting these methods on `BoardManager` (which already owns `isAdjacent`), but that class is DB-bound; mixing pure and impure on one class invited "just one quick query" creep.
- **One BFS predicate, reused by both algorithms.** Both waddle grouping and pool grouping iterate "for every unvisited hex, BFS over neighbors that satisfy `isAdjacent`". We inlined two copies rather than extracting a shared generic-BFS helper — the two loops differ in what they accumulate (penguin weight vs water-hex row), and the indirection of a callback-driven BFS made the call sites harder to read than the duplication.
- **Two fish helpers, one per consumer.** [`scoreFishingPools`](../modules/php/Scoring.php#L148-L266) returns flat `color → totalVP` (end-game wants the sum); [`computeFishingPoolBreakdown`](../modules/php/Scoring.php#L283-L406) returns *rank groups* with `colors`, `fish_hex_keys`, `penguin_keys` (live scoring wants per-rank waves to drive the animation in [`notif_lakeScored`](../src/waddle.ts#L1567-L1595)). Same rank rule in both, different output shapes — collapsing them into one function with a "give me the breakdown too" flag conflated end-game vs live-scoring concerns.
- **Per-player breakdown arrays computed once at half-end.** [`waddleSizesPerPlayer`](../modules/php/Scoring.php#L114-L128) + [`fishPerLakePerPlayer`](../modules/php/Scoring.php#L418-L443) feed the final-scoreboard grid (one cell per waddle, one cell per lake won). They're computed during `GameEnd::buildBoardBreakdown` (and `MidGameReset::buildBoardBreakdown` for half-1) while the board still holds the relevant penguins — see [`end-game-flow`](end-game-flow.md) for why the snapshot has to happen at the transition, not on demand.
- **`hexKeyForCoord` returns `q<q>r<r>`, not the storage form `i_q<q>r<r>`.** The waddle BFS only uses keys internally for visited-tracking; it never has to match `hex.hex_key` from the DB. So the cheaper coordinate-only key is fine. The fish algorithms *do* match against real hex_keys, but they get them already-resolved in the input.
- **`fish_vp_per_color` on each rank group.** The breakdown carries both `fish_vp` (the per-rank value, single int) and `fish_vp_per_color` (a map). They are redundant in the no-tie case, but the map is what [`fishPerLakePerPlayer`](../modules/php/Scoring.php#L418-L443) and the scoreboard hydration in [Game.php:202-209](../modules/php/Game.php#L202-L209) walk over — it lets the consumer dump VP directly into the per-player breakdown without re-deriving "how many colors tied here".
- **`penguin_count_per_color` on each rank group.** Each rank group also carries a `color → weighted_penguin_count` map (singles weigh 1, doubles weigh 2 — same tally that decided the rank). [`PoolScorer`](../modules/php/PoolScorer.php) reads it to attach `penguin_count` per `player_scores` entry and pick the singular vs plural log template when emitting the `lakeFishAwarded` follow-up notifications. Computing the map inside the pure scorer keeps the singular/plural choice testable from `FishingPoolTest` and avoids re-parsing `penguin_keys` on the dispatch side.

## Snags & refinements

- **Tied colors past the last fish hex were silently dropping to zero.** The original 3+ player loop wrote `if (fishIdx >= count(fishValues)) break;` *before* the new-rank check. So a lone-fish pool with three colors tied at the top scored the first-listed color and gave the tied players nothing — the loop bailed before reaching the share branch. The fix in [eb04791](../modules/php/Scoring.php#L249-L262) moves the out-of-fish check *inside* the `count !== prevCount` branch: a fresh rank still needs a fresh slot, but a tie keeps `$currentFish` from the previous iteration. Same fix applied to [`computeFishingPoolBreakdown`](../modules/php/Scoring.php#L366-L403). The pre-fix test [`testMorePlayersThanFishSlots`](../tests/FishingPoolTest.php#L162-L195) had codified the bug as "the current quirk of the algorithm" — flipping it caught the regression on the live-scoring path too, which the new [`testBreakdownTopTieSharesLoneFish`](../tests/FishingPoolTest.php#L222-L246) now covers.
- **Sizes vs. scores in the final-scoreboard payload.** An earlier breakdown shape stored *waddle sizes* and let the client multiply through `WADDLE_VP`. We later switched to storing *waddle scores* (post-table-lookup) so the client doesn't need the scoring table on the wire. Games persisted before the rename still have `waddle_sizes` in their `final_breakdown` global — the [`waddleScoresOf` shim](../modules/php/Game.php#L202-L209) in `getAllDatas` maps them through `getWaddleVP` so old-game reloads render correctly.
- **Doubles weight is parsed from `token_key`, not stored.** First pass passed a `weight` field through alongside `q`/`r`. Then we noticed every caller already had `token_key` and the weight is just `str_contains($key, '_d_') ? 2 : 1` — one substring check beat carrying a field through three layers. See [`waddleSizesByColor`](../modules/php/Scoring.php#L63-L64) and [`scoreFishingPools`](../modules/php/Scoring.php#L221).
- **`rsort($sizes)` is everywhere, by design.** Waddles, fish-per-lake, and the per-player sizes arrays are all sorted descending. The display logic depends on it (the top-left cell of each band is the biggest waddle, biggest lake-fish) and so do humans skimming the chat-log breakdown — "fish 28 + waddle 4 + waddle 1" reads from biggest contribution down.
- **Two notifications per pool closure, not one.** `lakeScored` carries the structured `rank_groups` payload that `notif_lakeScored` consumes for the animation (penguin pulse, fish-hex enlarge, score-counter tick). A follow-up `lakeFishAwarded` is fired *per scoring player* in rank order so the BGA chat log shows a per-player breakdown line beneath the summary — the animation tells you who's winning at a glance, the log tells you who got exactly what. `lakeFishAwarded` has no client handler; BGA's default substitution writes the line straight into the log. Singular ("1 penguin") and plural ("N penguins") are two distinct `clienttranslate` templates so translators get the right plural agreement per language.

## Cross-refs

- [[end-game-flow]] — when each scoring function fires, and how its output reaches the scoreboard UI.
- [[active-hex-activation]] — pool closure (no empty ice adjacent to any water hex in the pool) is what triggers a live `lakeScored` notif. PoolScorer fires from two places: PlayerTurn after each placement (catches mid-round closures), and RoundEnd's marker-advance path (catches closures that coincide with the active hex finishing). The `activated` flag is flipped pool-wide on score so the second call short-circuits — a pool is never scored twice.
- [[token-placement]] — `token_key` parsing for single/double weight; same convention as the supply count.
- [[hex-grid]] — `isAdjacent` is the only adjacency predicate, shared with the empty-ice query.
- [[turn-order]] — `invertMarblePositions` lives in `Scoring` too (pure math, no DB); see Mirror Match in [[end-game-flow]].
