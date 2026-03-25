<?php

/**
 * Model Tests
 */

namespace DGLab\Tests\Unit\Database;

use DGLab\Database\Connection;
use DGLab\Database\Model;
use DGLab\Tests\TestCase;

class ModelTest extends \DGLab\Tests\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up in-memory SQLite
        $db = new Connection([
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ],
            ],
        ]);

        Model::setConnection($db);

        // Create test table
        $db->statement('
            CREATE TABLE test_models (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                email TEXT,
                created_at TEXT,
                updated_at TEXT
            )
        ');
    }

    public function testCreate(): void
    {
        $model = TestModel::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertNotNull($model->id);
        $this->assertEquals('John Doe', $model->name);
        $this->assertEquals('john@example.com', $model->email);
        $this->assertTrue($model->exists);
    }

    public function testFind(): void
    {
        $created = TestModel::create(['name' => 'Test', 'email' => 'test@test.com']);
        $found = TestModel::find($created->id);

        $this->assertNotNull($found);
        $this->assertEquals($created->id, $found->id);
        $this->assertEquals('Test', $found->name);
    }

    public function testUpdate(): void
    {
        $model = TestModel::create(['name' => 'Original', 'email' => 'test@test.com']);

        $model->name = 'Updated';
        $model->save();

        $refreshed = TestModel::find($model->id);
        $this->assertEquals('Updated', $refreshed->name);
    }

    public function testDelete(): void
    {
        $model = TestModel::create(['name' => 'To Delete', 'email' => 'test@test.com']);
        $id = $model->id;

        $model->delete();

        $this->assertFalse($model->exists);
        $this->assertNull(TestModel::find($id));
    }

    public function testQueryBuilder(): void
    {
        TestModel::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        TestModel::create(['name' => 'Bob', 'email' => 'bob@test.com']);
        TestModel::create(['name' => 'Charlie', 'email' => 'charlie@test.com']);

        $all = TestModel::query()->get();
        $this->assertCount(3, $all);

        $alice = TestModel::query()->where('name', 'Alice')->first();
        $this->assertNotNull($alice);
        $this->assertEquals('alice@test.com', $alice->email);
    }

    public function testFillable(): void
    {
        $model = new TestModel();
        $model->fill(['name' => 'Test', 'email' => 'test@test.com', 'guarded_field' => 'should_not_set']);

        $this->assertEquals('Test', $model->name);
        $this->assertEquals('test@test.com', $model->email);
        $this->assertNull($model->guarded_field ?? null);
    }

    public function testDirtyChecking(): void
    {
        $model = TestModel::create(['name' => 'Test', 'email' => 'test@test.com']);

        $this->assertFalse($model->isDirty());

        $model->name = 'Changed';
        $this->assertTrue($model->isDirty());
        $this->assertTrue($model->isDirty('name'));
        $this->assertFalse($model->isDirty('email'));
    }
}

class TestModel extends Model
{
    protected ?string $table = 'test_models';
    protected array $fillable = ['name', 'email'];
    protected bool $timestamps = false;
}
