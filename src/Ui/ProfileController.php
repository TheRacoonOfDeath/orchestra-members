<?php

declare(strict_types=1);

namespace Orchestra\Members\Ui;

use Orchestra\Core\Ui\Notices;
use Orchestra\Core\Ui\Redirect;
use Orchestra\Members\Capabilities;
use Orchestra\Members\MemberDirectory;
use Orchestra\Members\Profile\AccountInput;
use Orchestra\Members\Profile\ProfileInput;
use Orchestra\Members\Profile\ProfileRepository;
use WP_User;

/**
 * Saves the user's own profile (admin-post.php?action=orchestra_members_save_profile).
 */
final class ProfileController
{
    public const ACTION = 'orchestra_members_save_profile';

    public function __construct(private readonly ProfileRepository $profiles)
    {
    }

    public function register(): void
    {
        \add_action('admin_post_' . self::ACTION, [$this, 'handle']);
    }

    /**
     * @internal Hooked to `admin_post_orchestra_members_save_profile`.
     */
    public function handle(): never
    {
        \check_admin_referer(self::ACTION);
        $user = \wp_get_current_user();
        if (!$user->exists() || !\current_user_can(Capabilities::PROFILE_EDIT, $user->ID)) {
            \wp_die(\esc_html__('You are not allowed to edit this profile.', 'orchestra-members'), 403);
        }

        $post = \wp_unslash($_POST);
        $account = AccountInput::fromForm($post);
        $profile = ProfileInput::fromForm($post);
        $takenBy = \email_exists($account->email);
        $passwordValid = $account->currentPassword !== ''
            && \wp_check_password($account->currentPassword, $user->user_pass, $user->ID);
        $errors = [
            ...$account->validate($user->user_email, $takenBy !== false && $takenBy !== $user->ID, $passwordValid),
            ...$profile->validate(),
        ];
        if ($errors !== []) {
            Notices::errors($errors);
            Redirect::back();
        }

        $update = ['ID' => $user->ID, 'display_name' => $account->displayName];
        if ($account->changesEmail($user->user_email)) {
            $update['user_email'] = $account->email;
        }
        if ($account->changesPassword()) {
            $update['user_pass'] = $account->newPassword;
        }
        $result = \wp_update_user($update);
        if (\is_wp_error($result)) {
            Notices::error($result->get_error_message());
            Redirect::back();
        }

        if ($this->hasOrchestraProfile($user)) {
            $this->profiles->saveSensitiveData($user->ID, $profile->financialSupport, $profile->dietaryRestrictions);
        }

        Notices::success(\__('Your profile has been saved.', 'orchestra-members'));
        Redirect::back();
    }

    private function hasOrchestraProfile(WP_User $user): bool
    {
        return MemberDirectory::orchestraRoles($user) !== [] && $this->profiles->isActive($user->ID);
    }
}
