<?php

namespace DGLab\Models;

use DGLab\Database\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $description
 */
class Permission extends Model
{
    protected ?string $table = 'permissions';
    protected array $fillable = ['name', 'description'];
}
