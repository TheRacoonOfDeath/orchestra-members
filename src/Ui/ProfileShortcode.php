<?php

declare(strict_types=1);

namespace Orchestra\Members\Ui;

use Orchestra\Core\Ui\Form;
use Orchestra\Core\Ui\Html;
use Orchestra\Members\Capabilities;
use Orchestra\Members\MemberDirectory;
use Orchestra\Members\Profile\AccountInput;
use Orchestra\Members\Profile\ProfileFields;
use Orchestra\Members\Profile\ProfileRepository;

/**
 * `[orchestra_profile]`: the user's own profile: name, email address, password and the orchestra
 * fields. Replaces profile.php for everyone who does not use wp-admin.
 */
final class ProfileShortcode
{
    public const TAG = 'orchestra_profile';

    public function __construct(
        private readonly ProfileRepository $profiles,
        private readonly ProfileFields $fields,
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
            return Html::loginRequired(\__('Please log in to edit your profile.', 'orchestra-members'));
        }
        $user = \wp_get_current_user();
        if (!\current_user_can(Capabilities::PROFILE_EDIT, $user->ID)) {
            return Html::notAllowed(\__('You are not allowed to edit your profile.', 'orchestra-members'));
        }

        $html = Form::open(ProfileController::ACTION, ProfileController::ACTION, [], ['class' => 'orchestra-form']);

        $html .= '<fieldset class="orchestra-fieldset orchestra-stack"><legend>'
            . \esc_html__('Account', 'orchestra-members') . '</legend>'
            . Html::field(
                'orchestra-profile-name',
                \__('Name', 'orchestra-members'),
                '<input type="text" id="orchestra-profile-name" name="' . AccountInput::FIELD_DISPLAY_NAME
                    . '" required maxlength="' . AccountInput::DISPLAY_NAME_MAX_LENGTH . '" value="'
                    . \esc_attr($user->display_name) . '" autocomplete="name">',
                \__('Shown to the other members.', 'orchestra-members')
            )
            . Html::field(
                'orchestra-profile-email',
                \__('Email address', 'orchestra-members'),
                '<input type="email" id="orchestra-profile-email" name="' . AccountInput::FIELD_EMAIL
                    . '" required value="' . \esc_attr($user->user_email) . '" autocomplete="email">'
            )
            . '<div class="orchestra-row">'
            . Html::field(
                'orchestra-profile-new-password',
                \__('New password', 'orchestra-members'),
                '<input type="password" id="orchestra-profile-new-password" name="' . AccountInput::FIELD_NEW_PASSWORD
                    . '" minlength="' . AccountInput::PASSWORD_MIN_LENGTH . '" autocomplete="new-password">',
                \__('Leave empty to keep your password.', 'orchestra-members')
            )
            . Html::field(
                'orchestra-profile-new-password-repeat',
                \__('Repeat new password', 'orchestra-members'),
                '<input type="password" id="orchestra-profile-new-password-repeat" name="'
                    . AccountInput::FIELD_NEW_PASSWORD_REPEAT . '" autocomplete="new-password">'
            )
            . '</div>'
            . Html::field(
                'orchestra-profile-current-password',
                \__('Current password', 'orchestra-members'),
                '<input type="password" id="orchestra-profile-current-password" name="'
                    . AccountInput::FIELD_CURRENT_PASSWORD . '" autocomplete="current-password">',
                \__('Only needed to change your email address or password.', 'orchestra-members')
            )
            . '</fieldset>';

        $profile = $this->profiles->find($user->ID);
        $fields = $this->fields->all();
        if ($profile !== null && $fields !== [] && MemberDirectory::orchestraRoles($user) !== []) {
            $html .= '<fieldset class="orchestra-fieldset orchestra-stack"><legend>'
                . \esc_html__('Orchestra', 'orchestra-members') . '</legend>';
            foreach ($fields as $field) {
                $id = 'orchestra-profile-' . $field->id;
                $html .= Html::field(
                    $id,
                    $field->label,
                    ProfileFieldControl::control($field, $profile->value($field->id), $id),
                    ProfileFieldControl::hint($field)
                );
            }
            $html .= '<p class="orchestra-muted">' . \esc_html__(
                'Only you, the organizers and the administrators can see this information.',
                'orchestra-members'
            ) . '</p></fieldset>';
        }

        $save = Form::submit(\__('Save', 'orchestra-members'), 'primary');

        return Html::page($html . '<div class="orchestra-actions">' . $save . '</div></form>');
    }
}
