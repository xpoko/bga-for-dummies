# Glossary

Terms used throughout the Waddle diary. The physical-game terms come from Cwali's rulebook; the implementation terms reflect how those concepts are named in code.

## Physical-game terms

- **Hex** — one space on the board. Either an **ice hex** (penguins go here) or a **water hex** (fish swim here; activation order is printed on it).
- **Tile** — a printed shape covering 3 or 4 hexes. Each tile contains exactly one water hex and the rest ice. Tiles are randomized into the board layout at setup.
- **Pool** — a connected group of water hexes that touch edge-to-edge. The game scores fish VP one pool at a time.
- **Waddle** — Cwali's term for a connected group of same-color penguins. A waddle's VP grows triangularly with its size.
- **Marble** — a colored marble representing a player's turn-order position. Lives on one of two **turn tracks**.
- **Turn track** — a vertical column holding marbles in turn order. Two tracks exist (A and B); only one is "active" each round.
- **Active hex** — the water hex whose round is currently being played. Penguins must be placed on ice hexes adjacent to it.
- **Scout ahead** — instead of placing, a player may "scout" by moving their marble to the bottom of the other turn track, deferring their next placement.
- **Mirror match** — optional second half where the board is reset, marble order reversed, and water hexes activated in descending order.
- **Waddle VP** — score for a connected group of same-color penguins; triangular in the group size (1→1, 2→3, 3→6, 4→10, …). The 2-player variant extends the table past size 8.
- **Fish VP** — score awarded when a pool closes: rank players by penguin count adjacent to the pool, give the highest-fish-numbered hex to rank 1, next to rank 2, etc. Ties share a slot. 2-player rule: leader takes the whole pool.
- **Lake** — synonym for *pool* used in the live-scoring breakdown payload (`lakeScored` notif, `fishPerLakePerPlayer`). One pool = one lake = one closure event.
- **Half** — Mirror Match splits a game into two halves. `half_index` game state is 1 or 2; `playerStats['game_half1_*']` snapshot the first half's per-player scoring at the [`MidGameReset`](../modules/php/States/MidGameReset.php) transition.

## Implementation terms

- **Token** — any movable piece in the universal `token` table: penguins, marbles, the active-hex marker. One schema for all.
  - `token_key` — primary key; encodes both type and identity (e.g. `penguin_red_d_1`, `marble_blue`, `active_hex`).
  - `token_location` — where the token currently sits (`supply_<color>`, a hex_key like `w3` or `i_q-1r2`, or `track_a`/`track_b`).
  - `token_state` — type-specific scalar: marble position on track, water-hex number for the active marker, ignored (0) for penguins.
- **Axial coordinates (q, r)** — two-int hex coordinate system. The third cube coordinate s is derived as `-q-r` and never stored.
- **hex_key** — string ID of a hex. Water hexes are `w<number>` (e.g. `w13`); ice hexes are `i_q<q>r<r>` (e.g. `i_q-1r2`).
- **Active track** — the turn track currently being drained, identified by the `active_track` game-state value (0 = A, 1 = B).
- **Pending action** — client-side preview state in [PlayerTurnHandler.ts](../src/states/PlayerTurnHandler.ts). Either a list of placements or a scout. Confirm dispatches; Reset reverts.
- **Forced-fill** — auto-resolution path in [PlayerTurn.php](../modules/php/States/PlayerTurn.php) that places singles for the last player on the track when they have no meaningful decision left.
- **Rank group** — one rung of a closed-pool fish-VP award: the colors tied at that rank, the fish hex_keys they share, the per-color VP, and the penguin token_keys driving the count. Produced by [`computeFishingPoolBreakdown`](../modules/php/Scoring.php); drives the live-scoring animation in `notif_lakeScored`.
- **Breakdown global** — `final_breakdown` (full game) and `half1_breakdown` (Mirror Match) on [`$this->game->globals`](../modules/php/Game.php). Persist per-player waddle-score + fish-value arrays so [`getAllDatas`](../modules/php/Game.php) can rehydrate the scoreboard after a reload without re-querying the (possibly already wiped) board.
- **`game_scored`** — game-state flag set to 1 by [`GameEnd::onEnteringState`](../modules/php/States/GameEnd.php); guards against re-running final scoring when a player reconnects to a finished game.

## Cross-references

The diary uses `[[entry-name]]`-style references for related entries: see [`turn-order`](turn-order.md), [`hex-grid`](hex-grid.md), [`token-placement`](token-placement.md), [`placement-interaction`](placement-interaction.md), [`active-hex-activation`](active-hex-activation.md), [`scoring`](scoring.md), [`end-game-flow`](end-game-flow.md).
