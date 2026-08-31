<?php

namespace DGLab\Events\Auth;

use DGLab\Core\BaseEvent;
use DGLab\Models\User;

class PasswordChanged extends BaseEvent
{
    public function __construct(public User $user)
    {
    }
}
