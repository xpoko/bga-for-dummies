---
name: bga-dev
description: >
  Full development guide for creating Board Game Arena (BGA) game adaptations using the modern
  BGA Studio framework (2024+). Use this skill whenever the user asks about BGA Studio, writing
  a BGA game, implementing game logic in PHP/TypeScript for BoardGameArena, debugging a BGA project,
  setting up their BGA dev environment, understanding the BGA state machine, creating game elements,
  handling notifications, or publishing/releasing a BGA game. Also trigger for questions about
  specific BGA framework APIs: getAllDatas, setupNewGame, GameState classes, bga.actions.performAction,
  setupPromiseNotifications, or any file in a BGA project (dbmodel.sql, gameinfos.inc.php,
  stats.json, gameoptions.json, Material.php, etc.). This skill covers the Cwali game pipeline
  context (EXHIBITION, HABITATS, WADDLE) and should be used proactively for any BGA implementation work.
---

# BGA Studio Development Skill

This skill covers end-to-end development for the **modern BGA framework (2024+)**.

> ⚠️ **Framework version warning:** If you see `states.inc.php`, `material.inc.php`,
> `gameoptions.inc.php`, `.tpl` template files, or heavy Dojo usage in examples online —
> those are **old patterns**. This skill uses modern patterns exclusively.

---

## Quick Reference: Which File to Read

| You need help with… | Read |
|---|---|
| Dev environment, FTP sync, first project setup | `references/01-setup.md` |
| File structure, layer architecture, framework overview | `references/02-architecture.md` |
| Designing game elements, database schema, Material.php | `references/03-game-elements.md` |
| PHP: setupNewGame, getAllDatas, state machine classes | `references/04-server.md` |
| TypeScript: setup(), state handlers, actions, notifications | `references/05-client.md` |
| Pre-release checklist, alpha, post-release updates | `references/06-publishing.md` |

---

## The BGA Stack at a Glance

BGA games run across two layers that must stay in sync:

```
Browser (TypeScript/JS + CSS)
  ↕  HTTP/notifications
BGA Server (PHP + MySQL)
```

Every game feature touches **both layers**. A typical feature implementation path:

1. PHP state class exposes an action via `#[PossibleAction]`
2. Client state handler calls `bga.actions.performAction()`
3. PHP validates, updates DB, sends a notification
4. Client `setupPromiseNotifications` handler animates the result

---

## Development Phase Map

Work through phases in order. Each phase builds on the previous.

```
Phase 0  →  Project setup, FTP sync, version control, smoke test
Phase 1  →  Assets (graphics, docs), gameinfos, box art
Phase 2  →  Scope reduction + game element taxonomy
Phase 3  →  UI layout & game graphics (CSS/TS, board, slots, tokens)
Phase 4  →  Database schema (dbmodel.sql)
Phase 5  →  Game setup (setupNewGame → setupNewGameTables)
Phase 6  →  getAllDatas + client setup() sync
Phase 7  →  State machine (PHP state classes)
Phase 8  →  User input (click handlers → performAction → PHP action)
Phase 9  →  Notifications & animation
Phase 10 →  Wrap-up (zombie, stats, logs, tiebreaker, tooltips)
Phase 11 →  Alpha & publishing
```

---

## Mandatory Prereqs Before Writing Any Real Game Code

The BGA wiki is explicit about these. Skip them and you will lose time:

1. **Read** `en.doc.boardgamearena.com/Studio` (overview)
2. **Complete at least one tutorial game** — Tutorial Reversi is recommended (maintained by the BGA team, closest to modern implementation)
3. **Set up FTP auto-sync** before writing a single line — manual file copying is a non-starter
4. **Confirm the game license exists** at `studio.boardgamearena.com/licensing` before starting — unlicensed projects can never be published

---

## Essential Links

| Resource | URL |
|---|---|
| BGA Studio portal | `studio.boardgamearena.com` |
| Available licenses | `studio.boardgamearena.com/licensing` |
| All projects list | `studio.boardgamearena.com/#!projects` |
| Wiki docs index | `en.doc.boardgamearena.com/Studio` |
| Community Discord | `discord.gg/YxEUacY` |
| Developers forum | `forum.boardgamearena.com/viewforum.php?f=12` |
| Pre-release checklist | `en.doc.boardgamearena.com/Pre-release_checklist` |
| Shared code repo | `github.com/elaskavaia/bga-sharedcode` |
| TypeScript template | `github.com/NevinAF/bga-ts-template` |

---

## Common Studio Errors → Root Causes

| Error message | Root cause | Fix |
|---|---|---|
| `Legacy game module not found: <project>.game.php` | Modern framework detection failed. Either `Game.php` is at project root (must be in `modules/php/`), or it extends the legacy `BgaGameBase` instead of `Bga\GameFramework\Table`. | Move to `modules/php/Game.php`, change `class Game extends Table`. |
| `Unknown gamestate label: <name>` | A `setGameStateValue` / `getGameStateValue` was called for a label that wasn't registered. | In `Game::__construct`, call `self::initGameStateLabels(['<name>' => 10, ...])` with arbitrary unique int IDs. |
| `Player statistic <name> cannot be initialized before players are set up.` | `Bga\GameFramework\Table::setupNewGame()` is **abstract**; the framework does NOT auto-populate the `player` table. You called `playerStats->init` before doing it yourself. | Add the boilerplate `DELETE FROM player; INSERT ...; reattributeColorsBasedOnPreferences; reloadPlayersBasicInfos` at the top of your `setupNewGame`. |
| `Unknown state: 2` | The framework needs to know which state to enter after `gameSetup` (state 1). Default target is state 2, which probably doesn't exist in your numbering. | Have `setupNewGame` (or `setupNewGameTables`) `return YourFirstState::class;`. |
| `Wrong formatted data from main BGA website` (during page render) | A PHP warning leaked into the JSON response, OR `getAllDatas`/state's `getArgs` returned non-serializable data. | Hunt warnings in the per-table debug bar's "BGA request & SQL logs". Make sure `getArgs` doesn't dereference null, and that `getAllDatas` returns plain arrays/scalars only. |
| `Cannot call abstract method Bga\GameFramework\Table::setupNewGame()` | You called `parent::setupNewGame(...)`. It's abstract; there's no parent to call. | Remove the parent call; do all setup in your override. |
| Stat init signature mismatch in modern API | Modern `playerStats->init` takes an **array** of stat names, not one stat at a time, and **no per-player loop**. | `$this->playerStats->init(['stat_a', 'stat_b', ...], 0);` |
| Project name mismatch on JS / CSS load | BGA autoloads `<projectname>.js` and `<projectname>.css` by convention. Files named after the friendly game name (e.g. `waddle.js` for project `waddlexpoko`) won't load. | Rename files to match the project folder name. Bundle must also `window.<projectname> = new Class()` so the framework finds the entry. |

---

## Top 10 Mistakes to Avoid

1. **Not setting up FTP auto-sync first** — ruins your workflow
2. **Skipping the tutorial** — you will not understand framework conventions
3. **Starting without a confirmed license** — wasted work if rejected
4. **Using old framework patterns** (`.tpl`, `states.inc.php`, `dojo.subscribe`) — incompatible with new framework
5. **Committing publisher art or SFTP passwords to GitHub** — BGA was hacked this way in 2020
6. **Over-engineering the DB schema** — keep it to 1–2 tables, string keys are fine
7. **Storing static data in DB** — names, tooltips, rules belong in `Material.php`, not the DB
8. **Designing 20+ server states** — push complexity to client-side states instead
9. **Starting with full scope** — implement reduced rules first; most projects die from scope
10. **Animating everything before game logic works** — animation is Phase 9, not Phase 1

---

## Cwali Game Pipeline Context

You are working on the **Cwali publisher pipeline** with games EXHIBITION, HABITATS, and WADDLE.
Collaborators introduced: Alan and Harry (prior devs on these titles).

- License status: confirm with Cwali / BGA admin before starting any project
- Request art files via the **"Request Art Files"** button on the studio license page immediately — it takes time to process
- Each game should be implemented with reduced rules first; all three are likely tile/token-based Euro games that map well to the generic token schema (`token_key`, `token_location`, `token_state`)

When implementing, load the relevant reference files from this skill for the phase you're working in.
