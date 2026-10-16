<?php

declare(strict_types=1);

namespace Orchestra\Members\Profile;

/**
 * A field of the orchestra profile, defined by the administrators (see {@see ProfileFields}).
 *
 * The values are sensitive: only the member, organizers and administrators can see them, and they
 * are deleted when the member is set inactive.
 */
final class ProfileField
{
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_TEXT = 'text';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_SELECT = 'select';

    public const TYPES = [self::TYPE_CHECKBOX, self::TYPE_TEXT, self::TYPE_TEXTAREA, self::TYPE_SELECT];

    public const LABEL_MAX_LENGTH = 100;
    public const HINT_MAX_LENGTH = 200;
    public const CHOICE_MAX_LENGTH = 100;
    public const TEXT_MAX_LENGTH = 200;
    public const TEXTAREA_MAX_LENGTH = 1000;

    private const META_PREFIX = 'orchestra_members_';

    /**
     * @param string $id Stable key; the label can change
     * @param string $hint Help text; the text next to the box for checkboxes
     * @param list<string> $choices Only for {@see TYPE_SELECT}
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $type,
        public readonly string $hint = '',
        public readonly array $choices = [],
    ) {
    }

    /**
     * The user meta key of the value. Also the name of the form field.
     */
    public function metaKey(): string
    {
        return self::META_PREFIX . $this->id;
    }

    public function maxLength(): int
    {
        return $this->type === self::TYPE_TEXTAREA ? self::TEXTAREA_MAX_LENGTH : self::TEXT_MAX_LENGTH;
    }

    /**
     * The value to store for submitted form data: "1" or "" for checkboxes, one of the choices or
     * "" for choice lists, text without tags and control characters otherwise.
     */
    public function normalize(mixed $submitted): string
    {
        if (!\is_string($submitted)) {
            return '';
        }

        return match ($this->type) {
            self::TYPE_CHECKBOX => $submitted === '1' ? '1' : '',
            self::TYPE_SELECT => \in_array($submitted, $this->choices, true) ? $submitted : '',
            self::TYPE_TEXTAREA => Text::multiLine($submitted),
            default => Text::singleLine($submitted),
        };
    }

    /**
     * Why the value cannot be saved, or null if it can.
     */
    public function error(string $value): ?string
    {
        if (\mb_strlen($value) <= $this->maxLength()) {
            return null;
        }

        /* translators: 1: field label, 2: maximum number of characters */
        $message = \__('%1$s must not be longer than %2$d characters.', 'orchestra-members');

        return \sprintf($message, $this->label, $this->maxLength());
    }

    /**
     * The value as plain text, e.g. for the privacy export.
     */
    public function display(string $value): string
    {
        if ($this->type === self::TYPE_CHECKBOX) {
            return $value === '1' ? \__('Yes', 'orchestra-members') : \__('No', 'orchestra-members');
        }

        return $value;
    }

    /**
     * @return array<string, string> type => translated name
     */
    public static function typeNames(): array
    {
        return [
            self::TYPE_TEXT => \__('Text', 'orchestra-members'),
            self::TYPE_TEXTAREA => \__('Long text', 'orchestra-members'),
            self::TYPE_CHECKBOX => \__('Checkbox', 'orchestra-members'),
            self::TYPE_SELECT => \__('Choice list', 'orchestra-members'),
        ];
    }

    /**
     * @return array{id: string, label: string, type: string, hint: string, choices: list<string>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'type' => $this->type,
            'hint' => $this->hint,
            'choices' => $this->choices,
        ];
    }

    /**
     * @param array<mixed> $data As returned by {@see toArray()}
     */
    public static function fromArray(array $data): ?self
    {
        $id = $data['id'] ?? null;
        $label = $data['label'] ?? null;
        $type = $data['type'] ?? null;
        if (!\is_string($id) || $id === '' || !\is_string($label) || !\in_array($type, self::TYPES, true)) {
            return null;
        }
        $hint = $data['hint'] ?? '';
        $choices = \is_array($data['choices'] ?? null) ? $data['choices'] : [];

        return new self(
            $id,
            $label,
            $type,
            \is_string($hint) ? $hint : '',
            $type === self::TYPE_SELECT ? \array_values(\array_filter($choices, 'is_string')) : [],
        );
    }
}
