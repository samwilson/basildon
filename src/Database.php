<?php

declare(strict_types=1);

namespace App;

use Cassandra\Date;
use DateTime;
use PDO;

class Database
{

    /** @var PDO */
    protected static $pdo;

    /** @var string[] */
    protected $keys;

    public function __construct()
    {
        self::$pdo = new PDO('sqlite::memory:');
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
    }

    /**
     * Get all key names.
     *
     * @return string[]
     */
    public function getColumns(Site $site): array
    {
        if ($this->keys) {
            return $this->keys;
        }
        $keys = [ 'id', 'body' ];
        foreach ($site->getPages() as $page) {
            $keys = array_merge($keys, array_keys($page->getMetadata()));
        }
        asort($keys);
        $keys = array_unique(array_filter(array_map('strtolower', $keys)));
        $this->keys = $keys;
        return $this->keys;
    }

    public function processSite(Site $site): void
    {
        // Get the metadata key names.
        $keys = $this->getColumns($site);

        // Create the table.
        $createTableSql = 'CREATE TABLE "pages" ("' . join('" TEXT, "', $keys) . '" TEXT)';
        self::$pdo->query($createTableSql);

        // Save the data.
        foreach ($site->getPages() as $page) {
            $metadata = $page->getMetadata();
//            $columnNames = [];
//            $columnPlaceholders = [];
//            foreach ($keys as $key) {
//                $value = $metadata[$key] ?? null;
//                $columnNames[] = $key;
//                if ($value instanceof DateTime) {
//                    $columnNames[] = ''$key;
//                }
//                $columnPlaceholders[] = $key;
//            }
            $sql = 'INSERT INTO "pages" ("' . join('", "', $keys) . '") VALUES (:' . join(', :', $keys) . ')';
            $stmt = self::$pdo->prepare($sql);
            foreach ($keys as $key) {
                $value = $metadata[$key] ?? null;
                if ($key === 'id') {
                    $value = $page->getId();
                } elseif ($key === 'body') {
                    $value = $page->getBody();
                } elseif ($value instanceof DateTime) {
                    $value = $value->format('c'); // ISO 8601 format.
                } elseif (is_array($value)) {
                    $value = json_encode($value);
                }
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();
        }
    }

    /**
     * @return mixed[]
     */
    public function query(string $sql): array
    {
        return self::$pdo->query($sql)->fetchAll();
    }
}
