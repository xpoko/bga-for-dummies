# Example Entry — Turn Order (lifted from Waddle's diary)

This is the canonical example for the diary format. The file you're reading is a copy of [waddle/diary/turn-order.md](#); refer to the live entry for the most up-to-date version.

The example is here so the skill is self-contained — a reader who's never seen Waddle can still see what a finished entry looks like without leaving the skill directory.

---

# Turn Order

**One-liner.** Two vertical "turn tracks" hold one marble per player; whichever marble sits lowest plays next. Placing rotates your marble to the top of the active track and shifts every other marble down by one. Scouting moves your marble to the bottom of the *other* track, deferring you.

## Source files

- `TokenManager.php:70-115` — `moveMarbleToTopOfTrack`, `rotateMarbleToTop`
- `TokenManager.php:121-139` — `getNextPlayerFromTrack` (the "lowest plays next" lookup)
- `TokenManager.php:17-48` — initial randomized seeding into `track_a`
- `PlayerTurn.php:66-83` — `actPlace` invokes the rotation
- `PlayerTurn.php:174-213` — `actScout` (cross-track move)
- `waddle.ts:1077-1103` — `notif_marbleMoved` (rebuilds track DOM after rotation or scout)
- `waddle.ts:761-771` — `sortTrack` (renders marbles by `data-position`)

## Rulebook → code

> *"The player whose marble is at the bottom of the active track plays next. After placing, that marble moves to the top of the active track and every other marble on the track slides down one space."*

That sliding behavior is the entire turn-order mechanism. Two ways to model it suggested themselves:

1. A `current_player` field plus a `played_this_round` bitmask — explicit "who's next" + "who's gone".
2. **Positions on a track** — derive everything from the integer positions of marbles.

We picked (2). "Lowest position plays next" is one SQL query (`TokenManager.php:124-126`); "rotate to top, others shift down" is two UPDATEs (`TokenManager.php:73-84` for the move, then `token_state = token_state - 1` across the track to keep positions packed in `1..N`). There's no separate played-bitmask to keep in sync, and the same data drives the visual track rendering on the client — DOM `data-position` attributes sort the marbles (`waddle.ts:761-771`).

The `marble_<color>` token is the same row in the `token` table as a penguin or the active-hex marker — see [[token-placement]] for the universal token schema.

## Design choices

- **Two tracks, one active.** The game-state value `active_track` (0 or 1) selects which track is drained each round. Scouting moves a marble *across* tracks, which is why the move helper is `moveMarbleToTopOfTrack(color, track)` (takes the destination explicitly) rather than just "rotate on current track".
- **Initial seeding is random and persistent.** `TokenManager::setupTokens` shuffles player IDs and assigns positions `1..N` on track A. The seed position is also written to `playerStats` (`game_setup_track_pos`) so the Mirror Match reset can *invert* it — the original first player becomes the last player in half 2.
- **The placement rotation broadcasts every marble's new position.** `emitMarbleRotation` sends the full color → position map for the active track. The client could compute the shift from a single delta, but sending the whole map lets the notification handler be a dumb `for-of` loop — and stays correct if any future change makes the rotation non-uniform.
- **Atomic MAX+1 for the move.** `moveMarbleToTopOfTrack` is a single UPDATE with a derived-table subquery. BGA serializes table requests today, so this is defense-in-depth — but writing it correctly once is cheaper than auditing locking later.

## Snags & refinements

- **`notif_marbleMoved` had to handle two shapes.** Scout sends `{player_color, track, position}` — one mover, cross-track. Placement sends the same plus `positions: {color: pos}` — multi-update on a single track. The handler now applies both: it always moves the explicitly-named marble (covers scouts), then optionally re-applies the position map (covers placements).
- **The rotation animates.** Earlier versions just re-parented the marble DOM node, so it teleported. We now write `data-position` first, then call `sortTrack` which `appendChild`s in order; CSS transitions handle the slide. Players see who just played and who's deferred.
- **Tracks render bottom-up.** The DOM order is ascending by `data-position` (lowest first), but the CSS uses `flex-direction: column-reverse` so position 1 appears at the *bottom* of the column. Matches the physical board where the bottom marble plays next.
- **Mid-game reset reads the original seed.** Mirror Match flips turn order by reading the per-player `game_setup_track_pos` stat and writing `(N + 1) - original` as the new position. We kept the seed in `playerStats` rather than recomputing because half 2's marbles need an exact inverse, not a re-shuffle.

## Cross-refs

- [[token-placement]] — `marble_<color>` shares the universal token schema with penguins.
- [[placement-interaction]] — the client-side preview for scouting (track decoration + label + revert).
- [[active-hex-activation]] — the rotation only happens when the active hex is current; when the round ends, the active hex advances and the track may flip.
