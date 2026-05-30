# BGA Dev: Setup & Project Creation

## Dev Environment

| Tool | Purpose | Notes |
|---|---|---|
| VSCode | Editor | Recommended; great TypeScript support |
| Node.js + npm | Build toolchain | Required for TypeScript compilation |
| Browser DevTools | Debugging | Chrome/Firefox — use Network, Console, Elements |
| FTP client (lftp) | File sync | See section below — set this up FIRST |
| PhpMyAdmin | DB inspection | Accessible from game debug bar in studio |
| Git | Version control | Set up before first real commit |
| Image editor | Sprite sheets | Needed for graphics prep |

---

## FTP Auto-Sync (Do This Before Anything Else)

BGA Studio does not have a live code editor. All code lives on your machine and syncs to BGA servers via SFTP.

**lftp mirror command:**
```bash
lftp sftp://myuser:mypassword@1.studio.boardgamearena.com:2022/ \
  -e "mirror --reverse --parallel=10 --delete \
      /local/path/to/myproject/ myproject/; exit"
```

- Use trailing `/` on both paths
- On subsequent syncs, unchanged files (same time + size) are skipped automatically
- For multi-developer projects, add `--ignore-time` to sync by size only
- Set this up as a watch script or IDE task so it runs on every save

**VSCode FTP-Sync extension** is a popular alternative to manual lftp — configure it to watch your project folder and sync on save.

> ⚠️ Never include your SFTP password in a file committed to GitHub. BGA Studio was compromised in June 2020 exactly this way.

---

## Creating a Project

1. Go to `studio.boardgamearena.com` → Control Panel → Create new project
2. Verify the license exists at `studio.boardgamearena.com/licensing` before starting
3. Check `studio.boardgamearena.com/#!projects` — if the game already exists, consider joining rather than duplicating (there are many abandoned projects)
4. Once created: **smoke test immediately**
   - Modify your `.ts` or `.js` client file to log "hello world"
   - Build, sync, reload the browser, confirm output appears
   - If this doesn't work, fix it now — don't proceed until sync is confirmed
5. Update project status in Control Panel ("development started", "waiting for license", etc.)

---

## Version Control

Set up Git **before** your first real code commit:

```bash
git init
git add .
git commit -m "Initial BGA project scaffold"
```

**`.gitignore` must exclude:**
```
# Publisher graphics (high-res originals — licensing issue)
img/original/
doc/rules*.pdf

# Never commit this
.ftpconfig
sftp-config.json
*.env
```

GitHub convention for BGA projects: `github.com/<yourname>/bga-<yourgamename>`

Also do periodic commits via Studio Control Panel (separate from GitHub — this is BGA's own version history).

---

## Starting a Game Session in Studio

1. Go to Studio → Select your game → Click **"Create"**
2. Specify the player count you want to test with
3. Click **"Express Start"**
4. To switch player perspectives: click the **red arrow** next to any player name — opens a new tab with that player's view. No login/logout needed.
5. Debug links appear at the **bottom of the game area** (no label):
   - **Go to game database** — PhpMyAdmin for your current game tables
   - **BGA request & SQL logs** — PHP output, all severities
   - **BGA unexpected exceptions logs** — warnings and errors only

**Save/restore game state:** Studio provides 3 save slots. Use these to lock in a hard-to-reproduce situation, then restore it repeatedly while debugging that specific case.

---

## Build Toolchain (TypeScript Projects)

Minimum `package.json` for a TypeScript BGA project:

```json
{
  "scripts": {
    "build": "tsc && npx rollup -c",
    "watch": "tsc --watch"
  },
  "devDependencies": {
    "typescript": "^5.0.0",
    "rollup": "^4.0.0"
  }
}
```

BGA does not compile TypeScript server-side — you must compile to JS locally and sync the compiled output. Both source `.ts` and compiled `.js` files should be synced (compiled file must not be minified, per BGA guidelines — future maintainers need readable code).

**TypeScript type-safe template:** `github.com/NevinAF/bga-ts-template`  
Provides full typing for all BGA/Dojo APIs, schema files for all major BGA JSON files (states, options, stats, preferences), and SCSS support. Highly recommended over starting from scratch.

---

## Obtaining Game Graphics

**If game is in Available Licenses:**
Click **"Request Art Files"** on the studio license page. This can take time — start it immediately and work other phases in parallel.

**Scavenger hunt (if needed):**
- BGA common assets (meeples, cubes, dice, standard cards): `en.doc.boardgamearena.com/Common_board_game_elements_image_resources`
- BoardGameGeek → Images → "Game Pieces" section
- Extract from rules PDF: `pdfimages rules.pdf output_prefix`
- Google `boardgame <name>` → Images

**Graphics prep:**
- Stitch individual files into sprite sheets, scale down for web
- Non-square pieces: export with transparency (PNG)
- Remove scoring track ring from board scans (BGA handles scoring separately)
- See full spec: `en.doc.boardgamearena.com/Game_art:_img_directory`

**No graphics yet?** Use CSS shapes + `::after` text content to fake pieces during development. The wiki explicitly endorses this approach. Ship layout and logic first; art comes later.
