<?php

declare(strict_types=1);

namespace Orchestra\Members\Ui;

use Orchestra\Core\Ui\Notices;
use Orchestra\Core\Ui\Redirect;
use Orchestra\Members\Account\MemberCreator;
use Orchestra\Members\Account\NewMemberInput;
use Orchestra\Members\Capabilities;

/**
 * Adds a WordPress user to the orchestra (admin-post.php?action=orchestra_members_add_member).
 */
final class NewMemberController
{
    public const ACTION = 'orchestra_members_add_member';

    public function __construct(private readonly MemberCreator $creator)
    {
    }

    public function register(): void
    {
        \add_action('admin_post_' . self::ACTION, [$this, 'handle']);
    }

    /**
     * Only users who may give any role choose one; everyone else adds members.
     */
    public static function canChooseRole(): bool
    {
        return \current_user_can('promote_users');
    }

    /**
     * @internal Hooked to `admin_post_orchestra_members_add_member`.
     */
    public function handle(): never
    {
        \check_admin_referer(self::ACTION);
        if (!\current_user_can(Capabilities::MEMBER_CREATE)) {
            \wp_die(\esc_html__('You are not allowed to add members.', 'orchestra-members'), 403);
        }

        $input = NewMemberInput::fromForm(\wp_unslash($_POST), self::canChooseRole());
        $result = $this->creator->create($input);
        if (\is_array($result)) {
            Notices::errors($result);
        } else {
            Notices::success(\sprintf(
                /* translators: %s: name of the new member */
                \__('%s has been added and receives an email with a link to set a password.', 'orchestra-members'),
                $input->displayName()
            ));
        }

        Redirect::back();
    }
}
