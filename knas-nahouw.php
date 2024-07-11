<?php

/**
 * KNAS-Nahouw interface
 *
 * @package             knas-nahouw
 * @author              Michiel Uitdehaag
 * @copyright           2024 - 2024 Michiel Uitdehaag for muis IT
 * @licenses            GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:         knas-nahouw
 * Plugin URI:          https://github.com/muisit/knas-nahouw
 * Description:         Interface to download Nahouw registered events to the KNAS schermen.org site
 * Version:             1.0.1
 * Requires at least:   6.3
 * Requires PHP:        8.0
 * Author:              Michiel Uitdehaag
 * Author URI:          https://www.muisit.nl
 * License:             GNU GPLv3
 * License URI:         https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:         knas-nahouw
 * Domain Path:         /languages
 *
 * This file is part of KNAS-Nahouw.
 *
 * knas-nahouw is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * knas-nahouw is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with knas-nahouw.  If not, see <https://www.gnu.org/licenses/>.
 */

define('KNASNAHOUW_VERSION', "1.0.1");
define('KNASNAHOUW_PACKAGENAME', 'knas-nahouw');
define('KNASNAHOUW_DEBUG', true);
define("KNASNAHOUW_DIR", WP_PLUGIN_DIR . '/knas-nahouw');

function knasnahouw_autoloader($name)
{
    if (!strncmp($name, 'KNASNahouw\\', 11)) {
        $elements = explode('\\', $name);
        // require at least KNASNahouw\<sub>\<name>, so 3 elements
        if (sizeof($elements) > 2 && $elements[0] == "KNASNahouw") {
            $fname = $elements[sizeof($elements) - 1] . ".php";
            $dir = implode("/", array_splice($elements, 1, -1)); // remove the base part and the file itself
            if (file_exists(__DIR__ . "/" . strtolower($dir) . "/" . $fname)) {
                include(__DIR__ . "/" . strtolower($dir) . "/" . $fname);
            }
        }
    }
}

spl_autoload_register('knasnahouw_autoloader');
require_once('vendor/autoload.php');

if (defined('ABSPATH')) {
    \KNASNahouw\Lib\Activator::register(__FILE__);

    add_action('plugins_loaded', function () {
        \KNASNahouw\Lib\Display::register(__FILE__);
    });
    add_action('wp_loaded', function () {
        \KNASNahouw\Lib\Plugin::register(__FILE__);
    });
}

function knasnahouw_daily_cron($args)
{
    \KNASNahouw\Lib\Plugin::daily($args);
}

function knasnahouw_log($txt)
{
    if (KNASNAHOUW_DEBUG) {
        error_log($txt);
    }
}
