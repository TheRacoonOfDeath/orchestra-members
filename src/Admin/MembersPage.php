<?php

declare(strict_types=1);

namespace Orchestra\Members\Admin;

use Orchestra\Core\Authorization\Roles;
use Orchestra\Members\Capabilities;
use Orchestra\Members\Member;
use Orchestra\Members\MemberDirectory;
use Orchestra\Members\Profile\ProfileRepository;
use Orchestra\Members\Status\StatusService;

/**
 * The "Members" screen: the member list for everyone, profiles and status changes for organizers.
 */
final class MembersPage
{
    public const SLUG = 'orchestra-members';
    public const STATUS_ACTION = 'orchestra_members_set_status';

    public function __construct(
        private readonly MemberDirectory $directory,
        private readonly ProfileRepository $profiles,
        private readonly StatusService $status,
        private readonly Notices $notices,
    ) {
    }

    public function register(): void
    {
        \add_action('admin_menu', [$this, 'addMenu']);
        \add_action('admin_post_' . self::STATUS_ACTION, [$this, 'handleStatusChange']);
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
        if (
            $memberId > 0
            && (
                $this->directory->member($memberId) === null
                || !\current_user_can(Capabilities::PROFILE_READ, $memberId)
            )
        ) {
            \wp_die(\esc_html__('You are not allowed to view this profile.', 'orchestra-members'), 403);
        }
    }

    /**
     * @internal Menu page callback.
     */
    public function render(): void
    {
        $memberId = self::requestedMemberId();
        if ($memberId > 0) {
            $this->renderMember($memberId);
            return;
        }

        $this->renderList();
    }

    /**
     * @internal Hooked to `admin_post_orchestra_members_set_status`.
     */
    public function handleStatusChange(): void
    {
        $memberId = \absint(\is_string($_POST['member'] ?? null) ? $_POST['member'] : 0);
        \check_admin_referer(self::STATUS_ACTION . '_' . $memberId);
        if (!\current_user_can(Capabilities::STATUS_EDIT)) {
            \wp_die(\esc_html__('You are not allowed to change the status of members.', 'orchestra-members'), 403);
        }

        $refusal = $this->status->refusal(\get_current_user_id(), $memberId);
        $active = ($_POST['status'] ?? '') === 'active';
        if ($refusal !== null) {
            $this->notices->error($refusal);
        } elseif ($active) {
            $this->status->activate($memberId);
            $this->notices->success(\__('The member is active again.', 'orchestra-members'));
        } else {
            $this->status->deactivate($memberId);
            $this->notices->success(
                \__('The member is inactive. Their sensitive profile data has been deleted.', 'orchestra-members')
            );
        }

        \wp_safe_redirect(self::url($memberId));
        exit;
    }

    private function renderList(): void
    {
        $privileged = \current_user_can(Capabilities::PROFILE_READ_OTHERS)
            || \current_user_can(Capabilities::STATUS_EDIT);
        $members = $this->directory->members([], $privileged);

        echo '<div class="wrap"><h1>' . \esc_html__('Members', 'orchestra-members') . '</h1>';
        echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
        echo '<th scope="col">' . \esc_html__('Name', 'orchestra-members') . '</th>';
        if ($privileged) {
            echo '<th scope="col">' . \esc_html__('Email', 'orchestra-members') . '</th>';
            echo '<th scope="col">' . \esc_html__('Role', 'orchestra-members') . '</th>';
            echo '<th scope="col">' . \esc_html__('Status', 'orchestra-members') . '</th>';
        }
        echo '</tr></thead><tbody>';

        if ($members === []) {
            echo '<tr><td colspan="4">' . \esc_html__('No members found.', 'orchestra-members') . '</td></tr>';
        }
        foreach ($members as $member) {
            $name = \esc_html($member->displayName);
            if (\current_user_can(Capabilities::PROFILE_READ, $member->id)) {
                $name = '<a href="' . \esc_url(self::url($member->id)) . '">' . $name . '</a>';
            }
            echo '<tr><td>' . $name . '</td>';
            if ($privileged) {
                echo '<td>' . \esc_html($member->email) . '</td>';
                echo '<td>' . \esc_html(self::roleNames($member)) . '</td>';
                echo '<td>' . \esc_html(StatusLabel::of($member->active)) . '</td>';
            }
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    private function renderMember(int $memberId): void
    {
        $member = $this->directory->member($memberId);
        $profile = $this->profiles->find($memberId);
        if ($member === null || $profile === null || !\current_user_can(Capabilities::PROFILE_READ, $memberId)) {
            \wp_die(\esc_html__('You are not allowed to view this profile.', 'orchestra-members'), 403);
        }

        echo '<div class="wrap"><h1>' . \esc_html($member->displayName) . '</h1>';
        echo '<p><a href="' . \esc_url(self::url()) . '">&larr; '
            . \esc_html__('All members', 'orchestra-members') . '</a></p>';

        $rows = [
            [\__('Email', 'orchestra-members'), $member->email],
            [\__('Role', 'orchestra-members'), self::roleNames($member)],
            [\__('Status', 'orchestra-members'), StatusLabel::of($profile->active)],
            [
                \__('Financial support', 'orchestra-members'),
                $profile->financialSupport
                    ? \__('Needed', 'orchestra-members')
                    : \__('Not needed', 'orchestra-members'),
            ],
            [
                \__('Dietary restrictions', 'orchestra-members'),
                $profile->dietaryRestrictions !== '' ? $profile->dietaryRestrictions : '—',
            ],
        ];
        echo '<table class="form-table" role="presentation">';
        foreach ($rows as [$label, $value]) {
            echo '<tr><th scope="row">' . \esc_html($label) . '</th><td>'
                . \nl2br(\esc_html($value)) . '</td></tr>';
        }
        echo '</table>';

        if ($memberId === \get_current_user_id() && \current_user_can(Capabilities::PROFILE_EDIT, $memberId)) {
            echo '<p><a class="button" href="' . \esc_url(\admin_url('profile.php')) . '">'
                . \esc_html__('Edit your profile', 'orchestra-members') . '</a></p>';
        }

        if (
            \current_user_can(Capabilities::STATUS_EDIT)
            && $this->status->refusal(\get_current_user_id(), $memberId) === null
        ) {
            $this->renderStatusForm($memberId, $profile->active);
        }

        echo '</div>';
    }

    private function renderStatusForm(int $memberId, bool $active): void
    {
        $confirm = $active
            ? \__(
                'Set this member inactive? They can no longer log in, and their sensitive profile data is deleted.',
                'orchestra-members'
            )
            : \__('Reactivate this member? They can log in again.', 'orchestra-members');

        echo '<h2>' . \esc_html__('Status', 'orchestra-members') . '</h2>';
        echo '<form method="post" action="' . \esc_url(\admin_url('admin-post.php')) . '" onsubmit="return confirm('
            . \esc_attr((string) \wp_json_encode($confirm)) . ');">';
        echo '<input type="hidden" name="action" value="' . self::STATUS_ACTION . '">';
        echo '<input type="hidden" name="member" value="' . $memberId . '">';
        echo '<input type="hidden" name="status" value="' . ($active ? 'inactive' : 'active') . '">';
        \wp_nonce_field(self::STATUS_ACTION . '_' . $memberId);
        \submit_button(
            $active ? \__('Set inactive', 'orchestra-members') : \__('Reactivate', 'orchestra-members'),
            $active ? 'delete' : 'primary',
            'submit',
            false
        );
        echo '</form>';
    }

    private static function requestedMemberId(): int
    {
        return \absint(\is_string($_GET['member'] ?? null) ? $_GET['member'] : 0);
    }

    private static function roleNames(Member $member): string
    {
        $names = Roles::displayNames();

        return \implode(', ', \array_map(static fn (string $role): string => $names[$role] ?? $role, $member->roles));
    }
}
