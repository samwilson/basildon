<?php

/**
 * This configuration will be read and overlaid on top of the
 * default configuration. Command-line arguments will be applied
 * after this file is read.
 *
 * @file
 */

declare(strict_types=1);

return [
    'target_php_version' => null,

    // A list of directories that should be parsed for class and
    // method information. After excluding the directories
    // defined in exclude_analysis_directory_list, the remaining
    // files will be statically analyzed for errors.
    //
    // Thus, both first-party and third-party code being used by
    // your application should be included in this list.
    'directory_list' => [
        'src/',
        'vendor/',
    ],

    // A list of files that will be excluded from parsing and analysis and will not be read at all.
    // This is useful for excluding hopelessly unanalyzable files that can't be removed for whatever reason.
    'exclude_file_list' => [
        // @todo Remove when https://github.com/phan/phan/issues/5071 is fixed.
        'vendor/simplepie/simplepie/src/Cache/Psr16.php',
    ],

    // A regex used to match every file name that you want to
    // exclude from parsing. Actual value will exclude every
    // "test", "tests", "Test" and "Tests" folders found in
    // "vendor/" directory.
    'exclude_file_regex' => '@^vendor/.*/(tests?|Tests?)/@',

    // A directory list that defines files that will be excluded
    // from static analysis, but whose class and method
    // information should be included.
    //
    // Generally, you'll want to include the directories for
    // third-party code (such as "vendor/") in this list.
    //
    // n.b.: If you'd like to parse but not analyze 3rd
    //       party code, directories containing that code
    //       should be added to both the `directory_list`
    //       and `exclude_analysis_directory_list` arrays.
    'exclude_analysis_directory_list' => [
        'vendor/',
    ],
];
