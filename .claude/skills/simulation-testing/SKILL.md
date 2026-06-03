---
name: simulation-testing
description: Use when building a dev-only simulation harness for a Board Game Arena (BGA) adaptation — Studio-only buttons that drive a random-but-legal play loop and a one-shot "stuff the board, jump to GameEnd" path. Triggered by requests like "add a simulate button", "I need to test end-game flow without playing 30 turns", "let me click through a game", or "how do we stress lake/round scoring animations end-to-end". Captures the working Waddle pattern: pure picker + CheckAction-bypass + Studio gate + client loop with auto-pause + source-text guards.
---

# Simulation Testing Harness (BGA)

A simulation harness is two dev-only buttons over the turn tracker that let any seat in BGA Studio drive a game to completion in seconds:

- **▶ Sim** — client loop that fires a server action ("pick one legal move and apply it") per turn, auto-pausing at natural breakpoints so you get a real inspection rhythm.
- **⏭ End** — one-shot that fills every empty board location with a random token and hands off to your end-game scoring cascade, so you can debug `GameEnd` / `MidGameReset` paths without playing through.

The pattern is **lifted directly from the Waddle implementation**. Read [Waddle's `simulation-testing` diary entry](../../../../diary/simulation-testing.md) for the worked example with all the snags it took to get right.

## When to Use

- You're debugging end-of-game scoring, animation sequences, or any UI state that only happens after many turns and a full board.
- You're tuning notification choreography (rank-group waves, score-update batches) and want to see it fire repeatedly without manual replay.
- You're stress-testing the state machine for transitions that only happen on specific board states (mirror match half-1 → half-2, last-player-must-place, pool closures).
- You want a fast loop to inspect rare emergent states (a 4-player Mirror Match with all five colors active).

## When NOT to Use

- Production tables — this is **Studio only**. The harness *must not* exist in production builds; the recipe below enforces a two-layer gate.
- Single-mechanism unit testing — that's a pure PHPUnit test against your scoring functions / picker / coordinate math.
- Anything that doesn't need a full played-through game state. If a fixture in `setupNewGame` can give you the same board, use that.

## Recipe

The pattern has six pieces. Each links to a worked example.

### 1. Pure action picker (unit-testable)

Move the "which legal action should I pick?" decision out of the action method into a pure class. Inject the RNG so tests can fix it.

```php
final class Simulation
{
    public const ACTION_SCOUT     = 'scout';
    public const ACTION_PLACE_ONE = 'placeOne';
    public const ACTION_PLACE_ALL = 'placeAll';

    public static function pickAction(
        bool $canScout,
        bool $canPlace,
        bool $isLast,
        ?callable $randFn = null   // <-- the seam tests use
    ): string {
        $choices = [];
        // ... build $choices from the booleans, weighting as you wish
        if (empty($choices)) {
            throw new \RuntimeException('No legal simulation action');
        }
        $randFn = $randFn ?? static fn(int $min, int $max): int => mt_rand($min, $max);
        return $choices[$randFn(0, count($choices) - 1)];
    }
}
```

**Why pure:** the picker is the only non-trivial decision; everything else is a delegation to existing helpers. Keeping it pure makes [`SimulationPickerTest`](../../../tests/SimulationPickerTest.php) DB-free and instant.

**Weighting tip:** if one action is more interesting than another (Waddle weights `PLACE_ONE` 2× over `SCOUT` for non-last players), list it twice in the choice array — simpler than weighted-random.

→ See [`examples/picker.php`](examples/picker.php) for the full Waddle picker with the last-player short-circuit.

### 2. Action methods that bypass active-player check

```php
use Bga\GameFramework\Actions\CheckAction;
use Bga\GameFramework\States\PossibleAction;

#[PossibleAction]
#[CheckAction(enabled: false)]   // <-- any seat can submit this action
public function actSimulateStep(): string
{
    $this->assertStudio();
    // gather booleans, call Simulation::pickAction, dispatch
    // return the next state class name
}
```

**Why bypass active-player check:** the Studio table usually has one human seat. Without `CheckAction(enabled: false)`, only the active player can drive the loop, which means the loop stalls every time turn order rotates to another seat (e.g. the AI).

**Gotcha — don't fake the bypass with a method call.** Calling `$this->checkPossibleAction(...)` is a no-op at best and a fatal at runtime: that method lives on `GamestateMachine`, not on the `GameState` class your action lives on. The attribute is the correct surface. If you find yourself writing the method call, you've reached for the wrong API.

### 3. Two-layer Studio gate

Client-side: refuse to render the buttons.

```ts
private setupSimulationButtons(): void {
    if (!this.gamedatas.is_studio) return;  // gamedatas.is_studio comes from getAllDatas
    // render buttons, wire handlers...
}
```

Server-side: refuse to do the work even if the action is dispatched.

```php
private function assertStudio(): void
{
    if ($this->game->getBgaEnvironment() !== 'studio') {
        throw new \BgaUserException(clienttranslate('Simulation actions are Studio-only'));
    }
}
```

And in `getAllDatas`:

```php
$result['is_studio'] = ($this->getBgaEnvironment() === 'studio');
```

**Why both:** hiding the button doesn't stop a tampered client from POSTing the action. The server check is the actual gate; the client flag is a UX nicety (don't render dead buttons).

### 4. Helper methods that mirror production paths

```php
private function simulatePlaceOne(int $playerId, string $hexKey, string $tokenKey): string
{
    // Same calls as actPlace, minus the user-input validation.
    // The picker already chose a legal hex + token.
    $this->emitPlacements($playerId, [['hex_key' => $hexKey, 'token_key' => $tokenKey]]);
    // ... rotate marble, increment stats, fire notifs
    return $this->advanceTurn();
}
```

**Why mirror, not call the action:** the action method runs its own active-player check, input validation, etc. The simulator has already pre-validated, so calling the action method would be redundant and error-prone (which parameters does it expect? what state must the player be in?). Mirror the *body* without the guards. The notifications and state writes stay identical — that's what makes the simulator useful for animation debugging.

**Don't** fork the helpers into entirely separate paths — that's how the simulator drifts away from production and stops being useful. Reuse `emitPlacements`, `notifyAllPlayers`, etc.

### 5. Client loop with auto-pause on notification

```ts
simBtn.addEventListener("click", async () => {
    simBtn.disabled = true;
    this.simRunning = true;
    this.simPauseRequested = false;
    try {
        for (let i = 0; i < 500; i++) {              // hard cap, defensive
            if (this.isGameOver()) break;
            if (this.simPauseRequested) break;       // race-window check (top)
            try {
                await this.bga.actions.performAction(
                    "actSimulateStep",
                    {},
                    { checkAction: false, lock: true },
                );
            } catch {
                break;  // server rejected — game ended or unexpected state
            }
            // performAction's promise resolves on AJAX response, NOT on
            // notification dispatch — notifs arrive over a separate channel.
            // The delay gives the pause-triggering notif time to fire.
            await new Promise((r) => setTimeout(r, 150));
            if (this.simPauseRequested) break;       // primary pause check
        }
    } finally {
        this.simRunning = false;
        const paused = this.simPauseRequested && !this.isGameOver();
        this.simPauseRequested = false;
        simBtn.disabled = false;
        simBtn.textContent = paused ? "▶ Resume" : "▶ Sim";
    }
});
```

And the pause flag set inside the notif handler:

```ts
async notif_roundEnd(args: { ... }): Promise<void> {
    if (this.simRunning) {
        this.simPauseRequested = true;
    }
    // ... normal notif handling
}
```

**Three things that bit us, captured in this snippet:**

1. **The 150ms delay isn't optional.** `performAction`'s promise resolves on the AJAX response, not on notification dispatch — notifs come over a separate channel (per `bga-framework.d.ts`). Without the gap, the pause-triggering notif races against the next `performAction` and the loop can fire one extra turn before noticing. Don't lower 150ms casually.

2. **Check the pause flag at the top *and* bottom of each iteration.** A late-arriving notif (socket lag, queued behind siblings) can land *between* the previous iteration's post-delay check and the next iteration's `performAction` call. The top-of-loop check closes that race for one boolean comparison.

3. **Gate the pause flag on `simRunning`.** Don't let `notif_roundEnd` flip `simPauseRequested` when no loop is active — otherwise a stale flag could pause the first turn of the next loop.

### 6. Pick the right pause trigger

Tempting choices in order from worst to best for most games:

- **Pause every turn** — too noisy. A 30-turn game is 30 clicks; nobody clicks 30 times for one inspection.
- **Pause when the score changes** (e.g. `notif_lakeScored` in Waddle) — sounds right but often has coverage gaps. In Waddle, multi-water-hex pools whose closure spans rounds don't live-score; they accumulate to GameEnd. Pausing on lakeScored *skipped exactly the rounds you'd want to inspect.*
- **Pause on a round-boundary notification** (the Waddle final pick — `notif_roundEnd`) — fires on every transition the framework cares about. One click ≈ one round. Predictable rhythm.

**Rule of thumb:** look at the notification that fires from `onEnteringState` of your "round wraps up" state, not from your scoring code. The round-wrap notification fires unconditionally; scoring notifications skip when there's nothing to score.

→ See [`examples/pause-trigger-decision.md`](examples/pause-trigger-decision.md) for the longer comparison.

### 7. One-shot "jump to end game" action

```php
#[PossibleAction]
#[CheckAction(enabled: false)]
public function actSimulateEndGame(): string
{
    $this->assertStudio();
    // Walk every still-active region of the board, fill empty slots with
    // random tokens from supply, emit the standard placement notification
    // for each so animations fire.
    // ...
    return RoundEnd::class;  // <-- hand off to the framework cascade
}
```

**Key point:** return the state class that *cascades through scoring*, not `GameEnd` directly. In Waddle that's `RoundEnd` — it handles round-by-round activation, lake scoring, marker movement, and eventually transitions to `MidGameReset` (Mirror Match) or `GameEnd`. Jumping straight to `GameEnd` bypasses the regular scoring path, which is usually the thing you're trying to debug.

**Heads-up:** the resulting board is *synthetic* (random colors per location, no respect for turn order or strategic clustering). It exercises end-game scoring/UI but is NOT a realistic mid-game snapshot. Note this in the action's comment.

### 8. Source-text guard tests (when integration testing isn't viable)

The action methods can't be run against a real BGA DB in CI. But the four properties that matter are checkable from the source text:

```php
public function testActSimulateStepGatesOnStudio(): void
{
    self::assertStringContainsString('assertStudio', $this->methodBody('actSimulateStep'));
}

public function testActSimulateStepBypassesActivePlayerCheck(): void
{
    // The #[CheckAction(enabled: false)] attribute sits BEFORE the method,
    // so methodBody() can't see it. Match the attribute directly preceding
    // the method declaration with only whitespace between.
    self::assertMatchesRegularExpression(
        '/#\[CheckAction\(enabled:\s*false\)\]\s+public\s+function\s+actSimulateStep\s*\(/',
        $this->source,
    );
}

public function testActSimulateStepUsesPickAction(): void
{
    self::assertStringContainsString('Simulation::pickAction', $this->methodBody('actSimulateStep'));
}

public function testActSimulateEndGameReturnsRoundEnd(): void
{
    self::assertStringContainsString('return RoundEnd::class', $this->methodBody('actSimulateEndGame'));
}
```

The `methodBody()` helper slices the file between two `function` declarations. See [`examples/source-guard-test.php`](examples/source-guard-test.php) for the full pattern (matches the project's `PerHexPlacementTest` style).

**What these tests catch:**
- Dropping the Studio gate → would expose the buttons in production (security regression).
- Dropping the `#[CheckAction]` attribute → would re-introduce the active-player restriction the simulation needs to bypass.
- Dropping the picker call → would silently fall back to a hard-coded choice.
- Dropping the `RoundEnd` return → would break the end-game cascade.

These are the four regressions where the buttons appear to "still work" but quietly do the wrong thing.

## Required Ingredients

A working harness must have:

- [ ] A pure picker class with an injectable RNG (so tests are deterministic).
- [ ] Picker unit tests covering: each action being the only legal option; the weighting; the "no legal action" error; the rule-required collapses (e.g. last-player-must-place).
- [ ] Both action methods carry `#[CheckAction(enabled: false)]` and `#[PossibleAction]`.
- [ ] Both action methods call `assertStudio()` (or equivalent) on entry.
- [ ] `getAllDatas` exposes `is_studio` boolean.
- [ ] Client-side button render guarded by `gamedatas.is_studio`.
- [ ] Client loop has a hard iteration cap (Waddle uses 500), top-of-loop pause check, bottom-of-loop pause check, and a meaningful delay between actions.
- [ ] Pause flag is set inside a *round-boundary* notification handler, gated on `simRunning`.
- [ ] `actSimulateEndGame` returns the **scoring-cascade** state class, not `GameEnd` directly.
- [ ] Source-text guards on the four regression points above.

## Anti-Patterns

| Anti-pattern | Why it's wrong | What to do instead |
|---|---|---|
| Calling `$this->checkPossibleAction('...')` | Method doesn't exist on `GameState` — silent fail or fatal at runtime | Use the `#[CheckAction(enabled: false)]` attribute |
| Hiding the buttons but leaving the server action ungated | Tampered client can POST in production | Always pair client flag + server `assertStudio` |
| Forking the action helpers into entirely new code paths | The simulator drifts away from production and stops exercising the real animations | Mirror the action body without input validation; reuse the same notification emitters |
| Pausing on every turn | 30-turn game = 30 clicks; nobody uses it | Pause on round-boundary notifications, not turn-boundary |
| Pausing on `lakeScored`-style "something scored" notifications | Has coverage gaps for pools/rounds that don't live-score | Pause on the unconditional `notif_roundEnd` (or your project's equivalent) |
| Skipping the 150ms delay because "performAction's promise should be enough" | The promise resolves on AJAX, not on notifs — separate channels | Keep the delay; document *why* in the comment |
| Returning `GameEnd::class` from `actSimulateEndGame` | Bypasses the scoring cascade you're trying to debug | Return `RoundEnd::class` (or your project's scoring-entry state) |
| Integration tests against a live BGA DB in CI | Not feasible on the PHPUnit-only CI we run | Source-text guards via `methodBody()` + regex |
| No iteration cap on the client loop | A bug in the picker or state machine could spin the loop forever | `for (let i = 0; i < 500; i++)` — beyond any legal game length |

## See Also

- [Waddle `simulation-testing` diary entry](../../../../diary/simulation-testing.md) — the full design history, snags, and rejected alternatives that led to this recipe.
- [`bga-dev`](#) skill — framework plumbing reference; `references/04-server.md` documents `CheckAction` and the action attribute system.
- [`diary-logging-implementation`](#) skill — write up your own harness as a mechanism entry once it's working.
- [Waddle scoring diary](../../../../diary/scoring.md) — explains the live-scoring coverage gap that made `lakeScored` the wrong pause trigger.
