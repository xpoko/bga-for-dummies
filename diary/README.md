# Waddle Implementation Diary

This diary records how we translated **Waddle** — Cwali's penguin placement game (BGG 435360) — into a Board Game Arena adaptation. Each entry covers one gameplay mechanism: what it does in the played game, where it lives in code, how the rulebook maps onto the implementation, and the design choices and refinements we made along the way.

Scope covers **gameplay mechanisms and the scoring pipeline they feed into**: turn order, hex grid, token placement, placement interaction, active-hex activation, the pure scoring math, and the end-game / Mirror Match flow that persists final scores. Generic BGA framework plumbing (notification dispatch, zoom manager, etc.) is still out of scope — it belongs to the [`bga-dev`](#) skill.

## Mechanism Entries

- [Glossary](glossary.md) — shared terms used across entries
- [Turn order](turn-order.md) — marble tracks, "lowest plays next", placement rotation
- [Hex grid](hex-grid.md) — axial coords, pointy-top geometry, tile assembly, zoom/pan
- [Token placement](token-placement.md) — penguin singles/doubles, location encoding, server validation
- [Placement interaction](placement-interaction.md) — UI glow, scout-ahead label, manual placements, error feedback
- [Active-hex activation](active-hex-activation.md) — water-hex pool, activation sequence
- [Scoring](scoring.md) — pure waddle + fish algorithms, tied-rank fish-sharing, per-player breakdown arrays
- [End-game flow](end-game-flow.md) — `GameEnd` / `MidGameReset` state machinery, breakdown persistence, reload-safe scoreboard
- [Simulation testing](simulation-testing.md) — Studio-only Sim / Sim End buttons, pure action picker, auto-pause on `roundEnd`, source-text guards
- [Table bootstrap](table-bootstrap.md) — *(cross-game framework plumbing)* the contract for getting a new project to create + load a table: player setup, `activeNextPlayer()`, the `zombie()` handler, smoke-test render. Companion to the [`bga-table-bootstrap`](../.claude/skills/bga-table-bootstrap/SKILL.md) skill

## Reading Order

If you're new to the codebase, read [Glossary](glossary.md) first, then [Hex grid](hex-grid.md) (sets up the board model that every other mechanism touches), then the gameplay entries in any order. Read [Scoring](scoring.md) before [End-game flow](end-game-flow.md) — the latter consumes the former.

## Code Layout (Quick Reference)

| Concern | File |
|---|---|
| Hex math, board setup, adjacency | [BoardManager.php](../modules/php/BoardManager.php) |
| Penguins, marbles, active-hex token | [TokenManager.php](../modules/php/TokenManager.php) |
| Player-turn state (place / scout) | [States/PlayerTurn.php](../modules/php/States/PlayerTurn.php) |
| Client placement UI + pending action | [src/states/PlayerTurnHandler.ts](../src/states/PlayerTurnHandler.ts) |
| Main client entry, board render, notif handlers | [src/waddle.ts](../src/waddle.ts) |
| Pure hex coordinate conversion (TS) | [src/hex.ts](../src/hex.ts) |
| DB schema (token, hex, player) | [dbmodel.sql](../dbmodel.sql) |
| Game constants, tile defs, board layouts | [modules/php/Material.php](../modules/php/Material.php) |
| Pure scoring functions (waddle + fish) | [modules/php/Scoring.php](../modules/php/Scoring.php) |
| End-game state, scoreboard payload, double-scoring guard | [modules/php/States/GameEnd.php](../modules/php/States/GameEnd.php) |
| Half-1 snapshot before board wipe (Mirror Match) | [modules/php/States/MidGameReset.php](../modules/php/States/MidGameReset.php) |
| Live lake-scored notification (rank-group waves) | [modules/php/States/RoundEnd.php](../modules/php/States/RoundEnd.php) |
| Studio-only simulation action picker (pure) | [modules/php/Simulation.php](../modules/php/Simulation.php) |
| `actSimulateStep` / `actSimulateEndGame` action methods | [modules/php/States/PlayerTurn.php](../modules/php/States/PlayerTurn.php) |
| Client sim-loop, auto-pause flag, button wiring | [src/waddle.ts](../src/waddle.ts) |

## Entry Format

Every entry follows the same shape:

1. **One-liner** — what this mechanism does at the table
2. **Source files** — code paths with line numbers
3. **Rulebook → code** — quoted rule, then the mapping
4. **Design choices** — alternatives we considered, what we picked, why
5. **Snags & refinements** — what we got wrong first, what we changed
6. **Cross-refs** — links to related entries
