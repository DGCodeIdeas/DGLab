<?php
/**
 * DGLab PWA - Base Model Class
 * 
 * The Model class provides an Active Record-style ORM with:
 * - CRUD operations
 * - Query building through Database class
 * - Relationships (hasOne, hasMany, belongsTo)
 * - Mass assignment protection
 * - Timestamps
 * - Soft deletes
 * 
 * @package DGLab\Core
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Core;

/**
 * Base Model Class
 * 
 * All application models should extend this class.
 */
abstract class Model
{
    /**
     * @var Database|null $db Database instance
     */
    protected static ?Database $db = null;
    
    /**
     * @var string $table Database table name
     */
    protected string $table = '';
    
    /**
     * @var string $primaryKey Primary key column
     */
    protected string $primaryKey = 'id';
    
    /**
     * @var array $fillable Mass assignable columns
     */
    protected array $fillable = [];
    
    /**
     * @var array $guarded Guarded columns (not mass assignable)
     */
    protected array $guarded = ['id'];
    
    /**
     * @var bool $timestamps Whether to use created_at/updated_at
     */
    protected bool $timestamps = true;
    
    /**
     * @var bool $softDeletes Whether to use soft deletes
     */
    protected bool $softDeletes = false;
    
    /**
     * @var string $createdAtColumn Created at column name
     */
    protected string $createdAtColumn = 'created_at';
    
    /**
     * @var string $updatedAtColumn Updated at column name
     */
    protected string $updatedAtColumn = 'updated_at';
    
    /**
     * @var string $deletedAtColumn Deleted at column name
     */
    protected string $deletedAtColumn = 'deleted_at';
    
    /**
     * @var array $attributes Model attributes
     */
    protected array $attributes = [];
    
    /**
     * @var array $original Original attribute values
     */
    protected array $original = [];
    
    /**
     * @var bool $exists Whether model exists in database
     */
    protected bool $exists = false;
    
    /**
     * @var array $relations Loaded relationships
     */
    protected array $relations = [];
    
    /**
     * @var array $appends Attributes to append to array/JSON
     */
    protected array $appends = [];

    /**
     * Constructor
     * 
     * @param array $attributes Initial attributes
     * @param bool $exists Whether model exists in database
     */
    public function __construct(array $attributes = [], bool $exists = false)
    {
        $this->exists = $exists;
        
        if ($exists) {
            $this->original = $attributes;
        }
        
        $this->fill($attributes);
        
        // Initialize database connection if not set
        if (self::$db === null) {
            self::$db = Database::getInstance();
        }
    }

    // =============================================================================
    // STATIC FACTORY METHODS
    // =============================================================================

    /**
     * Find a model by primary key
     * 
     * @param mixed $id Primary key value
     * @return static|null Model instance or null
     */
    public static function find($id): ?self
    {
        $instance = new static();
        
        $query = self::$db->select($instance->table)
            ->where($instance->primaryKey, $id);
        
        // Add soft delete check
        if ($instance->softDeletes) {
            $query->whereNull($instance->deletedAtColumn);
        }
        
        $result = $query->first();
        
        return $result ? new static($result, true) : null;
    }

    /**
     * Find all models
     * 
     * @return array Array of model instances
     */
    public static function all(): array
    {
        $instance = new static();
        
        $query = self::$db->select($instance->table);
        
        // Add soft delete check
        if ($instance->softDeletes) {
            $query->whereNull($instance->deletedAtColumn);
        }
        
        $results = $query->get();
        
        return array_map(function ($row) {
            return new static($row, true);
        }, $results);
    }

    /**
     * Find models by criteria
     * 
     * @param string $column Column name
     * @param mixed $value Column value
     * @return array Array of model instances
     */
    public static function where(string $column, $value): array
    {
        $instance = new static();
        
        $query = self::$db->select($instance->table)
            ->where($column, $value);
        
        // Add soft delete check
        if ($instance->softDeletes) {
            $query->whereNull($instance->deletedAtColumn);
        }
        
        $results = $query->get();
        
        return array_map(function ($row) {
            return new static($row, true);
        }, $results);
    }

    /**
     * Find first model by criteria
     * 
     * @param string $column Column name
     * @param mixed $value Column value
     * @return static|null Model instance or null
     */
    public static function firstWhere(string $column, $value): ?self
    {
        $results = static::where($column, $value);
        return $results[0] ?? null;
    }

    /**
     * Create a new model
     * 
     * @param array $attributes Model attributes
     * @return static Created model instance
     */
    public static function create(array $attributes): self
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    /**
     * Start a query builder
     * 
     * @return QueryBuilder Query builder instance
     */
    public static function query(): QueryBuilder
    {
        $instance = new static();
        $query = self::$db->select($instance->table);
        
        // Add soft delete check
        if ($instance->softDeletes) {
            $query->whereNull($instance->deletedAtColumn);
        }
        
        return $query;
    }

    // =============================================================================
    // INSTANCE METHODS
    // =============================================================================

    /**
     * Fill model attributes (mass assignment)
     * 
     * @param array $attributes Attributes to fill
     * @return self For method chaining
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
     * Save model to database
     * 
     * @return bool True on success
     */
    public function save(): bool
    {
        // Update timestamps
        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            
            if (!$this->exists) {
                $this->setAttribute($this->createdAtColumn, $now);
            }
            
            $this->setAttribute($this->updatedAtColumn, $now);
        }
        
        if ($this->exists) {
            return $this->performUpdate();
        } else {
            return $this->performInsert();
        }
    }

    /**
     * Delete model from database
     * 
     * @param bool $force Force delete (ignore soft deletes)
     * @return bool True on success
     */
    public function delete(bool $force = false): bool
    {
        if (!$this->exists) {
            return false;
        }
        
        // Soft delete
        if ($this->softDeletes && !$force) {
            $this->setAttribute($this->deletedAtColumn, date('Y-m-d H:i:s'));
            return $this->performUpdate();
        }
        
        // Hard delete
        $id = $this->getAttribute($this->primaryKey);
        
        $result = self::$db->delete(
            $this->table,
            "{$this->primaryKey} = ?",
            [$id]
        );
        
        if ($result) {
            $this->exists = false;
            $this->original = [];
        }
        
        return (bool) $result;
    }

    /**
     * Restore soft-deleted model
     * 
     * @return bool True on success
     */
    public function restore(): bool
    {
        if (!$this->softDeletes || !$this->exists) {
            return false;
        }
        
        $this->setAttribute($this->deletedAtColumn, null);
        return $this->performUpdate();
    }

    /**
     * Refresh model from database
     * 
     * @return self For method chaining
     */
    public function refresh(): self
    {
        if (!$this->exists) {
            return $this;
        }
        
        $id = $this->getAttribute($this->primaryKey);
        $fresh = static::find($id);
        
        if ($fresh) {
            $this->attributes = $fresh->attributes;
            $this->original = $fresh->original;
        }
        
        return $this;
    }

    // =============================================================================
    // ATTRIBUTE METHODS
    // =============================================================================

    /**
     * Get attribute value
     * 
     * @param string $key Attribute name
     * @return mixed Attribute value
     */
    public function getAttribute(string $key)
    {
        // Check for getter method
        $method = 'get' . str_replace('_', '', ucwords($key, '_')) . 'Attribute';
        
        if (method_exists($this, $method)) {
            return $this->$method($this->attributes[$key] ?? null);
        }
        
        return $this->attributes[$key] ?? null;
    }

    /**
     * Set attribute value
     * 
     * @param string $key Attribute name
     * @param mixed $value Attribute value
     * @return self For method chaining
     */
    public function setAttribute(string $key, $value): self
    {
        // Check for setter method
        $method = 'set' . str_replace('_', '', ucwords($key, '_')) . 'Attribute';
        
        if (method_exists($this, $method)) {
            $value = $this->$method($value);
        }
        
        $this->attributes[$key] = $value;
        
        return $this;
    }

    /**
     * Check if attribute exists
     * 
     * @param string $key Attribute name
     * @return bool True if exists
     */
    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Get all attributes
     * 
     * @return array All attributes
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get dirty attributes (changed since load)
     * 
     * @return array Changed attributes
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
     * 
     * @return bool True if has changes
     */
    public function isDirty(): bool
    {
        return !empty($this->getDirty());
    }

    // =============================================================================
    // RELATIONSHIP METHODS
    // =============================================================================

    /**
     * Define has-one relationship
     * 
     * @param string $related Related model class
     * @param string|null $foreignKey Foreign key column
     * @param string|null $localKey Local key column
     * @return static|null Related model
     */
    protected function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): ?self
    {
        $relatedInstance = new $related();
        $foreignKey = $foreignKey ?? $this->getForeignKey();
        $localKey = $localKey ?? $this->primaryKey;
        
        return $related::firstWhere($foreignKey, $this->getAttribute($localKey));
    }

    /**
     * Define has-many relationship
     * 
     * @param string $related Related model class
     * @param string|null $foreignKey Foreign key column
     * @param string|null $localKey Local key column
     * @return array Related models
     */
    protected function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): array
    {
        $relatedInstance = new $related();
        $foreignKey = $foreignKey ?? $this->getForeignKey();
        $localKey = $localKey ?? $this->primaryKey;
        
        return $related::where($foreignKey, $this->getAttribute($localKey));
    }

    /**
     * Define belongs-to relationship
     * 
     * @param string $related Related model class
     * @param string|null $foreignKey Foreign key column
     * @param string|null $ownerKey Owner key column
     * @return static|null Related model
     */
    protected function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): ?self
    {
        $relatedInstance = new $related();
        $foreignKey = $foreignKey ?? $relatedInstance->getForeignKey();
        $ownerKey = $ownerKey ?? $relatedInstance->primaryKey;
        
        return $related::find($this->getAttribute($foreignKey));
    }

    /**
     * Get foreign key name for this model
     * 
     * @return string Foreign key name
     */
    protected function getForeignKey(): string
    {
        $class = get_class($this);
        $parts = explode('\\', $class);
        $name = strtolower(end($parts));
        
        return $name . '_id';
    }

    // =============================================================================
    // PRIVATE METHODS
    // =============================================================================

    /**
     * Check if column is fillable
     * 
     * @param string $key Column name
     * @return bool True if fillable
     */
    private function isFillable(string $key): bool
    {
        // If fillable is empty, all columns are fillable except guarded
        if (empty($this->fillable)) {
            return !in_array($key, $this->guarded, true);
        }
        
        return in_array($key, $this->fillable, true);
    }

    /**
     * Perform insert operation
     * 
     * @return bool True on success
     */
    private function performInsert(): bool
    {
        $id = self::$db->insert($this->table, $this->attributes);
        
        if ($id) {
            $this->setAttribute($this->primaryKey, $id);
            $this->original = $this->attributes;
            $this->exists = true;
            return true;
        }
        
        return false;
    }

    /**
     * Perform update operation
     * 
     * @return bool True on success
     */
    private function performUpdate(): bool
    {
        $dirty = $this->getDirty();
        
        if (empty($dirty)) {
            return true;
        }
        
        $id = $this->getAttribute($this->primaryKey);
        
        $result = self::$db->update(
            $this->table,
            $dirty,
            "{$this->primaryKey} = ?",
            [$id]
        );
        
        if ($result) {
            $this->original = $this->attributes;
        }
        
        return (bool) $result;
    }

    // =============================================================================
    // MAGIC METHODS
    // =============================================================================

    /**
     * Get attribute via property access
     * 
     * @param string $key Attribute name
     * @return mixed Attribute value
     */
    public function __get(string $key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Set attribute via property access
     * 
     * @param string $key Attribute name
     * @param mixed $value Attribute value
     */
    public function __set(string $key, $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Check if attribute exists via property access
     * 
     * @param string $key Attribute name
     * @return bool True if exists
     */
    public function __isset(string $key): bool
    {
        return $this->hasAttribute($key);
    }

    /**
     * Convert model to array
     * 
     * @return array Model as array
     */
    public function toArray(): array
    {
        $array = $this->attributes;
        
        // Add appended attributes
        foreach ($this->appends as $key) {
            $array[$key] = $this->getAttribute($key);
        }
        
        return $array;
    }

    /**
     * Convert model to JSON
     * 
     * @return string JSON representation
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * String representation
     * 
     * @return string JSON representation
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}
