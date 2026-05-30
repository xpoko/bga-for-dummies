# BGA Dev: Publishing, Alpha & Post-Release

## Pre-Release Checklist

Work through this before requesting Alpha status. Full checklist:
`en.doc.boardgamearena.com/Pre-release_checklist`

### Gameplay Correctness
- [ ] All game rules implemented and tested
- [ ] Edge cases: 2-player variant, min/max player counts
- [ ] End game triggers work correctly
- [ ] Score calculation is correct, including tiebreaker
- [ ] Tiebreaker implemented via aux score field and described in `gameinfos.inc.php`

### Framework Requirements
- [ ] `getGameProgression()` implemented (returns 0–100 int representing game completion %)
- [ ] Zombie mode handled on **every** state class via `zombie()` method — must always advance the game, never leave it stuck
- [ ] Game statistics defined in `stats.json` with meaningful data (VP by source, turns, key actions)
- [ ] All player stats prefixed `game_` and initialized in `initStats()`

### UI & UX
- [ ] All UI strings wrapped in `clienttranslate()` (PHP) or `_()` (TypeScript) — every translatable string
- [ ] Tooltips on all image-based elements (tokens, cards, board slots) — not just text elements
- [ ] Game logs explain what happened to a player who wasn't watching — use `${player_name}` and `${token_name}` in notify messages
- [ ] Player panels show useful summary info (resources, card count, round score)
- [ ] Standard BGA star score counter used for the score display
- [ ] Interface is fluid-width (adapts when browser window changes)
- [ ] Tested with 2, 3, and max player counts

### Quality
- [ ] No JavaScript console errors
- [ ] No PHP errors in logs
- [ ] Game can be loaded mid-game (getAllDatas + setup() correctly reconstructs state)
- [ ] Full game can be played start to finish multiple times without errors
- [ ] No undocumented BGA framework functions used (they can be removed without notice)
- [ ] TypeScript compiled output is **not minified** (future maintainers must be able to read it)

### Art & Metadata
- [ ] `gameinfos.inc.php` filled with correct data from BoardGameGeek
- [ ] Game box art set in Game Metadata Manager
- [ ] Game starts and displays correctly in the game selector

---

## Requesting Alpha Status

1. Make sure the pre-release checklist is fully complete
2. Create a build via Studio Control Panel
3. Click **"Request ALPHA status"** — BGA admins will review your game
4. After approval, visit your game's page at `boardgamearena.com/gamepanel?game=<yourname>` and add:
   - Links to rules (all available languages)
   - Links to teaching videos (if any exist)
   - BGG page link
   - Official game website link (if any)
   - A written rules summary

The community can add these links too — you don't have to do it all yourself.

---

## Post-Alpha Updates

See full guide: `en.doc.boardgamearena.com/Post-release_phase`

Any changes after Alpha go through a review process. Key points:
- Bug fixes generally approved quickly
- Feature additions and variant additions need admin review
- Always test thoroughly in Studio before requesting a production push

---

## Level Up Features (Post-Alpha)

Once the base game is solid and published, add these in order of value:

### Game Variants
Define in `gameoptions.json`:
```json
{
  "my_variant": {
    "name": "Advanced Rules",
    "values": {
      "1": { "name": "Disabled", "tmdisplay": "Standard" },
      "2": { "name": "Enabled",  "tmdisplay": "Advanced" }
    },
    "default": 1
  }
}
```
Access in PHP: `$this->getGameStateValue('my_variant')`

### User Preferences
Define in `gamepreferences.json` for per-player UI choices (animation speed, colorblind mode, etc.) — these are not game variants, they don't affect game logic.

### Theming
- Replace the hardwood background
- Custom tooltip styling
- Custom fonts and colors
- Custom state prompt text styling

### Scoring Board
Use `bga-score-sheet` component for an animated end-game scoring breakdown.

### Polish Animations
- Dice rolling effects
- Card flip animations
- VP particle effects ("victory points evaporating")

Per BGA guidelines: **do not add sound effects** beyond what the framework already provides. BGA games are board games, not video games.

---

## BGA Studio Guidelines Summary

These govern what gets published. Violations = rejection.

| Rule | Detail |
|---|---|
| **Fidelity** | If a player knows the physical game, they must be able to play the adaptation with no learning |
| **No video game aesthetics** | Interface must look like the board game, not an arcade game |
| **Fluid layout** | Interface width must adapt — no fixed-width page breaking |
| **Player panels** | Summary info belongs in panels, not cluttering the main board area |
| **Score counter** | Always use BGA standard star counter for scores |
| **No undocumented APIs** | They can break without notice |
| **No minimized output** | Compiled JS must be readable for maintainability |
| **Both source + built files** | For TypeScript/SCSS projects, sync both source and compiled output |
| **No .tpl files** | Deprecated and forbidden for new projects |
| **Capitalized names** | Game name and option names must be capitalized |

---

## Getting Help

| Channel | Best for |
|---|---|
| `discord.gg/YxEUacY` | Quick questions, active community |
| `forum.boardgamearena.com/viewforum.php?f=12` | Longer discussions, searching past answers |
| BGA Studio wiki | Reference documentation |
| Support ticket | Access issues, maintainership of abandoned projects |
| Read-only project access | Learning from other games' source code |
