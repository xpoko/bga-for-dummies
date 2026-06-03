<?php
// Source-text guards on the Studio-only simulation actions.
//
// Why this pattern: we can't run the action against a live BGA DB in CI
// (composer/PHPUnit only — no framework, no DB). So we read the action
// file as text and assert that each action contains the calls + attribute
// the design requires. Adapted from Waddle's
// tests/SimulationActionsSourceTest.php and the surrounding
// PerHexPlacementTest / RoundEndPerHexActivationTest pattern.
//
// What these tests catch (the regressions that "still look like they
// work" but quietly do the wrong thing):
//   - Dropping the Studio gate → buttons exposed in production.
//   - Dropping #[CheckAction(enabled: false)] → only active player can
//     drive the loop, so it stalls when turn rotates.
//   - Dropping Simulation::pickAction call → silent fallback to a
//     hard-coded action choice.
//   - Dropping the RoundEnd return → end-game cascade broken.

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class SimulationActionsSourceTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $source = file_get_contents(__DIR__ . '/../modules/php/States/PlayerTurn.php');
        $this->assertNotFalse($source, 'PlayerTurn.php must be readable');
        $this->source = $source;
    }

    // Slice the file between two consecutive function declarations so we
    // can match strings inside a specific method body. The slice DOES NOT
    // include the method's attributes (those sit BEFORE the declaration),
    // so attribute-presence tests must regex against $this->source directly.
    private function methodBody(string $name): string
    {
        if (!preg_match(
            '/(?:public|private|protected)\s+function\s+' . preg_quote($name, '/') . '\s*\(/',
            $this->source,
            $m,
            PREG_OFFSET_CAPTURE,
        )) {
            $this->fail("{$name} method must exist");
        }
        $start = (int) $m[0][1] + strlen($m[0][0]);
        $rest  = substr($this->source, $start);
        if (preg_match(
            '/(?:public|private|protected)\s+function\s+\w+\s*\(/',
            $rest,
            $endMatch,
            PREG_OFFSET_CAPTURE,
        )) {
            return substr($rest, 0, (int) $endMatch[0][1]);
        }
        return $rest;
    }

    public function testSimulationClassIsImported(): void
    {
        self::assertStringContainsString(
            'use Bga\\Games\\YourGame\\Simulation;',
            $this->source,
        );
    }

    public function testActSimulateStepGatesOnStudio(): void
    {
        self::assertStringContainsString(
            'assertStudio',
            $this->methodBody('actSimulateStep'),
        );
    }

    public function testActSimulateStepBypassesActivePlayerCheck(): void
    {
        // #[CheckAction(enabled: false)] sits BEFORE the method, so
        // methodBody() can't see it. Match it directly preceding the
        // method declaration with only whitespace between.
        self::assertMatchesRegularExpression(
            '/#\[CheckAction\(enabled:\s*false\)\]\s+public\s+function\s+actSimulateStep\s*\(/',
            $this->source,
        );
        self::assertStringContainsString(
            'use Bga\\GameFramework\\Actions\\CheckAction;',
            $this->source,
        );
    }

    public function testActSimulateStepUsesPickAction(): void
    {
        self::assertStringContainsString(
            'Simulation::pickAction',
            $this->methodBody('actSimulateStep'),
        );
    }

    public function testActSimulateEndGameGatesOnStudio(): void
    {
        self::assertStringContainsString(
            'assertStudio',
            $this->methodBody('actSimulateEndGame'),
        );
    }

    public function testActSimulateEndGameBypassesActivePlayerCheck(): void
    {
        self::assertMatchesRegularExpression(
            '/#\[CheckAction\(enabled:\s*false\)\]\s+public\s+function\s+actSimulateEndGame\s*\(/',
            $this->source,
        );
    }

    public function testActSimulateEndGameReturnsRoundEnd(): void
    {
        // Adapt 'RoundEnd' to whichever state class your game uses as
        // the entry point of the scoring cascade.
        self::assertStringContainsString(
            'return RoundEnd::class',
            $this->methodBody('actSimulateEndGame'),
        );
    }

    public function testAssertStudioCheckUsesBgaEnvironment(): void
    {
        $body = $this->methodBody('assertStudio');
        self::assertStringContainsString(
            "getBgaEnvironment() !== 'studio'",
            $body,
            'Studio gate must compare against the framework env, not a custom flag',
        );
        self::assertStringContainsString(
            'BgaUserException',
            $body,
        );
    }
}
