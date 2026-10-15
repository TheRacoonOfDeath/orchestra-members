<?php

declare(strict_types=1);

namespace Orchestra\Members\Ui;

use Orchestra\Core\Ui\Html;
use Orchestra\Core\Ui\Redirect;
use Orchestra\Members\Capabilities;

/**
 * `[orchestra_members]`: the member list, and for organizers the profiles and status changes
 * (`?orchestra_member=ID`).
 */
final class MembersShortcode
{
    public const TAG = 'orchestra_members';
    private const PARAMETER = 'orchestra_member';

    public function __construct(private readonly MembersView $view)
    {
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

        $base = \remove_query_arg(self::PARAMETER, Redirect::currentUrl());
        $url = static fn (?int $memberId): string => $memberId === null
            ? $base
            : \add_query_arg(self::PARAMETER, $memberId, $base);
        $memberId = \absint(\is_string($_GET[self::PARAMETER] ?? null) ? $_GET[self::PARAMETER] : 0);

        return Html::page($memberId > 0 ? $this->view->member($memberId, $url) : $this->view->list($url));
    }
}
