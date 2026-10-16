<?php

declare(strict_types=1);

namespace Orchestra\Members\Profile;

/**
 * Normalizes and validates the profile fields submitted in a form. Each field is submitted under
 * its {@see ProfileField::metaKey()}.
 */
final class ProfileInput
{
    /**
     * @param list<ProfileField> $fields
     * @param array<string, string> $values field ID => value
     */
    private function __construct(
        private readonly array $fields,
        public readonly array $values,
    ) {
    }

    /**
     * @param array<mixed> $post Unslashed form data
     * @param list<ProfileField> $fields
     */
    public static function fromForm(array $post, array $fields): self
    {
        $values = [];
        foreach ($fields as $field) {
            $values[$field->id] = $field->normalize($post[$field->metaKey()] ?? '');
        }

        return new self($fields, $values);
    }

    /**
     * @return list<string> Error messages; empty if the input is valid
     */
    public function validate(): array
    {
        $errors = [];
        foreach ($this->fields as $field) {
            $error = $field->error($this->values[$field->id] ?? '');
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }
}
