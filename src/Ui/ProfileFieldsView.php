<?php

declare(strict_types=1);

namespace Orchestra\Members\Ui;

use Orchestra\Core\Ui\Form;
use Orchestra\Core\Ui\Html;
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
        $types = ProfileField::typeNames();

        $html = '<p class="orchestra-muted">' . \esc_html__(
            'Members fill in these fields in their profile. Only they, the organizers and the administrators'
                . ' can see the answers, and the answers are deleted when a member is set inactive.',
            'orchestra-members'
        ) . '</p>'
            . Form::open(ProfileFieldsController::ACTION, ProfileFieldsController::ACTION, [], [
                'class' => 'orchestra-form',
            ])
            // Enter in a text field saves instead of pressing the first delete button.
            . '<button type="submit" class="orchestra-default-submit" tabindex="-1" aria-hidden="true">'
            . \esc_html__('Save', 'orchestra-members') . '</button>'
            . '<div class="orchestra-table-wrap"><table class="orchestra-table orchestra-table--stacked">'
            . '<tbody id="' . self::ROWS . '" data-orchestra-sortable>';

        foreach ($this->fields->all() as $field) {
            $name = ProfileFieldsInput::FIELD_FIELDS . '[' . $field->id . ']';
            /* translators: %s: field label */
            $confirm = \__(
                'Delete the field "%s"? What the members entered in it is deleted too.',
                'orchestra-members'
            );
            $delete = '<button type="submit" class="orchestra-button orchestra-button--small orchestra-button--danger"'
                . ' name="' . ProfileFieldsInput::FIELD_DELETE . '" value="' . \esc_attr($field->id) . '"'
                . ' formnovalidate data-orchestra-confirm="' . \esc_attr(\sprintf($confirm, $field->label)) . '">'
                . \esc_html__('Delete', 'orchestra-members') . '</button>';
            $cells = [
                ['', self::header(
                    $field->id,
                    $field->label,
                    '<strong>' . \esc_html($types[$field->type] ?? $field->type) . '</strong>',
                    $delete
                )],
                [
                    \__('Label', 'orchestra-members'),
                    self::textInput($name . '[label]', $field->label, ProfileField::LABEL_MAX_LENGTH, true),
                ],
                [
                    \__('Hint', 'orchestra-members'),
                    self::textInput($name . '[hint]', $field->hint, ProfileField::HINT_MAX_LENGTH),
                ],
            ];
            if ($field->type === ProfileField::TYPE_SELECT) {
                $cells[] = [\__('Choices', 'orchestra-members'), self::choicesInput($name, $field->choices)];
            }
            $html .= self::row($cells);
        }

        $html .= self::newRow('0') . '</tbody></table></div>'
            . '<template id="' . self::NEW_ROW_TEMPLATE . '">' . self::newRow('__INDEX__') . '</template>'
            . '<div class="orchestra-actions"><button type="button" class="orchestra-button orchestra-button--small"'
            . ' data-orchestra-add-row="' . self::NEW_ROW_TEMPLATE . '" data-orchestra-target="' . self::ROWS . '">'
            . \esc_html__('+ Add field', 'orchestra-members') . '</button></div>'
            . '<p class="orchestra-muted">' . \esc_html__(
                'Drag the fields by their handle to change the order. For checkboxes, the hint is the text next to'
                    . ' the box.',
                'orchestra-members'
            ) . '</p>'
            . $this->financialSupportSelect()
            . '<div class="orchestra-actions">' . Form::submit(\__('Save', 'orchestra-members'), 'primary') . '</div>'
            . '</form>';

        return $html;
    }

    /**
     * Which checkbox field means that a member needs financial support. Organizers see it next to
     * the fees of the projects.
     */
    private function financialSupportSelect(): string
    {
        $current = $this->fields->financialSupportField()->id ?? '';
        $options = '<option value="">' . \esc_html__('None', 'orchestra-members') . '</option>';
        foreach ($this->fields->all() as $field) {
            if ($field->type === ProfileField::TYPE_CHECKBOX) {
                $options .= '<option value="' . \esc_attr($field->id) . '"'
                    . \selected($field->id, $current, false) . '>' . \esc_html($field->label) . '</option>';
            }
        }

        return Html::field(
            'orchestra-members-financial-support',
            \__('Field for financial support', 'orchestra-members'),
            '<select id="orchestra-members-financial-support" name="' . ProfileFieldsInput::FIELD_FINANCIAL_SUPPORT
                . '">' . $options . '</select>',
            \__(
                'Organizers see this checkbox next to the fees of the projects. New fields can be chosen once saved.',
                'orchestra-members'
            )
        );
    }

    private static function newRow(string $key): string
    {
        $name = ProfileFieldsInput::FIELD_NEW_FIELDS . '[' . $key . ']';
        $options = '';
        foreach (ProfileField::typeNames() as $type => $label) {
            $options .= '<option value="' . \esc_attr($type) . '">' . \esc_html($label) . '</option>';
        }

        return self::row([
            ['', self::header(
                ProfileFieldsInput::NEW_KEY_PREFIX . $key,
                \__('New field', 'orchestra-members'),
                '<select name="' . \esc_attr($name) . '[type]" aria-label="' . \esc_attr__('Type', 'orchestra-members')
                    . '">' . $options . '</select>',
                '<button type="button" class="orchestra-button orchestra-button--small" data-orchestra-remove-row>'
                    . \esc_html__('Remove', 'orchestra-members') . '</button>'
            )],
            [
                \__('Label', 'orchestra-members'),
                '<input type="text" name="' . \esc_attr($name) . '[label]" maxlength="' . ProfileField::LABEL_MAX_LENGTH
                    . '" placeholder="' . \esc_attr__('New field, e.g. T-shirt size', 'orchestra-members') . '">',
            ],
            [\__('Hint', 'orchestra-members'), self::textInput($name . '[hint]', '', ProfileField::HINT_MAX_LENGTH)],
            [
                \__('Choices', 'orchestra-members'),
                self::choicesInput($name, [], \__('Only for choice lists: one choice per line', 'orchestra-members')),
            ],
        ]);
    }

    /**
     * @param list<array{string, string}> $cells Label (plain text, "" for none) and content (HTML)
     */
    private static function row(array $cells): string
    {
        $html = '<tr>';
        foreach ($cells as [$label, $cell]) {
            $html .= '<td data-label="' . \esc_attr($label) . '"><div class="orchestra-cell">' . $cell . '</div></td>';
        }

        return $html . '</tr>';
    }

    /**
     * The top line of a row: the drag handle, which also submits the row's place in the order, the
     * type and the button that deletes or removes the field.
     */
    private static function header(string $key, string $label, string $type, string $action): string
    {
        /* translators: %s: field label */
        $move = \sprintf(\__('Move "%s"', 'orchestra-members'), $label);

        return '<div class="orchestra-row-header">' . Form::hidden(ProfileFieldsInput::FIELD_ORDER . '[]', $key)
            . '<button type="button" class="orchestra-drag-handle" data-orchestra-drag-handle aria-label="'
            . \esc_attr($move) . '" title="'
            . \esc_attr__('Drag to change the order, or use the arrow keys', 'orchestra-members') . '">'
            . '<span aria-hidden="true">&#8801;</span></button>' . $type
            . '<span class="orchestra-row-header__end">' . $action . '</span></div>';
    }

    private static function textInput(string $name, string $value, int $maxLength, bool $required = false): string
    {
        return '<input type="text" name="' . \esc_attr($name) . '" value="' . \esc_attr($value) . '" maxlength="'
            . $maxLength . '"' . ($required ? ' required' : '') . '>';
    }

    /**
     * @param list<string> $choices
     */
    private static function choicesInput(string $name, array $choices, string $placeholder = ''): string
    {
        return '<textarea name="' . \esc_attr($name) . '[choices]" rows="3" aria-label="'
            . \esc_attr__('Choices, one per line', 'orchestra-members') . '"'
            . ($placeholder === '' ? '' : ' placeholder="' . \esc_attr($placeholder) . '"') . '>'
            . \esc_textarea(\implode("\n", $choices)) . '</textarea>';
    }
}
