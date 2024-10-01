<?php

namespace App\Classes\Eloquent\Traits;

use Closure;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

trait ArrayTrait
{
    protected static $arrayConnection;

    public function getRows()
    {
        $rows = $this->rows ?? [];
        if (empty($rows)) {
            $method = 'array_rows';
            if (method_exists($this, $method)) {
                $reflection = new \ReflectionMethod($this, $method);
                if ($reflection->isPrivate()) {
                    throw new \RuntimeException("The requested $method method is not public.");
                }
                $rows = $this->{$method}();
                foreach ($rows as &$row) {
                    foreach ($row as $field => &$value) {
                        if (is_array($value)) {
                            $value = json_encode($value);
                        }
                    }
                }
            }
        }

        // normailize
        for ($i = 0; $i < count($rows); $i++) {
            for ($j = $i; $j < count($rows); $j++) {
                foreach (array_keys($rows[$i]) as $k) {
                    if (is_null($rows[$i][$k])) {
                        $rows[$i][$k] = '';
                    }
                    foreach (array_keys($rows[$j]) as $k2) {
                        if (!isset($rows[$i][$k2])) {
                            $rows[$i][$k2] = '';
                        }
                        if (!isset($rows[$j][$k])) {
                            $rows[$j][$k] = '';
                        }
                    }
                }
            }
        }

        return $rows ?? [];
    }

    public function getSchema()
    {
        $result = $this->schema ?? ['_id' => 'string'];
        foreach($this->casts as $name => $type) {
            if (!isset($result[$name])) {
                $result[$name] = str_replace('array', 'string', $type);
            }
        }
        return $result;
    }

    protected function arrayCacheReferencePath()
    {
        return (new \ReflectionClass(static::class))->getFileName();
    }

    protected function arrayShouldCache()
    {
        return property_exists(static::class, 'rows');
    }

    public static function resolveConnection($connection = null)
    {
        return static::$arrayConnection;
    }

    public static function bootArrayTrait()
    {
        $instance = (new static);

        $cacheFileName = config('array.cache-prefix', 'array') . '-' . Str::kebab(str_replace('\\', '', static::class)) . '.sqlite';
        $cacheDirectory = realpath(config('array.cache-path', storage_path('framework/cache')));
        $cachePath = $cacheDirectory . '/' . $cacheFileName;
        $dataPath = $instance->arrayCacheReferencePath();

        $states = [
            'cache-file-found-and-up-to-date' => function () use ($cachePath) {
                static::setSqliteConnection($cachePath);
            },
            'cache-file-not-found-or-stale' => function () use ($cachePath, $dataPath, $instance) {
                file_put_contents($cachePath, '');

                static::setSqliteConnection($cachePath);

                $instance->migrate();

                touch($cachePath, filemtime($dataPath));
            },
            'no-caching-capabilities' => function () use ($instance) {
                static::setSqliteConnection(':memory:');

                $instance->migrate();
            },
        ];

        switch (true) {
            case !$instance->arrayShouldCache():
                $states['no-caching-capabilities']();
                break;

            case file_exists($cachePath) && filemtime($dataPath) <= filemtime($cachePath):
                $states['cache-file-found-and-up-to-date']();
                break;

            case file_exists($cacheDirectory) && is_writable($cacheDirectory):
                $states['cache-file-not-found-or-stale']();
                break;

            default:
                $states['no-caching-capabilities']();
                break;
        }
    }

    protected static function setSqliteConnection($database)
    {
        $config = [
            'driver' => 'sqlite',
            'database' => $database,
        ];

        static::$arrayConnection = app(ConnectionFactory::class)->make($config);

        app('config')->set('database.connections.' . static::class, $config);
    }

    public function migrate()
    {
        $rows = $this->getRows();
        $tableName = $this->getTable();

        if (count($rows)) {
            $firstRow = [];
            foreach ($rows as $row) {
                foreach ($row as $k => $v) {
                    if (!isset($firstRow[$k])) {
                        $firstRow[$k] = $v;
                    } else
                    if (is_array($firstRow[$k]) != is_array($v)) {
                        $firstRow[$k] = $v;
                    }
                }
            }
            $this->createTable($tableName, $firstRow); //$rows[0]
        } else {
            $this->createTableWithNoData($tableName);
        }

        if (count($rows)) {
            foreach (array_chunk($rows, $this->getArrayInsertChunkSize()) ?? [] as $inserts) {
                if (!empty($inserts)) {
                    static::insert($inserts);
                }
            }
        }
    }

    public function createTable(string $tableName, $firstRow)
    {
        $this->createTableSafely($tableName, function ($table) use ($firstRow) {
            // Add the "id" column if it doesn't already exist in the rows.
            if ($this->incrementing && !array_key_exists($this->primaryKey, $firstRow)) {
                $table->increments($this->primaryKey);
            }

            $schema = $this->getSchema();

            foreach ($firstRow as $column => $value) {
                switch (true) {
                    case is_int($value):
                        $type = 'integer';
                        break;
                    case is_numeric($value):
                        $type = 'float';
                        break;
                    case is_string($value):
                        $type = 'string';
                        break;
                    case is_object($value) && $value instanceof \DateTime:
                        $type = 'dateTime';
                        break;
                    case is_array($value):
                        $type = 'string';
                        break;
                    default:
                        $type = 'string';
                }

                if ($column === $this->primaryKey && $type == 'integer') {
                    $table->increments($this->primaryKey);
                    continue;
                }

                $type = $schema[$column] ?? $type;

                $table->{$type}($column)->nullable();
            }

            if ($this->usesTimestamps() && (!in_array('updated_at', array_keys($firstRow)) || !in_array('created_at', array_keys($firstRow)))) {
                $table->timestamps();
            }
        });
    }

    public function createTableWithNoData(string $tableName)
    {
        $this->createTableSafely($tableName, function ($table) {
            $schema = $this->getSchema();

            if ($this->incrementing && !in_array($this->primaryKey, array_keys($schema))) {
                $table->increments($this->primaryKey);
            }

            foreach ($schema as $name => $type) {
                if ($name === $this->primaryKey && $type == 'integer') {
                    $table->increments($this->primaryKey);
                    continue;
                }

                $table->{$type}($name)->nullable();
            }

            if ($this->usesTimestamps() && (!in_array('updated_at', array_keys($schema)) || !in_array('created_at', array_keys($schema)))) {
                $table->timestamps();
            }
        });
    }

    protected function createTableSafely(string $tableName, Closure $callback)
    {
        /** @var \Illuminate\Database\Schema\SQLiteBuilder $schemaBuilder */
        $schemaBuilder = static::resolveConnection()->getSchemaBuilder();

        try {
            $schemaBuilder->create($tableName, $callback);
        } catch (QueryException $e) {
            if (Str::contains($e->getMessage(), 'already exists (SQL: create table')) {
                // This error can happen in rare circumstances due to a race condition.
                // Concurrent requests may both see the necessary preconditions for
                // the table creation, but only one can actually succeed.
                return;
            }

            throw $e;
        }
    }

    public function usesTimestamps()
    {
        // Override the Laravel default value of $timestamps = true; Unless otherwise set.
        return (new \ReflectionClass($this))->getProperty('timestamps')->class === static::class
            ? parent::usesTimestamps()
            : false;
    }

    public function getArrayInsertChunkSize()
    {
        return $this->arrayInsertChunkSize ?? 100;
    }

    public function getConnectionName()
    {
        return static::class;
    }
}
