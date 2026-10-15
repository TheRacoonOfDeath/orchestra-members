<?php

declare(strict_types=1);

namespace Orchestra\Members\Profile;

/**
 * Normalizes and validates the editable profile fields submitted in a form.
 */
final class ProfileInput
{
    public const FIELD_FINANCIAL_SUPPORT = 'orchestra_members_financial_support';
    public const FIELD_DIETARY_RESTRICTIONS = 'orchestra_members_dietary_restrictions';

    public const DIETARY_RESTRICTIONS_MAX_LENGTH = 1000;

    private function __construct(
        public readonly bool $financialSupport,
        public readonly string $dietaryRestrictions,
    ) {
    }

    /**
     * @param array<mixed> $post Unslashed form data
     */
    public static function fromForm(array $post): self
    {
        $dietary = $post[self::FIELD_DIETARY_RESTRICTIONS] ?? '';

        return new self(
            ($post[self::FIELD_FINANCIAL_SUPPORT] ?? '') === '1',
            \is_string($dietary) ? self::normalizeText($dietary) : '',
        );
    }

    /**
     * @return list<string> Error messages; empty if the input is valid
     */
    public function validate(): array
    {
        if (\mb_strlen($this->dietaryRestrictions) > self::DIETARY_RESTRICTIONS_MAX_LENGTH) {
            /* translators: %d: maximum number of characters */
            $message = \__('Dietary restrictions must not be longer than %d characters.', 'orchestra-members');

            return [\sprintf($message, self::DIETARY_RESTRICTIONS_MAX_LENGTH)];
        }

        return [];
    }

    /**
     * Strips tags and control characters, keeps line breaks and trims surrounding whitespace.
     */
    private static function normalizeText(string $text): string
    {
        $text = \strip_tags(\str_replace("\r\n", "\n", $text));
        $text = (string) \preg_replace('/[^\P{C}\n\t]/u', '', $text);

        return \trim($text);
    }
}
