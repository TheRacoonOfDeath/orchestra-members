<?php

/**
 * Plugin Name:      Orchestra Members
 * Plugin URI:       https://github.com/TheRacoonOfDeath/orchestra-members
 * Description:      Member profiles and active status for the Orchestra plugin suite.
 * Version:          0.2.1
 * Author:           Julian Nickerl
 * License:          MIT
 * Text Domain:      orchestra-members
 * Requires at least: 6.5
 * Requires PHP:     8.3
 * Requires Plugins: orchestra-core
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

Orchestra\Members\Plugin::boot(__FILE__);
