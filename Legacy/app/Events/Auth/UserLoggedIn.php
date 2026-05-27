<?php

namespace DGLab\Events\Auth;

use DGLab\Core\BaseEvent;

class UserLoggedIn extends BaseEvent
{
    public function __construct(public mixed $user)
    {
    }
}
