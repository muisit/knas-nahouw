<?php

/**
 * KNAS-Nahouw activation routines
 *
 * @package             knas-nahouw
 * @author              Michiel Uitdehaag
 * @copyright           2024 - 2024 Michiel Uitdehaag for muis IT
 * @licenses            GPL-3.0-or-later
 *
 * This file is part of knas-nahouw.
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

namespace KNASNahouw\Lib;

//use KNASNahouw\Models\Migration;

function knasnahouw_uninstall_hook()
{
    Activator::uninstall();
}

class Activator
{
    private const CONFIG = KNASNAHOUW_PACKAGENAME . "_version";

    public static function register($plugin)
    {
        register_activation_hook($plugin, fn() => self::activate());
        register_deactivation_hook($plugin, fn() => self::deactivate());
        register_uninstall_hook($plugin, "knasnahouw_uninstall_hook");
        add_action('upgrader_process_complete', fn($ob, $op) => self::upgrade($ob, $op), 10, 2);
        add_action('plugins_loaded', fn() => self::update());
        add_filter('cron_schedules', fn($s) => self::addIntervals($s));
    }

    private static function activate()
    {
        update_option(self::CONFIG, 'new');
        self::update();

        //$role = get_role('administrator');
        //$role->add_cap('manage_' . Display::PACKAGENAME, true);
    }

    private static function deactivate()
    {
        Plugin::unregister();
    }

    public static function uninstall()
    {
        //$model = new Migration(Display::PACKAGENAME . '_migrations');
        //$model->uninstall(realpath(__DIR__ . '/../models'));
    }

    private static function upgrade($upgrader_object, $options)
    {
        $current_plugin_path_name = plugin_basename(__FILE__);

        if ($options['action'] == 'update' && $options['type'] == 'plugin') {
            foreach ($options['plugins'] as $each_plugin) {
                if ($each_plugin == $current_plugin_path_name) {
                    update_option(self::CONFIG, 'new');
                }
            }
        }
    }

    private static function update()
    {
        if (get_option(self::CONFIG) == "new") {
            // this loads all database migrations from file and executes
            // all those that are not yet marked as migrated
            //$model = new Migration(Display::PACKAGENAME . '_migrations');
            //$model->activate(realpath(__DIR__ . '/../models'));
            update_option(self::CONFIG, (new \DateTimeImmutable())->format('Y-m-d H:i:s'));
        }
    }

    private static function addIntervals($schedules)
    {
        $schedules['every_minute'] = array(
            'interval' => 1 * 60,
            'display'  => esc_html__('Every Minute'));
        $schedules['every_2_minutes'] = array(
            'interval' => 2 * 60,
            'display'  => esc_html__('Every 2 Minutes'));
        $schedules['every_5_minutes'] = array(
            'interval' => 5 * 60,
            'display'  => esc_html__('Every 5 Minutes'));
        $schedules['every_10_minutes'] = array(
            'interval' => 10 * 60,
            'display'  => esc_html__('Every 10 Minutes'));
        $schedules['every_hour'] = array(
            'interval' => 60 * 60,
            'display'  => esc_html__('Every Hour'));
        return $schedules;
    }
}
