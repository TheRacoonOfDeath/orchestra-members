<?php

declare(strict_types=1);

namespace Orchestra\Members\Profile;

/**
 * Normalizes and validates the profile fields submitted in a form. Each field is submitted under
 * its {@see ProfileField::metaKey()}, the free-text answers of choice lists under
 * `orchestra_members_other[ID]` ({@see ProfileField::OTHER_FIELD}), the birth date under
 * {@see BirthDate::FIELD}.
 */
final class ProfileInput
{
    /**
     * @param list<ProfileField> $fields
     * @param array<string, string> $values field ID => value
     * @param string $birthDate as entered; Y-m-d once {@see validate()} found no error
     */
    private function __construct(
        private readonly array $fields,
        public readonly array $values,
        public readonly string $birthDate = '',
    ) {
    }

    /**
     * @param array<mixed> $post Unslashed form data
     * @param list<ProfileField> $fields
     */
    public static function fromForm(array $post, array $fields): self
    {
        $other = \is_array($post[ProfileField::OTHER_FIELD] ?? null) ? $post[ProfileField::OTHER_FIELD] : [];
        $values = [];
        foreach ($fields as $field) {
            $values[$field->id] = $field->normalize($post[$field->metaKey()] ?? '', $other[$field->id] ?? '');
        }

        return new self($fields, $values, BirthDate::normalize($post[BirthDate::FIELD] ?? ''));
    }

    /**
     * @return list<string> Error messages; empty if the input is valid
     */
    public function validate(): array
    {
        $errors = [];
        $birthDateError = BirthDate::error($this->birthDate, \current_time('Y-m-d'));
        if ($birthDateError !== null) {
            $errors[] = $birthDateError;
        }
        foreach ($this->fields as $field) {
            $error = $field->error($this->values[$field->id] ?? '');
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }
}
