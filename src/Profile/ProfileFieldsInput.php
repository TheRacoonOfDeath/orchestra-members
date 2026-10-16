<?php

declare(strict_types=1);

namespace Orchestra\Members\Profile;

/**
 * Reads the form in which administrators change the profile fields.
 *
 * Existing fields are submitted as `orchestra_profile_fields[ID][position|label|hint|choices|delete]`,
 * new ones as `orchestra_new_profile_fields[INDEX][position|label|type|hint|choices]`. The type of
 * an existing field cannot be changed, so stored values always match their field. New rows without
 * a label are ignored.
 */
final class ProfileFieldsInput
{
    public const FIELD_FIELDS = 'orchestra_profile_fields';
    public const FIELD_NEW_FIELDS = 'orchestra_new_profile_fields';

    /**
     * @param list<ProfileField> $fields The fields to save, in display order
     * @param list<ProfileField> $removed Fields to delete, including their values
     * @param list<string> $errors If not empty, nothing may be saved
     */
    private function __construct(
        public readonly array $fields,
        public readonly array $removed,
        public readonly array $errors,
    ) {
    }

    /**
     * @param array<mixed> $post Unslashed form data
     * @param list<ProfileField> $current
     */
    public static function fromForm(array $post, array $current): self
    {
        $submitted = \is_array($post[self::FIELD_FIELDS] ?? null) ? $post[self::FIELD_FIELDS] : [];
        $new = \is_array($post[self::FIELD_NEW_FIELDS] ?? null) ? $post[self::FIELD_NEW_FIELDS] : [];

        $errors = [];
        $removed = [];
        /** @var list<array{int, ProfileField}> $positioned */
        $positioned = [];
        $taken = [];
        foreach ($current as $index => $field) {
            $taken[] = $field->id;
            $row = $submitted[$field->id] ?? null;
            if (!\is_array($row)) {
                $positioned[] = [$index + 1, $field];
                continue;
            }
            if (!empty($row['delete'])) {
                $removed[] = $field;
                continue;
            }

            $label = self::text($row, 'label');
            [$choices, $choiceErrors] = self::choices($field->type, $label, $row);
            $errors = [...$errors, ...self::labelErrors($label, self::text($row, 'hint')), ...$choiceErrors];
            $positioned[] = [
                self::position($row, $index + 1),
                new ProfileField($field->id, $label, $field->type, self::text($row, 'hint'), $choices),
            ];
        }

        foreach ($new as $row) {
            if (!\is_array($row) || self::text($row, 'label') === '') {
                continue;
            }
            $label = self::text($row, 'label');
            $type = $row['type'] ?? null;
            if (!\is_string($type) || !\in_array($type, ProfileField::TYPES, true)) {
                /* translators: %s: field label */
                $errors[] = \sprintf(\__('Please choose a type for "%s".', 'orchestra-members'), $label);
                continue;
            }

            [$choices, $choiceErrors] = self::choices($type, $label, $row);
            $errors = [...$errors, ...self::labelErrors($label, self::text($row, 'hint')), ...$choiceErrors];
            $id = ProfileFields::newId($label, $taken);
            $taken[] = $id;
            $positioned[] = [
                self::position($row, \count($current) + 1),
                new ProfileField($id, $label, $type, self::text($row, 'hint'), $choices),
            ];
        }

        // Stable: fields with the same position keep their order.
        \usort($positioned, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $fields = \array_map(static fn (array $entry): ProfileField => $entry[1], $positioned);

        return new self($fields, $removed, [...$errors, ...self::duplicateErrors($fields)]);
    }

    /**
     * @return list<string>
     */
    private static function labelErrors(string $label, string $hint): array
    {
        $errors = [];
        if ($label === '') {
            $errors[] = \__('Every field needs a label.', 'orchestra-members');
        } elseif (\mb_strlen($label) > ProfileField::LABEL_MAX_LENGTH) {
            /* translators: 1: field label, 2: maximum number of characters */
            $message = \__('The label "%1$s" is longer than %2$d characters.', 'orchestra-members');
            $errors[] = \sprintf($message, $label, ProfileField::LABEL_MAX_LENGTH);
        }
        if (\mb_strlen($hint) > ProfileField::HINT_MAX_LENGTH) {
            /* translators: 1: field label, 2: maximum number of characters */
            $message = \__('The hint of "%1$s" is longer than %2$d characters.', 'orchestra-members');
            $errors[] = \sprintf($message, $label, ProfileField::HINT_MAX_LENGTH);
        }

        return $errors;
    }

    /**
     * The choices of a choice list, one per line, without empty lines and duplicates.
     *
     * @param array<mixed> $row
     * @return array{list<string>, list<string>} choices and errors
     */
    private static function choices(string $type, string $label, array $row): array
    {
        if ($type !== ProfileField::TYPE_SELECT) {
            return [[], []];
        }

        $lines = \explode("\n", Text::multiLine(\is_string($row['choices'] ?? null) ? $row['choices'] : ''));
        $choices = \array_values(\array_unique(\array_filter(
            \array_map(static fn (string $line): string => Text::singleLine($line), $lines),
            static fn (string $choice): bool => $choice !== ''
        )));

        $errors = [];
        if ($choices === []) {
            /* translators: %s: field label */
            $errors[] = \sprintf(\__('Please enter the choices for "%s", one per line.', 'orchestra-members'), $label);
        }
        foreach ($choices as $choice) {
            if (\mb_strlen($choice) > ProfileField::CHOICE_MAX_LENGTH) {
                /* translators: 1: choice, 2: maximum number of characters */
                $message = \__('The choice "%1$s" is longer than %2$d characters.', 'orchestra-members');
                $errors[] = \sprintf($message, $choice, ProfileField::CHOICE_MAX_LENGTH);
            }
        }

        return [$choices, $errors];
    }

    /**
     * @param list<ProfileField> $fields
     * @return list<string>
     */
    private static function duplicateErrors(array $fields): array
    {
        $errors = [];
        $seen = [];
        foreach ($fields as $field) {
            $key = \mb_strtolower($field->label);
            if ($key !== '' && isset($seen[$key])) {
                /* translators: %s: field label */
                $errors[] = \sprintf(\__('The label "%s" is used more than once.', 'orchestra-members'), $field->label);
            }
            $seen[$key] = true;
        }

        return \array_values(\array_unique($errors));
    }

    /**
     * @param array<mixed> $row
     */
    private static function text(array $row, string $key): string
    {
        return \is_string($row[$key] ?? null) ? Text::singleLine($row[$key]) : '';
    }

    /**
     * @param array<mixed> $row
     */
    private static function position(array $row, int $default): int
    {
        $position = $row['position'] ?? null;

        return \is_numeric($position) ? (int) $position : $default;
    }
}
