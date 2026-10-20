<?php

declare(strict_types=1);

namespace Orchestra\Members\Ui;

use Orchestra\Core\Ui\Notices;
use Orchestra\Core\Ui\Redirect;
use Orchestra\Members\Capabilities;
use Orchestra\Members\Profile\ProfileFields;
use Orchestra\Members\Profile\ProfileFieldsInput;
use Orchestra\Members\Profile\ProfileRepository;

/**
 * Saves the profile fields (admin-post.php?action=orchestra_members_save_profile_fields).
 */
final class ProfileFieldsController
{
    public const ACTION = 'orchestra_members_save_profile_fields';

    public function __construct(
        private readonly ProfileFields $fields,
        private readonly ProfileRepository $profiles,
    ) {
    }

    public function register(): void
    {
        \add_action('admin_post_' . self::ACTION, [$this, 'handle']);
    }

    /**
     * @internal Hooked to `admin_post_orchestra_members_save_profile_fields`.
     */
    public function handle(): never
    {
        \check_admin_referer(self::ACTION);
        if (!\current_user_can(Capabilities::FIELDS_MANAGE)) {
            \wp_die(\esc_html__('You are not allowed to change the profile fields.', 'orchestra-members'), 403);
        }

        $input = ProfileFieldsInput::fromForm(\wp_unslash($_POST), $this->fields->all());
        if ($input->errors !== []) {
            Notices::errors($input->errors);
            Redirect::back();
        }

        $this->fields->save($input->fields);
        if ($input->financialSupportField !== null) {
            $this->fields->saveFinancialSupportField($input->financialSupportField);
        }
        foreach ($input->removed as $field) {
            $this->profiles->deleteFieldValues($field);
        }

        Notices::success(\__('The profile fields have been saved.', 'orchestra-members'));
        Redirect::back();
    }
}
