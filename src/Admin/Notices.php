<?php

declare(strict_types=1);

namespace Orchestra\Members\Admin;

/**
 * Messages that survive the redirect after submitting a form.
 */
final class Notices
{
    private const TRANSIENT_PREFIX = 'orchestra_members_notices_';

    public function register(): void
    {
        \add_action('admin_notices', [$this, 'render']);
    }

    public function success(string $message): void
    {
        $this->add('success', $message);
    }

    public function error(string $message): void
    {
        $this->add('error', $message);
    }

    /**
     * @internal Hooked to `admin_notices`.
     */
    public function render(): void
    {
        $notices = $this->stored();
        if ($notices === []) {
            return;
        }
        \delete_transient($this->transientName());

        foreach ($notices as [$type, $message]) {
            \printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                \esc_attr($type),
                \esc_html($message)
            );
        }
    }

    private function add(string $type, string $message): void
    {
        $notices = $this->stored();
        $notices[] = [$type, $message];
        \set_transient($this->transientName(), $notices, 5 * MINUTE_IN_SECONDS);
    }

    /**
     * @return list<array{string, string}>
     */
    private function stored(): array
    {
        $stored = \get_transient($this->transientName());
        if (!\is_array($stored)) {
            return [];
        }

        $notices = [];
        foreach ($stored as $notice) {
            if (\is_array($notice) && \is_string($notice[0] ?? null) && \is_string($notice[1] ?? null)) {
                $notices[] = [$notice[0], $notice[1]];
            }
        }

        return $notices;
    }

    private function transientName(): string
    {
        return self::TRANSIENT_PREFIX . \get_current_user_id();
    }
}
