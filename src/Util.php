<?php

declare(strict_types=1);

namespace App;

use DirectoryIterator;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SimpleXMLElement;

use function assert;

final class Util
{
    public static function mkdir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Delete a directory and all of its contents.
     *
     * @link https://stackoverflow.com/a/7288067/99667
     *
     * @param string $dir The directory to remove.
     */
    public static function rmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $rdi = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
        $rii = new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::CHILD_FIRST);
        assert($rii instanceof DirectoryIterator);
        foreach ($rii as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
    }

    /**
     * Convert XML into an array structure suitable for use in Twig.
     *
     * @param string $xml The XML input.
     * @return mixed[]
     */
    public static function xmlToArray(string $xml): array
    {
        $json = json_encode((array) new SimpleXMLElement($xml));
        // Change the '@attributes' key to have an underscore, for easier use in Twig.
        $newJson = preg_replace('/"@attributes":/', '"_attributes":', $json);
        return json_decode($newJson, true);
    }
}
