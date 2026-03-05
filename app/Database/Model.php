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

/**
 * Class Model
 * 
 * Base model class implementing Active Record pattern.
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
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
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
        
        return $table;
    }

    /**
     * Simple pluralization
     */
    private function pluralize(string $word): string
    {
        // Simple rules (can be expanded)
        if (str_ends_with($word, 'y') && !str_ends_with($word, 'ay') && !str_ends_with($word, 'ey') && !str_ends_with($word, 'iy') && !str_ends_with($word, 'oy') && !str_ends_with($word, 'uy')) {
            return substr($word, 0, -1) . 'ies';
        }
        
        if (str_ends_with($word, 's') || str_ends_with($word, 'x') || str_ends_with($word, 'ch') || str_ends_with($word, 'sh')) {
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
        $this->attributes[$key] = $value;
        
        return $this;
    }

    /**
     * Get an attribute
     */
    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
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
        return static::query()->where('id', $id)->first();
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
        
        return $query->first();
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

/**
 * Query Builder
 */
class QueryBuilder
{
    private string $table;
    private string $modelClass;
    private array $wheres = [];
    private array $bindings = [];
    private ?string $orderBy = null;
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(string $table, string $modelClass)
    {
        $this->table = $table;
        $this->modelClass = $modelClass;
    }

    public function where(string $column, mixed $operator = null, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND',
        ];
        
        $this->bindings[] = $value;
        
        return $this;
    }

    public function orWhere(string $column, mixed $operator = null, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR',
        ];
        
        $this->bindings[] = $value;
        
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $placeholders = array_fill(0, count($values), '?');
        
        $this->wheres[] = [
            'column' => $column,
            'operator' => 'IN',
            'value' => '(' . implode(', ', $placeholders) . ')',
            'boolean' => 'AND',
            'raw' => true,
        ];
        
        $this->bindings = array_merge($this->bindings, $values);
        
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy = "`{$column}` {$direction}";
        
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        
        return $this;
    }

    public function get(): array
    {
        $sql = $this->buildSelect();
        $results = Model::getConnection()->select($sql, $this->bindings);
        
        return array_map(function ($row) {
            $model = new $this->modelClass($row);
            $model->exists = true;
            $model->original = $row;
            return $model;
        }, $results);
    }

    public function first(): ?Model
    {
        $this->limit = 1;
        $results = $this->get();
        
        return $results[0] ?? null;
    }

    public function count(): int
    {
        $sql = $this->buildCount();
        $result = Model::getConnection()->selectOne($sql, $this->bindings);
        
        return (int) ($result['count'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function delete(): int
    {
        $sql = $this->buildDelete();
        
        return Model::getConnection()->delete($sql, $this->bindings);
    }

    public function update(array $values): int
    {
        $set = [];
        $bindings = [];
        
        foreach ($values as $key => $value) {
            $set[] = "`{$key}` = ?";
            $bindings[] = $value;
        }
        
        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $set);
        
        if (!empty($this->wheres)) {
            $sql .= $this->buildWheres();
        }
        
        $bindings = array_merge($bindings, $this->bindings);
        
        return Model::getConnection()->update($sql, $bindings);
    }

    private function buildSelect(): string
    {
        $sql = "SELECT * FROM `{$this->table}`";
        
        if (!empty($this->wheres)) {
            $sql .= $this->buildWheres();
        }
        
        if ($this->orderBy !== null) {
            $sql .= " ORDER BY {$this->orderBy}";
        }
        
        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }
        
        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }
        
        return $sql;
    }

    private function buildCount(): string
    {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}`";
        
        if (!empty($this->wheres)) {
            $sql .= $this->buildWheres();
        }
        
        return $sql;
    }

    private function buildDelete(): string
    {
        $sql = "DELETE FROM `{$this->table}`";
        
        if (!empty($this->wheres)) {
            $sql .= $this->buildWheres();
        }
        
        return $sql;
    }

    private function buildWheres(): string
    {
        $sql = '';
        
        foreach ($this->wheres as $i => $where) {
            $boolean = $i === 0 ? 'WHERE' : $where['boolean'];
            
            if (isset($where['raw']) && $where['raw']) {
                $sql .= " {$boolean} `{$where['column']}` {$where['operator']} {$where['value']}";
            } else {
                $sql .= " {$boolean} `{$where['column']}` {$where['operator']} ?";
            }
        }
        
        return $sql;
    }
}

/**
 * Relationship: Has Many
 */
class HasMany
{
    public function __construct(
        private Model $parent,
        private string $related,
        private string $foreignKey,
        private string $localKey
    ) {}

    public function get(): array
    {
        $related = new $this->related();
        
        return $this->related::query()
            ->where($this->foreignKey, $this->parent->getAttribute($this->localKey))
            ->get();
    }
}

/**
 * Relationship: Belongs To
 */
class BelongsTo
{
    public function __construct(
        private Model $child,
        private string $related,
        private string $foreignKey,
        private string $ownerKey
    ) {}

    public function get(): ?Model
    {
        return $this->related::query()
            ->where($this->ownerKey, $this->child->getAttribute($this->foreignKey))
            ->first();
    }
}
