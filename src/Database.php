<?php

namespace App;

use PDO;

class Database
{

    /** @var PDO */
    protected static $pdo;

    public function __construct()
    {
        self::$pdo = new PDO('sqlite::memory:');
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
    }

    public function processSite(Site $site)
    {
        // Get the metadata key names.
        $keys = [ 'id', 'body' ];
        foreach ($site->getPages() as $page) {
            $keys = array_merge($keys, array_keys($page->getMetadata()));
        }
        asort($keys);
        $keys = array_unique(array_filter(array_map('strtolower', $keys)));

        // Create the table.
        $createTableSql = 'CREATE TABLE "pages" ("' . join('" TEXT, "', $keys) . '" TEXT)';
        self::$pdo->query($createTableSql);

        // Save the data.
        foreach ($site->getPages() as $page) {
            $sql = 'INSERT INTO "pages" ("' . join('", "', $keys) . '") VALUES (:' . join(', :', $keys) . ')';
            $stmt = self::$pdo->prepare($sql);
            $metadata = $page->getMetadata();
            foreach ($keys as $key) {
                $value = $metadata[$key] ?? null;
                if ($key === 'id') {
                    $value = $page->getId();
                } elseif ($key === 'body') {
                    $value = $page->getBody();
                }
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();
        }
    }

    public function query($sql)
    {
        return self::$pdo->query($sql)->fetchAll();
    }
}
