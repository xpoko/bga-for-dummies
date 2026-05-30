# Part 1 — Rulebook Interpretation

Four sub-skills, used in order:

1. **Rulebook → structured model** — extract entities, actions, states, transitions, victory conditions, edge cases.
2. **Ambiguities & house-rule choices** — find unspecified edges; document the interpretation.
3. **Rules → UI/UX mapping** — what affordance does every legal action need?
4. **Fidelity vs digital ergonomics** — when (and why) to deviate from a literal translation.

## 1. Rulebook → Structured Model

Don't open an editor yet. First, write a one-page rules spec.

**The five extractions** (do them in order):

1. **Entities.** Every nameable noun the rules mention: player, penguin, hex, tile, water hex, marble, turn track, fish, supply, pool. For each, note: how many exist, who owns it, what state it carries.
2. **Actions.** Every verb the player can do. For each: preconditions ("on your turn", "if your supply is non-empty"), effects ("removes a token from supply, places it on hex"), and the rule that says "this is a complete turn".
3. **States.** What discrete configurations does the game pass through? At minimum: setup → player-turn (looped) → round-end (looped) → game-end. Some games add intermissions (mid-game reset, drafting phase).
4. **Transitions.** What triggers each state change? "Round ends when no ice hexes remain adjacent to the active water hex" is a transition rule.
5. **Victory conditions.** Including tie-breakers. Tie-breakers are usually buried in fine print and forgotten in the first implementation.

Then **edge cases**. Scan the rulebook again and list every "however", "except", "in this case". Each is a special path that has to be implemented.

**Output.** One markdown file: 5 sections (entities, actions, states, transitions, victory) plus an edge-cases appendix. Keep it under one page if you can — a long rules spec is a sign that you're documenting the rules, not modeling them.

## 2. Ambiguities & House-Rule Choices

Rulebooks are designed for humans negotiating in real-time. Digital adaptations don't negotiate — they decide. Every ambiguity in the rulebook becomes a design choice.

**Three flavors of ambiguity:**

- **Unspecified edges.** "The player who scores the most fish VP wins" — but what if two players tie? "Draw a card" — but what if the deck is empty? Look for the unsaid.
- **Simultaneous triggers.** "When a pool is completed, score it" + "When the last ice hex is filled, the round ends". What if a single placement triggers both? Whose effect resolves first?
- **Implicit timing.** "After placing, you may scout" — does "may scout" replace placing or follow it? When something is described in present-tense English, the order of operations is often unstated.

**Process:**

1. For each ambiguity, write down the chosen interpretation in the *same* place you store the rule. The interpretation is part of the implementation now.
2. Format: "Rule says X. We interpret as Y. Reason: Z."
3. If the rulebook publisher is contactable (BGG forums, designer's email), check — but don't wait. Pick an interpretation, ship it, document it; correct later if needed.

**Example (Waddle):**

> Rule: "On your turn, place a penguin or scout ahead."
> Ambiguity: The rule doesn't say what happens to the last player on a track when they have no doubles and ice hexes remain.
> Interpretation: They place singles into every remaining empty ice hex (auto-resolution). Reason: their choice is mechanical (every placement is identical); making them click N times adds friction without agency.

This becomes the "forced-fill" path in [`PlayerTurn.php`](#).

## 3. Rules → UI/UX Mapping

For every action the player can take, the UI needs:

| Element | Purpose | Waddle example |
|---|---|---|
| **Affordance** | "you can do this" | `.selectable-hex` glow on empty ice adjacent to active water |
| **Confirm** | "is this what you want?" | Optimistic preview + Confirm/Reset buttons |
| **Undo path** | "actually, no" | Reset reverts the pending DOM state; state-leave reverts pending |
| **Error feedback** | "you can't do that" | Server returns `BgaUserException`; framework displays in status bar |
| **Active indication** | "the system did something" | `.active-hex` glow on the current water hex; `centerOnActiveHex` scrolls it into view |
| **Spectator view** | "what's happening" | Same highlights, no click handlers; state-handler bails after the highlight if `!isCurrentPlayerActive` |

**The principle.** Every legal action gets *all five* (affordance, confirm, undo, error, indication). The rule's *legality* lives on the server (validation); the UI's job is to make the legality *visible* before the click happens.

**Two corollaries:**

- **Make the illegal unclickable, don't error after the click.** Show only valid targets; clicking an invalid hex shouldn't even fire the action.
- **Optimistic preview is the confirm step.** Moving the token immediately on click gives the player a visual checkpoint without an extra "yes really?" button.

**Where to put the affordance.** Three rules of thumb:

1. **On the target.** Glow the destination hex, not a separate "place here" button.
2. **Near the relevant token.** Waddle's scout-ahead label sits *on the destination track*, next to the marble that would be moving — not on a status-bar button across the screen.
3. **One affordance per option.** Type selection (single vs double) lives in a single pill that toggles. Two separate buttons for "select single" and "select double" multiplies clicks without disambiguating.

## 4. Fidelity vs Digital Ergonomics

Some rules deserve literal translation. Others don't. The decision framework:

**Deviate when the rule is...**

- **Mechanical bookkeeping the computer should do.** "Move all marbles down by one position" → players don't enjoy doing this manually at the table; the computer should just do it. (Waddle: the rotation is automatic.)
- **A workaround for a missing tactile cue.** "The active token is the one with the cardboard ring around it" → on screen, glow + scrolling-into-view replaces the physical ring.
- **A choice that's only nominally a choice.** Last-player-on-track-with-no-doubles is "forced to place singles". The computer can resolve this. (Waddle: forced-fill path.)
- **Spatial reasoning the screen flattens.** "Move the cardboard tile and rotate it 60°" — fine at the table; on screen, the rotation is just a CSS transform that the player never has to perform.

**Don't deviate when the rule is...**

- **A choice the player should make.** "You may place a single OR one of your two doubles" — even if doubles are usually better, the choice has strategic weight and must stay manual.
- **Information the player needs to see.** "After placing, each opponent reveals their token" — replicate the reveal animation; don't just update the score silently.
- **A pacing element.** Waiting for everyone to look at the board for a moment before the next round is sometimes part of the game's feel. A turn-end "pause for breath" notification keeps that.

**Document the deviations.** Every place you've deviated, write it down — in the diary entry for that mechanism, alongside the rule it overrides. Future-you (or a collaborator) will ask "did we mean to do this?" and the answer should already exist.

**Example (Waddle):**

> Rule: "The last player on the active track must place a penguin if ice remains; they may not scout."
> Adapted: When the last-on-track player also has no doubles left, the server auto-places singles into every empty ice hex — no confirmation needed.
> Reason: The player's choice is mechanical (all singles are identical; ordering doesn't matter); clicking N times to confirm a forced sequence adds friction.
> Caveat: When the player *does* have doubles, the choice is preserved — they can manually pick which hexes get the doubles.

## Putting It All Together

The flow:

1. **Write the structured model** (§1). Output: 1-page rules spec.
2. **Scan for ambiguity** (§2). Output: ambiguity list with chosen interpretations.
3. **Map rules to UI** (§3). Output: per-action affordance/confirm/undo/error/indication table.
4. **Mark deviations** (§4). Output: list of where the digital version differs from literal translation + why.

That gives you enough to start [Part 2 — Mechanism Implementation](mechanism-implementation.md). Code, then diary entry via [`diary-logging-implementation`](#).
