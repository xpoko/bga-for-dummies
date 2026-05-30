---
name: changelog
description: Write player-facing release notes for the Waddle BGA game. Use whenever the user asks for a changelog, release notes, a summary for alpha testers, "what changed for players", or wants to communicate recent updates to non-technical playtesters. Also use proactively after finishing a batch of player-visible UI/gameplay changes so testers have a digest to read before their next session.
---

# Waddle player-facing changelog

Waddle is a Board Game Arena adaptation. Its audience for release notes is **alpha testers and board-game players**, not developers. The job here is to translate recent code changes into a short, friendly digest the player can skim before their next game.

## Where to save

- Directory: `changelog/` at the project root. Create it if missing.
- Filename: `YYYY-MM-DD-summary.txt` (plain text, not markdown — testers paste these into Discord/email/etc. and rendered markdown asterisks look like noise). If multiple changelogs land on the same day, append a hyphenated suffix: `2026-05-20-summary.txt`, then `2026-05-20-summary-2.txt`, etc.
- One file per release/batch — never overwrite an old one.

## How to gather the changes

Pull from these sources, in order of priority:

1. **The current conversation.** If this run just implemented or merged a batch of changes, those are the source of truth — the conversation knows the player-facing intent that `git log` alone won't.
2. **Recent git history.** Find the cutoff commit by reading the trailer of the most recent file in `changelog/` — every changelog ends with a marker line of the form `last-covered-commit: <full-sha>`. Then run `git log --oneline <sha>..HEAD` and read the diff for anything that touched user-visible code (`waddle.css`, `src/`, state descriptions in PHP). If the trailer is missing (older file), fall back to `git log --since="<file date>"`.
3. **The previous changelog.** Read the most recent file in `changelog/` to anchor tone and avoid repeating items (same file you just read for the marker).

If the user has already given you the list of changes (e.g., "summarize these 12 things"), skip the git step and work from what they said — but still grep for any side effects they may not have mentioned (text strings, removed buttons, art tweaks).

### Marking the cutoff in the new file

Every changelog you write **must** end with this trailer, so the next run picks up cleanly from where you stopped:

```
last-covered-commit: <full SHA of HEAD at write time>
```

Get the SHA with `git rev-parse HEAD` right before you write the file. Put the line at the very end, after a blank line.

## What to include vs. skip

**Include — the player will notice it:**
- Visual changes (sizes, colors, hover states, new highlights)
- Flow changes (fewer clicks, new shortcuts, button moves)
- Status/prompt text changes
- New panel info, score displays, animations
- Auto-resolved situations the player no longer has to click through
- Bug fixes that previously made play feel wrong

**Skip — the player won't notice:**
- Refactors, helper extractions, type changes
- Test additions, CI changes
- Build/tooling changes
- Internal API renames

When in doubt, ask: "would an alpha tester mention this in their feedback?" If no, leave it out.

## Format

Plain text — no markdown syntax. Use setext-style underlines for headings (===== for the title, ----- for sections). No `**bold**`, no `*italic*`, no backticks; just words. Special glyphs that don't survive plain-text reading (⤢, →, etc.) should be spelled out ("the diagonal-arrows button", "becomes").

Use this exact template. The headings act as scanning anchors — testers should be able to skim and stop at whichever section matters to them.

```
Waddle update — <Month DD, YYYY>
================================

Quick summary of what's new for this session. Two sentences, no jargon.


What's new
----------

- <Short name>. One sentence on what's different from the player's seat. If a screenshot/visual matters, hint at where to look ("in the right-side panel", "next to the turn track").


Quality-of-life
---------------

- <Short name>. Same shape — change + where they'll see it.


Bug fixes
---------

- <Short name>. What used to happen, then what happens now.


Heads-up
--------

Anything testers should explicitly try out, watch for, or report feedback on. Skip this section entirely if there's nothing to flag.


last-covered-commit: <full SHA of HEAD>
```

Notes on layout:
- One blank line between bullets in the same section (bullets often run a couple of lines, so the breathing room helps).
- Two blank lines between sections.
- The underline row should be at least as long as the heading text above it.

Group items into the three sections by feel — "What's new" for additions, "Quality-of-life" for friction reductions, "Bug fixes" for corrections. Don't pad a section that has nothing in it; drop the heading instead.

## Voice

- Talk to the player, not about them. "You can now…" beats "Players can now…".
- Plain language. No "refactored", "endpoint", "state machine", "DOM", "TS", "PHP". Replace with what the player sees: "the placement screen", "the side panel", "the active hex".
- Short. The whole file should fit on one screen unless the batch is genuinely huge. Bullets over paragraphs.
- Specifics over generalities. "Marbles are now ~25% larger" beats "Marbles improved".
- Don't apologize or hedge. State the change.

## Tone calibration examples

Example 1 — good (player-focused):

  Available hexes glow without hover. The pool of placeable ice hexes around the active water number is now lit up by default, so you can see your options at a glance instead of sweeping the mouse around.

Example 1 — bad (developer-focused):

  Moved :hover::after { background } rule into base selector for .hex-ice.selectable-hex so the indicator no longer requires pointer interaction.

Example 2 — good:

  Double-penguin selector is now a toggle. Click the double-penguin pill once to arm a double placement; click it again to switch back to a single. No need to dig for the singles pill.

Example 2 — bad:

  onTypeClick now flips selectedType back to 'single' when the same 'double' is re-clicked, with refreshTypePillState() re-rendering the active class.

## Final check before saving

Before you write the file:

- Read it back as if you've never seen the code. Does each bullet describe an experience, not an implementation?
- Look for words a non-coder wouldn't use in casual conversation. Replace them.
- Make sure there are no leftover markdown markers — no `**`, no `#`, no backticks, no `[link](url)` syntax. Plain text only.
- Make sure the filename matches `YYYY-MM-DD-summary.txt` and the date is **today** (resolved from the system clock if you have it, otherwise from the user's most recent message), not the date of an old commit.
- Confirm the `last-covered-commit: <sha>` trailer is present at the end.
- Check that this changelog doesn't re-list anything covered in the previous file in `changelog/`.

Then write to `changelog/<date>-summary.txt` and tell the user the path.