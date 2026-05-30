# BGA Dev: Game Elements, Material & Database

## Element Taxonomy

Every physical piece maps to this hierarchy:

| Level | Meaning | Example |
|---|---|---|
| **Supertype** | Broad category | `meeple` |
| **Type** | Specific appearance | `meeple_ff0000` (red meeple) |
| **Instance** | Individual piece | `meeple_ff0000_7` (7th red meeple) |
| **Player color** | Often a supertype axis | `ff0000` = player 1's color |

**Naming convention** (reverse DNS style):
```
meeple_ff0000_7       → instance #7 of red meeple type
card_yellow_magic_2   → instance #2 of yellow magic card
slot_action_2         → action slot #2 on the board
hand_ff0000           → red player's hand zone
```

**ID rules (from BGA cookbook):**
- All pieces must have a **unique ID**
- IDs must be **meaningful** — `meeple_red_1` not `item_3847`
- Player-specific pieces use **color** not player ID (player IDs are dynamic; colors are stable)
- Separate class per property for CSS grouping: `class="meeple red n1"` lets you style all meeples, all red pieces, or the first piece independently

---

## Material.php (Static Game Data)

`Material.php` holds everything that **never changes during a game**: names, tooltips, rules text, point values, card effects.

**Use one unified structure** — not split by type:
```php
// GOOD — one unified variable, easy to iterate
$this->token_types = [
    'meeple' => ['name' => clienttranslate('Meeple')],
    'slot_action_2' => [
        'type'    => 'slot_action',
        'name'    => clienttranslate('2 Gray Track Advancements'),
        'tooltip' => clienttranslate('Gives two gray track advancements...'),
        'o'       => '1,0,0,gg', // rule encoding
    ],
    // ...
];

// BAD — split by type, hard to work with programmatically
$this->card_types = [...];
$this->meeple_types = [...];
$this->building_types = [...];
```

**For complex card games:** Keep data in a CSV/spreadsheet and write a script to generate `Material.php`. Much easier to maintain than hand-editing PHP arrays with 50+ entries.

**Send to client via `getAllDatas()`:**
```php
$result['token_types'] = $this->token_types;
```
Name it identically on both sides — makes client/server correlation much easier to debug.

---

## Database Schema

### Core Principle: Keep It Simple

300 pieces is tiny data. One or two tables with 3–5 columns is correct. Do not normalize. Do not use bitmask integers. String primary keys are fine.

**Static data → `Material.php`**  
**Dynamic data (position, state) → DB**

### The Universal Token Schema (works for ~95% of games)

```sql
CREATE TABLE IF NOT EXISTS `token` (
  `token_key`      varchar(32) NOT NULL,  -- e.g. 'meeple_ff0000_7'
  `token_location` varchar(32) NOT NULL,  -- e.g. 'hand_ff0000', 'slot_action_2'
  `token_state`    int(10),               -- e.g. face up/down, order, tapped
  PRIMARY KEY (`token_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

`token_state` is a multipurpose int — use it for whatever the game needs:
- Face up/down (0/1)
- Order within a zone
- Activation state
- Rotation

Because `token_key` is the PK, you almost never need schema migrations — any new game element is just a new row.

### The Card Schema (when using the Deck component)

```sql
CREATE TABLE IF NOT EXISTS `card` (
  `card_id`           int(10) unsigned NOT NULL AUTO_INCREMENT,
  `card_type`         varchar(16) NOT NULL,
  `card_type_arg`     int(11) NOT NULL,
  `card_location`     varchar(16) NOT NULL,
  `card_location_arg` int(11) NOT NULL,
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
```

The `Deck` PHP class wraps this schema with shuffle, draw, move, count operations.

### Choosing a Schema

| Game type | Schema to use |
|---|---|
| Grid-based (chess, Go, reversi-style) | Use reversi tutorial template |
| Card game with deck/hand mechanics | Use Deck class + card schema |
| Euro-style (workers, tokens, resources) | Use token schema above |
| Hybrid | Token schema for everything; use location naming to distinguish |

### DB Design Examples

**Chess** (conceptual — token schema)
| token_key | token_location | token_state |
|---|---|---|
| `Q_white` | `f3` | `0` |
| `K_black` | `e8` | `1` (has moved — castling blocked) |

**Card game** (2 decks)
| token_key | token_location | token_state |
|---|---|---|
| `Q_spades_1` | `hand_ff0000` | `0` |
| `10_hearts_2` | `tableau_ff0000` | `2` (position) |
| `10_hearts_1` | `tableau_common` | `1` (face down) |

**Euro game** (resources on cards)
| token_key | token_location | token_state |
|---|---|---|
| `card_planet_19` | `tableau_ff0000` | `1` (face up) |
| `resource_s_22` | `card_planet_19` | `2` (production state) |

Note: tokens can be located *on other tokens* — this is how you model "resource on card".

---

## Scope Reduction (Critical)

Before implementing anything, define your **reduced scope for v1**:

- [ ] No expansions — not even placeholder graphics
- [ ] Basic rules only — skip advanced/expert variants
- [ ] Most common player count only
- [ ] Reduce unique card variety (2 unique × 25 copies vs 50 unique × 2 copies)
- [ ] No custom animations yet
- [ ] No sound effects

Most BGA projects are abandoned due to scope. Shipping a reduced-rules game that works is infinitely better than an ambitious game that never ships.

---

## Element Implementation Checklist

For each piece type in the game, create entries in all four layers:

- [ ] **Material.php** — name, tooltip, any rules properties
- [ ] **`dbmodel.sql`** — the instance lives in token/card table
- [ ] **CSS/SCSS** — sprite background-position, dimensions, transparency for non-square pieces
- [ ] **TypeScript** — HTML template string for creation, initial placement call in `setup()`
- [ ] **Event handler** — if the piece is clickable, wire it in the state handler

**CSS faking pieces (before art arrives):**
```css
.meeple_fake {
    width: 25px;
    height: 25px;
    background-color: #e74c3c;
    border-radius: 50%;
}
.meeple_fake::after {
    content: 'M';
    display: block;
    text-align: center;
    line-height: 25px;
    font-size: 10px;
    color: white;
}
```
