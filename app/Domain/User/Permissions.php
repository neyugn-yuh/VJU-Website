<?php

namespace App\Domain\User;

use App\Models\User;

/**
 * Permission catalogue and role matrix. Seeded by RolesAndPermissionsSeeder; ownership rules live in policies.
 */
final class Permissions
{
    public const ALL = [
        'content.view', 'content.viewAny', 'content.viewOwn',
        'content.create',
        'content.update', 'content.updateOwn',
        'content.delete', 'content.deleteOwn',
        'content.publish', 'content.publishOwn',
        'content.schedule', 'content.restore', 'content.forceDelete',
        'media.view', 'media.upload', 'media.update', 'media.delete',
        'taxonomy.manage', 'menus.manage', 'seo.manage', 'comments.moderate',
        'users.manage', 'settings.manage', 'audit.view', 'migration.run',
    ];

    /** @return array<string, list<string>> */
    public static function matrix(): array
    {
        return [
            User::ROLE_ADMIN => self::ALL,
            User::ROLE_EDITOR => [
                'content.view', 'content.viewAny', 'content.viewOwn', 'content.create',
                'content.update', 'content.updateOwn', 'content.delete', 'content.deleteOwn',
                'content.publish', 'content.publishOwn', 'content.schedule', 'content.restore',
                'media.view', 'media.upload', 'media.update', 'media.delete',
                'taxonomy.manage', 'menus.manage', 'seo.manage', 'comments.moderate',
            ],
            User::ROLE_AUTHOR => [
                'content.viewOwn', 'content.create', 'content.updateOwn', 'content.deleteOwn',
                'content.publishOwn', 'content.schedule',
                'media.view', 'media.upload',
            ],
            User::ROLE_CONTRIBUTOR => [
                'content.viewOwn', 'content.create', 'content.updateOwn',
                'media.view',
            ],
        ];
    }
}
