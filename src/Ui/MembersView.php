<?php

declare(strict_types=1);

namespace Orchestra\Members\Ui;

use Closure;
use Orchestra\Core\Authorization\Roles;
use Orchestra\Core\Ui\Form;
use Orchestra\Core\Ui\Html;
use Orchestra\Core\Ui\Pages;
use Orchestra\Members\Admin\StatusLabel;
use Orchestra\Email\Plugin as EmailPlugin;
use Orchestra\Members\Capabilities;
use Orchestra\Members\Email\MemberEmailGroups;
use Orchestra\Members\Member;
use Orchestra\Members\MemberDirectory;
use Orchestra\Members\MemberFilter;
use Orchestra\Members\Profile\ProfileFields;
use Orchestra\Members\Profile\ProfileRepository;
use Orchestra\Members\Status\StatusService;

/**
 * The member list and a member's profile. Shown by `[orchestra_members]` and in wp-admin.
 */
final class MembersView
{
    public const SEARCH_PARAMETER = 'orchestra_members_q';
    public const STATUS_PARAMETER = 'orchestra_members_status';
    public const ROLE_PARAMETER = 'orchestra_members_role';

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
        $all = $this->directory->members([], $privileged);
        $filter = self::filter($privileged);
        $members = $filter->apply($all);

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

        $count = $filter->isEmpty()
            // "People", not "members": the list also contains conductors and organizers.
            /* translators: %d: number of people in the list */
            ? \sprintf(\_n('%d person', '%d people', \count($all), 'orchestra-members'), \count($all))
            : \sprintf(
                /* translators: 1: number of people found, 2: number of all people in the list */
                \_n('%1$d of %2$d person', '%1$d of %2$d people', \count($all), 'orchestra-members'),
                \count($members),
                \count($all)
            );

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

        return $toolbar . self::filterForm($url(null), $filter, $privileged)
            . Html::table($headings, $rows, \__('No members found.', 'orchestra-members'));
    }

    private static function filter(bool $privileged): MemberFilter
    {
        $value = static fn (string $key): string => \is_string($_GET[$key] ?? null)
            ? \sanitize_text_field(\wp_unslash($_GET[$key]))
            : '';
        $status = $value(self::STATUS_PARAMETER);
        $role = $value(self::ROLE_PARAMETER);

        return new MemberFilter(
            $value(self::SEARCH_PARAMETER),
            $privileged && \in_array($status, [MemberFilter::ACTIVE, MemberFilter::INACTIVE], true) ? $status : '',
            $privileged && isset(Roles::displayNames()[$role]) ? $role : '',
            $privileged
        );
    }

    /**
     * Search for everyone who sees the list; status and role filters for those who see them.
     */
    private static function filterForm(string $listUrl, MemberFilter $filter, bool $privileged): string
    {
        $base = \remove_query_arg([self::SEARCH_PARAMETER, self::STATUS_PARAMETER, self::ROLE_PARAMETER], $listUrl);
        // The page's own query, e.g. ?page_id=5 or ?page=orchestra-members, survives the GET form.
        $hidden = '';
        \parse_str((string) \wp_parse_url($base, PHP_URL_QUERY), $query);
        foreach ($query as $key => $value) {
            if (\is_string($value)) {
                $hidden .= Form::hidden((string) $key, $value);
            }
        }

        $html = '<form method="get" action="' . \esc_url($base) . '" class="orchestra-actions">' . $hidden
            . '<input type="search" name="' . self::SEARCH_PARAMETER . '" value="' . \esc_attr($filter->search) . '"'
            . ' aria-label="' . \esc_attr__('Search members', 'orchestra-members') . '" placeholder="'
            . \esc_attr($privileged
                ? \__('Name or email address', 'orchestra-members')
                : \__('Name', 'orchestra-members'))
            . '" style="width:auto;min-width:14rem">';
        if ($privileged) {
            $html .= self::select(self::STATUS_PARAMETER, \__('Status', 'orchestra-members'), [
                '' => \__('All statuses', 'orchestra-members'),
                MemberFilter::ACTIVE => \__('Active', 'orchestra-members'),
                MemberFilter::INACTIVE => \__('Inactive', 'orchestra-members'),
            ], $filter->status);
            $html .= self::select(
                self::ROLE_PARAMETER,
                \__('Role', 'orchestra-members'),
                ['' => \__('All roles', 'orchestra-members')] + Roles::displayNames(),
                $filter->role
            );
        }
        $html .= Form::submit(\__('Filter', 'orchestra-members'), 'small');
        if (!$filter->isEmpty()) {
            $html .= Form::link($base, \__('Show all', 'orchestra-members'), 'link');
        }

        return $html . '</form>';
    }

    /**
     * @param array<string, string> $options
     */
    private static function select(string $name, string $label, array $options, string $selected): string
    {
        $html = '<select name="' . \esc_attr($name) . '" aria-label="' . \esc_attr($label) . '" style="width:auto">';
        foreach ($options as $value => $optionLabel) {
            $html .= '<option value="' . \esc_attr((string) $value) . '"' . \selected((string) $value, $selected, false)
                . '>' . \esc_html($optionLabel) . '</option>';
        }

        return $html . '</select>';
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
        } elseif (
            $profile->active
            && \current_user_can(Capabilities::EMAIL_MEMBER)
            && MemberEmailGroups::singleMemberAvailable()
        ) {
            $composeUrl = EmailPlugin::instance()->email()->composeUrl([MemberEmailGroups::memberGroupId($memberId)]);
            $html .= '<div class="orchestra-card__actions">'
                . Form::link($composeUrl, \__('Write an email', 'orchestra-members'), 'small') . '</div>';
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
