---
name: board-game-mechanism-implementation
description: Use when adapting a physical board game's mechanism into web/digital form — turning rulebook prose into state, validations, and UI. Complements the bga-dev skill (framework plumbing) by focusing on game-logic translation. Triggered by implementing turn order, placement rules, scoring, or any rule-driven mechanism in a board-game project, regardless of platform (BGA, custom Next.js, anything else).
---

# Board Game Mechanism Implementation

This skill covers the *hard* problem of digital board-game adaptation: not the framework setup, but the moment you sit down with a rulebook and have to decide how a printed mechanism becomes code.

It's organized in two parts, used in roughly this order:

1. **Rulebook interpretation** — read the rules into a structured model *before* writing code.
2. **Mechanism implementation** — turn the structured model into state, validations, and UI.

You can revisit (1) at any point during (2); good interpretations often become clearer once you've tried implementing them.

## When to Use

- Implementing any rule-driven mechanism: turn order, placement, scoring, drafting, area control, hand management.
- Working out where the digital adaptation should *deviate* from the rulebook for ergonomics.
- Onboarding to an existing adaptation and trying to understand why a mechanism was built the way it was.

## When NOT to Use

- Framework setup, file layout, deployment — that's [`bga-dev`](#) territory.
- Pure rendering / animation work that doesn't touch rules — that's a UI skill.
- One-off bug fixes that don't change the mechanism model.

## The Two Parts

### Part 1 — Rulebook Interpretation

How to read a printed rulebook into a structured model:

→ [`parts/rulebook-interpretation.md`](parts/rulebook-interpretation.md)

Topics:
- Rulebook → structured model (entities, actions, states, transitions, victory conditions, edge cases)
- Identifying ambiguities and house-rule choices
- Mapping rules to UI/UX affordances
- Negotiating fidelity vs digital ergonomics

### Part 2 — Mechanism Implementation

How to translate that structured model into code:

→ [`parts/mechanism-implementation.md`](parts/mechanism-implementation.md)

Topics:
- Universal token-location encoding (one schema, all movable pieces)
- Pure scoring functions (no DB, take state in, return VP out)
- Centralized grid math (adjacency, coordinate conversion, pixel mapping)
- State machines: game vs player states
- Notification-driven UI updates (server emits, client reacts)
- Invariants encoded in data, not flags

## Case Studies

The Waddle BGA project is the worked example for this skill. Two case studies show the format in action:

- [`case-studies/waddle-turn-order.md`](case-studies/waddle-turn-order.md) — marble-track encoding (turn-order via positions, no separate played bitmask)
- [`case-studies/waddle-hex-placement.md`](case-studies/waddle-hex-placement.md) — active-hex activation gating placement + adjacency validation

For full mechanism diaries with rulebook → code mappings, see the [Waddle diary](#).

## Cross-Skill References

- [`bga-dev`](#) — Board Game Arena framework specifics: file layout, state machine plumbing, deployment. If the question is "how do I wire a notification handler" or "where does `gameinfos.inc.php` go", read `bga-dev` first.
- [`diary-logging-implementation`](#) — once you've implemented a mechanism, log how you did it. The diary captures the "why we picked X" that this skill helps you decide.

## Order of Operations (for a new mechanism)

1. **Read the rules** — twice. Make a list of every entity (player, token, hex, deck, …), every action (place, draw, score, …), and every condition that triggers a state change.
2. **Sketch the structured model** — entities → actions → states → transitions → victory conditions → edge cases. Output is a one-page rules spec.
3. **Scan for ambiguity** — every rule that could be interpreted two ways. Pick one, note the interpretation alongside the rule.
4. **Map rules to UI** — for each legal action, what affordance does the player need? What feedback does the system give when the action is illegal?
5. **Decide where to deviate** — is anything in the rulebook bookkeeping that the computer should do automatically? Anything that assumes spatial reasoning the screen flattens?
6. **Implement** — using the patterns in Part 2.
7. **Write the diary entry** — using the [`diary-logging-implementation`](#) skill.

Steps 1–5 are Part 1. Step 6 is Part 2. Step 7 is the diary skill.
