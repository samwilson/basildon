<?php

declare(strict_types=1);

namespace App;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Util
{

    public static function mkdir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Delete the output directory.
     *
     * @link https://stackoverflow.com/a/7288067/99667
     */
    public static function rmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $rdi = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
        $rii = new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($rii as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($dir);
    }
}
