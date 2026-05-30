# Hex Grid

**One-liner.** A pointy-top hex board, laid out from axial `(q, r)` coordinates stored in the DB. Each tile (3 or 4 hexes) is rendered as a single rotated PNG placed at its slot centroid; per-hex DOM nodes overlay the art for click handling, fish/number overlays, and tile-border SVG outlines.

## Source files

- [dbmodel.sql:44-70](../dbmodel.sql#L44-L70) — `hex` table schema (axial `q`, `r`, derived `s = -q-r`)
- [BoardManager.php:17-25](../modules/php/BoardManager.php#L17-L25) — `isAdjacent` (cube-distance = 1)
- [BoardManager.php:35-116](../modules/php/BoardManager.php#L35-L116) — `setupHexLayout` (slot → tile → DB rows)
- [src/hex.ts](../src/hex.ts) — pure `pixelFromAxial` / `axialFromPixel`
- [waddle.ts:320-623](../src/waddle.ts#L320-L623) — `buildBoard` (tile art, hex divs, border SVG, fish/number overlays)
- [waddle.ts:203-308](../src/waddle.ts#L203-L308) — `setupZoomManager` + `loadZoomManager` (fit-to-screen, zoom levels)

## Rulebook → code

The rulebook ships a printed board, divided into named slot configurations per player count (2p, 3p, 4p, 5p). Each slot is a 3-hex or 4-hex tile footprint with one printed water position (or, for some slots, a printed choice between symmetric water positions).

Two representations we considered:

1. **Cube coordinates** `(q, r, s)` with `s` stored — symmetric math, but every row carries a redundant column.
2. **Axial coordinates** `(q, r)` with `s` derived as `-q-r` — half the storage, all the same math (cube distance is recoverable as `(|dq| + |dr| + |dq+dr|) / 2`).

We took (2). [dbmodel.sql:44-70](../dbmodel.sql#L44-L70) keeps only `q, r` (signed `smallint`) and indexes them; the adjacency predicate falls out as `|dq| + |dr| + |dq+dr| = 2` ([BoardManager.php:19-25](../modules/php/BoardManager.php#L19-L25)) and runs as an indexed SQL WHERE in [`getEmptyAdjacentIceHexes`](../modules/php/BoardManager.php#L132-L144).

`Material::LAYOUTS` stores each per-player-count board as a list of slots, each carrying `hexes` and `water_options`. Setup picks one water position per slot at random and converts from the source double-width `(col, row)` coordinates to axial via `q = (col - row) / 2; r = row` ([BoardManager.php:90-91](../modules/php/BoardManager.php#L90-L91)).

## Design choices

- **Tile-art rendering via one PNG per tile, rotated.** Each tile has a single PNG showing the painted shape with a canonical water position. The client measures the slot's chosen water angle in axial-derived pixel space, then rotates the PNG by `(actualAngle − canonicalAngle)`, snapped to 30° ([waddle.ts:430-439](../src/waddle.ts#L430-L439)). Alternatives — per-hex stamps, per-water-position art assets — would have meant either visible seams between same-tile hexes or a 6× explosion of art files.
- **Per-hex DOM divs sit on top of the tile PNG.** Each hex gets a `<div id="<hex_key>">` with the right `(left, top)` from axial→pixel, but no background. Click targets, fish images, hex-number overlays, and the "active hex" glow all attach to these divs ([waddle.ts:505-532](../src/waddle.ts#L505-L532)). The tile PNG carries the art; the divs carry the interaction.
- **Tile borders drawn as SVG lines, edges-not-shared-within-tile.** For every hex in a tile, we draw the 6 edges, *skipping* edges where the neighbor in `NEIGHBOR_DIRS[k]` is another hex of the same tile ([waddle.ts:466-503](../src/waddle.ts#L466-L503)). Result: a single thin black outline traces each tile's perimeter. Drawing borders as full hex outlines and letting same-tile borders overlap looked bolder-on-shared-edges; the edge-skip avoids that.
- **`hex_key` is stable and human-readable.** Water hexes are `w<number>` (so `w1`..`w13`); ice hexes are `i_q<q>r<r>` (so `i_q-1r2`). Notifications carry `hex_key` directly into HTML element IDs ([waddle.ts:1117-1119](../src/waddle.ts#L1117-L1119)); the client never has to map "hex with q=-1, r=2" back to a DOM node.
- **Zoom uses BGA's `bga-zoom` Manager, with our own fit-level.** We compute the largest level in `[0.15, 0.2, ..., 1.3]` where unscaled-wrapper-width ≥ grid-width ([waddle.ts:300-308](../src/waddle.ts#L300-L308)). The reset button snaps to that level. The library handles smooth transitions and persistent zoom (`localStorageZoomKey: waddle-<playerCount>-zoom`).

## Snags & refinements

- **`q`/`r` came back from the DB as strings.** BGA returns integer columns as PHP strings → JSON strings on the wire → JS strings in `gamedatas.hexes`. The first version of the same-tile-edge check used `q + dq` directly, which concatenated (`"2" + -1 = "2-1"`) and never matched. Fix: explicit `Number(p.h.q)` before any arithmetic ([waddle.ts:478-488](../src/waddle.ts#L478-L488)).
- **Mobile zoom raced layout.** `bga-zoom`'s `zoomOrDimensionChanged` overwrites `#hex-grid.style.width` to `wrapper.offsetWidth / zoom`. On mobile, the flex column hadn't settled when the manager constructed → wrapper width was 0 → grid width written as 0 → tiles pushed off-canvas. Two issues, one fix: a `ResizeObserver` re-applies fit once the wrapper has real width, and an `applyFit` helper clamps zoom *down* if a stored desktop level is too big for the current viewport ([waddle.ts:269-289](../src/waddle.ts#L269-L289)).
- **Pointy-top vs flat-top tile art.** Some tile families ship pointy-top art, others ship flat-top. The board is pointy-top. Because every slot's water position differs from canonical by a multiple of 30°, and 30°/90° rotations turn pointy-top into flat-top (60° is a hex self-symmetry), no per-tile shape-offset is needed — the same `actualAngle - canonicalAngle` rotation gets both families right. Detail in the [`buildBoard` block comment](../src/waddle.ts#L417-L429).
- **Tile-art PNG fallback.** Tiles 14–16 (3p board) didn't have art when we shipped the layout. An `Image` probe at end of [buildBoard](../src/waddle.ts#L610-L622) flags missing tiles with `no-tile-art`; CSS falls back to colored circles per hex. Same fallback covers hex-number PNGs via `onerror` swap to a `<span>` ([waddle.ts:522-527](../src/waddle.ts#L522-L527)).

## Cross-refs

- [[token-placement]] — the per-hex divs are the destinations for penguin appends.
- [[placement-interaction]] — `.selectable-hex` and `.active-hex` classes attach to these divs.
- [[active-hex-activation]] — the active hex is identified by `hex_number`, looked up against the `hex` table.
