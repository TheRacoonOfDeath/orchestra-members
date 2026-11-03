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
    public const OTHER_MAX_LENGTH = 100;

    /** The form value of the choice "Other" of a choice list with a free-text answer. */
    public const OTHER = '__other__';

    /** The form field of the free-text answers: `orchestra_members_other[ID]`. */
    public const OTHER_FIELD = 'orchestra_members_other';

    /** Stored for an unticked checkbox, so that it differs from a checkbox never answered. */
    public const UNCHECKED = '0';

    private const META_PREFIX = 'orchestra_members_';

    /**
     * @param string $id Stable key; the label can change
     * @param string $hint Help text; the text next to the box for checkboxes
     * @param list<string> $choices Only for {@see TYPE_SELECT}
     * @param bool $checkedByDefault Only for {@see TYPE_CHECKBOX}: the answer of members who have
     *     not answered yet
     * @param bool $allowsOther Only for {@see TYPE_SELECT}: offers "Other" with a free-text answer
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $type,
        public readonly string $hint = '',
        public readonly array $choices = [],
        public readonly bool $checkedByDefault = false,
        public readonly bool $allowsOther = false,
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
        return match ($this->type) {
            self::TYPE_TEXTAREA => self::TEXTAREA_MAX_LENGTH,
            self::TYPE_SELECT => self::OTHER_MAX_LENGTH,
            default => self::TEXT_MAX_LENGTH,
        };
    }

    /**
     * The value for submitted form data: "1" or "" for checkboxes, one of the choices, the
     * free-text answer or "" for choice lists, text without tags and control characters otherwise.
     *
     * The free-text answer counts if "Other" or nothing is chosen, so that it also works without
     * choosing "Other" first.
     */
    public function normalize(mixed $submitted, mixed $other = ''): string
    {
        if (!\is_string($submitted)) {
            return '';
        }

        return match ($this->type) {
            self::TYPE_CHECKBOX => $submitted === '1' ? '1' : '',
            self::TYPE_SELECT => match (true) {
                \in_array($submitted, $this->choices, true) => $submitted,
                $this->allowsOther && ($submitted === self::OTHER || $submitted === '') && \is_string($other)
                    => Text::singleLine($other),
                default => '',
            },
            self::TYPE_TEXTAREA => Text::multiLine($submitted),
            default => Text::singleLine($submitted),
        };
    }

    /**
     * The user meta to store for a value, or "" to delete it. Unticked checkboxes are stored.
     */
    public function stored(string $value): string
    {
        return $this->type === self::TYPE_CHECKBOX && $value !== '1' ? self::UNCHECKED : $value;
    }

    /**
     * The value for stored user meta ("" if there is none). A checkbox that has never been answered
     * has its default.
     */
    public function fromStored(string $stored): string
    {
        if ($this->type !== self::TYPE_CHECKBOX) {
            return $stored;
        }
        if ($stored === '') {
            return $this->checkedByDefault ? '1' : '';
        }

        return $stored === '1' ? '1' : '';
    }

    /**
     * The free-text answer of a choice list, or "" if the value is one of the choices.
     */
    public function otherText(string $value): string
    {
        return $this->allowsOther && $value !== '' && !\in_array($value, $this->choices, true) ? $value : '';
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
     * @return array{
     *     id: string, label: string, type: string, hint: string, choices: list<string>, checked: bool, other: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'type' => $this->type,
            'hint' => $this->hint,
            'choices' => $this->choices,
            'checked' => $this->checkedByDefault,
            'other' => $this->allowsOther,
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
            $type === self::TYPE_CHECKBOX && ($data['checked'] ?? false) === true,
            $type === self::TYPE_SELECT && ($data['other'] ?? false) === true,
        );
    }
}
