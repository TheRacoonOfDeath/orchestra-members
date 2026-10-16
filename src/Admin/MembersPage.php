<?php

declare(strict_types=1);

namespace Orchestra\Members\Admin;

use Orchestra\Core\Ui\Html;
use Orchestra\Members\Capabilities;
use Orchestra\Members\Ui\MembersView;
use Orchestra\Members\Ui\ProfileFieldsView;

/**
 * The "Members" screen in wp-admin, showing the same {@see MembersView} as `[orchestra_members]`.
 */
final class MembersPage
{
    public const SLUG = 'orchestra-members';
    public const FIELDS_SLUG = 'orchestra-members-fields';

    public function __construct(
        private readonly MembersView $view,
        private readonly ProfileFieldsView $fieldsView,
    ) {
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
        \add_submenu_page(
            self::SLUG,
            \__('All Members', 'orchestra-members'),
            \__('All Members', 'orchestra-members'),
            Capabilities::DIRECTORY_READ,
            self::SLUG,
            [$this, 'render']
        );
        \add_submenu_page(
            self::SLUG,
            \__('Profile Fields', 'orchestra-members'),
            \__('Profile Fields', 'orchestra-members'),
            Capabilities::FIELDS_MANAGE,
            self::FIELDS_SLUG,
            [$this, 'renderFields']
        );
    }

    public static function fieldsUrl(): string
    {
        return \add_query_arg('page', self::FIELDS_SLUG, \admin_url('admin.php'));
    }

    /**
     * @internal Submenu page callback.
     */
    public function renderFields(): void
    {
        echo Html::adminPage(
            \__('Profile Fields', 'orchestra-members'),
            $this->fieldsView->form()
        ); // escaped by the view
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
            $memberId > 0 ? $this->view->member($memberId, $url) : $this->view->list($url, self::fieldsUrl())
        ); // escaped by the view
    }

    private static function requestedMemberId(): int
    {
        return \absint(\is_string($_GET['member'] ?? null) ? $_GET['member'] : 0);
    }
}
