<?php

/**
 * DGLab Active Record Base Model
 *
 * Provides Active Record pattern with:
 * - CRUD operations
 * - Query builder
 * - Relationships
 * - Mass assignment protection
 * - Timestamp handling
 *
 * @package DGLab\Database
 */

namespace DGLab\Database;

use DGLab\Core\Application;
use DGLab\Database\Relationships\BelongsTo;
use DGLab\Database\Relationships\HasMany;

/**
 * Class Model
 *
 * Base model class implementing Active Record pattern.
 *
 * @property int|string $id
 * @property string $created_at
 * @property string $updated_at
 */
abstract class Model
{
    /**
     * Database connection
     */
    protected static ?Connection $connection = null;

    /**
     * Table name (auto-inferred if not set)
     */
    protected ?string $table = null;

    /**
     * Primary key
     */
    protected string $primaryKey = 'id';

    /**
     * Fillable attributes (mass assignment)
     */
    protected array $fillable = [];

    /**
     * Guarded attributes (not mass assignable)
     */
    protected array $guarded = [];

    /**
     * Attributes
     */
    protected array $attributes = [];

    /**
     * Original attributes (for dirty checking)
     */
    protected array $original = [];

    /**
     * Whether timestamps are managed
     */
    protected bool $timestamps = true;

    /**
     * Date format
     */
    protected string $dateFormat = 'Y-m-d H:i:s';

    /**
     * Whether model exists in database
     */
    protected bool $exists = false;

    /**
     * Query builder instance
     */
    protected ?QueryBuilder $query = null;

    /**
     * Constructor
     */
    public function __construct(array $attributes = [], bool $exists = false)
    {
        if ($exists) {
            $this->attributes = $attributes;
            $this->original = $attributes;
            $this->exists = true;
        } else {
            $this->fill($attributes);
        }
    }

    /**
     * Get database connection
     */
    public static function getConnection(): Connection
    {
        if (self::$connection === null) {
            self::$connection = Application::getInstance()->get(Connection::class);
        }

        return self::$connection;
    }

    /**
     * Set database connection
     */
    public static function setConnection(Connection $connection): void
    {
        self::$connection = $connection;
    }

    /**
     * Clear the connection (for tests)
     */
    public static function clearConnection(): void
    {
        self::$connection = null;
    }

    /**
     * Get table name
     */
    public function getTable(): string
    {
        if ($this->table !== null) {
            return $this->table;
        }

        // Infer from class name
        $class = basename(str_replace('\\', '/', static::class));

        // Convert to snake_case and pluralize
        $table = preg_replace('/([a-z])([A-Z])/', '$1_$2', $class);
        $table = strtolower($table);
        $table = $this->pluralize($table);

        $this->table = $table;
        return $table;
    }

    /**
     * Simple pluralization
     */
    private function pluralize(string $word): string
    {
        // Simple rules (can be expanded)
        $endsInY = str_ends_with($word, 'y');
        $vowelBeforeY = str_ends_with($word, 'ay') || str_ends_with($word, 'ey') ||
                        str_ends_with($word, 'iy') || str_ends_with($word, 'oy') ||
                        str_ends_with($word, 'uy');

        if ($endsInY && !$vowelBeforeY) {
            return substr($word, 0, -1) . 'ies';
        }

        $specialEnds = str_ends_with($word, 's') || str_ends_with($word, 'x') ||
                       str_ends_with($word, 'ch') || str_ends_with($word, 'sh');

        if ($specialEnds) {
            return $word . 'es';
        }

        return $word . 's';
    }

    /**
     * Fill attributes
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    /**
     * Check if attribute is fillable
     */
    protected function isFillable(string $key): bool
    {
        if (in_array($key, $this->guarded, true)) {
            return false;
        }

        if (empty($this->fillable)) {
            return true;
        }

        return in_array($key, $this->fillable, true);
    }

    /**
     * Set an attribute
     */
    public function setAttribute(string $key, mixed $value): self
    {
        if ($key === 'id') {
            $this->attributes[$this->primaryKey] = $value;
        } else {
            $this->attributes[$key] = $value;
        }

        return $this;
    }

    /**
     * Get an attribute
     */
    public function getAttribute(string $key): mixed
    {
        if (isset($this->attributes[$key])) {
            return $this->attributes[$key];
        }

        return null;
    }

    /**
     * Get all attributes
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Check if attribute exists
     */
    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Get dirty attributes
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Check if model is dirty
     */
    public function isDirty(?string $key = null): bool
    {
        if ($key !== null) {
            return array_key_exists($key, $this->getDirty());
        }

        return !empty($this->getDirty());
    }

    /**
     * Magic getter
     */
    public function __get(string $key): mixed
    {
        if ($key === 'exists') {
            return $this->exists;
        }

        if ($key === 'id') {
            return $this->getAttribute($this->primaryKey);
        }

        return $this->getAttribute($key);
    }

    /**
     * Magic setter
     */
    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Magic isset
     */
    public function __isset(string $key): bool
    {
        if ($key === 'id') {
            return $this->hasAttribute($this->primaryKey);
        }

        return $this->hasAttribute($key);
    }

    /**
     * Save the model
     */
    public function save(): bool
    {
        if ($this->exists) {
            return $this->update();
        }

        return $this->insert();
    }

    /**
     * Insert a new record
     */
    protected function insert(): bool
    {
        if ($this->timestamps) {
            $now = date($this->dateFormat);
            $this->attributes['created_at'] = $now;
            $this->attributes['updated_at'] = $now;
        }

        $attributes = $this->attributes;

        $columns = array_keys($attributes);
        $values = array_values($attributes);
        $placeholders = array_fill(0, count($values), '?');

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $this->getTable(),
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );

        $id = self::getConnection()->insert($sql, $values);

        $this->attributes[$this->primaryKey] = $id;
        $this->original = $this->attributes;
        $this->exists = true;

        return true;
    }

    /**
     * Update existing record
     */
    protected function update(): bool
    {
        $dirty = $this->getDirty();

        if (empty($dirty)) {
            return true;
        }

        if ($this->timestamps) {
            $dirty['updated_at'] = date($this->dateFormat);
        }

        $set = [];
        $values = [];

        foreach ($dirty as $key => $value) {
            $set[] = "`{$key}` = ?";
            $values[] = $value;
        }

        $values[] = $this->attributes[$this->primaryKey];

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE `%s` = ?',
            $this->getTable(),
            implode(', ', $set),
            $this->primaryKey
        );

        self::getConnection()->update($sql, $values);

        $this->original = $this->attributes;

        return true;
    }

    /**
     * Delete the model
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $sql = sprintf(
            'DELETE FROM `%s` WHERE `%s` = ?',
            $this->getTable(),
            $this->primaryKey
        );

        self::getConnection()->delete($sql, [$this->attributes[$this->primaryKey]]);

        $this->exists = false;

        return true;
    }

    /**
     * Refresh the model from database
     */
    public function refresh(): self
    {
        if (!$this->exists) {
            return $this;
        }

        $fresh = static::find($this->attributes[$this->primaryKey]);

        if ($fresh) {
            $this->attributes = $fresh->getAttributes();
            $this->original = $this->attributes;
        }

        return $this;
    }

    /**
     * Find a record by primary key
     */
    public static function find(int|string $id): ?static
    {
        $instance = new static();
        /** @var static|null $model */
        $model = static::query()->where($instance->primaryKey, $id)->first();
        return $model;
    }

    /**
     * Get primary key name
     */
    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    /**
     * Find by attributes
     */
    public static function findBy(array $attributes): ?static
    {
        $query = static::query();

        foreach ($attributes as $key => $value) {
            $query->where($key, $value);
        }

        /** @var static|null $model */
        $model = $query->first();
        return $model;
    }

    /**
     * Create a new record
     */
    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();

        return $model;
    }

    /**
     * Update or create
     */
    public static function updateOrCreate(array $attributes, array $values = []): static
    {
        $model = static::findBy($attributes);

        if ($model) {
            $model->fill($values)->save();
        } else {
            $model = static::create(array_merge($attributes, $values));
        }

        return $model;
    }

    /**
     * Get query builder
     */
    public static function query(): QueryBuilder
    {
        $instance = new static();

        return new QueryBuilder($instance->getTable(), static::class);
    }

    /**
     * Define a has-many relationship
     */
    protected function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $foreignKey = $foreignKey ?? $this->getForeignKey();
        $localKey = $localKey ?? $this->primaryKey;

        return new HasMany($this, $related, $foreignKey, $localKey);
    }

    /**
     * Define a belongs-to relationship
     */
    protected function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        $foreignKey = $foreignKey ?? $this->getForeignKey($related);
        $ownerKey = $ownerKey ?? 'id';

        return new BelongsTo($this, $related, $foreignKey, $ownerKey);
    }

    /**
     * Get foreign key name
     */
    protected function getForeignKey(?string $related = null): string
    {
        if ($related !== null) {
            $class = basename(str_replace('\\', '/', $related));
            return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $class)) . '_id';
        }

        $class = basename(str_replace('\\', '/', static::class));
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $class)) . '_id';
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Convert to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * String representation
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}
