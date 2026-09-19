<?php

namespace DGLab\Events\Auth;

use DGLab\Core\BaseEvent;

class LoginFailed extends BaseEvent
{
    public function __construct(public string $username, public array $credentials = [])
    {
    }
}
