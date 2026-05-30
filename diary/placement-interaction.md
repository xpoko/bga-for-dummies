# Placement Interaction

**One-liner.** During the active player's turn, empty ice hexes adjacent to the active water hex glow; clicking one previews a placement (optimistically moves a penguin from the local supply onto the hex). Confirm dispatches; Reset reverts. Scout-ahead is triggered by a "Scout ahead" action button in the status bar, or as a shortcut by clicking the *other* turn track. A forced last-player turn accumulates multiple placements before Confirm.

## Source files

- [PlayerTurnHandler.ts:50-82](../src/states/PlayerTurnHandler.ts#L50-L82) — `onEnteringState` / `onLeavingState`
- [PlayerTurnHandler.ts:88-118](../src/states/PlayerTurnHandler.ts#L88-L118) — `attachSelectableInputs` (`.selectable-hex` class on every empty target)
- [PlayerTurnHandler.ts:134-169](../src/states/PlayerTurnHandler.ts#L134-L169) — `attachScoutTarget` / `renderScoutAheadButton` (action button + opposite-track click shortcut)
- [PlayerTurnHandler.ts:261-343](../src/states/PlayerTurnHandler.ts#L261-L343) — `onHexClick` (the placement preview / swap / cancel logic)
- [PlayerTurnHandler.ts:357-399](../src/states/PlayerTurnHandler.ts#L357-L399) — `onScout` (cross-track marble preview)
- [PlayerTurnHandler.ts:431-509](../src/states/PlayerTurnHandler.ts#L431-L509) — `onConfirm` / `onReset` / `revertPending*`
- [PlayerTurnHandler.ts:174-220](../src/states/PlayerTurnHandler.ts#L174-L220) — `renderPlaceAllButton` / `onPlaceAllRemaining`
- [PlayerTurn.php:31-50](../modules/php/States/PlayerTurn.php#L31-L50) — `getArgs` (what the server tells the client)
- [waddle.ts:817-910](../src/waddle.ts#L817-L910) — `setupFishHoverEnlarge` (long-press-to-enlarge fish)

## Rulebook → code

The rulebook tells the player what choices exist; the UI's job is to surface those choices and refuse the rest. We translated each rule clause into a visible affordance:

| Rule clause | Affordance |
|---|---|
| "place on an empty ice hex adjacent to the active water hex" | `.selectable-hex` glow on exactly those hexes; non-targets unclickable |
| "the active water hex is this one" | `.active-hex` highlight around the current water hex |
| "you may scout instead, unless you are the last on the active track and ice remains" | `can_scout` flag on `getArgs` → "Scout ahead" action button appears next to the prompt only when allowed (opposite turn track is also click-armed as a shortcut) |
| "place a single OR one of your two doubles" | toggle pill in the local player's panel; default single, click double to arm a double, click again to revert |
| "the last player on the track with no doubles places into every remaining empty hex" | server forced-fill *and* a "Place all remaining" button as an explicit player choice |

A second pass added quieter affordances:

| Cue | Implementation |
|---|---|
| Active hex is centered in view on entry / when it advances | [`centerOnActiveHex`](../src/waddle.ts#L735-L748) with `behavior: smooth, inline: center` |
| Doubles tile in your panel grays out when both are spent | `.empty` class via [`refreshTypePillState`](../src/states/PlayerTurnHandler.ts#L224-L235) |
| Tap-and-hold a fish on a water hex to enlarge it | [`setupFishHoverEnlarge`](../src/waddle.ts#L817-L910), 500ms timer, swallow the next click on touch |

## Design choices

- **One client-side "pending action" state.** A `PendingAction` union is either a list of placements or a scout ([PlayerTurnHandler.ts:38](../src/states/PlayerTurnHandler.ts#L38)). Every interaction reads/writes it. Confirm dispatches the action; Reset/state-leave reverts the DOM. This collapses what otherwise wants to be three independent state machines (placement, scout, batch-fill) into one.
- **Two modes share one state.** Normal turn (`can_scout=true`) and forced last-player turn (`can_scout=false`) use the same `PendingPlacements` shape. Normal mode caps placements at 1 — clicking a second hex *moves* the placement. Forced mode appends. The branch is a single check on `args.can_scout` ([PlayerTurnHandler.ts:305-310](../src/states/PlayerTurnHandler.ts#L305-L310)).
- **Scout-ahead is a status-bar button, with the opposite track as a shortcut.** Two iterations here. The first prototype put a "Scout ahead" button in the action bar. We then tried replacing it with a label hovering on the opposite turn track — the affordance sat next to the marble you'd be moving and made the destination visually obvious. Playtesters preferred the button: the status text already reads "place a penguin **or scout ahead**", so the matching action button is what they reached for; the floating label felt easy to miss next to live marbles. The track itself stays click-armed as a power-user shortcut (cursor on hover via `.scout-target`), so both worlds coexist. See [`renderScoutAheadButton`](../src/states/PlayerTurnHandler.ts#L184-L193) and [`attachScoutTarget`](../src/states/PlayerTurnHandler.ts#L134-L147).
- **Same-hex re-clicks have rule semantics.** Clicking a pending hex with the *same* type cancels it; clicking with a *different* type swaps the token type ([PlayerTurnHandler.ts:281-302](../src/states/PlayerTurnHandler.ts#L281-L302)). Common case: player picks single, changes mind, clicks again with double-pill armed → token swaps without an explicit Reset. We considered making this require Reset; the swap variant is two clicks instead of three for the same outcome.
- **Optimistic preview is `appendChild` + `data-position`, not a clone.** The preview *is* the real token DOM node, just moved. When the server confirms, `notif_penguinPlaced` does another `appendChild` which is a no-op when the node is already in the right place ([waddle.ts:1060-1063](../src/waddle.ts#L1060-L1063)). No clone-and-replace dance.
- **Confirm picks between three actions based on shape.** One placement → `actPlace` (positional args). Multiple, manually picked → `actPlaceMulti` (placements as JSON). "Place all remaining" without manual edits → `actPlaceAll` (server picks tokens) ([PlayerTurnHandler.ts:431-464](../src/states/PlayerTurnHandler.ts#L431-L464)). The `fromBatchButton` flag distinguishes "I clicked the batch button and didn't touch it" from "I clicked the batch button then manually edited the queue" — the latter has to send the client's exact tokens, the former lets the server be the source of truth.
- **Forced-fill resolves on the server, "Place all remaining" exists for player agency.** The server auto-fills when the player has *no* meaningful choice (last on track, no doubles). When the player *does* have a choice (e.g. doubles still available), they get the manual-fill UI plus a "Place all remaining" button — explicit consent, server picks tokens singles-first.

## Snags & refinements

- **Fish hover/long-press swallowed the placement click.** Long-press on a water-hex fish (to enlarge it for reading) fires a synthetic click on release. Without a guard, that click placed a penguin on the water hex — which then errored on the server. Fix: a `suppressNextClick` flag set on long-press for touch pointers; the next click on the grid is `stopPropagation`'d ([waddle.ts:872-877](../src/waddle.ts#L872-L877), [waddle.ts:899-909](../src/waddle.ts#L899-L909)).
- **Hex/type/scout listeners stay attached when Confirm/Reset show.** Earlier versions tore down listeners when pending. Then the player couldn't change their mind without hitting Reset. Now the listeners stay live and the overwrite-on-click flow in `onHexClick` handles re-selection — the status bar just toggles between "no pending → maybe `Place all remaining`" and "pending → Confirm + Reset" ([syncConfirmResetVisibility](../src/states/PlayerTurnHandler.ts#L408-L415)).
- **Place-all reverts its own optimistic moves before dispatching.** `actPlaceAll` picks token IDs server-side from DB order, which may not match the tokens the client previewed. If we don't revert first, the server's `penguinPlaced` animations re-place *additional* tokens onto the same hexes ([PlayerTurnHandler.ts:443-446](../src/states/PlayerTurnHandler.ts#L443-L446)).
- **Mobile floating supply bar.** On narrow screens, the local-player pills are duplicated into a position-fixed bar at the bottom of the viewport so the type toggle is always tappable without scrolling. Listeners on both the in-flow pills and the floating-bar pills hit the same `onTypeClick` — `refreshTypePillState` syncs the visual state across both ([PlayerTurnHandler.ts:224-235](../src/states/PlayerTurnHandler.ts#L224-L235)).
- **Spectator path bails after the highlight.** `onEnteringState` always paints the `.active-hex` highlight and centers the view; it only wires up listeners when `isCurrentPlayerActive` ([PlayerTurnHandler.ts:62](../src/states/PlayerTurnHandler.ts#L62)). Spectators see the state of play without becoming click targets.

## Cross-refs

- [[token-placement]] — what the click actually does (server-side validation + commit).
- [[turn-order]] — scout-ahead is a turn-order operation; the pending-action union handles both kinds.
- [[active-hex-activation]] — the `.active-hex` highlight and `centerOnActiveHex` are driven by the active-hex marker.
