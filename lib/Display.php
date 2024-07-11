<?php

/**
 * KNAS-Nahouw page display routines
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

class Display
{
    public const PACKAGENAME = KNASNAHOUW_PACKAGENAME;
    public const OPTIONS = [
        'url' => 'url',
        'time' => 'string',
        'filter_ned' => 'bool',
        'filter_ecc' => 'bool',
        'filter_wc' => 'bool',
        'filter_title' => 'bool',
    ];
    //private const ADMINSCRIPT = 'src/admin.ts';

    public static function adminPage()
    {
        wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css');
        $view = self::loadTemplate("admin.html");
        $config = Plugin::getConfig();
        echo $view->render([
            'config' => $config,
            'action' => admin_url('admin-post.php'),
            'nonce' => wp_create_nonce('knasnahouw_save'),
            'tournaments' => json_decode(get_option(self::PACKAGENAME . '_syncstat') ?? '[]'),
            'labels' => [
                'title' => __('Settings', 'knas-nahouw'),
                'introduction' => __('Settings_Intro', 'knas-nahouw'),
                'url' => __('API Url', 'knas-nahouw'),
                'schedule' => __('Run time', 'knas-nahouw'),
                'filter_ned' => __('Include NED tournaments', 'knas-nahouw'),
                'filter_ecc' => __('Include ECC tournaments', 'knas-nahouw'),
                'filter_wc' => __('Include World Cup tournaments', 'knas-nahouw'),
                'filter_title' => __('Include title tournaments', 'knas-nahouw'),
                'name' => __('Name', 'knas-nahouw'),
                'start' => __('Start', 'knas-nahouw'),
                'end' => __('End', 'knas-nahouw'),
                'level' => __('Level', 'knas-nahouw'),
                'location' => __('Location', 'knas-nahouw'),
                'weapons' => __('Weapons', 'knas-nahouw'),
                'submit' => __('Submit', 'knas-nahouw'),
                'sync' => __('Synchronize', 'knas-nahouw'),
                'titletournament' => __('Title', 'knas-nahouw'),
                'worldcup' => __('WC', 'knas-nahouw'),
                'ecc' => __('ECC', 'knas-nahouw'),
                'international' => __('Int', 'knas-nahouw'),
                'national' => __('Nat', 'knas-nahouw'),
                'other' => __('Other', 'knas-nahouw'),
                'sabre' => __('Sabre', 'knas-nahouw'),
                'epee' => __('Epee', 'knas-nahouw'),
                'foil' => __('Foil', 'knas-nahouw'),
            ]
        ]);
    }

    public static function register($plugin)
    {
        load_plugin_textdomain('knas-nahouw', false, 'knas-nahouw/languages');
        add_action('admin_menu', fn() => self::adminMenu());
        add_action('admin_post_knasnahouw_save', fn() => self::adminSave());
    }

    private static function adminSave()
    {
        $nonce = sanitize_text_field($_POST['nonce']);
        $action = sanitize_text_field($_POST['action']);
        if (!isset($nonce) || !wp_verify_nonce($nonce, $action)) {
            print __('Sorry, your nonce did not verify.', 'knas-nahouw');
            exit;
        }
        if (!current_user_can('manage_options')) {
            print __("You can't manage options", 'knas-nahouw');
            exit;
        }

        if (isset($_POST['button'])) {
            if ($_POST['button'] == __('Submit', 'knas-nahouw')) {
                $fields_to_update = Plugin::getConfig();
                foreach (self::OPTIONS as $key => $validator) {
                    if (array_key_exists($key, $_POST)) {
                        $fields_to_update[$key] = self::validate($_POST[$key], $validator);
                    }
                    else if ($validator == 'bool') {
                        $fields_to_update[$key] = false;
                    }
                }
                Plugin::saveConfig($fields_to_update);
                Plugin::reregister(); // make sure we schedule according to the new time
                add_settings_error('knasnahouw_message', 'knasnahouw_message_option', __("Changes saved.", 'knas-nahouw'), 'success');
                set_transient('settings_errors', get_settings_errors(), 30);
            }
            else if ($_POST['button'] == __('Synchronize', 'knas-nahouw')) {
                Plugin::synchronize();
            }
        }
        wp_safe_redirect(admin_url('admin.php?page=knas-nahouw'));
    }

    private static function validate($value, $type)
    {
        switch ($type) {
            case 'url':
                if (filter_var($value, FILTER_VALIDATE_URL)) {
                    return $value;
                }
                return '';
            case 'bool':
                return $value == '1' || strtolower($value) == 'on' || strtolower($value) == 'yes';
            case 'int':
                return intval($value);
            case 'string':
                return sanitize_text_field($value);
            case 'numeric':
                return floatval($value);
            case 'date':
                $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
                if ($date === false) {
                    return '';
                }
                return $date->format('Y-m-d');
        }
        return '';
    }

    private static function adminMenu()
    {
        add_menu_page(
            __('Nahouw', 'knas-nahouw'),
            __('Nahouw', 'knas-nahouw'),
            'manage_options', // use generic manage_options capability to restrict this to admins
            self::PACKAGENAME,
            fn() => Display::adminPage(),
            'dashicons-media-spreadsheet',
            100
        );
    }

    private static function loadTemplate($view)
    {
        $loader = new \Twig\Loader\FilesystemLoader('views', KNASNAHOUW_DIR);
        $twig = new \Twig\Environment($loader, [
            'cache' => false, //get_temp_dir() . '/knas-nahouw-cache'
        ]);
        return $twig->load($view);
    }
}
