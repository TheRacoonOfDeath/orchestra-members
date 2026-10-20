<?php

/**
 * Removes the profile fields, all profile data and the active status of all users when the plugin is deleted.
 *
 * Deactivating the plugin intentionally keeps all data. While it is deactivated, inactive members
 * can log in again.
 */

declare(strict_types=1);

use Orchestra\Members\Profile\ProfileFields;
use Orchestra\Members\Profile\ProfileRepository;

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

$orchestraMembersFields = new ProfileFields();
$orchestraMembersProfiles = new ProfileRepository($orchestraMembersFields);
foreach ($orchestraMembersFields->all() as $orchestraMembersField) {
    $orchestraMembersProfiles->deleteFieldValues($orchestraMembersField);
}
delete_metadata('user', 0, ProfileRepository::META_INACTIVE, '', true);
delete_option(ProfileFields::OPTION);
delete_option(ProfileFields::OPTION_FINANCIAL_SUPPORT);
