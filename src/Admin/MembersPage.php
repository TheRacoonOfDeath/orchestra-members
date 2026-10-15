<?php

declare(strict_types=1);

namespace Orchestra\Members\Admin;

use Orchestra\Core\Ui\Html;
use Orchestra\Members\Capabilities;
use Orchestra\Members\Ui\MembersView;

/**
 * The "Members" screen in wp-admin, showing the same {@see MembersView} as `[orchestra_members]`.
 */
final class MembersPage
{
    public const SLUG = 'orchestra-members';

    public function __construct(private readonly MembersView $view)
    {
    }

    public function register(): void
    {
        \add_action('admin_menu', [$this, 'addMenu']);
    }

    public static function url(?int $memberId = null): string
    {
        $args = ['page' => self::SLUG];
        if ($memberId !== null) {
            $args['member'] = $memberId;
        }

        return \add_query_arg($args, \admin_url('admin.php'));
    }

    /**
     * @internal Hooked to `admin_menu`.
     */
    public function addMenu(): void
    {
        $hookSuffix = \add_menu_page(
            \__('Members', 'orchestra-members'),
            \__('Members', 'orchestra-members'),
            Capabilities::DIRECTORY_READ,
            self::SLUG,
            [$this, 'render'],
            'dashicons-groups',
            71
        );
        \add_action('load-' . $hookSuffix, [$this, 'authorizeProfileView']);
    }

    /**
     * Denies access to a profile before any output is sent, so the response has status 403.
     *
     * @internal Hooked to `load-{page}`.
     */
    public function authorizeProfileView(): void
    {
        $memberId = self::requestedMemberId();
        if ($memberId > 0 && !$this->view->canView($memberId)) {
            \wp_die(\esc_html__('You are not allowed to view this profile.', 'orchestra-members'), 403);
        }
    }

    /**
     * @internal Menu page callback.
     */
    public function render(): void
    {
        $memberId = self::requestedMemberId();
        $url = static fn (?int $id): string => self::url($id);

        echo Html::adminPage(
            \__('Members', 'orchestra-members'),
            $memberId > 0 ? $this->view->member($memberId, $url) : $this->view->list($url)
        ); // escaped by the view
    }

    private static function requestedMemberId(): int
    {
        return \absint(\is_string($_GET['member'] ?? null) ? $_GET['member'] : 0);
    }
}
