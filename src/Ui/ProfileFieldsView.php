<?php

declare(strict_types=1);

namespace Orchestra\Members\Ui;

use Orchestra\Core\Ui\Form;
use Orchestra\Members\Profile\ProfileField;
use Orchestra\Members\Profile\ProfileFields;
use Orchestra\Members\Profile\ProfileFieldsInput;

/**
 * The form in which administrators choose the fields of the orchestra profile. Shown by
 * `[orchestra_members]` and in wp-admin; saved by {@see ProfileFieldsController}.
 */
final class ProfileFieldsView
{
    private const NEW_ROW_TEMPLATE = 'orchestra-members-new-field';
    private const ROWS = 'orchestra-members-field-rows';

    public function __construct(private readonly ProfileFields $fields)
    {
    }

    public function form(): string
    {
        $fields = $this->fields->all();
        $headings = [
            \__('Order', 'orchestra-members'),
            \__('Label', 'orchestra-members'),
            \__('Type', 'orchestra-members'),
            \__('Hint', 'orchestra-members'),
            \__('Choices', 'orchestra-members'),
            \__('Delete', 'orchestra-members'),
        ];
        $types = ProfileField::typeNames();

        $html = '<p class="orchestra-muted">' . \esc_html__(
            'Members fill in these fields in their profile. Only they, the organizers and the administrators'
                . ' can see the answers, and the answers are deleted when a member is set inactive.',
            'orchestra-members'
        ) . '</p>'
            . Form::open(ProfileFieldsController::ACTION, ProfileFieldsController::ACTION, [], [
                'class' => 'orchestra-form',
            ])
            . '<div class="orchestra-table-wrap"><table class="orchestra-table orchestra-table--stacked"><thead><tr>';
        foreach ($headings as $heading) {
            $html .= '<th scope="col">' . \esc_html($heading) . '</th>';
        }
        $html .= '</tr></thead><tbody id="' . self::ROWS . '">';

        foreach ($fields as $index => $field) {
            $name = ProfileFieldsInput::FIELD_FIELDS . '[' . $field->id . ']';
            $html .= self::row(
                $headings,
                self::positionInput($name, $index + 1),
                self::textInput($name . '[label]', $field->label, ProfileField::LABEL_MAX_LENGTH, true),
                \esc_html($types[$field->type] ?? $field->type),
                self::textInput($name . '[hint]', $field->hint, ProfileField::HINT_MAX_LENGTH),
                $field->type === ProfileField::TYPE_SELECT ? self::choicesInput($name, $field->choices) : '—',
                '<label class="orchestra-checkbox"><input type="checkbox" name="' . \esc_attr($name)
                    . '[delete]" value="1"> <span>' . \esc_html__('Delete field', 'orchestra-members')
                    . '</span></label>'
            );
        }

        $next = \count($fields) + 1;
        $html .= self::newRow($headings, '0', $next) . '</tbody></table></div>'
            . '<template id="' . self::NEW_ROW_TEMPLATE . '">' . self::newRow($headings, '__INDEX__', $next)
            . '</template>'
            . '<div class="orchestra-actions"><button type="button" class="orchestra-button orchestra-button--small"'
            . ' data-orchestra-add-row="' . self::NEW_ROW_TEMPLATE . '" data-orchestra-target="' . self::ROWS . '">'
            . \esc_html__('+ Add field', 'orchestra-members') . '</button></div>'
            . '<p class="orchestra-muted">' . \esc_html__(
                'For checkboxes, the hint is the text next to the box. Choice lists need one choice per line.'
                    . ' Deleting a field also deletes what the members entered in it.',
                'orchestra-members'
            ) . '</p>'
            . '<div class="orchestra-actions">' . Form::submit(\__('Save', 'orchestra-members'), 'primary') . '</div>'
            . '</form>';

        return $html;
    }

    /**
     * @param list<string> $headings
     */
    private static function newRow(array $headings, string $key, int $position): string
    {
        $name = ProfileFieldsInput::FIELD_NEW_FIELDS . '[' . $key . ']';
        $options = '';
        foreach (ProfileField::typeNames() as $type => $label) {
            $options .= '<option value="' . \esc_attr($type) . '">' . \esc_html($label) . '</option>';
        }

        return self::row(
            $headings,
            self::positionInput($name, $position),
            '<input type="text" name="' . \esc_attr($name) . '[label]" maxlength="' . ProfileField::LABEL_MAX_LENGTH
                . '" placeholder="' . \esc_attr__('New field, e.g. T-shirt size', 'orchestra-members')
                . '" aria-label="' . \esc_attr__('Label of the new field', 'orchestra-members') . '">',
            '<select name="' . \esc_attr($name) . '[type]" aria-label="' . \esc_attr__('Type', 'orchestra-members')
                . '">' . $options . '</select>',
            self::textInput($name . '[hint]', '', ProfileField::HINT_MAX_LENGTH),
            self::choicesInput($name, []),
            ''
        );
    }

    /**
     * @param list<string> $headings
     */
    private static function row(array $headings, string ...$cells): string
    {
        $html = '<tr>';
        foreach ($cells as $index => $cell) {
            $html .= '<td data-label="' . \esc_attr($headings[$index] ?? '') . '"><div class="orchestra-cell">'
                . $cell . '</div></td>';
        }

        return $html . '</tr>';
    }

    private static function positionInput(string $name, int $position): string
    {
        return '<input type="number" name="' . \esc_attr($name) . '[position]" value="' . $position
            . '" style="max-width:5em" aria-label="' . \esc_attr__('Order', 'orchestra-members') . '">';
    }

    private static function textInput(string $name, string $value, int $maxLength, bool $required = false): string
    {
        return '<input type="text" name="' . \esc_attr($name) . '" value="' . \esc_attr($value) . '" maxlength="'
            . $maxLength . '"' . ($required ? ' required' : '') . '>';
    }

    /**
     * @param list<string> $choices
     */
    private static function choicesInput(string $name, array $choices): string
    {
        return '<textarea name="' . \esc_attr($name) . '[choices]" rows="3" aria-label="'
            . \esc_attr__('Choices, one per line', 'orchestra-members') . '">'
            . \esc_textarea(\implode("\n", $choices)) . '</textarea>';
    }
}
