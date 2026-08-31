<?php

namespace DGLab\Models;

use DGLab\Database\Model;
use DGLab\Database\Connection;

/**
 * @property int $id
 * @property string $name
 * @property string $description
 */
class Role extends Model
{
    protected ?string $table = 'roles';
    protected array $fillable = ['name', 'description'];

    public function permissions(): array
    {
        $sql = "SELECT p.* FROM permissions p
                INNER JOIN role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role_id = ?";

        $results = Connection::getInstance()->select($sql, [$this->id]);
        return array_map(fn($r) => new Permission($r), $results);
    }
}
