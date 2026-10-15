<?php

declare(strict_types=1);

namespace Orchestra\Members;

use LogicException;
use Orchestra\Core\Authorization\CapabilityRegistry;
use Orchestra\Core\Ui\Html;
use Orchestra\Core\Ui\Menu;
use Orchestra\Core\Ui\MenuItem;
use Orchestra\Members\Admin\MembersPage;
use Orchestra\Members\Admin\ProfileSection;
use Orchestra\Members\Admin\UserListColumn;
use Orchestra\Core\Email\EmailService;
use Orchestra\Members\Email\MemberEmailGroups;
use Orchestra\Members\Privacy\PersonalData;
use Orchestra\Members\Profile\ProfileRepository;
use Orchestra\Members\Status\LoginBlocker;
use Orchestra\Members\Status\StatusService;
use Orchestra\Members\Ui\MembersShortcode;
use Orchestra\Members\Ui\MembersView;
use Orchestra\Members\Ui\ProfileController;
use Orchestra\Members\Ui\ProfileShortcode;
use Orchestra\Members\Ui\StatusController;
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
        // The shared UI exists since orchestra-core 0.4.
        if (!\class_exists(CapabilityRegistry::class) || !\class_exists(Html::class)) {
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

        $view = new MembersView($this->directory, $this->profiles, $this->status);
        (new StatusController($this->status))->register();
        (new ProfileController($this->profiles))->register();
        (new MembersShortcode($view))->register();
        (new ProfileShortcode($this->profiles))->register();
        \add_action(Menu::REGISTER_ACTION, [$this, 'registerMenuItems']);

        if (\is_admin()) {
            (new MembersPage($view))->register();
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
     * @internal Hooked to `orchestra_core_register_menu_items`.
     */
    public function registerMenuItems(Menu $menu): void
    {
        $menu->add(new MenuItem(
            MembersShortcode::TAG,
            \__('Members', 'orchestra-members'),
            \__('Who plays in the orchestra.', 'orchestra-members'),
            static fn (int $userId): bool => \user_can($userId, Capabilities::DIRECTORY_READ),
            40
        ));
        $menu->add(new MenuItem(
            ProfileShortcode::TAG,
            \__('My profile', 'orchestra-members'),
            \__('Your name, email address, password and dietary restrictions.', 'orchestra-members'),
            static fn (int $userId): bool => \user_can($userId, Capabilities::PROFILE_EDIT, $userId),
            90
        ));
    }

    /**
     * @internal Hooked to `admin_notices`.
     */
    public function renderMissingCoreNotice(): void
    {
        echo '<div class="notice notice-error"><p>'
            . \esc_html__('Orchestra Members requires Orchestra Core 0.4 or later to be active.', 'orchestra-members')
            . '</p></div>';
    }

    private function registerUpdateChecker(): void
    {
        PucFactory::buildUpdateChecker(self::UPDATE_METADATA_URL, $this->pluginFile, 'orchestra-members');
    }
}
