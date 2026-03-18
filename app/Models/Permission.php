<?php

namespace DGLab\Models;

use DGLab\Database\Model;

class Permission extends Model
{
    protected ?string $table = 'permissions';
    protected array $fillable = ['name', 'description'];
}
