<?php

namespace DGLab\Tests\Unit\Core;

use DGLab\Core\AuditService;
use DGLab\Core\Request;
use DGLab\Database\Connection;
use DGLab\Services\Auth\AuthManager;
use DGLab\Services\Tenancy\TenancyService;
use DGLab\Tests\TestCase;
use Prophecy\Argument;

class AuditServiceTest extends TestCase
{
    private AuditService $audit;
    private $db;
    private $request;
    private $tenancy;
    private $auth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = $this->prophesize(Connection::class);
        $this->request = $this->prophesize(Request::class);
        $this->tenancy = $this->prophesize(TenancyService::class);
        $this->auth = $this->prophesize(AuthManager::class);

        $this->audit = new AuditService(
            $this->db->reveal(),
            $this->request->reveal(),
            $this->tenancy->reveal(),
            $this->auth->reveal()
        );
    }

    public function testLog()
    {
        $this->auth->id()->willReturn(1);
        $this->tenancy->getCurrentTenantId()->willReturn(10);
        $this->request->getServer('REMOTE_ADDR')->willReturn('127.0.0.1');
        $this->request->getServer('HTTP_USER_AGENT')->willReturn('PHPUnit');

        // Use any() for return to avoid PHPStan/Double type mismatch if it exists,
        // though insert() expects int. Prophecy reveal returns an ObjectProphecy.
        $this->db->insert(Argument::any(), Argument::any())->willReturn(123);

        $this->audit->log('test-cat', 'test-event', 'test-id', ['foo' => 'bar'], 200, 100);

        $this->db->insert(Argument::containingString('INSERT INTO audit_logs'), Argument::that(function($args) {
            return $args[0] === 10 && $args[1] === 1 && $args[2] === 'test-cat';
        }))->shouldHaveBeenCalled();
    }
}
