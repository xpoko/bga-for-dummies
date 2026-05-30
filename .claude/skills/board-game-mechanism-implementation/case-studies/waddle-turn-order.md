# Case Study — Waddle Turn Order

A short distillation of how Waddle encodes turn order. The full mechanism diary lives in the project: [`waddle/diary/turn-order.md`](#).

## The Rule

> *"The player whose marble is at the bottom of the active track plays next. After placing, that marble moves to the top of the active track and every other marble on the track slides down one space."*

## The Mistake to Avoid

The obvious first-pass design:

```sql
-- Bad: turn order via flags
ALTER TABLE player ADD COLUMN played_this_round TINYINT DEFAULT 0;
ALTER TABLE player ADD COLUMN turn_order_index TINYINT;

-- "Who plays next?"
SELECT * FROM player WHERE played_this_round = 0 ORDER BY turn_order_index ASC LIMIT 1;

-- "I'm done"
UPDATE player SET played_this_round = 1 WHERE player_id = ?;
```

Two pieces of data — `turn_order_index` and `played_this_round` — that have to stay synchronized. Every action that affects turn order has to update both. Round-end has to remember to clear `played_this_round` across all players.

Plus: scouting (which moves a marble to the *opposite* track) has no clean expression. You'd need a third column for "which track is the marble on", and the scoring/UI code would have to special-case it.

## The Design

> Encode turn order in the **positions** of marbles, and put the marbles on the *board's* track schema, not the player table.

```sql
-- Marbles live in the universal token table.
-- token_key      = 'marble_<color>'
-- token_location = 'track_a' | 'track_b'
-- token_state    = position on track (1..N, lowest plays next)
```

Two operations cover the entire mechanism:

**"Who plays next?"**
```sql
SELECT token_key FROM token
WHERE token_location = '<active_track>'
ORDER BY token_state ASC LIMIT 1;
```

**"I just placed — rotate me to the top, shift others down."**
```sql
-- Move the placer to MAX+1
UPDATE token SET token_state = (SELECT m FROM (
    SELECT COALESCE(MAX(token_state), 0) + 1 AS m
    FROM token WHERE token_location = '<active_track>'
) s)
WHERE token_key = 'marble_<color>';

-- Decrement everyone on that track by 1
UPDATE token SET token_state = token_state - 1
WHERE token_location = '<active_track>';
```

After the two UPDATEs, positions stay packed in `1..N`, the placer is at the top (position `N`), and everyone else has shifted down by one. No bitmask to clear, no off-by-one on round-end.

**Scout-ahead** is the same `moveMarbleToTopOfTrack` helper called with the *other* track as destination — no extra schema, no flag.

## Why It Works

| Question | Answer |
|---|---|
| "Has this player gone?" | Check their marble's position — they're at the top if they just played. |
| "What's the turn order?" | `ORDER BY token_state ASC` on the active track. |
| "Did the last action change the order?" | Compare before/after position maps — the rotation logic returns the new map directly. |
| "What happens on round-end?" | Nothing to clear; positions are already correct. |
| "How do we render the track?" | Sort DOM children by `data-position`. |

Every question reads from the same data shape. The rule "lowest plays next, top just played" *is* the data structure.

## The Pattern

This case study illustrates [§6 of mechanism-implementation.md](../parts/mechanism-implementation.md#6-invariants-encoded-in-data-not-flags): **invariants encoded in data, not flags**.

When a rule can be expressed as a property of how the data is shaped — sorted, grouped, packed in a range — prefer that to a boolean. Booleans drift; data shape doesn't, as long as every operation that mutates the data preserves the shape.

## Refinements Worth Knowing

- **Atomic MAX+1.** The `moveMarbleToTopOfTrack` UPDATE uses a derived-table subquery so the read-and-write happen as one statement. BGA serializes table requests today, so this is defense-in-depth — but writing it correctly once is cheaper than auditing locking later.
- **Persistent seed.** Initial position is shuffled and stored in `playerStats` (`game_setup_track_pos`) so the Mirror Match (half 2) can invert it cleanly — original first player becomes the last player.
- **Animation falls out of the design.** The DOM uses `flex-direction: column-reverse` so position 1 visually appears at the bottom; `data-position` attribute updates trigger CSS transitions; `sortTrack` reorders children. No special "animate the rotation" code.

## See Also

- Full diary entry: [`waddle/diary/turn-order.md`](#)
- Related pattern: [§1 Universal Token-Location Encoding](../parts/mechanism-implementation.md#1-universal-token-location-encoding) — why marbles are in the same table as penguins.
- Companion case study: [`waddle-hex-placement.md`](waddle-hex-placement.md)
