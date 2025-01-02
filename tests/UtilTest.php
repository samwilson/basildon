<?php

declare(strict_types=1);

namespace Test;

use App\Util;
use PHPUnit\Framework\TestCase;

final class UtilTest extends TestCase
{
    /**
     * @covers App\Util::cleanDir()
     */
    public function testCleanDir(): void
    {
        $testDir = __DIR__ . '/utiltest';
        if (!file_exists($testDir)) {
            mkdir($testDir);
        }
        $this->assertDirectoryExists($testDir);

        // Cleaning an empty directory doesn't delete the directory.
        Util::cleanDir($testDir);
        $this->assertDirectoryExists($testDir);
        $this->assertEmpty(glob($testDir . '/*'));

        // Add a file, and clean again.
        touch($testDir . '/test.txt');
        $this->assertCount(1, glob($testDir . '/*'));
        Util::cleanDir($testDir);
        $this->assertEmpty(glob($testDir . '/*'));

        // Add three files and a directory, and exclude some.
        touch($testDir . '/test1.txt');
        touch($testDir . '/excluded.txt');
        mkdir($testDir . '/excluded/');
        touch($testDir . '/excluded/lower.txt');
        $this->assertCount(3, glob($testDir . '/*'));
        Util::cleanDir($testDir, ['/excluded.*/']);
        $this->assertCount(2, glob($testDir . '/*'));

        // Clean up.
        Util::cleanDir($testDir);
    }
}
