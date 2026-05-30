# End-Game Flow

**One-liner.** When the last pool closes, [`GameEnd`](../modules/php/States/GameEnd.php) runs once per game: it computes authoritative totals from the current board, persists a structured breakdown into a global, emits per-player `scoreUpdate` notifications, and returns a `GameResult` (which transitions the framework to its terminal state). For Mirror Match the half-1 totals were already snapshotted by [`MidGameReset`](../modules/php/States/MidGameReset.php) before the board was wiped. Page reloads after game-end rebuild the scoreboard from `playerStats` plus the persisted breakdown — no notifications replay.

## Source files

- [States/GameEnd.php](../modules/php/States/GameEnd.php) — single-shot final scoring + notif emit
- [States/MidGameReset.php](../modules/php/States/MidGameReset.php) — half-1 snapshot before board wipe; transitions to `PlayerTurn` for half 2
- [Game.php:131-138](../modules/php/Game.php#L131-L138) — `setupNewGame` clears the breakdown globals so a rematch starts clean
- [Game.php:184-269](../modules/php/Game.php#L184-L269) — `getAllDatas` `final_scores` branch (the reload hydration path)
- [waddle.ts:1603-1640](../src/waddle.ts#L1603-L1640) — `applyFinalScore` (single rendering function for live + reload)
- [waddle.ts:1645-1671](../src/waddle.ts#L1645-L1671) — `renderBandGrid` (entries grid + bottom-pinned subtotal)
- [waddle.css:826-874](../waddle.css#L826-L874) — `.esb-band-grid` layout (auto-wrap up to 3 cols, subtotal aligned across cells)

## Rulebook → code

There's no rulebook clause here — this entry maps *operational constraints* (page reloads, rematches, two halves) onto state-machine structure.

> *Constraint set:*
> 1. Final scores must be authoritative — not "whatever the live counter happened to show".
> 2. A player who reloads the page after end-of-game must see the same scoreboard as one who watched live.
> 3. Mirror Match: the half-1 board is *gone* by the time we score (penguins returned to supply); we still need its per-waddle / per-lake breakdown for the scoreboard.
> 4. A rematch on the same table must not show last game's breakdown.

Each constraint corresponds to a specific piece of plumbing:

| Constraint | Implementation |
|---|---|
| 1 — authoritative totals | [`GameEnd`](../modules/php/States/GameEnd.php#L45-L48) recomputes waddle + fish from the current board and writes `game_vp_total` / `game_waddle_vp` / `game_fish_vp` per player |
| 2 — reload-safe | [`final_breakdown`](../modules/php/States/GameEnd.php#L178) global persisted *before* any `scoreUpdate` notif fires; [`getAllDatas`](../modules/php/Game.php#L189-L268) reads it back and serves it to late joiners and reloaders |
| 3 — Mirror Match half-1 | [`MidGameReset`](../modules/php/States/MidGameReset.php#L42-L70) computes half-1 breakdown from the still-intact board and snapshots it into [`half1_breakdown`](../modules/php/States/MidGameReset.php#L70) before wiping the board; [`GameEnd`](../modules/php/States/GameEnd.php#L57-L60) reads it back |
| 4 — rematch cleanup | [`setupNewGame`](../modules/php/Game.php#L137) deletes both `final_breakdown` and `half1_breakdown` |

## Design choices

- **`game_scored` guard for one-shot scoring.** [`GameEnd::onEnteringState`](../modules/php/States/GameEnd.php#L27-L34) returns early if the flag is already 1. Without this, a player reconnecting to a finished game re-enters the state, the framework re-fires `scoreUpdate`, and the BGA score counter doubles. The flag is initialised to 0 in [`setupNewGame`](../modules/php/Game.php#L85) and set to 1 in the same call that does the actual scoring — same transaction, no race window.
- **Two-pass: persist first, notify second.** [GameEnd.php:67-178](../modules/php/States/GameEnd.php#L67-L178) builds `$finalBreakdown` and `$notifPayloads` in pass 1, then `globals->set('final_breakdown')` runs *between* pass 1 and pass 2, then pass 2 [iterates the cached payloads](../modules/php/States/GameEnd.php#L181-L187) and fires the notifs. The ordering matters — see the reload-window snag below.
- **Single client renderer for both paths.** [`applyFinalScore`](../src/waddle.ts#L1603-L1640) is called from `notif_scoreUpdate` (live path) and from `setup` (reload path, via `gamedatas.final_scores`). One function, one set of cell IDs, one band-grid layout — both code paths produce identical pixels. Earlier versions had two near-duplicate renderers; the divergence bit us when the band-grid landed in only one.
- **Band-grid layout: entries top, subtotal pinned bottom.** Each scoreboard cell is a `.esb-band-grid` flex column. Entries (one per waddle, or one per lake won) sit in an auto-wrap grid at the top; the subtotal pins to the cell bottom via `margin-top: auto` ([waddle.css:867-873](../waddle.css#L867-L873)). This makes subtotals align horizontally across players regardless of how many waddles each player formed.
- **Auto-wrap to 1/2/3 columns based on entry count.** `cols = min(3, ceil(values.length / 8))` ([waddle.ts:1654](../src/waddle.ts#L1654)) — up to 8 entries get one column; 9–24 get two; more than 24 get three (capped, because digits stop being legible past that). The CSS uses fixed `1fr` / `1fr 1fr` / `1fr 1fr 1fr` templates rather than `repeat(auto-fit, minmax(...))` so the layout is deterministic at the fixed cell width.
- **Mirror Match payload carries both halves as full objects.** The `scoreUpdate` payload for a Mirror Match game has `mirror: true` plus `half1` and `half2` each with their own `waddle_scores` / `fish_values`. The non-Mirror payload has those at the top level. The client branches on `mirror` ([waddle.ts:1616-1626](../src/waddle.ts#L1616-L1626)) and selects which cells to populate. One `FinalScore` interface covers both shapes via optional fields, instead of two interfaces with a discriminated union — the call site is symmetric enough that the extra type machinery wasn't earning its keep.
- **Authoritative totals from `playerStats`, breakdown details from globals.** `getAllDatas` reads totals from `playerStats` (`game_vp_total` etc.) but pulls the breakdown *arrays* from `final_breakdown`. Two reasons: stats are queried regardless (the panel always needs the score counter); and the breakdown arrays are too structured for `playerStats`, which is keyed on scalar `game_*_vp` columns. Mirror games get both half1 and half2 sub-objects, again driven by `mirror_match` game state, not by the shape of the global.

## Snags & refinements

- **`scoreUpdate` fired before the global was persisted.** First version of `GameEnd` looped per player, firing `scoreUpdate` inline. The `globals->set('final_breakdown', ...)` ran at the *end* of the loop. A client reloading mid-notification-batch would land in `getAllDatas` with `game_scored = 1` but no `final_breakdown` global yet, and the scoreboard would render with empty breakdown cells. Fix: split into two passes ([19bc07c](../modules/php/States/GameEnd.php#L67-L187)) — compute everything + persist the global, *then* loop again to emit notifs. Same fix reasoning landed [`half1_breakdown` persistence](../modules/php/States/MidGameReset.php#L70) before the board wipe in `MidGameReset`.
- **Rematches showed last game's scoreboard.** Globals survive across games on the same table. Without an explicit clear, a rematch's `setupNewGame` would leave `final_breakdown` from the prior game; if a new player joined and called `getAllDatas` before any scoring happened, [`game_scored` was 0](../modules/php/Game.php#L189) so the stale read was masked — but a rematch *after* the prior game's `game_scored` was reset (e.g. by the framework) would briefly serve the wrong data. The defensive [`globals->delete`](../modules/php/Game.php#L137) at the top of `setupNewGame` eliminates the class of bug regardless of the framework's exact reset semantics.
- **Legacy `waddle_sizes` fallback in `getAllDatas`.** Mid-development we renamed `waddle_sizes` (raw integers) → `waddle_scores` (post-table-lookup VP) in the persisted blob, because the client shouldn't need the scoring table on the wire. But ongoing test games already had globals with the old key. The [`waddleScoresOf` shim](../modules/php/Game.php#L202-L209) reads either shape and maps sizes through `Material::getWaddleVP` if that's what it finds. A throwaway compatibility ramp — could be deleted once no live test games have the old globals.
- **`scoreUpdate` notif type was widened in place.** The pre-band-grid `scoreUpdate` payload only had scalar `waddle` / `fish` / `total` per half. Adding `waddle_scores` / `fish_values` arrays meant either renaming the notification or extending it. Extending was strictly safer — older clients that hadn't loaded the new JS would still see the totals (the new fields are optional). The `HalfScore` interface gained optional `waddle_scores` / `fish_values` ([waddle.ts:41-48](../src/waddle.ts#L41-L48)) and the renderer falls back to a plain subtotal node when they're missing ([renderBandGrid:1650-1653](../src/waddle.ts#L1650-L1653)).
- **`forward breakdown via notif + show waddle VP per entry` (ff307a6).** The live `scoreUpdate` notif used to send only the half-totals; the rich arrays only reached the client via `getAllDatas` on reload. A player watching live wouldn't see per-waddle / per-lake cells until they refreshed. Fix: thread the arrays through the notif payload directly, so the live render is identical to the reload render — no need to drop and re-enter the table to see the breakdown.
- **Scoreboard frame widened 350→500px.** With breakdown grids occupying the waddle/fish bands, 350px crushed the digits. Doubling the per-cell area (taller bands, slightly wider frame) makes 2-column grids comfortable at narrow viewports and 3-column legible at wider ones. Mobile-portrait stacks the half panels vertically rather than squeezing them side-by-side.

## Cross-refs

- [[scoring]] — pure functions whose outputs this flow plumbs into globals and notifs.
- [[turn-order]] — `MidGameReset` reads `game_setup_track_pos` to invert marble ordering for half 2; same `playerStats` snapshot pattern as the half-1 breakdown.
- [[active-hex-activation]] — `MidGameReset` un-activates every hex and seeds the marker at the highest `hex_number` for descending half-2 play.
- [[token-placement]] — `MidGameReset` returns every penguin to its supply container (one bulk UPDATE per color) before the board wipe.
