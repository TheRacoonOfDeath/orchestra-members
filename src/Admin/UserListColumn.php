<?php

declare(strict_types=1);

namespace Orchestra\Members\Admin;

use Orchestra\Members\MemberDirectory;
use Orchestra\Members\Profile\ProfileRepository;
use WP_User;

/**
 * Shows the orchestra status in the WordPress user list (Users → All Users).
 */
final class UserListColumn
{
    private const COLUMN = 'orchestra_members_status';

    public function __construct(private readonly ProfileRepository $profiles)
    {
    }

    public function register(): void
    {
        \add_filter('manage_users_columns', [$this, 'addColumn']);
        \add_filter('manage_users_custom_column', [$this, 'renderColumn'], 10, 3);
    }

    /**
     * @internal Hooked to `manage_users_columns`.
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function addColumn(array $columns): array
    {
        $columns[self::COLUMN] = \__('Orchestra status', 'orchestra-members');

        return $columns;
    }

    /**
     * @internal Hooked to `manage_users_custom_column`.
     */
    public function renderColumn(string $output, string $column, int $userId): string
    {
        if ($column !== self::COLUMN) {
            return $output;
        }
        $user = \get_userdata($userId);
        if (!$user instanceof WP_User || MemberDirectory::orchestraRoles($user) === []) {
            return '—';
        }

        return \esc_html(StatusLabel::of($this->profiles->isActive($userId)));
    }
}
