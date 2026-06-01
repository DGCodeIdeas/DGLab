<?php

namespace DGLab\Tests\Unit\Nexus;

use PHPUnit\Framework\TestCase;
use DGLab\Services\Nexus\HandshakeValidator;
use DGLab\Services\Auth\JWTService;
use DGLab\Services\Auth\Repositories\UserRepository;
use DGLab\Models\User;
use RuntimeException;
use stdClass;

class HandshakeValidatorTest extends TestCase
{
    protected $jwtService;
    protected $userRepository;
    protected $validator;
    protected $secret = 'test-secret';

    protected function setUp(): void
    {
        $this->jwtService = $this->createMock(JWTService::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->validator = new HandshakeValidator(
            $this->jwtService,
            $this->userRepository,
            $this->secret
        );
    }

    public function testValidateSuccessful()
    {
        $request = new stdClass();
        $request->header = ['authorization' => 'Bearer valid-token'];

        $user = new User();
        $user->id = 1;

        $this->jwtService->method('decode')->willReturn(['sub' => 1]);
        $this->userRepository->method('find')->with(1)->willReturn($user);

        $result = $this->validator->validate($request);
        $this->assertSame($user, $result);
    }

    public function testValidateMissingToken()
    {
        $request = new stdClass();
        $request->header = [];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Authentication token missing');

        $this->validator->validate($request);
    }

    public function testValidateInvalidToken()
    {
        $request = new stdClass();
        $request->get = ['token' => 'invalid-token'];

        $this->jwtService->method('decode')->willThrowException(new RuntimeException('Invalid signature'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid or expired token');

        $this->validator->validate($request);
    }

    public function testValidateUserNotFound()
    {
        $request = new stdClass();
        $request->header = ['sec-websocket-protocol' => 'valid-token'];

        $this->jwtService->method('decode')->willReturn(['sub' => 999]);
        $this->userRepository->method('find')->with(999)->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('User not found');

        $this->validator->validate($request);
    }
}
