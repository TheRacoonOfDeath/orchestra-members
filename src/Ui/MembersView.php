<?php

declare(strict_types=1);

namespace Orchestra\Members\Ui;

use Closure;
use Orchestra\Core\Authorization\Roles;
use Orchestra\Core\Ui\Form;
use Orchestra\Core\Ui\Html;
use Orchestra\Core\Ui\Pages;
use Orchestra\Members\Admin\StatusLabel;
use Orchestra\Members\Capabilities;
use Orchestra\Members\Member;
use Orchestra\Members\MemberDirectory;
use Orchestra\Members\Profile\ProfileFields;
use Orchestra\Members\Profile\ProfileRepository;
use Orchestra\Members\Status\StatusService;

/**
 * The member list and a member's profile. Shown by `[orchestra_members]` and in wp-admin.
 */
final class MembersView
{
    public function __construct(
        private readonly MemberDirectory $directory,
        private readonly ProfileRepository $profiles,
        private readonly StatusService $status,
        private readonly ProfileFields $fields,
    ) {
    }

    public function canView(int $memberId): bool
    {
        return $this->directory->member($memberId) !== null && \current_user_can(Capabilities::PROFILE_READ, $memberId);
    }

    /**
     * @param Closure(?int $memberId): string $url The list (null) or a member's profile
     * @param string|null $fieldsUrl The profile fields, linked for those who may change them
     * @param string|null $newUrl The form for adding a member, linked for those who may add members
     */
    public function list(Closure $url, ?string $fieldsUrl = null, ?string $newUrl = null): string
    {
        $privileged = \current_user_can(Capabilities::PROFILE_READ_OTHERS)
            || \current_user_can(Capabilities::STATUS_EDIT);
        $members = $this->directory->members([], $privileged);

        $headings = [\__('Name', 'orchestra-members')];
        if ($privileged) {
            $headings[] = \__('Email', 'orchestra-members');
            $headings[] = \__('Role', 'orchestra-members');
            $headings[] = \__('Status', 'orchestra-members');
        }

        $rows = [];
        foreach ($members as $member) {
            $name = \esc_html($member->displayName);
            if (\current_user_can(Capabilities::PROFILE_READ, $member->id)) {
                $name = '<a href="' . \esc_url($url($member->id)) . '">' . $name . '</a>';
            }
            $row = ['<strong>' . $name . '</strong>'];
            if ($privileged) {
                $row[] = '<a href="mailto:' . \esc_attr($member->email) . '">' . \esc_html($member->email) . '</a>';
                $row[] = \esc_html(self::roleNames($member));
                $row[] = self::statusBadge($member->active);
            }
            $rows[] = $row;
        }

        /* translators: %d: number of members */
        $count = \sprintf(\_n('%d member', '%d members', \count($members), 'orchestra-members'), \count($members));

        $actions = '';
        if ($newUrl !== null && \current_user_can(Capabilities::MEMBER_CREATE)) {
            $actions .= Form::link($newUrl, \__('Add member', 'orchestra-members'), 'primary small');
        }
        if ($fieldsUrl !== null && \current_user_can(Capabilities::FIELDS_MANAGE)) {
            $actions .= Form::link($fieldsUrl, \__('Profile fields', 'orchestra-members'), 'small');
        }
        $toolbar = '<p class="orchestra-muted">' . \esc_html($count) . '</p>';
        if ($actions !== '') {
            $toolbar = '<div class="orchestra-toolbar">' . $toolbar . '<div class="orchestra-actions">' . $actions
                . '</div></div>';
        }

        return $toolbar
            . Html::table($headings, $rows, \__('No members found.', 'orchestra-members'));
    }

    /**
     * @param Closure(?int $memberId): string $url The list (null) or a member's profile
     */
    public function member(int $memberId, Closure $url): string
    {
        $member = $this->directory->member($memberId);
        $profile = $this->profiles->find($memberId);
        if ($member === null || $profile === null || !$this->canView($memberId)) {
            return Html::empty(\__('You are not allowed to view this profile.', 'orchestra-members'));
        }

        $email = '<a href="mailto:' . \esc_attr($member->email) . '">' . \esc_html($member->email) . '</a>';

        $details = [
            [\__('Email', 'orchestra-members'), $email],
            [\__('Role', 'orchestra-members'), \esc_html(self::roleNames($member))],
        ];
        foreach ($this->fields->all() as $field) {
            $details[] = [$field->label, ProfileFieldControl::value($field, $profile->value($field->id))];
        }

        $html = '<p class="orchestra-back"><a href="' . \esc_url($url(null)) . '">&larr; '
            . \esc_html__('All members', 'orchestra-members') . '</a></p>'
            . '<div class="orchestra-card"><div class="orchestra-toolbar"><h2 class="orchestra-card__title">'
            . \esc_html($member->displayName) . '</h2>' . self::statusBadge($profile->active) . '</div>'
            . Html::details($details);

        if ($memberId === \get_current_user_id() && \current_user_can(Capabilities::PROFILE_EDIT, $memberId)) {
            $editUrl = Pages::url(ProfileShortcode::TAG) ?? \admin_url('profile.php');
            $html .= '<div class="orchestra-card__actions">'
                . Form::link($editUrl, \__('Edit your profile', 'orchestra-members')) . '</div>';
        }
        $html .= '</div>';

        if (
            \current_user_can(Capabilities::STATUS_EDIT)
            && $this->status->refusal(\get_current_user_id(), $memberId) === null
        ) {
            $html .= $this->statusForm($memberId, $profile->active);
        }

        return $html;
    }

    private function statusForm(int $memberId, bool $active): string
    {
        $confirm = $active
            ? \__(
                'Set this member inactive? They can no longer log in, and their sensitive profile data is deleted.',
                'orchestra-members'
            )
            : \__('Reactivate this member? They can log in again.', 'orchestra-members');
        $explanation = $active
            ? \__(
                'Inactive members cannot log in and receive no emails. Their history is kept.',
                'orchestra-members'
            )
            : \__('Reactivated members can log in again and respond to open projects.', 'orchestra-members');

        return '<div class="orchestra-card"><h3 class="orchestra-card__title">'
            . \esc_html__('Status', 'orchestra-members') . '</h3>'
            . '<p class="orchestra-muted">' . \esc_html($explanation) . '</p>'
            . Form::button(
                StatusController::ACTION,
                StatusController::nonceAction($memberId),
                $active ? \__('Set inactive', 'orchestra-members') : \__('Reactivate', 'orchestra-members'),
                ['member' => $memberId, 'status' => $active ? 'inactive' : 'active'],
                $confirm,
                $active ? 'danger' : 'primary'
            ) . '</div>';
    }

    public static function statusBadge(bool $active): string
    {
        return Html::badge(StatusLabel::of($active), $active ? 'success' : 'warning');
    }

    private static function roleNames(Member $member): string
    {
        $names = Roles::displayNames();

        return \implode(', ', \array_map(static fn (string $role): string => $names[$role] ?? $role, $member->roles));
    }
}
