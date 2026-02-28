<?php

declare(strict_types=1);

namespace App;

use DateTimeInterface;
use PDO;
use PDOException;
use PDOStatement;

final class Database
{
    public const COL_NAME_ID = 'id';

    public const COL_NAME_BODY = 'body';

    /** @var string[]|null */
    protected ?array $keys = null;

    protected static PDO $pdo;

    /**
     * @param string $dsn A valid SQLite DSN. Usually the full filesystem path to the database file.
     */
    public function __construct(string $dsn = ':memory:')
    {
        self::$pdo = new PDO("sqlite:$dsn");
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
        if (is_array($this->keys)) {
            return $this->keys;
        }
        $keys = [self::COL_NAME_ID, self::COL_NAME_BODY];
        foreach ($site->getPages() as $page) {
            $keys = array_merge($keys, array_keys($page->getMetadata()));
        }
        asort($keys);
        $keys = array_values(array_unique(array_filter(array_map('strtolower', $keys))));
        $this->keys = $keys;

        return $this->keys;
    }

    public function processSite(Site $site): void
    {
        // Get the metadata key names.
        $keys = $this->getColumns($site);

        // Create the table.
        self::$pdo->query('DROP TABLE IF EXISTS "pages"');
        $createTableSql = 'CREATE TABLE "pages" ("' . join('" TEXT, "', $keys) . '" TEXT)';
        self::$pdo->query($createTableSql);

        // Save the data.
        foreach ($site->getPages() as $page) {
            $metadata = $page->getMetadata();
            $sql = 'INSERT INTO "pages" ("' . join('", "', $keys) . '") VALUES (:' . join(', :', $keys) . ')';
            $stmt = self::$pdo->prepare($sql);
            foreach ($keys as $key) {
                $value = $metadata[$key] ?? null;
                if ($key === 'id') {
                    $value = $page->getId();
                } elseif ($key === 'body') {
                    $value = $page->getBody();
                } elseif ($value instanceof DateTimeInterface) {
                    // ISO 8601 format.
                    $value = $value->format('c');
                } elseif (is_array($value)) {
                    $value = json_encode($value);
                }
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->execute();
        }
    }

    /**
     * @param string[] $params
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        if (is_array($params) && count($params) > 0) {
            $stmt = self::$pdo->prepare($sql);
            foreach ($params as $placeholder => $value) {
                if (is_bool($value)) {
                    $type = PDO::PARAM_BOOL;
                } elseif (is_null($value)) {
                    $type = PDO::PARAM_NULL;
                } elseif (is_int($value)) {
                    $type = PDO::PARAM_INT;
                } else {
                    $type = PDO::PARAM_STR;
                }
                $stmt->bindValue($placeholder, $value, $type);
            }
            $stmt->setFetchMode(PDO::FETCH_OBJ);
            $result = $stmt->execute();
            if (!$result) {
                throw new PDOException('Unable to execute parameterised SQL: <code>' . $sql . '</code>');
            }
        } else {
            try {
                $stmt = self::$pdo->query($sql);
            } catch (PDOException $e) {
                throw new PDOException($e->getMessage() . ' -- Unable to execute SQL: <code>' . $sql . '</code>');
            }
        }

        return $stmt;
    }
}
