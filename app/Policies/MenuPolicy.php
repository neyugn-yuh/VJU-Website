<?php

namespace App\Policies;

class MenuPolicy extends PermissionPolicy
{
    protected string $permission = 'menus.manage';
}
