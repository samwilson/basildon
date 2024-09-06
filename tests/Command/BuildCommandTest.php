<?php

declare(strict_types=1);

namespace Test\Command;

use App\Command\BuildCommand;
use PHPUnit\Framework\TestCase;

/**
 * @covers BuildCommand
 */
final class BuildCommandTest extends TestCase
{
    /**
     * @dataProvider provideTimeElapsed()
     */
    public function testTimeElapsed(int $duration, string $expected): void
    {
        $buildCommand = new BuildCommand();
        $this->assertSame($expected, $buildCommand->getTimeElapsed(microtime(true) - $duration));
    }

    /**
     * @return array<array<int,string>>
     */
    public function provideTimeElapsed(): array
    {
        return [
            [45, '45 seconds'],
            [125, '2 minutes, 5 seconds'],
            [3601, '1 hour, 1 second'],
            [5580, '1 hour, 33 minutes'],
        ];
    }
}
