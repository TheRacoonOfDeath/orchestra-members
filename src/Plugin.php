<?php

declare(strict_types=1);

namespace Orchestra\Members;

use LogicException;
use Orchestra\Core\Authorization\CapabilityRegistry;
use Orchestra\Members\Admin\MembersPage;
use Orchestra\Members\Admin\Notices;
use Orchestra\Members\Admin\ProfileSection;
use Orchestra\Members\Admin\UserListColumn;
use Orchestra\Core\Email\EmailService;
use Orchestra\Members\Email\MemberEmailGroups;
use Orchestra\Members\Privacy\PersonalData;
use Orchestra\Members\Profile\ProfileRepository;
use Orchestra\Members\Status\LoginBlocker;
use Orchestra\Members\Status\StatusService;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

final class Plugin
{
    /**
     * Update metadata, attached to every GitHub release by build-plugin.sh. This download link
     * always points to the latest release and, unlike the GitHub API, has no rate limit for
     * requests without a token.
     */
    private const UPDATE_METADATA_URL =
        'https://github.com/TheRacoonOfDeath/orchestra-members/releases/latest/download/update.json';

    private static ?self $instance = null;

    private readonly ProfileRepository $profiles;
    private readonly MemberDirectory $directory;
    private readonly StatusService $status;
    private readonly MembersService $members;

    private function __construct(private readonly string $pluginFile)
    {
        $this->profiles = new ProfileRepository();
        $this->directory = new MemberDirectory($this->profiles);
        $this->status = new StatusService($this->profiles);
        $this->members = new MembersService($this->directory, $this->profiles);
    }

    public static function boot(string $pluginFile): self
    {
        if (self::$instance === null) {
            self::$instance = new self($pluginFile);
            self::$instance->register();
        }

        return self::$instance;
    }

    /**
     * The running plugin. Other plugins use it to access {@see MembersService}.
     */
    public static function instance(): self
    {
        return self::$instance ?? throw new LogicException('orchestra-members is not loaded.');
    }

    public function members(): MembersService
    {
        return $this->members;
    }

    private function register(): void
    {
        $this->registerUpdateChecker();

        // Blocking inactive members must not depend on orchestra-core being active.
        (new LoginBlocker($this->profiles))->register();

        \add_action('plugins_loaded', [$this, 'registerFeatures']);
    }

    /**
     * @internal Hooked to `plugins_loaded`, when orchestra-core is guaranteed to be loaded if active.
     */
    public function registerFeatures(): void
    {
        if (!\class_exists(CapabilityRegistry::class)) {
            \add_action('admin_notices', [$this, 'renderMissingCoreNotice']);
            return;
        }

        \add_action('orchestra_core_register_capabilities', [Capabilities::class, 'register']);
        \add_action('user_register', [$this, 'announceNewMember']);
        (new PersonalData($this->profiles))->register();
        // The email service exists since orchestra-core 0.3.
        if (\class_exists(EmailService::class)) {
            (new MemberEmailGroups($this->directory, $this->profiles))->register();
        }

        if (\is_admin()) {
            $notices = new Notices();
            $notices->register();
            (new MembersPage($this->directory, $this->profiles, $this->status, $notices))->register();
            (new ProfileSection($this->profiles))->register();
            (new UserListColumn($this->profiles))->register();
        }
    }

    /**
     * @internal Hooked to `user_register`.
     */
    public function announceNewMember(int $userId): void
    {
        \do_action(MembersService::ACTION_ACTIVATED, $userId);
    }

    /**
     * @internal Hooked to `admin_notices`.
     */
    public function renderMissingCoreNotice(): void
    {
        echo '<div class="notice notice-error"><p>'
            . \esc_html__('Orchestra Members requires the Orchestra Core plugin to be active.', 'orchestra-members')
            . '</p></div>';
    }

    private function registerUpdateChecker(): void
    {
        PucFactory::buildUpdateChecker(self::UPDATE_METADATA_URL, $this->pluginFile, 'orchestra-members');
    }
}
