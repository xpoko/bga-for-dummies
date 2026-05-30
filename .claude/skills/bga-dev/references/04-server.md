# BGA Dev: Server Side (PHP)

## Game Setup — `setupNewGame`

Called once when a table is created. Logging does **not** work here — use `$this->dump()` for debug output.

**The "framework boilerplate" is real and required.** The modern `Bga\GameFramework\Table::setupNewGame()` is **abstract** — it does not auto-create players. You must populate the `player` table yourself before any `playerStats->init` call, or you get the misleading error `Player statistic <name> cannot be initialized before players are set up.`

```php
protected function setupNewGame($players, $options = []) {
    // ---- Required boilerplate: create the player rows ----
    $this->DbQuery("DELETE FROM player");
    $defaultColors = $this->getGameinfos()['player_colors'];  // or a Material constant
    $values = [];
    foreach ($players as $player_id => $player) {
        $color = array_shift($defaultColors);
        $values[] = "('$player_id','$color','" . $player['player_canal'] . "','"
            . addslashes($player['player_name']) . "','"
            . addslashes($player['player_avatar']) . "')";
    }
    $this->DbQuery(
        "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES "
        . implode(',', $values)
    );
    $this->reattributeColorsBasedOnPreferences($players, $defaultColors);
    $this->reloadPlayersBasicInfos();
    $this->activeNextPlayer();

    // ---- Your custom setup ----
    return $this->setupNewGameTables();
}

function setupNewGameTables() {
    try {
        $players = $this->loadPlayersBasicInfos();
        $this->initStats();
        $this->initGameTables();
        $player_id = $this->getActivePlayerId();
        $this->playerStats->inc('turns_number', 1, $player_id, true);
    } catch (Exception $e) {
        $this->error("Fatal error while creating game");
        $this->dump('err', $e);
    }
    return GameDispatch::class;  // your initial state class — REQUIRED, framework
                                  // dispatches into it after gameSetup. Without
                                  // this you get "Unknown state: 2".
}
```

**Stat init API (modern):** takes an **array** of stat names, no per-player loop:

```php
$this->tableStats->init(['turns_number'], 0);
$this->playerStats->init(['game_vp_total', 'game_waddle_vp', ...], 0);
```

**Game state labels (`getGameStateValue` / `setGameStateValue`):** must be registered in the constructor before any value calls, or you get `Unknown gamestate label: <name>`:

```php
public function __construct() {
    parent::__construct();
    self::initGameStateLabels([
        'active_track' => 10,
        'scouted_this_round' => 11,
    ]);
}
```

### Initializing Stats

```php
public function initStats() {
    $all_stats = $this->getStatTypes();
    $player_stats = $all_stats['player'];
    foreach ($player_stats as $key => $value) {
        if (str_starts_with($key, 'game_')) {
            $this->playerStats->init($key, 0);
        }
        if ($key === 'turns_number') {
            $this->playerStats->init($key, 0);
        }
    }
    $table_stats = $all_stats['table'];
    foreach ($table_stats as $key => $value) {
        if (str_starts_with($key, 'game_')) {
            $this->tableStats->init($key, 0);
        }
    }
}
```

### Initializing Tables (Bulk Insert Pattern)

**Always batch inserts** — build the full array first, then do one `DbQuery()`:

```php
function initGameTables() {
    $values = [];

    $players = $this->loadPlayersBasicInfos();
    foreach ($players as $player_id => $player) {
        $color = $player['player_color'];

        // Place 3 meeples per player in their home zone
        for ($i = 1; $i <= 3; $i++) {
            $values[] = "('meeple_{$color}_{$i}', 'home_{$color}', 0)";
        }
    }

    // Shuffle and deal cards, build remaining token values...

    $this->DbQuery(
        "INSERT INTO token (token_key, token_location, token_state) VALUES "
        . implode(',', $values)
    );
}
```

---

## `getAllDatas()` — Game State Sync

Called whenever a player loads/reloads the game. Must return everything needed to reconstruct the full game view for the requesting player.

```php
protected function getAllDatas() {
    $result = [];

    // 1. Players (base class handles some; add extras here)
    $sql = "SELECT player_id id, player_score score, player_no no FROM player";
    $result['players'] = self::getCollectionFromDb($sql);

    // 2. Material (static data — use ONE variable, named same as in PHP)
    $result['token_types'] = $this->token_types;

    // 3. Dynamic data, filtered by current player visibility
    $current_player_id = self::getCurrentPlayerId();
    $players_basic = $this->loadPlayersBasicInfos();
    $result['tokens'] = [];
    $result['counters'] = [];

    foreach ($players_basic as $player_id => $player_info) {
        $color = $player_info['player_color'];

        // Public zone (everyone sees)
        $result['tokens'] += $this->tokens->getTokensInLocation("tableau_{$color}");

        // Private zone (only the owning player sees full data)
        if ($current_player_id == $player_id) {
            $result['tokens'] += $this->tokens->getTokensInLocation("hand_{$color}");
        } else {
            // Other players only get the count, not the contents
            $result['counters']["hand_{$color}"] =
                $this->tokens->countTokensInLocation("hand_{$color}");
        }
    }

    // Hidden zone counts
    $result['counters']['deck'] = $this->tokens->countTokensInLocation('deck');
    $result['counters']['discard'] = $this->tokens->countTokensInLocation('discard');

    return $result;
}
```

**Key rules:**
- Never return private information to the wrong player (hand cards, hidden tiles)
- Return counts for hidden zones so clients can display "3 cards in hand" without revealing contents
- Name variables identically in PHP and TypeScript for easier debugging

---

## State Machine — PHP State Classes

In the modern framework, each state is a **PHP class** in `modules/php/States/`. No `states.inc.php`.

### State Class Template

```php
<?php
declare(strict_types=1);
namespace Bga\Games\MyGame\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\Actions\Types\JsonParam;
use Bga\Games\MyGame\Game;
use Bga\Games\MyGame\StateConstants;

class PlayerTurn extends GameState {
    public function __construct(protected Game $game) {
        parent::__construct(
            $game,
            id: StateConstants::STATE_PLAYER_TURN,
            type: StateType::ACTIVE_PLAYER,
            descriptionMyTurn: clienttranslate('${you} must perform an action'),
            description: clienttranslate('${actplayer} must perform an action')
        );
    }

    // Called when state becomes active — return data visible to all players
    public function getArgs(int $active_player_id): array {
        return [
            'possibleMoves' => $this->game->getPossibleMoves($active_player_id),
        ];
    }

    // Called on state entry (optional setup)
    public function onEnteringState(int $active_player_id) { }

    // Player action — declared with attribute, no separate action file needed
    #[PossibleAction]
    function action_playCard(#[JsonParam] array $data): void {
        $card_id = $data['id'] ?? null;

        // 1. Validate
        if (!$card_id) throw new \BgaUserException('Invalid card');
        // ... further validation ...

        // 2. Update DB
        $this->game->DbQuery(
            "UPDATE token SET token_location='discard' WHERE token_key='$card_id'"
        );

        // 3. Send notification
        $this->game->notifyAllPlayers('cardPlayed', clienttranslate('${player_name} played a card'), [
            'player_id'   => $this->game->getActivePlayerId(),
            'player_name' => $this->game->getActivePlayerName(),
            'card_id'     => $card_id,
        ]);

        // 4. Transition
        $this->game->gamestate->nextState('cardPlayed');
    }

    #[PossibleAction]
    function action_pass(): void {
        $this->game->gamestate->nextState('pass');
    }

    // Handle disconnected (zombie) player — must advance game
    public function zombie(int $playerId): void {
        $this->game->gamestate->nextState('pass');
    }
}
```

### What Changed From Old Framework

| Old (states.inc.php) | New (PHP class) |
|---|---|
| `states.inc.php` array entry | PHP class in `modules/php/States/` |
| Separate action file | Methods with `#[PossibleAction]` on state class |
| `possibleactions` array | Replaced by `#[PossibleAction]` attribute |
| `args` function name in array | `getArgs()` method on state class |
| Global zombie function | `zombie()` method on state class |
| `#[CheckAction(false)]` to skip | Per-action attribute available |

### State Design Guidelines

- **Target: ~10 states, max ~20** — if you have more, move complexity to client-side states
- Not every player choice needs a server state — multi-step choices can be collected client-side and sent as one consolidated action
- Common state types: `ACTIVE_PLAYER`, `MULTIPLE_ACTIVE_PLAYER`, `GAME` (automatic/no player action)

---

## Turn Order

Standard clockwise order is handled automatically — do nothing.

**Custom turn order:**
```php
// Don't use player_no — that's the seating position, not turn order
// Use a custom column or token-based order tracking

// Utility to build next-player lookup table:
$player_ids = array_keys($this->loadPlayersBasicInfos());
$next_player_table = $this->createNextPlayerTable($player_ids);

// Advance to custom next player:
$current = $this->getActivePlayerId();
$next = $next_player_table[$current];
$this->gamestate->changeActivePlayer($next);
```

---

## Notifications

Send from PHP → received by TypeScript client:

```php
// To all players (public info)
$this->notifyAllPlayers(
    'tokenMoved',                          // notification type
    clienttranslate('${player_name} moved ${token_name}'),  // log message
    [
        'player_id'   => $player_id,
        'player_name' => $this->getPlayerNameById($player_id),
        'token_id'    => $token_id,
        'location'    => $new_location,
        // i18n: wrap names in clienttranslate() if they need translation
    ]
);

// To one player only (private info — e.g. drawn card)
$this->notifyPlayer(
    $player_id,
    'cardDrawn',
    '',     // empty string = no log entry for private notifs
    ['card_id' => $card_id, 'card_type' => $card_type]
);
```

**Log messages:** Write them so a player who wasn't watching can reconstruct what happened. Use `${player_name}` and `${token_name}` style interpolation — the framework fills these in.
