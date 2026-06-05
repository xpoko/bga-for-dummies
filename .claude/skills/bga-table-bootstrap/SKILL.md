---
name: bga-table-bootstrap
description: >
  Checklist for getting a modern BGA Studio project from empty scaffold to a table that
  CREATES AND LOADS without fatal errors — the bootstrap phase before any real game rules.
  Use this skill whenever the user is starting a new BGA game, just scaffolded a project and
  wants a first running table, or hits a table-creation / page-load fatal such as
  "A zombie function is needed for state class ...", "there is no more active player!",
  "Unknown state: 2", "Player statistic ... cannot be initialized before players are set up",
  "Legacy game module not found", or a blank/erroring game page on first load. Also use
  proactively right after creating the PHP/TS scaffolding, before the user deploys, to
  pre-empt these errors. Complements the bga-dev skill (which covers full development);
  this one is the narrow "first successful table" smoke-test path.
---

# BGA Table Bootstrap

Getting a brand-new modern-framework BGA project to *create and load a table* is a
distinct milestone from "implementing the game." Almost none of it is about your
rules — it's about satisfying the framework's setup contract. The failures are
fatal (the table won't create at all) and they surface **one at a time, in
execution order**: you fix one, redeploy, and the next one appears. That makes the
bootstrap phase feel like whack-a-mole unless you front-load the whole checklist.

This skill is that checklist. Work it top-to-bottom *before* the first deploy and
you skip the whack-a-mole. Already mid-error? Jump to the matching row in
[Failure → fix](#failure--fix) and then walk the rest of the list to catch the
errors waiting behind it.

> This is the narrow "first table loads" path. For full development — game
> elements, real state machines, notifications, publishing — use the **bga-dev**
> skill. The `references/04-server.md` and `05-client.md` files there are the
> authoritative source for the APIs referenced below.

## Mental model: what happens when a table is created

The framework runs a fixed sequence. Each step has a precondition your code must
satisfy, and a specific fatal if it doesn't:

```
1. Framework detects the game module      → needs Game.php in modules/php/ extending Table
2. Game::__construct runs                  → state labels must be registered before use
3. setupNewGame($players, $options) runs   → YOU populate players, activate one, return first state
4. Framework dispatches into that state    → an ACTIVE_PLAYER state needs an active player + a zombie()
5. Client loads: getAllDatas → setup()     → must return plain data; setup() renders into #game_play_area
```

The rest of this skill is one checklist item per precondition.

## The bootstrap checklist

Go in order — this is the same order the framework executes, so it's the order
errors appear.

### 1. Framework detection

- [ ] `Game.php` lives in `modules/php/`, **not** the project root.
- [ ] It declares `class Game extends \Bga\GameFramework\Table` (the modern base),
      not the legacy `BgaGameBase` / `Table` from the old framework.
- [ ] No legacy files driving the build: `states.inc.php`, `material.inc.php`,
      `gameoptions.inc.php`, `.tpl` templates. Their presence signals an
      old-framework project and the modern loader won't engage.

**Why:** the framework picks modern vs. legacy by inspecting these. Get it wrong
and you get `Legacy game module not found: <project>.game.php` — misleading,
because the real cause is "modern detection failed."

### 2. State labels registered before use

- [ ] Every label you read/write with `getGameStateValue`/`setGameStateValue`
      (or `setGameStateInitialValue`) is registered in `Game::__construct` via
      `self::initGameStateLabels(['my_label' => 10, ...])`. The int IDs are
      arbitrary but must be unique within the game.

**Why:** an unregistered label throws `Unknown gamestate label: <name>` the
moment you touch it — often during `setupNewGame`, which masquerades as a setup
bug.

### 3. `setupNewGame` — the required boilerplate

`Bga\GameFramework\Table::setupNewGame()` is **abstract**. The framework does
*not* create players, assign turn order, or activate anyone for you. Your override
must do all of it, in this order:

- [ ] `DELETE FROM player`, then `INSERT` one row per entry in `$players`
      (id, color, canal, name, avatar).
- [ ] `reattributeColorsBasedOnPreferences($players, $colors)`.
- [ ] `reloadPlayersBasicInfos()`.
- [ ] **`activeNextPlayer()`** — sets the opening active player. This is the one
      that's easy to forget and there's no compile-time hint.
- [ ] Initialize stats *after* the player rows exist:
      `$this->playerStats->init(['stat_a', ...], 0)` (array form, no per-player
      loop), `$this->tableStats->init([...], 0)`.
- [ ] `return YourFirstState::class;` — tells the framework where to dispatch.

**Why each bites:**
- Skip player creation → `Player statistic ... cannot be initialized before
  players are set up` (the stat call, not the players, throws — misleading).
- Skip `activeNextPlayer()` → the table creates, then dies dispatching into your
  active-player opening state with **`there is no more active player!`**.
- Skip the `return` → `Unknown state: 2` (framework's default next-state guess).
- Call `parent::setupNewGame(...)` → fatal "cannot call abstract method." There is
  no parent; do everything in your override.

### 4. Every active-player state has a `zombie()`

- [ ] Each state of `StateType::ACTIVE_PLAYER` or `MULTIPLE_ACTIVE_PLAYER` defines
      a `zombie(int $playerId)` method that **advances the game** — it returns the
      next state class (or calls `nextState(...)` in the string-transition style).
- [ ] `StateType::GAME` states (automatic, no player input) do **not** need one and
      should not have one.

A zombie player has quit or timed out; the framework calls this to keep the table
moving so the other players aren't stuck. The safe default is "do what passing
does" — return the same state your `actPass()` would.

**Why:** without it, table creation fails immediately with `A zombie function is
needed for state class <...>`. This fires at *create* time, not when someone
actually goes zombie — the framework validates up front.

```php
// Active-player state, return-class transition style:
public function zombie(int $playerId): string
{
    return RoundEnd::class; // same target as actPass(): a zombie just passes
}
```

### 5. Client load: `getAllDatas` + `setup()`

- [ ] `getAllDatas(int $currentPlayerId)` returns **only plain arrays/scalars** —
      no objects, no nulls dereferenced. A leaked PHP warning or non-serializable
      value yields `Wrong formatted data from main BGA website` on render.
- [ ] The JS/CSS bundle is named after the **project folder** (`<project>.js` /
      `<project>.css`), not the friendly game name — BGA autoloads by convention.
- [ ] `setup(gamedatas)` runs without throwing. For the very first table, render a
      trivial **smoke-test element** into `#game_play_area` so a blank page tells
      you "render didn't run" vs. "render ran but drew nothing":

```ts
const playArea = document.getElementById("game_play_area");
if (playArea) {
  const el = document.createElement("div");
  el.id = "setup_test";          // tag it so removal later is a clean grep
  playArea.appendChild(el);       // style it visibly in <project>.css
}
```

## Failure → fix

Jump-table for when a table won't create or load. After applying a fix, **keep
walking the checklist** — these errors queue up behind each other.

| Fatal | Checklist item | One-line fix |
|---|---|---|
| `Legacy game module not found` | §1 | Move `Game.php` to `modules/php/`; extend `Bga\GameFramework\Table`. |
| `Unknown gamestate label: X` | §2 | Register `X` in `initGameStateLabels` in the constructor. |
| `Player statistic ... before players are set up` | §3 | Populate the `player` table before any `stats->init`. |
| `there is no more active player!` | §3 | Add `$this->activeNextPlayer()` after `reloadPlayersBasicInfos()`. |
| `Unknown state: 2` | §3 | `return YourFirstState::class` from `setupNewGame`. |
| `cannot call abstract method ...setupNewGame` | §3 | Remove the `parent::setupNewGame()` call. |
| `A zombie function is needed for state class X` | §4 | Add `zombie(int $playerId)` to state `X` that advances the game. |
| `Wrong formatted data from main BGA website` | §5 | Make `getAllDatas`/`getArgs` return plain serializable data; hunt PHP warnings in the debug bar. |
| Blank game page, no error | §5 | Confirm bundle is named `<project>.js`; add a visible smoke-test element in `setup()`. |

## Verifying without guessing

- **TypeScript is locally verifiable** — run `npm run typecheck && npm run build`
  before deploying. This catches client-side breakage cheaply and is worth doing
  on every change.
- **PHP usually is *not* locally verifiable** — most BGA dev setups have no local
  PHP, so `setupNewGame`/state changes can't be linted or unit-tested on your
  machine. The real test is **creating a Studio table**. Treat "deploy + create a
  fresh table" as the verification step for any PHP change, and read the per-table
  debug bar ("BGA request & SQL logs") when something fails — that's where leaked
  warnings hide.
- Because PHP errors only surface at table-create time, front-loading this whole
  checklist before the first deploy is strictly cheaper than discovering the
  errors one redeploy at a time.

## Done when

A fresh table with 2+ players creates, the page loads, and your smoke-test element
is visible in the play area. That's the bootstrap milestone — now switch to the
**bga-dev** skill (Phase 4 onward: schema, real states, actions, notifications) to
build the actual game.
