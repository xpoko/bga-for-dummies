# BGA Dev: Framework Architecture & File Structure

## Project File Map

```
mygame/
├── img/                        # All game graphics (sprites, board, box art)
├── doc/                        # Rules PDFs, FAQs — exclude from git
├── misc/                       # Small data files up to 1MB — checked in
├── modules/
│   └── php/
│       ├── States/             # One PHP class per game state (modern framework)
│       │   ├── PlayerTurn.php
│       │   ├── EndGame.php
│       │   └── ...
│       └── Material.php        # Static game data (names, tooltips, rules)
├── src/                        # TypeScript source (compiled → mygame.js)
│   ├── mygame.ts               # Main game class
│   ├── states/                 # One TS class per state handler
│   └── ...
├── dbmodel.sql                 # Database schema
├── gameinfos.inc.php           # Game metadata (players, duration, BGG id...)
├── gameoptions.json            # Game variant options (modern — not .inc.php)
├── gamepreferences.json        # Per-user UI preferences
├── stats.json                  # Statistics definitions (modern — not .inc.php)
├── mygame.js                   # Compiled TypeScript output (synced to BGA)
├── mygame.css                  # (or .scss compiled output)
└── modules/php/Game.php         # Main server-side game class (NOT at project root)
```

> **No `.tpl` files.** No `material.inc.php`. No `stats.inc.php`. No `gameoptions.inc.php`.  
> No `states.inc.php`. These are all old-framework patterns. Modern framework uses PHP classes and JSON files.

### File-Location Gotchas (Modern Framework)

- **`Game.php` MUST live at `modules/php/Game.php`**, not at the project root.
  If BGA reports `Legacy game module not found: <project>.game.php`, the file
  is missing or in the wrong place — the framework fell back to legacy mode.
- **Game class MUST extend `Bga\GameFramework\Table`** (not the legacy
  `BgaGameBase`). Importing `BgaGameBase` silently keeps you on the legacy code
  path even if the file location is correct.
- **PHP namespace must be `Bga\Games\<projectname>`** where `<projectname>` is
  the studio project folder name exactly (e.g. `waddlexpoko`, NOT a friendly
  display name). State classes go under `Bga\Games\<projectname>\States`.
- **JS / CSS file names must match the project name**: `<projectname>.js`,
  `<projectname>.css`. The framework autoloads them by convention. The bundle
  must also assign `window.<projectname> = new <Class>()` so the framework
  finds the entry instance.
- **`gameinfos.inc.php` is still accepted** alongside the newer `.jsonc` form
  the walkthrough mentions — both work today.

---

## Four-Layer Architecture

Every game element (card, token, meeple) leaves a footprint in all four layers:

```
┌─────────────────────────────────────────────────────┐
│  Material.php  (static data — server, sent to client)│
│  Names, tooltips, rules, types — never per-instance  │
├─────────────────────────────────────────────────────┤
│  dbmodel.sql + DB  (dynamic state — server only)     │
│  Which instance is where, in what state              │
├─────────────────────────────────────────────────────┤
│  CSS/SCSS  (visual appearance — client)              │
│  Sprite background positions, sizes, colors          │
├─────────────────────────────────────────────────────┤
│  TypeScript  (DOM + interaction — client)            │
│  HTML generation, event handlers, animation          │
└─────────────────────────────────────────────────────┘
```

**Rule of thumb:** If the data never changes during a game, it belongs in `Material.php` (not the DB). If it changes, it belongs in the DB.

---

## Request/Response Flow

```
Player clicks something
    ↓
TS state handler: bga.actions.performAction('action_doThing', { id })
    ↓
HTTP POST → PHP state class method: action_doThing(#[JsonParam] $data)
    ↓
PHP: validate input → update DB → $this->notifyAllPlayers('tokenMoved', [...])
    ↓
PHP: $this->gamestate->nextState('next')
    ↓
Client receives notification → setupPromiseNotifications handler runs → animation
    ↓
Client receives new state → state handler onEnteringState fires
```

---

## Key Framework Objects (Client Side)

| Object | Purpose |
|---|---|
| `this.bga` | Root framework access object |
| `this.bga.gameArea.getElement()` | Main game board container div |
| `this.bga.playerPanels.getElement(playerId)` | Individual player panel div |
| `this.bga.statusBar.addActionButton(label, fn)` | Add button to action bar |
| `this.bga.actions.performAction(name, args)` | Send action to server (returns Promise) |
| `this.bga.notifications.setupPromiseNotifications({...})` | Register notification handlers |
| `this.bga.states.register(name, handler)` | Register a state handler class |

---

## Key Framework Objects (Server Side — PHP)

| Method | Purpose |
|---|---|
| `$this->DbQuery($sql)` | Run a SQL query |
| `$this->getCollectionFromDb($sql)` | Query → associative array |
| `$this->notifyAllPlayers($type, $msg, $data)` | Broadcast notification to all |
| `$this->notifyPlayer($id, $type, $msg, $data)` | Private notification to one player |
| `$this->gamestate->nextState($transition)` | Move to next state |
| `$this->getActivePlayerId()` | Current active player |
| `$this->getCurrentPlayerId()` | Player making the HTTP request |
| `$this->loadPlayersBasicInfos()` | All players: id, color, name, score |
| `$this->activeNextPlayer()` | Advance to next player in order |

---

## JSON Config Files

### `stats.json`
```json
{
  "table": {
    "turns_number": { "id": 10, "name": "Number of turns", "type": "int" }
  },
  "player": {
    "turns_number": { "id": 10, "name": "Number of turns", "type": "int" },
    "game_vp_total": { "id": 11, "name": "Total VP", "type": "int" }
  }
}
```
Player stats that should auto-initialize must be prefixed with `game_`.

### `gameoptions.json`
Replaces old `gameoptions.inc.php`. Defines selectable variants shown at table creation.

### `gamepreferences.json`
Per-user preferences (e.g. colorblind mode, animation speed). Not game-variant options.

---

## Built-in Framework Components

| Component | Type | Purpose |
|---|---|---|
| `Deck` | PHP class | Card management: draw, shuffle, move, count |
| `Counter` | JS class | Animated incrementing/decrementing counter |
| `Stock` | JS class | Display a set of elements at a fixed position |
| `Zone` | JS class | Area where elements gather and lay out automatically |
| `Scrollmap` | JS class | Scrollable infinite game area |
| `Draggable` | JS class | Drag-and-drop interaction |
| `PlayerCounter` | PHP class | Counter tied to player (e.g. score, resources) |
| `TableCounter` | PHP class | Counter tied to game table (e.g. round number) |

---

## Inspecting Other BGA Games

The wiki highlights this as underused:
- **Right-click any element on any BGA game → Inspect Element** to see its exact HTML structure and CSS
- Add yourself as **read-only** to any BGA project from its project page to see full source
- BGA GitHub convention: `github.com/<name>/bga-<game>` — many projects are public
- Reference games for patterns: Nippon, Dungeon Petz, Lewis & Clark, Russian Railroads (all use the token DB pattern)
