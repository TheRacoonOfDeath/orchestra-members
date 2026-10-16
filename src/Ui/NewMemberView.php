<?php

declare(strict_types=1);

namespace Orchestra\Members\Ui;

use Orchestra\Core\Authorization\Roles;
use Orchestra\Core\Ui\Form;
use Orchestra\Core\Ui\Html;
use Orchestra\Members\Account\NewMemberInput;

/**
 * The form for adding a WordPress user to the orchestra. Shown by `[orchestra_members]` and in
 * wp-admin; saved by {@see NewMemberController}.
 */
final class NewMemberView
{
    public function form(): string
    {
        $fields = Html::field(
            'orchestra-members-first-name',
            \__('First name', 'orchestra-members'),
            self::input('first-name', NewMemberInput::FIELD_FIRST_NAME, 'text', true)
        )
            . Html::field(
                'orchestra-members-last-name',
                \__('Last name', 'orchestra-members'),
                self::input('last-name', NewMemberInput::FIELD_LAST_NAME, 'text')
            )
            . Html::field(
                'orchestra-members-email',
                \__('Email', 'orchestra-members'),
                self::input('email', NewMemberInput::FIELD_EMAIL, 'email', true),
                \__('The new member receives an email with a link to set a password.', 'orchestra-members')
            );

        if (NewMemberController::canChooseRole()) {
            $options = '';
            foreach (Roles::displayNames() as $role => $name) {
                $options .= '<option value="' . \esc_attr($role) . '"' . \selected($role, Roles::MEMBER, false) . '>'
                    . \esc_html($name) . '</option>';
            }
            $fields .= Html::field(
                'orchestra-members-role',
                \__('Role', 'orchestra-members'),
                '<select id="orchestra-members-role" name="' . NewMemberInput::FIELD_ROLE . '">' . $options
                    . '</select>'
            );
        }

        return Form::open(NewMemberController::ACTION, NewMemberController::ACTION, [], [
            'class' => 'orchestra-form orchestra-card',
        ])
            . '<div class="orchestra-stack">' . $fields . '</div>'
            . '<div class="orchestra-actions">' . Form::submit(\__('Add member', 'orchestra-members'), 'primary')
            . '</div></form>';
    }

    /**
     * The fields describe someone else, so the browser must not fill in the user's own data.
     */
    private static function input(string $id, string $name, string $type, bool $required = false): string
    {
        return '<input type="' . $type . '" id="orchestra-members-' . $id . '" name="' . $name . '" autocomplete="off"'
            . ($required ? ' required' : '') . '>';
    }
}
