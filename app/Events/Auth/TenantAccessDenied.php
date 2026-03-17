<?php

namespace DGLab\Events\Auth;

use DGLab\Core\BaseEvent;
use DGLab\Models\User;
use DGLab\Models\Tenant;

class TenantAccessDenied extends BaseEvent
{
    public function __construct(public User $user, public Tenant $tenant) {}
}
