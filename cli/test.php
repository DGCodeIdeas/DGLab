<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DGLab\Core\Application;

$app = new Application(dirname(__DIR__));

if ($argc < 2) {
    echo "Usage: php cli/test.php [command] [args...]\n";
    echo "Commands:\n";
    echo "  make:component-test [component-name]  Scaffold a new component test\n";
    echo "  make:integration-test [test-name]      Scaffold a new integration test\n";
    exit(1);
}

$command = $argv[1];

switch ($command) {
    case 'make:component-test':
        if (!isset($argv[2])) {
            echo "Error: Component name is required.\n";
            exit(1);
        }
        $componentName = $argv[2];
        $className = str_replace(['.', '/'], '', ucwords($componentName, './')) . 'Test';
        $filePath = __DIR__ . "/../tests/Unit/Components/{$className}.php";

        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0777, true);
        }

        if (file_exists($filePath)) {
            echo "Error: Test file already exists: {$filePath}\n";
            exit(1);
        }

        $template = <<<TPL
<?php

namespace DGLab\Tests\Unit\Components;

use DGLab\Tests\ComponentTestCase;

class {$className} extends ComponentTestCase
{
    public function test_it_renders_successfully()
    {
        \$this->assertComponentRenders('{$componentName}');
    }

    public function test_it_renders_with_props()
    {
        \$props = [
            'label' => 'Click Me',
        ];
        \$this->assertComponentRenders('{$componentName}', \$props, 'Click Me');
    }
}
TPL;
        file_put_contents($filePath, $template);
        echo "Successfully created component test: {$filePath}\n";
        break;

    case 'make:integration-test':
        if (!isset($argv[2])) {
            echo "Error: Test name is required.\n";
            exit(1);
        }
        $testName = $argv[2];
        if (!str_ends_with($testName, 'Test')) {
            $testName .= 'Test';
        }
        $className = ucwords($testName);
        $filePath = __DIR__ . "/../tests/Integration/{$className}.php";

        if (file_exists($filePath)) {
            echo "Error: Test file already exists: {$filePath}\n";
            exit(1);
        }

        $template = <<<TPL
<?php

namespace DGLab\Tests\Integration;

use DGLab\Tests\TestCase;

class {$className} extends TestCase
{
    public function test_example()
    {
        \$this->addTestRoute('GET', '/test-route', function() {
            return 'OK';
        });

        \$this->get('/test-route');
        \$this->assertStatus(\$this->lastResponse, 200);
        \$this->assertSee('OK');
    }
}
TPL;
        file_put_contents($filePath, $template);
        echo "Successfully created integration test: {$filePath}\n";
        break;

    default:
        echo "Unknown command: {$command}\n";
        exit(1);
}
