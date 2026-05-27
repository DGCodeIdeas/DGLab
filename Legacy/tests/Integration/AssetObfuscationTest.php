<?php

namespace DGLab\Tests\Integration;

use DGLab\Tests\IntegrationTestCase;

class AssetObfuscationTest extends IntegrationTestCase
{
    public function testObfuscationPlaceholder()
    {
        $this->markTestSkipped('Asset obfuscation testing via cli/obfuscate.js is disabled in this environment.');
    }
}
