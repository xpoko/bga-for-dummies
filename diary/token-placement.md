# Token Placement

**One-liner.** A placement removes a penguin from the active player's supply and puts it on an empty ice hex adjacent to the current active water hex. Singles count as 1 in [waddle scoring]; doubles count as 2. Every movable piece — penguins, marbles, the active-hex marker — lives in the same `token` table.

## Source files

- [dbmodel.sql:19-42](../dbmodel.sql#L19-L42) — universal `token` schema (`token_key`, `token_location`, `token_state`)
- [TokenManager.php:17-48](../modules/php/TokenManager.php#L17-L48) — initial supply seeding (singles + doubles + marble per player)
- [PlayerTurn.php:236-272](../modules/php/States/PlayerTurn.php#L236-L272) — `validatePlacements` (server-side gate)
- [PlayerTurn.php:277-303](../modules/php/States/PlayerTurn.php#L277-L303) — `emitPlacements` (DB update + notification)
- [PlayerTurn.php:308-354](../modules/php/States/PlayerTurn.php#L308-L354) — `runForcedFill` (auto-resolution for forced last player)
- [BoardManager.php:132-144](../modules/php/BoardManager.php#L132-L144) — `getEmptyAdjacentIceHexes` (the empty-ice query)
- [waddle.ts:1055-1067](../src/waddle.ts#L1055-L1067) — `notif_penguinPlaced` (idempotent DOM move)

## Rulebook → code

> *"On your turn, take one penguin from your supply (a single or one of your two doubles) and place it on an empty ice hex adjacent to the active water hex."*

That sentence contains three constraints:

1. The token comes from *your* supply.
2. The destination is an *empty ice* hex.
3. The destination is *adjacent* to the active water hex.

All three are checked server-side in [`validatePlacements`](../modules/php/States/PlayerTurn.php#L236-L272). The first by `WHERE token_location='supply_{$playerColor}'`; the second and third by precomputing the valid empty-ice set via [`getEmptyAdjacentIceHexes`](../modules/php/BoardManager.php#L132-L144), which combines the adjacency predicate (`|dq| + |dr| + |dq+dr| = 2`) with a `NOT EXISTS` against any penguin token at that hex_key.

The client-side previews placement before the server confirms — see [`placement-interaction`](placement-interaction.md) — but the server is the truth.

## Design choices

- **Universal token schema.** One table for penguins, marbles, the active-hex marker. `token_key` encodes type + identity (`penguin_red_d_1`, `marble_blue`, `active_hex`); `token_location` is a string that can be a supply container, a hex_key, or a track name; `token_state` is a type-specific scalar (marble position, water-hex number for the active marker, unused for penguins). One schema, one set of move semantics. See [`dbmodel.sql:19-42`](../dbmodel.sql#L19-L42) and the deep dive in [`turn-order`](turn-order.md).
- **`token_location` doubles as the destination DOM element ID.** A penguin placed on hex `w3` has `token_location = 'w3'`, which is also the DOM `id` of that hex's div. The client's notification handler just does `document.getElementById(args.hex_key).appendChild(token)` ([waddle.ts:1060-1063](../src/waddle.ts#L1060-L1063)); no separate location→DOM mapping table.
- **Singles unlimited, doubles capped at two.** The rulebook gives each player two double-penguin tokens and an unlimited supply of singles (physical game uses "substitutes" when components run out). `SINGLES_PER_PLAYER` is set to 52 — the ice-hex count of the largest board (5p) — so a single player can fill the entire board solo without exhausting their pool, even in pathological scout-heavy 2p games. In the UI, singles are unlimited and not counted; only the two doubles are visually tracked. See [`refreshSupplyCount`](../src/waddle.ts#L976-L986). An earlier value (`= 18`) caused a "Cwali" half-2 stall when one player's forced-fill ran out of singles mid-round, stranding the next player on a turn they couldn't complete.
- **Token IDs are human-meaningful.** `penguin_red_d_1` parses as `(type, color, single|double, index)`. We use this directly in code — e.g. detecting single vs double from `token_key.split('_')[2]` in both PHP ([PlayerTurn.php:325-329](../modules/php/States/PlayerTurn.php#L325-L329)) and TS ([PlayerTurnHandler.ts:286-287](../src/states/PlayerTurnHandler.ts#L286-L287)). Cheaper than carrying a `type` column.
- **Forced-fill on the server, not the client.** When the last player on the active track has only singles left and ice hexes remain, the choice is trivial — every placement is identical. [`isForcedFillCandidate`](../modules/php/States/PlayerTurn.php#L425-L439) detects this and `advanceTurn` auto-resolves by calling [`runForcedFill`](../modules/php/States/PlayerTurn.php#L308-L354). The client just sees a `penguinPlaced` notification for each auto-placed token. Done server-side because the choice is mechanical and we didn't want to bother the player with N consecutive "click confirm" turns.

## Snags & refinements

- **Multi-place needed JSON-on-the-wire.** BGA's action framework form-encodes scalars only — arrays don't survive transport. `actPlaceMulti` takes `placements` as a JSON string ([PlayerTurn.php:97-128](../modules/php/States/PlayerTurn.php#L97-L128)); the client does `JSON.stringify` before dispatch ([PlayerTurnHandler.ts:458-463](../src/states/PlayerTurnHandler.ts#L458-L463)).
- **Three actions, one validation path.** `actPlace` (single), `actPlaceMulti` (multi, client-picked), and `actPlaceAll` (multi, server-picked) all share `validatePlacements`. The forced-fill path bypasses it because the server is also the placer — validation would catch nothing real and add a query.
- **Optimistic placement collides with server-picked tokens.** The "Place all remaining" button (which fires `actPlaceAll`) used to leave the client's previewed tokens on the board, and then the server's `penguinPlaced` notifications would land *additional* tokens on the same hexes. Fix: before dispatching `actPlaceAll`, the handler reverts all previewed tokens to the supply ([PlayerTurnHandler.ts:443-446](../src/states/PlayerTurnHandler.ts#L443-L446)), so the server's animations have clean hexes to animate into.
- **Idempotent notification handler.** `notif_penguinPlaced` does `dest.appendChild(token)`. If the active player already optimistically moved the token, `appendChild` is a no-op when the token is already the last child. If they didn't (spectator, opponent), it animates the move. One code path, both audiences ([waddle.ts:1055-1067](../src/waddle.ts#L1055-L1067)).

## Cross-refs

- [[placement-interaction]] — the client UI for choosing what to place, where.
- [[turn-order]] — placement also rotates the player's marble; same `token` table.
- [[active-hex-activation]] — what defines "adjacent to the active water hex".
- [[hex-grid]] — why `token_location` and DOM element IDs can be the same string.
