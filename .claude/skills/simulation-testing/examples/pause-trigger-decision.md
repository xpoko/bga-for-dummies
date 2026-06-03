# Picking the Pause Trigger

The single design choice that most affects how useful the Sim loop is. Once you have the harness working, the question is *when* it should auto-pause: after every turn? after each score? after a round?

This is the decision log from Waddle. Use it as a rubric, not as a recipe — your game's round structure may push the answer differently.

## Tempting choices, ordered worst → best

### "Pause every turn" (a single-step button)

**Why it seems right.** One click = one action; total control.

**Why it's wrong in practice.** A typical Waddle game is ~36 turns per half. Mirror Match doubles that. Even at one click per second, that's 70+ clicks to inspect one game — far worse than just playing it manually.

**Verdict.** Useful as a *debug fallback* (hold a modifier key to single-step instead of looping), but a bad primary mode.

### "Pause when the score changes"

In Waddle: `notif_lakeScored`. Sounds right — a pool just scored, you'd want to see the rank-group wave, the +VP ticks.

**Why it fails.** Score notifications are conditional on a thing actually scoring. In Waddle:

- Single-water-hex pools live-score when their adjacent ice is filled — `lakeScored` fires, all good.
- Multi-water-hex pools whose closure spans rounds DON'T live-score; they accumulate to `GameEnd`'s batch (see [scoring diary](../../../../diary/scoring.md)). `lakeScored` never fires for them mid-game.

So pausing on `lakeScored` skipped exactly the multi-water-pool rounds — the most visually complex ones, where you'd most want to inspect the marker movement, the round-end check, and (if scoring HAD fired) the wave choreography. The trigger was inversely correlated with inspection need.

**Verdict.** A trap. Score notifications are conditional; round-boundary notifications aren't.

### "Pause on a round-boundary notification" — Waddle's pick

In Waddle: `notif_roundEnd`, fired unconditionally from `RoundEnd::onEnteringState` whenever a water hex's adjacent ice is fully filled.

**Why it works.**

1. Fires *every* time the state transitions to `RoundEnd`, regardless of whether scoring happens.
2. Carries the right semantic meaning: "a round just wrapped". That's the natural breakpoint a human player would also stop at to look at the board.
3. Survives the case where scoring is batched (multi-water pools, GameEnd).
4. One pause per water-hex completion ≈ 1 click per round. A 7-round half is 7 clicks instead of 36.

**Trade-off.** Mirror Match's half-1 → half-2 transition does NOT emit `roundEnd` — `RoundEnd` returns `MidGameReset::class` directly without firing the notif. The loop continues seamlessly across the half boundary; the next pause comes after the first round of half 2. We accepted this: the user can always click Resume manually if they want to inspect the half setup.

**Verdict.** The right primary trigger for Waddle.

## Rule of thumb

Find the state in your project that:

- Runs on a meaningful round/turn-cluster boundary (not every individual action).
- Has an `onEnteringState` that *always* runs — not conditional on the score changing or a special board condition.
- Emits a notification with the round-wrap meaning (Waddle: `roundEnd`).

That notification is your pause trigger.

If your game has multiple such notifications (e.g. a `phaseEnded` and a `roundEnded`), pick the *more frequent* one — pauses are cheap to skip past with Resume, and a too-rare pause means a long run before the user can inspect.

## Anti-rule: "pause on score notifications"

If you find yourself reaching for the score notification because it's the only one you know fires reliably, **stop**. Score notifications are conditional by design — they only fire when something scores. The reason you want to inspect the game state is often that you suspect *scoring didn't fire when it should have*. Using the same notification as both the pause trigger and the thing-you're-debugging is circular.

## Future-extension hint

For finer-grained control, you can layer pause triggers:

- Default: pause on `roundEnd`.
- Modifier (Shift held while clicking Sim): pause on every turn for fine inspection.
- Modifier (Ctrl held): never pause, run to game end.

Waddle doesn't ship this; it's an idea worth borrowing if your testing rhythm demands it.
