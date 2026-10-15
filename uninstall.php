<?php

/**
 * Removes all profile data and the active status of all users when the plugin is deleted.
 *
 * Deactivating the plugin intentionally keeps all data. While it is deactivated, inactive members
 * can log in again.
 */

declare(strict_types=1);

use Orchestra\Members\Profile\ProfileRepository;

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

foreach (ProfileRepository::META_KEYS as $orchestraMembersMetaKey) {
    delete_metadata('user', 0, $orchestraMembersMetaKey, '', true);
}
