<?php

namespace DGLab\Events\Auth;

use DGLab\Core\BaseEvent;

class UserLoggedOut extends BaseEvent
{
    public function __construct(public mixed $user) {}
}
