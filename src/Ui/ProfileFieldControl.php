<?php

declare(strict_types=1);

namespace Orchestra\Members\Ui;

use Orchestra\Members\Profile\ProfileField;

/**
 * The form control and the displayed value of a {@see ProfileField}, shared by the profile page, the
 * WordPress profile screen and the member view.
 */
final class ProfileFieldControl
{
    /**
     * The input element; checkboxes come with their text (the field's hint). Escaped.
     *
     * @param string $class CSS class of text inputs, e.g. "regular-text" in wp-admin
     */
    public static function control(
        ProfileField $field,
        string $value,
        string $id,
        bool $disabled = false,
        string $class = ''
    ): string {
        $attributes = ' id="' . \esc_attr($id) . '" name="' . \esc_attr($field->metaKey()) . '"'
            . ($disabled ? ' disabled' : '');
        $classAttribute = $class === '' ? '' : ' class="' . \esc_attr($class) . '"';

        switch ($field->type) {
            case ProfileField::TYPE_CHECKBOX:
                return '<label class="orchestra-checkbox"><input type="checkbox" value="1"' . $attributes
                    . \checked($value, '1', false) . '> <span>'
                    . \esc_html($field->hint !== '' ? $field->hint : $field->label) . '</span></label>';
            case ProfileField::TYPE_SELECT:
                // A value whose choice was removed stays selectable until it is changed.
                $choices = $value !== '' && !\in_array($value, $field->choices, true)
                    ? [...$field->choices, $value]
                    : $field->choices;
                $options = '<option value="">—</option>';
                foreach ($choices as $choice) {
                    $options .= '<option value="' . \esc_attr($choice) . '"' . \selected($value, $choice, false) . '>'
                        . \esc_html($choice) . '</option>';
                }

                return '<select' . $attributes . '>' . $options . '</select>';
            case ProfileField::TYPE_TEXTAREA:
                return '<textarea' . $attributes . $classAttribute . ' rows="3" maxlength="' . $field->maxLength()
                    . '">' . \esc_textarea($value) . '</textarea>';
            default:
                return '<input type="text"' . $attributes . $classAttribute . ' maxlength="' . $field->maxLength()
                    . '" value="' . \esc_attr($value) . '">';
        }
    }

    /**
     * The hint shown below the control. Checkboxes show it next to the box instead.
     */
    public static function hint(ProfileField $field): string
    {
        return $field->type === ProfileField::TYPE_CHECKBOX ? '' : $field->hint;
    }

    /**
     * The value for display, escaped. "—" if it is empty.
     */
    public static function value(ProfileField $field, string $value): string
    {
        if ($field->type !== ProfileField::TYPE_CHECKBOX && $value === '') {
            return '—';
        }

        return \nl2br(\esc_html($field->display($value)));
    }
}
