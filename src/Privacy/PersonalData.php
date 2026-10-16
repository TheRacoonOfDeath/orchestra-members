<?php

declare(strict_types=1);

namespace Orchestra\Members\Privacy;

use Orchestra\Members\Admin\StatusLabel;
use Orchestra\Members\Profile\ProfileField;
use Orchestra\Members\Profile\ProfileFields;
use Orchestra\Members\Profile\ProfileRepository;
use WP_User;

/**
 * Connects the profile data to the WordPress privacy tools (Tools → Export / Erase Personal Data)
 * and suggests a text for the privacy policy.
 */
final class PersonalData
{
    private const ID = 'orchestra-members';

    public function __construct(
        private readonly ProfileRepository $profiles,
        private readonly ProfileFields $fields,
    ) {
    }

    public function register(): void
    {
        \add_filter('wp_privacy_personal_data_exporters', [$this, 'addExporter']);
        \add_filter('wp_privacy_personal_data_erasers', [$this, 'addEraser']);
        \add_action('admin_init', [$this, 'addPolicyText']);
    }

    /**
     * @internal Hooked to `wp_privacy_personal_data_exporters`.
     * @param array<mixed> $exporters
     * @return array<mixed>
     */
    public function addExporter(array $exporters): array
    {
        $exporters[self::ID] = [
            'exporter_friendly_name' => \__('Orchestra profile', 'orchestra-members'),
            'callback' => [$this, 'export'],
        ];

        return $exporters;
    }

    /**
     * @internal Hooked to `wp_privacy_personal_data_erasers`.
     * @param array<mixed> $erasers
     * @return array<mixed>
     */
    public function addEraser(array $erasers): array
    {
        $erasers[self::ID] = [
            'eraser_friendly_name' => \__('Orchestra profile', 'orchestra-members'),
            'callback' => [$this, 'erase'],
        ];

        return $erasers;
    }

    /**
     * @return array{data: list<array<string, mixed>>, done: bool}
     */
    public function export(string $email, int $page = 1): array
    {
        $user = \get_user_by('email', $email);
        $profile = $user instanceof WP_User ? $this->profiles->find($user->ID) : null;
        if ($profile === null) {
            return ['data' => [], 'done' => true];
        }

        $data = [['name' => \__('Status', 'orchestra-members'), 'value' => StatusLabel::of($profile->active)]];
        foreach ($this->fields->all() as $field) {
            $data[] = ['name' => $field->label, 'value' => $field->display($profile->value($field->id))];
        }

        return [
            'data' => [[
                'group_id' => self::ID,
                'group_label' => \__('Orchestra profile', 'orchestra-members'),
                'item_id' => 'orchestra-profile-' . $profile->userId,
                'data' => $data,
            ]],
            'done' => true,
        ];
    }

    /**
     * Deletes the sensitive data. The inactive status is kept, so an erased inactive account
     * stays blocked.
     *
     * @return array{items_removed: bool, items_retained: bool, messages: list<string>, done: bool}
     */
    public function erase(string $email, int $page = 1): array
    {
        $user = \get_user_by('email', $email);
        $profile = $user instanceof WP_User ? $this->profiles->find($user->ID) : null;
        if ($profile === null) {
            return ['items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true];
        }

        $hadData = $profile->hasValues();
        $this->profiles->deleteSensitiveData($profile->userId);

        $messages = [];
        if (!$profile->active) {
            $messages[] = \__(
                'The inactive status of the orchestra account was kept, so that the account stays blocked.',
                'orchestra-members'
            );
        }

        return [
            'items_removed' => $hadData,
            'items_retained' => !$profile->active,
            'messages' => $messages,
            'done' => true,
        ];
    }

    /**
     * @internal Hooked to `admin_init`.
     */
    public function addPolicyText(): void
    {
        if (!\function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        $sentences = [];
        $labels = \array_map(static fn (ProfileField $field): string => $field->label, $this->fields->all());
        if ($labels !== []) {
            $sentences[] = \sprintf(
                /* translators: %s: list of profile fields, e.g. "Financial support, Dietary restrictions" */
                \__('Members can enter the following information in their profile: %s.', 'orchestra-members'),
                \implode(', ', $labels)
            );
            $sentences[] = \__(
                'This information is visible only to the member, the organizers and the administrators.',
                'orchestra-members'
            );
            $sentences[] = \__(
                'It is used to plan projects and deleted when a member is set inactive.',
                'orchestra-members'
            );
        }
        $sentences = [
            ...$sentences,
            \__(
                'Members who leave are set inactive instead of deleted, to preserve the history of projects.',
                'orchestra-members'
            ),
            \__('Inactive members cannot log in.', 'orchestra-members'),
        ];
        $text = '<p>' . \esc_html(\implode(' ', $sentences)) . '</p>';

        \wp_add_privacy_policy_content(\__('Orchestra Members', 'orchestra-members'), \wp_kses_post($text));
    }
}
