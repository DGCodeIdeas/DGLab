<?php

namespace DGLab\Tests\Unit\Database;

use DGLab\Database\Model;
use DGLab\Tests\TestCase;

class TestModel extends Model
{
    protected ?string $table = 'test_table';
    protected array $fillable = ['name', 'email', 'age'];

    public function getNameAttribute($value)
    {
        return ucfirst($value);
    }

    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = strtolower($value);
    }
}

class ModelTest extends TestCase
{
    public function testFillableAttributes()
    {
        $model = new TestModel(['name' => 'john', 'email' => 'JOHN@TEST.COM', 'extra' => 'discard']);

        $this->assertEquals('John', $model->name);
        $this->assertEquals('john@test.com', $model->email);
        $this->assertFalse(isset($model->extra));
    }

    public function testDirtyChecking()
    {
        $model = new TestModel(['name' => 'john']);
        $model->syncOriginal(); // Simulate saved state

        $this->assertFalse($model->isDirty());

        $model->name = 'jane';
        $this->assertTrue($model->isDirty());
        $this->assertTrue($model->isDirty('name'));
        $this->assertArrayHasKey('name', $model->getDirty());
        $this->assertEquals('jane', $model->getDirty()['name']);
    }

    public function testAccessorsAndMutators()
    {
        $model = new TestModel();

        $model->name = 'alice';
        $this->assertEquals('Alice', $model->name);
        $this->assertEquals('alice', $model->getAttributes()['name']);

        $model->email = 'BOB@EXAMPLE.COM';
        $this->assertEquals('bob@example.com', $model->email);
        $this->assertEquals('bob@example.com', $model->getAttributes()['email']);
    }
}
