# {Mechanism Name}

**One-liner.** {One sentence, present tense, describing what this mechanism does at the played game / running application. If you can't compress it to one sentence, the entry is probably covering two mechanisms — split.}

## Source files

- [{path/to/file.ext}:{lo}-{hi}](path/to/file.ext#L{lo}-L{hi}) — {what this file owns for this mechanism}
- [{...}](...) — {...}
- {3–7 entries; canonical file first; load-bearing only}

## Rulebook → code

> *"{Briefly quote the external constraint — rulebook clause, spec, user requirement. Fair-use snippet, not the whole document.}"*

{Describe the mapping. What state/data/UI elements does this rule become? What's implicit in the rule that the implementation has to make explicit?}

{Two or three short paragraphs is normal. Reference code paths inline: `[file.ext:N](file.ext#LN)`.}

## Design choices

- **{Alternative A}** — {one line on why rejected}.
- **{Alternative B}** — {one line on why rejected}.
- **{Chosen approach}** — {what we picked and why}.

{Then a few bullets on the choices made within the picked approach. Each bullet leads with the choice in bold.}

- **{Choice}.** {Reasoning.} See [{file.ext:N}](file.ext#LN).
- **{Choice}.** {Reasoning.}

## Snags & refinements

{At least one entry. What got wrong on first pass and what you changed.}

- **{Snag in one phrase}.** {What went wrong, with a code reference.} Fix: {what you changed and where}.
- **{Refinement}.** {What the earlier version did, why it wasn't right, what it is now.}

## Cross-refs

- [[other-mechanism]] — {how it relates}.
- [[another-mechanism]] — {how it relates}.
