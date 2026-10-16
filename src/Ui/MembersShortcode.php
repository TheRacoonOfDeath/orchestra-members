<?php

declare(strict_types=1);

namespace Orchestra\Members\Ui;

use Orchestra\Core\Ui\Html;
use Orchestra\Core\Ui\Redirect;
use Orchestra\Members\Capabilities;

/**
 * `[orchestra_members]`: the member list, and for organizers the profiles and status changes
 * (`?orchestra_member=ID`). Administrators also choose the profile fields here
 * (`?orchestra_members_view=fields`).
 */
final class MembersShortcode
{
    public const TAG = 'orchestra_members';
    private const PARAMETER = 'orchestra_member';
    private const VIEW_PARAMETER = 'orchestra_members_view';
    private const VIEW_FIELDS = 'fields';

    public function __construct(
        private readonly MembersView $view,
        private readonly ProfileFieldsView $fieldsView,
    ) {
    }

    public function register(): void
    {
        \add_shortcode(self::TAG, [$this, 'render']);
    }

    /**
     * @internal Shortcode callback.
     */
    public function render(): string
    {
        if (!\is_user_logged_in()) {
            return Html::loginRequired(\__('Please log in to see the members.', 'orchestra-members'));
        }
        if (!\current_user_can(Capabilities::DIRECTORY_READ)) {
            return Html::notAllowed(\__('You are not allowed to see the members.', 'orchestra-members'));
        }

        $base = \remove_query_arg([self::PARAMETER, self::VIEW_PARAMETER], Redirect::currentUrl());
        if (($_GET[self::VIEW_PARAMETER] ?? null) === self::VIEW_FIELDS) {
            return $this->fields($base);
        }

        $url = static fn (?int $memberId): string => $memberId === null
            ? $base
            : \add_query_arg(self::PARAMETER, $memberId, $base);
        $memberId = \absint(\is_string($_GET[self::PARAMETER] ?? null) ? $_GET[self::PARAMETER] : 0);

        return Html::page(
            $memberId > 0
                ? $this->view->member($memberId, $url)
                : $this->view->list($url, \add_query_arg(self::VIEW_PARAMETER, self::VIEW_FIELDS, $base))
        );
    }

    private function fields(string $listUrl): string
    {
        if (!\current_user_can(Capabilities::FIELDS_MANAGE)) {
            return Html::notAllowed(\__('You are not allowed to change the profile fields.', 'orchestra-members'));
        }

        return Html::page(
            '<p><a href="' . \esc_url($listUrl) . '">&larr; ' . \esc_html__('All members', 'orchestra-members')
                . '</a></p><h2>' . \esc_html__('Profile fields', 'orchestra-members') . '</h2>'
                . $this->fieldsView->form()
        );
    }
}
