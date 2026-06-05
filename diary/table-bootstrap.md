## Table Bootstrap

**One-liner.** Getting a brand-new modern-framework BGA project to *create and load a table* — populate the player rows, activate the first player, give every active-player state a `zombie()`, and render a smoke-test element — is a distinct milestone from "implementing the game", and its failures are fatal table-creation errors that surface one at a time in the framework's execution order.

This is a *cross-game* entry: it documents framework plumbing, not any one game's rules. The canonical, actionable form is the [`bga-table-bootstrap`](../.claude/skills/bga-table-bootstrap/SKILL.md) skill — a top-to-bottom checklist. This diary records *why* that checklist exists and the journey that produced it.

## Source files

There is no single load-bearing file — this entry is about a *contract* the framework imposes on `setupNewGame` and your state classes. The shapes below are representative; the first project to hit and fix all of them was **Restoration** (its `modules/php/Game.php` and `modules/php/States/PlayerTurn.php`).

- [`.claude/skills/bga-table-bootstrap/SKILL.md`](../.claude/skills/bga-table-bootstrap/SKILL.md) — the checklist + failure→fix jump-table this entry distills.
- [`bga-dev`](../.claude/skills/bga-dev/SKILL.md) `references/04-server.md` — authoritative `setupNewGame` boilerplate and state-machine API.

## Rulebook → code

There is no rulebook clause here — the external constraint is the **modern BGA framework's setup contract**. Three clauses of it bite during table creation, in this order:

> *1. `Bga\GameFramework\Table::setupNewGame()` is abstract — the framework does not create players, assign turn order, or activate anyone.*

Your override must `DELETE`/`INSERT` the `player` rows from `$players`, `reattributeColorsBasedOnPreferences`, `reloadPlayersBasicInfos`, **`activeNextPlayer()`**, init stats (array form, after players exist), and `return YourFirstState::class`. Miss the activation and the table creates, then dies the instant the framework dispatches into an active-player opening state.

> *2. Every active-player state needs a zombie function.*

A state of `StateType::ACTIVE_PLAYER` (or `MULTIPLE_ACTIVE_PLAYER`) must define `zombie(int $playerId)` that *advances* the game — returning the next state class (or calling `nextState(...)`). The framework validates this at **create** time, not when a player actually goes zombie. A zombie just does what passing does:

```php
public function zombie(int $playerId): string
{
    return RoundEnd::class; // same target as actPass(): a zombie passes
}
```

`StateType::GAME` states (automatic, no player input) never go zombie and need no handler.

> *3. The client must receive serializable setup data and render into the play area.*

`getAllDatas` must return plain arrays/scalars (a leaked PHP warning or a stray object yields `Wrong formatted data from main BGA website`), the JS/CSS bundle must be named after the **project folder**, and `setup()` should drop a visible smoke-test element into `#game_play_area` so a blank page distinguishes "render didn't run" from "render ran, drew nothing".

## Design choices

- **A checklist ordered by execution sequence, not by topic.** The alternative — a flat "common errors" list (which the bga-dev skill already has) — doesn't capture that these errors *queue up behind each other*. Ordering the checklist the way the framework runs means fixing item N reliably surfaces item N+1, so a reader mid-error can jump in and walk forward. That ordering *is* the insight; a topic-sorted reference loses it.
- **A visible throwaway square for the first render, not an empty `setup()`.** A no-op `setup()` gives no signal the render pipeline works, so the first real render bug is indistinguishable from a build/deploy failure. A console log is weaker than a DOM element you can actually see — the square is a true end-to-end smoke test (build → deploy → load → DOM). Tag it with an id so removal later is a clean two-spot grep.
- **Zombie returns the same state as a pass.** A disconnected player must never stall the table; passing is the minimal "advance the game" action, so the zombie mirrors `actPass()` rather than inventing bespoke recovery logic this early.
- **Activate via `activeNextPlayer()`, not `setActivePlayer($firstId)`.** `activeNextPlayer()` walks the framework's next-player table, so it stays correct once a real turn order exists; hard-coding the first id would silently rot.

## Snags & refinements

The whole reason this entry (and the skill) exists: on a freshly-scaffolded project the fatals appeared as a **sequence**, each fix revealing the next.

- **Zombie fatal first.** `createGame` threw *"A zombie function is needed for state class …PlayerTurn"*. The scaffold's active-player state had no `zombie()`. Fix: add one returning the pass target. Surprising because nobody had gone zombie — the framework validates up front.
- **"There is no more active player!" second.** With the zombie in place the table got further, then failed on load: `setupNewGame` built the player table but never called `activeNextPlayer()`, so dispatching into the active-player opening state found no active player. Fix: add `activeNextPlayer()` after `reloadPlayersBasicInfos()`. This is the easiest step to forget — there's no compile-time hint, and the scaffolding looked complete without it.
- **No local PHP lint.** Most BGA dev setups have no local PHP, so `setupNewGame`/state changes can't be syntax-checked or unit-tested on your machine — the real verification is *creating a Studio table*, which is exactly when these fatals fire. That's what makes front-loading the whole checklist before the first deploy strictly cheaper than discovering the errors one redeploy at a time. The TypeScript side, by contrast, *is* locally verifiable (`npm run typecheck && npm run build`) and caught an unused-field warning when the smoke-test edit removed the only read of the stored gamedatas.

## Cross-refs

- [[simulation-testing]] — also a Studio-phase concern; both rely on `getAllDatas` reporting `is_studio` and on the active-player state machine being sound first.
- The [`bga-table-bootstrap`](../.claude/skills/bga-table-bootstrap/SKILL.md) skill is the actionable companion to this entry — read it when *doing* the bootstrap; read this when you want the *why*.
