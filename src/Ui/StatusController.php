<?php

declare(strict_types=1);

namespace Orchestra\Members\Ui;

use Orchestra\Core\Ui\Notices;
use Orchestra\Core\Ui\Redirect;
use Orchestra\Members\Capabilities;
use Orchestra\Members\Status\StatusService;

/**
 * Sets members inactive or active again (admin-post.php?action=orchestra_members_set_status).
 */
final class StatusController
{
    public const ACTION = 'orchestra_members_set_status';

    public function __construct(private readonly StatusService $status)
    {
    }

    public function register(): void
    {
        \add_action('admin_post_' . self::ACTION, [$this, 'handle']);
    }

    public static function nonceAction(int $memberId): string
    {
        return self::ACTION . '_' . $memberId;
    }

    /**
     * @internal Hooked to `admin_post_orchestra_members_set_status`.
     */
    public function handle(): never
    {
        $memberId = \absint(\is_string($_POST['member'] ?? null) ? $_POST['member'] : 0);
        \check_admin_referer(self::nonceAction($memberId));
        if (!\current_user_can(Capabilities::STATUS_EDIT)) {
            \wp_die(\esc_html__('You are not allowed to change the status of members.', 'orchestra-members'), 403);
        }

        $refusal = $this->status->refusal(\get_current_user_id(), $memberId);
        if ($refusal !== null) {
            Notices::error($refusal);
        } elseif (($_POST['status'] ?? '') === 'active') {
            $this->status->activate($memberId);
            Notices::success(\__('The member is active again.', 'orchestra-members'));
        } else {
            $this->status->deactivate($memberId);
            Notices::success(
                \__('The member is inactive. Their sensitive profile data has been deleted.', 'orchestra-members')
            );
        }

        Redirect::back();
    }
}
