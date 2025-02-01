<?php

declare(strict_types=1);

namespace Test;

use App\Util;
use PHPUnit\Framework\TestCase;

final class UtilTest extends TestCase
{
    /**
     * @covers App\Util::rmdir()
     */
    public function testRmdir(): void
    {
        $testDir = __DIR__ . '/utiltest';
        if (!file_exists($testDir)) {
            mkdir($testDir);
        }
        $this->assertDirectoryExists($testDir);

        // Cleaning an empty directory doesn't remove the directory.
        Util::rmdir($testDir);
        $this->assertDirectoryExists($testDir);
        $this->assertEmpty(glob($testDir . '/*'));

        // Add a file, and remove again.
        touch($testDir . '/test.txt');
        $this->assertCount(1, glob($testDir . '/*'));
        Util::rmdir($testDir);
        $this->assertEmpty(glob($testDir . '/*'));

        // Add three files and a directory, and remove.
        touch($testDir . '/test1.txt');
        touch($testDir . '/excluded.txt');
        mkdir($testDir . '/excluded/');
        touch($testDir . '/excluded/lower.txt');
        $this->assertCount(3, glob($testDir . '/*'));
        Util::rmdir($testDir);
        $this->assertEmpty(glob($testDir . '/*'));

        // Clean up.
        Util::rmdir($testDir);
    }

    /**
     * @covers \App\Util::xmlToArray()
     * @dataProvider provideXmlToArray
     */
    public function testXmlToArray(string $xml, mixed $array): void
    {
        $this->assertSame($array, Util::xmlToArray($xml));
    }

    /**
     * @return mixed[]
     */
    public function provideXmlToArray(): array
    {
        return [
            [
                '<foo><p></p></foo>',
                ['p' => []],
            ],
            [
                '<root><p class="blah"><span>Lorem</span></p></root>',
                ['p' => [
                    '_attributes' => ['class' => 'blah'],
                    'span' => 'Lorem',
                ]],
            ],
        ];
    }
}
