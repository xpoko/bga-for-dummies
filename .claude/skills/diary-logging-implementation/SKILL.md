---
name: diary-logging-implementation
description: Use when documenting how a non-trivial feature or mechanism was implemented — captures the journey (alternatives considered, dead-ends, refinements) not just the final shape. Triggered by "document this", "write up how we built X", finishing a mechanism worth preserving for future readers, or starting a diary for a new project. Not for fix-a-typo work, not for API reference docs.
---

# Diary Logging for Implementation Work

A diary entry records **how** a mechanism was built, not just **what** it does now. The code already shows what; the comments and tests show what *should* hold. Neither shows the alternatives you rejected, the snag you hit on the third refactor, or the rule clause that the implementation is really tracking.

This skill prescribes a format we developed on the [Waddle](#) BGA project. It's tuned for board-game adaptations but the format works for any project where multiple mechanisms compose into a working system.

## When to Use

- Finishing a non-trivial mechanism worth preserving for future readers.
- Starting a new project and want a stable home for design memory.
- Onboarding a collaborator — diary entries beat verbal "let me explain the code" because they outlive the conversation.
- Returning to a mechanism after a long gap and you want to *not* re-derive context.

## When NOT to Use

- One-line bug fixes (the commit message is the diary).
- API reference docs (use OpenAPI / TSDoc / etc.).
- TODO lists or working-session notes (use TodoWrite or memory).
- Anywhere a future reader could derive the answer in under a minute from code + tests.

## File Layout

```
<project>/diary/
  README.md                    Index + reading order + code map
  glossary.md                  Shared terms across entries
  <mechanism-1>.md             One entry per mechanism
  <mechanism-2>.md
  ...
```

Each mechanism gets one file. File names are kebab-case nouns (`turn-order.md`, `hex-grid.md`, `token-placement.md`), not verbs (don't write `implementing-X.md`) and not dated (the diary is *organized by mechanism*, not by session).

For personal side-notes that don't belong in a public repo, mirror the structure under `~/.claude/projects/<project>/diary/INDEX.md` and link back to the in-repo canonical entries.

## Entry Format

Every entry follows the same six sections, in this order. Sections can be short (one paragraph) but **none are optional**.

### 1. One-liner

A single sentence — present tense, describing what this mechanism does at the played game / running application.

> *Two vertical "turn tracks" hold one marble per player; whichever marble sits lowest plays next. Placing rotates your marble to the top of the active track and shifts every other marble down by one. Scouting moves your marble to the bottom of the other track, deferring you.*

If you can't compress the mechanism into one sentence, the entry is probably trying to cover two mechanisms — split it.

### 2. Source files

A bullet list of code paths with **line numbers**. Use markdown link syntax `[file.ts:42-50](file.ts#L42-L50)` so readers can click through. Order: the most "canonical" file first (the one a new reader should open first), then supporting files.

Don't list every file in the codebase that touches the mechanism — list the *load-bearing* ones. Three to seven entries is normal.

### 3. Rulebook → code

For board games: quote the rule (briefly — fair-use snippet, not the whole rulebook) then describe how it maps to state, data, and UI.

For non-game projects: substitute the spec, design doc, or user requirement. The pattern is the same — quote the *external* constraint, then describe the internal mapping.

This section is where you make the implicit explicit. Anything the rules *imply* but don't state (e.g. "the last player must place if ice remains") goes here, not buried in code comments.

### 4. Design choices

Lead with the alternatives you considered. For each, one line on why you rejected it. Then describe the chosen approach.

You're documenting **decisions**, not features. If there was only one obvious way to do it, that itself is worth saying ("we picked X because no alternative came to mind"). If there were three plausible paths, name all three.

### 5. Snags & refinements

What got wrong on first pass and what you changed. Mandatory: **at least one entry** in this section, even if minor. If the first version really did work first try, write that — but it's almost never true on a mechanism worth diary-ing.

Examples of good snag entries:
- "BGA returns DB integer columns as strings, so `q + dq` concatenated; fix was explicit `Number()`."
- "Notification handler was wired through the params object — silently ignored; framework auto-discovers `notif_<name>` methods."
- "Scout-ahead started as a status-bar button; moved onto the opposite turn track itself because it puts the affordance next to the marble you'd be moving."

### 6. Cross-refs

Link to related entries with `[[entry-name]]`-style references at the bottom. Liberal — a forward reference to an entry that doesn't exist yet is a *note to write it*, not a broken link.

## Voice and Tone

- **First-person plural** ("we built", "we considered", "we picked"). The diary is a team artifact, even if the team is one person + a future self.
- **Past tense** for the journey ("we rejected X because…", "the first version failed because…").
- **Present tense** for what the code currently does ("the handler reads X and writes Y").
- **Specific** over abstract. "BGA serializes table requests today" beats "concurrency is handled".
- **Concrete code paths**. If you find yourself writing "see the relevant file", grep for the file path and put it in.

## Required Ingredients

A diary entry **must** contain:

- [ ] At least one code-path link with line numbers.
- [ ] At least one quoted external constraint (rulebook clause, spec, user story).
- [ ] At least one rejected alternative in the design-choices section.
- [ ] At least one snag/refinement that happened after the first-pass implementation.
- [ ] At least one cross-ref to another entry (or a placeholder noting the entry doesn't exist yet).

If any of these is missing, the entry is incomplete — keep working.

## Anti-Patterns

| Anti-pattern | Why it's wrong | What to do instead |
|---|---|---|
| "We use X." (final-state only) | Loses the journey; reader can't tell which constraints actually drove the choice | Add "We considered Y and Z; picked X because…" |
| "On May 21 I added the rotation animation." | Diary is organized by mechanism, not by commit | Move into the snag/refinement section: "The rotation used to teleport; we now animate via `data-position` + CSS transitions." |
| Listing every file that touches the mechanism | Forces the reader to triage | List 3–7 load-bearing files; the canonical one first |
| Restating the rules verbatim | Adds no value over the rulebook | Quote *briefly*, then describe the **mapping** |
| One giant `implementation.md` covering everything | Unreadable; impossible to find anything | One file per mechanism; index from `README.md` |
| Skipping snags because "first pass worked" | Almost never true; if true, mention something subtle you almost got wrong | Even minor friction is worth a line — it's what future-you forgets |

## Case Study — Waddle

The Waddle project is the worked example for this skill. Read its diary as a model:

- [Waddle README](#) — entry index + code map
- Worked examples: turn-order, hex-grid, token-placement, placement-interaction, active-hex-activation

The Waddle entries use this exact format. If you're starting a diary on a new project, copy `templates/mechanism-entry.md` into your project's `diary/` and fill in the sections.

## See Also

- `templates/mechanism-entry.md` — fillable template for a new entry.
- `examples/waddle-turn-order.md` — canonical example, lifted from the Waddle diary.
- [`board-game-mechanism-implementation`](#) skill — when you're documenting *how to implement* board-game mechanisms, not just *that you did*.
