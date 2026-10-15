<?php

declare(strict_types=1);

namespace Orchestra\Members\Admin;

use Orchestra\Members\Capabilities;
use Orchestra\Members\MemberDirectory;
use Orchestra\Members\Profile\ProfileInput;
use Orchestra\Members\Profile\ProfileRepository;
use WP_Error;
use WP_User;

/**
 * The "Orchestra" section on the WordPress profile screen (profile.php and user-edit.php).
 */
final class ProfileSection
{
    private const NONCE_ACTION = 'orchestra_members_profile';
    private const NONCE_FIELD = 'orchestra_members_profile_nonce';

    /** @var list<string> Validation errors of the current request, shown by WordPress. */
    private array $errors = [];

    public function __construct(private readonly ProfileRepository $profiles)
    {
    }

    public function register(): void
    {
        \add_action('show_user_profile', [$this, 'render']);
        \add_action('edit_user_profile', [$this, 'render']);
        \add_action('personal_options_update', [$this, 'save']);
        \add_action('edit_user_profile_update', [$this, 'save']);
        \add_action('user_profile_update_errors', [$this, 'reportErrors']);
    }

    /**
     * @internal Hooked to `show_user_profile` and `edit_user_profile`.
     */
    public function render(WP_User $user): void
    {
        if (
            MemberDirectory::orchestraRoles($user) === []
            || !\current_user_can(Capabilities::PROFILE_READ, $user->ID)
        ) {
            return;
        }
        $profile = $this->profiles->find($user->ID);
        if ($profile === null) {
            return;
        }
        $editable = $profile->active && \current_user_can(Capabilities::PROFILE_EDIT, $user->ID);
        $disabled = $editable ? '' : ' disabled';

        echo '<h2>' . \esc_html__('Orchestra', 'orchestra-members') . '</h2>';
        \wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
        echo '<table class="form-table" role="presentation">';

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

        echo '<tr><th>' . \esc_html__('Financial support', 'orchestra-members') . '</th><td><label>'
            . '<input type="checkbox" name="' . ProfileInput::FIELD_FINANCIAL_SUPPORT . '" value="1"'
            . \checked($profile->financialSupport, true, false) . $disabled . '> '
            . \esc_html__('I need financial support, e.g. for project fees.', 'orchestra-members')
            . '</label></td></tr>';

        echo '<tr><th><label for="' . ProfileInput::FIELD_DIETARY_RESTRICTIONS . '">'
            . \esc_html__('Dietary restrictions', 'orchestra-members') . '</label></th><td>'
            . '<textarea id="' . ProfileInput::FIELD_DIETARY_RESTRICTIONS . '" name="'
            . ProfileInput::FIELD_DIETARY_RESTRICTIONS . '" rows="3" cols="50" maxlength="'
            . ProfileInput::DIETARY_RESTRICTIONS_MAX_LENGTH . '"' . $disabled . '>'
            . \esc_textarea($profile->dietaryRestrictions) . '</textarea>'
            . '<p class="description">'
            . \esc_html__('Allergies, vegetarian, vegan, …', 'orchestra-members') . '</p></td></tr>';

        echo '</table>';
        echo '<p class="description">'
            . \esc_html__(
                'Only you, the organizers and the administrators can see this information.',
                'orchestra-members'
            )
            . '</p>';
    }

    /**
     * @internal Hooked to `personal_options_update` and `edit_user_profile_update`.
     */
    public function save(int $userId): void
    {
        $nonce = $_POST[self::NONCE_FIELD] ?? null;
        if (!\is_string($nonce) || \wp_verify_nonce(\sanitize_key($nonce), self::NONCE_ACTION) === false) {
            return;
        }
        if (!\current_user_can(Capabilities::PROFILE_EDIT, $userId) || !$this->profiles->isActive($userId)) {
            return;
        }
        $user = \get_userdata($userId);
        if (!$user instanceof WP_User || MemberDirectory::orchestraRoles($user) === []) {
            return;
        }

        $input = ProfileInput::fromForm(\wp_unslash($_POST));
        $this->errors = $input->validate();
        if ($this->errors === []) {
            $this->profiles->saveSensitiveData($userId, $input->financialSupport, $input->dietaryRestrictions);
        }
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
}
