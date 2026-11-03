<?php

declare(strict_types=1);

namespace Orchestra\Members\Admin;

use Orchestra\Core\Authorization\Roles;
use Orchestra\Members\Account\OrchestraRole;
use Orchestra\Members\Capabilities;
use Orchestra\Members\MemberDirectory;
use Orchestra\Members\Profile\Profile;
use Orchestra\Members\Profile\ProfileFields;
use Orchestra\Members\Profile\ProfileInput;
use Orchestra\Members\Profile\ProfileRepository;
use Orchestra\Members\Ui\ProfileFieldControl;
use WP_Error;
use WP_User;

/**
 * The "Orchestra" section on the WordPress profile screen (profile.php and user-edit.php).
 *
 * Administrators (`promote_user`) choose the Orchestra role there, in addition to the WordPress
 * role, e.g. for an administrator who plays. WordPress allows one role on its own screens and
 * replaces all roles when the "Role" dropdown is saved, so the Orchestra roles are left out of that
 * dropdown and the Orchestra role is saved afterwards (`profile_update`).
 */
final class ProfileSection
{
    private const NONCE_ACTION = 'orchestra_members_profile';
    private const NONCE_FIELD = 'orchestra_members_profile_nonce';
    private const ROLE_FIELD = 'orchestra_members_role';

    /** @var list<string> Validation errors of the current request, shown by WordPress. */
    private array $errors = [];

    public function __construct(
        private readonly ProfileRepository $profiles,
        private readonly ProfileFields $fields,
    ) {
    }

    public function register(): void
    {
        \add_action('show_user_profile', [$this, 'render']);
        \add_action('edit_user_profile', [$this, 'render']);
        \add_action('personal_options_update', [$this, 'save']);
        \add_action('edit_user_profile_update', [$this, 'save']);
        \add_action('user_profile_update_errors', [$this, 'reportErrors']);
        \add_action('profile_update', [$this, 'saveRole']);
        \add_action('load-user-edit.php', [$this, 'separateRoles']);
    }

    /**
     * @internal Hooked to `show_user_profile` and `edit_user_profile`.
     */
    public function render(WP_User $user): void
    {
        $choosesRole = self::canChooseRole($user->ID);
        $profile = MemberDirectory::orchestraRoles($user) !== []
            && \current_user_can(Capabilities::PROFILE_READ, $user->ID)
            ? $this->profiles->find($user->ID)
            : null;
        if (!$choosesRole && $profile === null) {
            return;
        }

        echo '<h2>' . \esc_html__('Orchestra', 'orchestra-members') . '</h2>';
        \wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
        echo '<table class="form-table" role="presentation">';
        if ($choosesRole) {
            $this->renderRole($user);
        }
        if ($profile !== null) {
            $this->renderProfile($user, $profile);
        }
        echo '</table>';

        if ($profile !== null) {
            echo '<p class="description">'
                . \esc_html__(
                    'Only you, the organizers and the administrators can see this information.',
                    'orchestra-members'
                )
                . '</p>';
        }
    }

    private function renderRole(WP_User $user): void
    {
        $current = MemberDirectory::orchestraRoles($user)[0] ?? '';
        $options = '';
        foreach (['' => \__('None', 'orchestra-members')] + Roles::displayNames() as $role => $name) {
            $options .= '<option value="' . \esc_attr($role) . '"' . \selected($current, $role, false) . '>'
                . \esc_html($name) . '</option>';
        }

        echo '<tr><th><label for="orchestra-role">' . \esc_html__('Orchestra role', 'orchestra-members')
            . '</label></th><td><select name="' . \esc_attr(self::ROLE_FIELD) . '" id="orchestra-role">'
            . $options . '</select><p class="description">'
            . \esc_html__(
                'In addition to the WordPress role, e.g. for an administrator who plays in the orchestra.',
                'orchestra-members'
            )
            . '</p></td></tr>'; // escaped above
    }

    private function renderProfile(WP_User $user, Profile $profile): void
    {
        $editable = $profile->active && \current_user_can(Capabilities::PROFILE_EDIT, $user->ID);

        echo '<tr><th>' . \esc_html__('Status', 'orchestra-members') . '</th><td>'
            . \esc_html(StatusLabel::of($profile->active));
        if (!$profile->active) {
            echo '<p class="description">'
                . \esc_html__(
                    'The sensitive profile data of inactive members is deleted. It can be entered again once active.',
                    'orchestra-members'
                )
                . '</p>';
        }
        echo '</td></tr>';

        foreach ($this->fields->all() as $field) {
            $id = 'orchestra-profile-' . $field->id;
            $hint = ProfileFieldControl::hint($field);
            echo '<tr><th><label for="' . \esc_attr($id) . '">' . \esc_html($field->label) . '</label></th><td>'
                . ProfileFieldControl::control($field, $profile->value($field->id), $id, !$editable, 'regular-text')
                . ($hint === '' ? '' : '<p class="description">' . \esc_html($hint) . '</p>')
                . '</td></tr>';
        }
    }

    /**
     * @internal Hooked to `personal_options_update` and `edit_user_profile_update`.
     */
    public function save(int $userId): void
    {
        if (!self::verifiedNonce()) {
            return;
        }
        if (!\current_user_can(Capabilities::PROFILE_EDIT, $userId) || !$this->profiles->isActive($userId)) {
            return;
        }
        $user = \get_userdata($userId);
        if (!$user instanceof WP_User || MemberDirectory::orchestraRoles($user) === []) {
            return;
        }

        $input = ProfileInput::fromForm(\wp_unslash($_POST), $this->fields->all());
        $this->errors = $input->validate();
        if ($this->errors === []) {
            $this->profiles->saveSensitiveData($userId, $input->values);
        }
    }

    /**
     * Saves the Orchestra role after WordPress has saved the "Role" dropdown, which replaces all
     * roles.
     *
     * @internal Hooked to `profile_update`.
     */
    public function saveRole(int $userId): void
    {
        $chosen = $_POST[self::ROLE_FIELD] ?? null;
        $editedUser = $_POST['user_id'] ?? null;
        if (
            !\is_string($chosen)
            || !\is_numeric($editedUser)
            || (int) $editedUser !== $userId
            || !self::verifiedNonce()
            || !self::canChooseRole($userId)
        ) {
            return;
        }
        $chosen = \sanitize_key($chosen);
        $user = \get_userdata($userId);
        if (!OrchestraRole::isChoice($chosen) || !$user instanceof WP_User) {
            return;
        }

        OrchestraRole::change($user, $chosen);
    }

    /**
     * Leaves the Orchestra roles out of WordPress's "Role" dropdown on user-edit.php; they are
     * chosen in the Orchestra section.
     *
     * @internal Hooked to `load-user-edit.php`.
     */
    public function separateRoles(): void
    {
        \add_filter('editable_roles', [self::class, 'withoutOrchestraRoles']);
    }

    /**
     * @internal Hooked to `editable_roles` on user-edit.php.
     *
     * @param array<string, mixed> $roles
     * @return array<string, mixed>
     */
    public static function withoutOrchestraRoles(array $roles): array
    {
        return \array_diff_key($roles, \array_flip(Roles::GRANTABLE));
    }

    /**
     * @internal Hooked to `user_profile_update_errors`.
     */
    public function reportErrors(WP_Error $errors): void
    {
        foreach ($this->errors as $message) {
            $errors->add('orchestra_members_profile', $message);
        }
    }

    private static function canChooseRole(int $userId): bool
    {
        return \current_user_can('promote_user', $userId);
    }

    private static function verifiedNonce(): bool
    {
        $nonce = $_POST[self::NONCE_FIELD] ?? null;

        return \is_string($nonce) && \wp_verify_nonce(\sanitize_key($nonce), self::NONCE_ACTION) !== false;
    }
}
