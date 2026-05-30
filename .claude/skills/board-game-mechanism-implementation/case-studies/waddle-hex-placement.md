# Case Study — Waddle Hex Placement

How Waddle gates penguin placement on adjacency to an "active" water hex. Distillation of [`waddle/diary/token-placement.md`](#) and [`waddle/diary/active-hex-activation.md`](#).

## The Rule

> *"On your turn, take one penguin from your supply and place it on an empty ice hex adjacent to the active water hex."*

Three constraints:

1. Token comes from *your* supply.
2. Destination is *empty* ice.
3. Destination is *adjacent to the active water hex*.

## The Single Query That Validates All Three

```sql
-- Empty ice hexes adjacent to (q, r) = the active water hex's coordinates
SELECT h.*
FROM hex h
WHERE h.hex_type = 'ice'
  AND ABS(h.q - $q) + ABS(h.r - $r) + ABS((h.q + h.r) - ($q + $r)) = 2  -- adjacency
  AND NOT EXISTS (                                                       -- empty
      SELECT 1 FROM token t
      WHERE t.token_location = h.hex_key
        AND t.token_key LIKE 'penguin_%'
  );
```

This returns the *set of valid placement targets*. The server validates by checking the requested hex against this set ([§3 of mechanism-implementation.md](../parts/mechanism-implementation.md#3-centralized-grid-math)); the client renders the same set as glowing `.selectable-hex` divs.

The query exists in one place ([`BoardManager::getEmptyAdjacentIceHexes`](#)). Validation, UI rendering, and forced-fill all consume it.

## How the Active Hex Is Identified

The active water hex is a **token**, not a column on the `hex` table:

```
token_key = 'active_hex'
token_state = <hex_number>   -- the activation number printed on the water hex (1..N)
```

The hex table doesn't need an `is_active` column. The marker token's `token_state` *is* the answer. Resolution:

```sql
SELECT * FROM hex WHERE hex_number = (
    SELECT token_state FROM token WHERE token_key = 'active_hex'
);
```

On the client, the marker is a single DOM element pinned inside the corresponding hex's div. When the round ends and the marker moves, the same DOM node is re-parented; CSS animates the move.

## The Adjacency Predicate

> `|dq| + |dr| + |dq+dr| = 2`

Cube distance in axial coordinates. We didn't store `s = -q-r` — it's always derivable. The predicate falls out of "cube distance = 1 → `(|dq| + |dr| + |dq+dr|) / 2 = 1`".

Same predicate used in:
- PHP: [`BoardManager::isAdjacent`](#) (also embedded in the empty-ice SQL above)
- TypeScript: same convention in `src/hex.ts` (pixel conversion uses the same axial basis)

One canonical helper per language, cross-checked by both implementing the same math.

## The Client UI

Three layers of feedback for a single click:

| Cue | Implementation |
|---|---|
| **Selectable** | `.selectable-hex` glow on each empty-adjacent-ice element — set in `attachSelectableInputs`. |
| **Active anchor** | `.active-hex` glow on the active water hex itself — so the player sees the gating constraint visualized, not just inferred. |
| **Centered** | `centerOnActiveHex` scrolls the active hex into the viewport's horizontal center on state-entry and on round-end. |

Plus the optimistic preview: clicking a selectable hex `appendChild`s the penguin token to the hex div *immediately*; Confirm dispatches; Reset reverts.

## What Made It Cleaner

Two design choices that paid off:

1. **`token_location` doubles as the destination DOM element ID.** A penguin's `token_location = 'w3'` means it lives on the hex with `id="w3"`. The notification handler is one line: `document.getElementById(hex_key).appendChild(token)`. No location-table-to-DOM-id mapping. ([§1 universal token encoding](../parts/mechanism-implementation.md#1-universal-token-location-encoding).)
2. **`hex_key` strings are stable and meaningful.** `w<number>` for water, `i_q<q>r<r>` for ice. They show up in payloads, in DOM IDs, in DB rows — same string everywhere. Debugging is `View Page Source → grep`.

## The Refinements

- **Integer-coercion at the JSON boundary.** BGA returns integer columns as PHP strings → JSON strings on the wire → JS strings in `gamedatas.hexes`. The same-tile-border-edge check had to wrap `Number(p.h.q)` before arithmetic; otherwise `"2" + -1` concatenated to `"2-1"` and the lookup failed silently. (See [`waddle.ts:478-488`](#).)
- **Forced-fill bypasses validation.** The last-player-with-no-doubles auto-fill runs server-side and skips `validatePlacements` — the server is also the placer, validation would catch nothing real. This is correct as long as `runForcedFill` itself only picks from the empty-ice set returned by the same query the validator uses.
- **Multi-place sends placements as JSON.** BGA's action framework form-encodes scalars only; arrays travel as JSON-stringified payloads. Both `actPlaceMulti` (client picks tokens) and `actPlaceAll` (server picks) take the same path; the difference is *who* picks the tokens, not how the validation runs.

## The Pattern

This case study illustrates [§3 Centralized Grid Math](../parts/mechanism-implementation.md#3-centralized-grid-math) and [§5 Notification-Driven UI Updates](../parts/mechanism-implementation.md#5-notification-driven-ui-updates):

- The adjacency math is one canonical predicate, shared across SQL, PHP, and (implicitly) TS.
- The placement action emits a notification; the client handler is idempotent so the same code path covers both the active player (already optimistically moved) and spectators (animating fresh).

## See Also

- Full diary entries: [`waddle/diary/token-placement.md`](#), [`waddle/diary/active-hex-activation.md`](#)
- Companion case study: [`waddle-turn-order.md`](waddle-turn-order.md)
- Part 1 — [Rules → UI/UX Mapping](../parts/rulebook-interpretation.md#3-rules--uiux-mapping)
