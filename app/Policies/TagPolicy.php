<?php

namespace App\Policies;

class TagPolicy extends PermissionPolicy
{
    protected string $permission = 'taxonomy.manage';
}
