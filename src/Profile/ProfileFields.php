<?php

declare(strict_types=1);

namespace Orchestra\Members\Profile;

/**
 * The fields of the orchestra profile, chosen by the administrators. Stored in an option; until
 * they are changed for the first time, the profile has the default fields.
 */
final class ProfileFields
{
    public const OPTION = 'orchestra_members_profile_fields';

    /** The ID of the checkbox field that means "needs financial support"; "" for none. */
    public const OPTION_FINANCIAL_SUPPORT = 'orchestra_members_financial_support_field';

    /** The field that means financial support until administrators choose one (a default field). */
    public const DEFAULT_FINANCIAL_SUPPORT = 'financial_support';

    /** Field IDs that would collide with other user meta of orchestra-members. */
    private const RESERVED_IDS = ['inactive'];

    private const ID_MAX_LENGTH = 40;

    /** @var list<ProfileField>|null */
    private ?array $cache = null;

    /**
     * @return list<ProfileField> in display order
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $stored = \get_option(self::OPTION);
        if (!\is_array($stored)) {
            return $this->cache = self::defaults();
        }

        $fields = [];
        foreach ($stored as $data) {
            $field = \is_array($data) ? ProfileField::fromArray($data) : null;
            if ($field !== null) {
                $fields[] = $field;
            }
        }

        return $this->cache = $fields;
    }

    /**
     * @param list<ProfileField> $fields in display order
     */
    public function save(array $fields): void
    {
        \update_option(
            self::OPTION,
            \array_map(static fn (ProfileField $field): array => $field->toArray(), $fields),
            false
        );
        $this->cache = $fields;
    }

    /**
     * The checkbox field in which members ask for financial support, e.g. for project fees; shown to
     * organizers next to the fees of orchestra-projects. Null if there is none.
     */
    public function financialSupportField(): ?ProfileField
    {
        $stored = \get_option(self::OPTION_FINANCIAL_SUPPORT, self::DEFAULT_FINANCIAL_SUPPORT);
        $id = \is_string($stored) ? $stored : '';
        foreach ($this->all() as $field) {
            if ($field->id === $id && $field->type === ProfileField::TYPE_CHECKBOX) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param string $fieldId The ID of a checkbox field, or "" for none
     */
    public function saveFinancialSupportField(string $fieldId): void
    {
        \update_option(self::OPTION_FINANCIAL_SUPPORT, $fieldId, false);
    }

    /**
     * The fields every orchestra starts with. Their IDs match the user meta of versions up to 0.4,
     * so existing data is kept.
     *
     * @return list<ProfileField>
     */
    public static function defaults(): array
    {
        return [
            new ProfileField(
                'financial_support',
                \__('Financial support', 'orchestra-members'),
                ProfileField::TYPE_CHECKBOX,
                \__('I need financial support, e.g. for project fees.', 'orchestra-members')
            ),
            new ProfileField(
                'dietary_restrictions',
                \__('Dietary restrictions', 'orchestra-members'),
                ProfileField::TYPE_TEXTAREA,
                \__('Allergies, vegetarian, vegan, …', 'orchestra-members')
            ),
        ];
    }

    /**
     * A new, unused field ID derived from the label, e.g. "t_shirt_size" for "T-shirt size".
     *
     * @param list<string> $taken
     */
    public static function newId(string $label, array $taken): string
    {
        $base = \strtolower(\remove_accents($label));
        $base = \trim((string) \preg_replace('/[^a-z0-9]+/', '_', $base), '_');
        $base = \rtrim(\substr($base, 0, self::ID_MAX_LENGTH), '_');
        if ($base === '') {
            $base = 'field';
        }

        $taken = [...$taken, ...self::RESERVED_IDS];
        $id = $base;
        for ($number = 2; \in_array($id, $taken, true); $number++) {
            $id = $base . '_' . $number;
        }

        return $id;
    }
}
