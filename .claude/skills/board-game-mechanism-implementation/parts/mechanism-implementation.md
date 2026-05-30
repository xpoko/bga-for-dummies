# Part 2 — Mechanism Implementation

Six patterns that recur across digital board-game adaptations. Each pattern leads with a one-liner, then the rationale, then the Waddle reference.

## 1. Universal Token-Location Encoding

**Pattern.** One DB table for *every* movable piece. Token type and identity are encoded in the primary key.

```sql
CREATE TABLE token (
  token_key      varchar(32) PRIMARY KEY,    -- 'penguin_red_d_1', 'marble_blue', 'active_hex'
  token_location varchar(32) NOT NULL,        -- 'supply_red', 'w3', 'i_q-1r2', 'track_a'
  token_state    int          NOT NULL DEFAULT 0  -- type-specific scalar (position, number, ...)
);
```

**Why.** Movable pieces in board games share lifecycle: they sit somewhere, they move somewhere, sometimes they carry a scalar (position on a track, value of a card). Modelling each type with its own table multiplies queries ("is this hex empty?" → check the penguin table, *and* the marble table, *and* …), forces type-aware join logic, and balloons the schema. One schema with stringly-typed key + location keeps move operations identical: `UPDATE token SET token_location = '<dest>' WHERE token_key = '<id>'`.

**Tradeoff.** You lose foreign-key enforcement on location values. Mitigate by making `token_location` a string that *also* serves as the destination DOM element ID on the client — `token_location = 'w3'` means "the token is on the hex whose div has `id="w3"`". Move animations become `document.getElementById(token.location).appendChild(tokenEl)`.

**Waddle reference.** [`dbmodel.sql:19-42`](#) — universal `token` schema; [`TokenManager.php`](#) — type-aware operations on top of it.

## 2. Pure Scoring Functions

**Pattern.** Scoring functions take state in, return VP out. No DB, no notifications, no side effects.

```php
// Bad: scoring reads from DB
public function scoreWaddles(): array {
    $placements = $this->game->getObjectListFromDB("SELECT ...");
    // ... compute ...
}

// Good: scoring takes state
public static function scoreWaddles(array $placements, int $playerCount): array {
    // ... compute and return VP map ...
}
```

**Why.** Two payoffs:

- **Testability.** Pure functions accept synthetic boards. You can unit-test "what does a 4-penguin red waddle score when adjacent to a 2-penguin red waddle" without setting up a database fixture.
- **Reusability.** The same function powers the end-of-game scoring, the live-scoring updates, and the score breakdown tooltip — without three implementations drifting.

**Anti-pattern.** Don't add a `bool $live` parameter that changes behavior. Live scoring isn't a different scoring rule; it's the *same* function called more often. If live scoring needs a breakdown structure, build it as a separate function that *calls* the pure one.

**Waddle reference.** [`Scoring.php`](#) — `scoreWaddles(placements, playerCount)`, `scorePool(...)`. Both consumed by `PlayerTurn.php` (live), `RoundEnd.php` (per-pool), and `GameEnd.php` (final).

## 3. Centralized Grid Math

**Pattern.** Adjacency, coordinate conversion, and pixel mapping live in **one** module per representation.

```typescript
// src/hex.ts — pure pixel ↔ axial conversion
export const HEX_SIZE = 78;
export function pixelFromAxial(q, r) { /* ... */ }
export function axialFromPixel(x, y) { /* ... */ }

// BoardManager.php — adjacency + neighbor queries
public static function isAdjacent(int $aq, int $ar, int $bq, int $br): bool { /* ... */ }
public function getAdjacentIceHexes(int $q, int $r): array { /* ... */ }
```

**Why.** Grid math has many subtle conventions: pointy-top vs flat-top, axial vs cube vs offset, y-up vs y-down. Spreading conversions across files guarantees someone will pick the wrong sign and the bug will manifest as a single off-by-one hex that's intermittent on rotated boards.

Two rules:

- One module owns the canonical conversion. Everything else calls into it.
- Adjacency math is canonical too. If two functions need "is X next to Y", they call the same helper.

**Cross-language consistency.** If the server (PHP) and client (TypeScript) both need adjacency, both `isAdjacent` implementations must be byte-for-byte semantically equal. Best to write one and copy with comments; second best, test both against the same fixture set.

**Waddle reference.** [`BoardManager.php:17-25`](#) for `isAdjacent`; [`src/hex.ts`](#) for pixel conversion. Same axial convention, same pointy-top, same `q + r + s = 0` cube constraint.

## 4. State Machines — Game States vs Player States

**Pattern.** Distinguish **player states** (waiting on a specific player) from **game states** (the server resolves something without input).

| Type | Triggered by | Example |
|---|---|---|
| Player state | Player action (place, scout, confirm) | PlayerTurn |
| Game state | Automatic transition | RoundEnd, MidGameReset, GameEnd |

**Why.** Player states need a designated active player and a list of `#[PossibleAction]`s. Game states just run logic and transition. Conflating them — e.g. trying to do round-end inside the active player's turn handler — couples timing-of-display to timing-of-rules and makes "what happens on reconnect" hard to reason about.

**Tooling.** Most board-game frameworks (BGA included) give you both types as distinct base classes. Use them.

**Waddle reference.** [`States/PlayerTurn.php`](#) (player state, `StateType::ACTIVE_PLAYER`), [`States/RoundEnd.php`](#) / [`States/MidGameReset.php`](#) / [`States/GameEnd.php`](#) (game states, automatic).

## 5. Notification-Driven UI Updates

**Pattern.** Server is the source of truth. Client renders state. State changes are broadcast as notifications; the client's UI handlers react to them.

```php
// Server: do the thing, then announce it
$this->game->DbQuery("UPDATE token SET ...");
$this->game->notifyAllPlayers('penguinPlaced', '...', [
    'token_key' => $tokenKey,
    'hex_key'   => $hexKey,
]);
```

```typescript
// Client: handler receives the announcement
async notif_penguinPlaced(args): Promise<void> {
    const token = document.getElementById(args.token_key);
    const dest  = document.getElementById(args.hex_key);
    dest?.appendChild(token);
}
```

**Why.** Two clients (e.g. two players watching the same game) can be in slightly different states at any moment. A notification stream gives them a single ordered timeline to apply. If a client misses a notification (reconnect, refresh), the framework replays from the canonical state — the same handler code that drives live play also drives the reload path.

**Idempotency matters.** Notification handlers must be safe to run when the change has already been applied (active player optimistically moved a token before the server confirmed). For Waddle, this falls out naturally: `appendChild` to a parent the node is already in is a no-op.

**Optimistic preview.** For the *active* player, immediately previewing the action (moving the token DOM before the server confirms) makes the UI feel responsive. The notification, when it arrives, lands on a DOM that already matches — and if the server *rejects* the action, the client reverts.

**Waddle reference.** [`PlayerTurn.php` notifies of placements + rotations](#); [`waddle.ts` handlers `notif_penguinPlaced`, `notif_marbleMoved`, `notif_roundEnd`](#).

## 6. Invariants Encoded in Data, Not Flags

**Pattern.** Prefer designs where the rule falls out of the data shape, not a separate boolean.

**Example — turn order:**

| Design A | Design B |
|---|---|
| `players.current_player_id` + `players.played_this_round` bitmask | Marbles on a track; lowest position plays next |
| "Has this player gone?" requires reading the bitmask | "Has this player gone?" → are they near the top of the track? |
| Two pieces of data to keep in sync | One piece of data; both questions read from it |

Design B (Waddle's choice) means there's no `played_this_round` to forget to update. The rule "lowest plays next" is literally `ORDER BY token_state ASC LIMIT 1`. Rotating a marble to the top is "set to MAX+1, then decrement all" — and the invariant "positions are packed in `1..N`" is preserved by construction.

**Why.** Every flag you don't have is one more thing that can't go out of sync. The rule is in the data shape; if the data is right, the rule is right.

**When to deviate.** Sometimes there's no data-shape encoding. Then add the flag, but make it the *only* representation — don't keep both ("turn order via positions *and* a `played_this_round` bitmask"). That's strictly worse than either alone.

**Waddle reference.** [`TokenManager::rotateMarbleToTop`](#); described in [`case-studies/waddle-turn-order.md`](../case-studies/waddle-turn-order.md).

## Quick Reference

| Need | Pattern |
|---|---|
| Several types of movable pieces | Universal token-location encoding (§1) |
| Scoring used in multiple places | Pure scoring function (§2) |
| Adjacency or coordinate logic | Centralized grid math (§3) |
| New rule-trigger boundary (player→server→player) | Game state vs player state (§4) |
| UI needs to react to an event | Notification + idempotent handler (§5) |
| Tempted to add a boolean | Encode as data shape first (§6) |
