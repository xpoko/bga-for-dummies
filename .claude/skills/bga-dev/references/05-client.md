# BGA Dev: Client Side (TypeScript)

## Main Game Class Structure

```typescript
// mygame.ts
import { Bga } from 'bga-ts-types'; // if using type-safe template

export class MyGame {
    constructor(private bga: Bga) {
        // Register state handlers — one per server state
        this.bga.states.register('PlayerTurn', new PlayerTurnHandler(this, bga));
        this.bga.states.register('EndGame', new EndGameHandler(this, bga));
    }

    // Called once on page load with full game state from getAllDatas()
    setup(gamedatas: any) {
        this.setupBoard(gamedatas);
        this.setupTokens(gamedatas.tokens);
        this.setupCounters(gamedatas.counters);
        this.setupNotifications();
    }

    setupBoard(gamedatas: any) {
        const playerCount = Object.keys(gamedatas.players).length;
        this.bga.gameArea.getElement().insertAdjacentHTML('beforeend', `
            <div id="board" class="board board${playerCount}p shadow"></div>
        `);

        // Place slots, static elements, initial tokens from gamedatas
    }

    setupTokens(tokens: Record<string, any>) {
        for (const [key, token] of Object.entries(tokens)) {
            this.placeToken(key, token.token_location);
        }
    }

    placeToken(tokenId: string, location: string) {
        const tokenDiv = `<div id="${tokenId}" class="${this.getTokenClass(tokenId)}"></div>`;
        const container = document.getElementById(location);
        container?.insertAdjacentHTML('beforeend', tokenDiv);
    }

    setupNotifications() {
        this.bga.notifications.setupPromiseNotifications({
            tokenMoved: async (args: { token_id: string, location: string }) => {
                await this.animateMoveToken(args.token_id, args.location);
            },
            cardDrawn: (args: { card_id: string, card_type: string }) => {
                this.placeToken(args.card_id, `hand_${this.bga.player_id}`);
            },
            scoreUpdate: (args: { player_id: number, score: number }) => {
                this.bga.scoreCtrl[args.player_id]?.toValue(args.score);
            }
        });
    }
}
```

---

## State Handler Pattern

Each game state gets its own handler class — registered in the game constructor.

```typescript
// states/PlayerTurnHandler.ts
export class PlayerTurnHandler {
    constructor(private game: MyGame, private bga: Bga) {}

    onEnteringState(args: any, isCurrentPlayerActive: boolean) {
        if (!isCurrentPlayerActive) return;

        // 1. Highlight interactive elements
        document.querySelectorAll('.playable-card').forEach(el => {
            el.classList.add('active_slot');
            el.addEventListener('click', this.onCardClick);
        });

        // 2. Add action buttons to status bar
        this.bga.statusBar.addActionButton(
            _('Pass'),
            () => this.bga.actions.performAction('action_pass')
        );
    }

    onLeavingState(args: any, isCurrentPlayerActive: boolean) {
        // Always clean up — remove highlights and listeners
        document.querySelectorAll('.active_slot').forEach(el => {
            el.classList.remove('active_slot');
            el.removeEventListener('click', this.onCardClick);
        });
    }

    onCardClick = (event: Event) => {
        const target = event.currentTarget as HTMLElement;
        const cardId = target.id;

        this.bga.actions.performAction('action_playCard', { id: cardId })
            ?.then(() => console.log('card played'))
            .catch(e => console.error(e.message));
    };
}
```

**Key rules:**
- Always check `isCurrentPlayerActive` before setting up UI — non-active players should not see clickable elements
- Always remove event listeners in `onLeavingState` — memory leaks and double-firing are common bugs
- Use `outline` or `box-shadow` for highlighting, not `border` (border changes element dimensions)

---

## Sending Actions to Server

```typescript
// Modern framework — returns Promise, handles error display automatically
this.bga.actions.performAction('action_playCard', { id: cardId, extra: 'data' });

// With then/catch
this.bga.actions.performAction('action_doThing', { count: 3 })
    ?.then(() => { /* optional success handling */ })
    .catch(e => { /* error already shown to user by framework */ });
```

**Do not use** `this.ajaxcall()` — that is the old framework pattern.

---

## Notification Handlers

```typescript
setupNotifications() {
    this.bga.notifications.setupPromiseNotifications({
        // Async handler — game waits for the animation Promise to resolve
        // before processing the next notification
        tokenMoved: async (args: TokenMovedArgs) => {
            const token = document.getElementById(args.token_id);
            const dest = document.getElementById(args.location);
            if (token && dest) {
                await this.bga.animations.slideToObject(token, dest);
                dest.appendChild(token);
            }
        },

        // Sync handler — fire and forget
        scoreUpdate: (args: { player_id: number, score: number }) => {
            this.bga.scoreCtrl[args.player_id]?.toValue(args.score);
        },

        // Private card draw — only received by the drawing player
        cardDrawn: (args: { card_id: string, card_type: string }) => {
            const hand = document.getElementById(`hand_${this.bga.player_id}`);
            const cardHtml = `<div id="${args.card_id}" class="card card_${args.card_type}"></div>`;
            hand?.insertAdjacentHTML('beforeend', cardHtml);
        }
    });
}
```

**Async notifications:** Use `async` handlers for animations that must complete before the next notification fires. If a notification is fast/visual-only, a sync handler is fine.

**Do not use** `dojo.subscribe('notif_xxx', ...)` — old framework pattern.

---

## HTML Generation

All HTML is generated from TypeScript — no `.tpl` files in the modern framework.

```typescript
// Board
setup(gamedatas: any) {
    this.bga.gameArea.getElement().insertAdjacentHTML('beforeend', `
        <div id="board" class="board board4p shadow">
            <div id="slot_action_1" class="slot slot_action slot_action_1"></div>
            <div id="slot_action_2" class="slot slot_action slot_action_2"></div>
        </div>
        <div id="player_areas">
            ${Object.values(gamedatas.players).map((p: any) =>
                `<div id="player_area_${p.id}" class="player_area" 
                      style="border-color: #${p.color}"></div>`
            ).join('')}
        </div>
    `);
}
```

**Player panel access:**
```typescript
const panel = this.bga.playerPanels.getElement(playerId);
panel.insertAdjacentHTML('beforeend', '<div class="resource_counter">...</div>');
```

---

## CSS / SCSS Patterns

### Sprite Sheet Pattern (tokens, cards, meeples)

```css
/* Base class — shared dimensions and sprite image */
.meeple {
    background-image: url(img/tokens.png);
    width: 25px;
    height: 25px;
    cursor: pointer;
}

/* Color variants — background position within sprite */
.meeple_ff0000 { background-position: 0% 0%; }
.meeple_0000ff { background-position: 25% 0%; }
.meeple_00aa00 { background-position: 50% 0%; }
```

### Board Layout

```css
.board {
    position: relative;   /* parent for absolutely positioned slots */
    width: 980px;
    height: 433px;
    margin-bottom: 5px;
}
.board4p { background-image: url(img/board4p.jpg); }
.board2p { background-image: url(img/board2p.jpg); }
```

### Slots (Interactive Board Areas)

```css
.slot {
    position: absolute;
    cursor: pointer;
}
.slot_action_1 {
    width: 46px;
    height: 26px;
    top: 83px;
    left: 37px;   /* percentage preferred: left: 3.8%; */
}
```

Use **percentage-based positioning** for slots where possible — scales better across browser widths. BGA interfaces are fluid-width.

### Interactive Highlighting

```css
/* Use outline or box-shadow — NOT border (border changes element size) */
.active_slot {
    outline: 2px dashed white;
    outline-offset: 2px;
}

/* Glow alternative */
.active_slot {
    box-shadow: 0 0 8px 2px rgba(255, 255, 255, 0.8);
}
```

---

## Counters and Score Display

```typescript
// Score counter (always use the BGA star counter for score)
this.bga.scoreCtrl[player_id].toValue(newScore);     // jump to value
this.bga.scoreCtrl[player_id].incValue(delta);        // increment

// Custom counter for resources, etc.
const counter = new ebg.counter();
counter.create('element_id');
counter.setValue(initialValue);
counter.incValue(1);
```

Always use the standard BGA star score counter for the score — players expect it and it's a requirement for publication.

---

## Fluidity and Scaling

BGA game interfaces are **fluid-width** — your layout must adapt when the browser width changes. Use CSS flex/grid or percentage widths rather than fixed pixel widths where possible. The board itself can be a fixed pixel size, but surrounding layout should flex.

```css
#game_play_area {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.player_area {
    flex: 1 1 200px;  /* grows and shrinks, min 200px */
}
```

---

## Debugging the Client

- **Console errors:** Check browser console first — most issues show here immediately
- **`gamedatas` inspection:** `console.log(gamedatas)` at the top of `setup()` — verify server is sending what you expect
- **Network tab:** Check the AJAX request for any action, verify payload and response
- **Inspect element:** Right-click any BGA game element to see exact HTML/CSS used
- **BGA logs link:** Bottom of game area → "BGA request & SQL logs" — shows PHP output and DB queries
- **Save/restore state:** Use the 3 studio save slots to lock in edge cases for repeated testing
