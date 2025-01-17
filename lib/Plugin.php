<?php

/**
 * KNAS-Nahouw Plugin hooks
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

class Plugin
{
    public static function register($plugin)
    {
        add_action("knasnahouw_daily_hook", "knasnahouw_daily_cron");
        if (!wp_get_scheduled_event("knasnahouw_daily_hook", ["process_uri"])) {
            self::reregister();
        }
    }

    public static function unregister()
    {
        wp_clear_scheduled_hook("knasnahouw_daily_hook", ["process_uri"]);
    }

    public static function reregister()
    {
        wp_clear_scheduled_hook("knasnahouw_daily_hook", ["process_uri"]);
        $config = self::getConfig();
        if (isset($config['time'])) {
            $now = new \DateTimeImmutable();
            $timestamp = \DateTime::createFromFormat('Y-m-d H:i', $now->format('Y-m-d') . ' ' . $config['time'])->add(new \DateInterval("P1D"));
            wp_schedule_event(
                KNASNAHOUW_DEBUG ? time() : $timestamp->getTimestamp(),
                KNASNAHOUW_DEBUG ? 'every_minute' : 'daily',
                'knasnahouw_daily_hook',
                ['process_uri'],
                false
            );
        }
    }

    public static function synchronize()
    {
        $config = self::getConfig();
        $tournaments = [];
        if (isset($config['url'])) {
            $url = $config['url'];
            ini_set("allow_url_fopen", 1);
            $json = file_get_contents($url);
            $obj = json_decode($json);
            if ($obj !== false) {
                foreach ($obj as $tournament) {
                    $tn['name'] = $tournament->name ?? '';
                    $tn['start'] = $tournament->start_date ?? '';
                    $tn['end'] = $tournament->end_date ?? $tn['start'];
                    $tn['location'] = $tournament->location ?? '';
                    $tn['country'] = $tournament->ioc_country_abbr ?? 'NED';
                    $tn['weapons'] = $tournament->weapon_summary ?? 'SaFlEp';
                    $tn['categories'] = $tournament->weapon_age_gender ?? ["weapon" => "epee", "gender" => "male", "age" => "seniors"];
                    $tn['level'] = $tournament->level ?? 'National';
                    $tn['uri'] = $tournament->url ?? '';

                    $tn['conv_weapon'] = self::weaponToMeta($tn);
                    $tn['conv_category'] = self::categoryToMeta($tn);
                    $tn['conv_type'] = self::typeToMeta($tn);

                    if (self::filterTournament($tn, $config)) {
                        $tournaments[] = $tn;
                    }
                }
                update_option(Display::PACKAGENAME . "_syncstat", json_encode($tournaments));
            }
        }

        foreach ($tournaments as $tournament) {
            self::searchAndReplaceTournament($tournament);
        }

        return $tournaments;
    }

    private static function filterTournament($tn, $config)
    {
        if ($config['filter_ned'] !== false) {
            if ($tn['country'] == 'NED') {
                return true;
            }
        }
        if ($config['filter_ecc'] !== false) {
            $type = $tn['conv_type'];
            if ($type['ECC'] == 'true') {
                return true;
            }
        }
        if ($config['filter_wc'] !== false) {
            $type = $tn['conv_type'];
            if ($type['Wereldbeker'] == 'true') {
                return true;
            }
        }
        if ($config['filter_title'] !== false) {
            $type = $tn['conv_type'];
            if ($type['Titeltoernooien'] == 'true') {
                return true;
            }
        }
        return false;
    }

    public static function daily($args)
    {
        $tournaments = self::synchronize();
    }

    private static function searchAndReplaceTournament($tournament)
    {
        $query = new \WP_Query([
            'post_type' => 'wedstrijden',
            'meta_query' => [
                [
                    'key' => 'nahouw-link',
                    'value' => $tournament['uri'] ?? '',
                    'compare' => '='
                ]
            ]
        ]);
        $posts = $query->get_posts();
        if (empty($posts)) {
            self::createPostForTournament($tournament);
        }
        else {
            self::updatePostForTournament($posts[0], $tournament);
        }
    }

    private static function updatePostForTournament($post, $tournament)
    {
        // do not update the title. We assume this hardly ever happens and the chance
        // that we have updated it in the back-end is bigger
        // $post->post_title = $tournament['name'];
        // do not update the post-name, this is a permalink
        // $post->post_name = self::sanitizeTournamentName($tournament['name'], $tournament['start']);
        // do not update the post_status
        // do not update the post_type
        // do not update the post_author
        // do not update the toernooi-naam for the same reason as above
        // do not update homepagina_event
        //update_post_meta($post->ID, 'toernooi-naam', $tournament['name']);
        update_post_meta($post->ID, 'wapen', $tournament['conv_weapon']);
        update_post_meta($post->ID, 'categorie', $tournament['conv_category']);
        update_post_meta($post->ID, 'toernooi-type', $tournament['conv_type']);
        update_post_meta($post->ID, 'locatie', $tournament['location']);
        update_post_meta($post->ID, 'datum', ((new \DateTimeImmutable($tournament['start']))->getTimestamp()));
        update_post_meta($post->ID, 'eind-datum_612', ((new \DateTimeImmutable($tournament['end']))->getTimestamp()));
        // do not update the nahouw-link, it was not changed or else we could not find this tournament
    }

    private static function createPostForTournament($tournament)
    {
        $sanitizedtitle = self::sanitizeTournamentName($tournament['name'], $tournament['start']);
        wp_insert_post([
            'post_author' => 1,
            'post_title' => $tournament['name'],
            'post_status' => 'publish',
            'post_type' => 'wedstrijden',
            'post_name' => $sanitizedtitle,
            'meta_input' => [
                'toernooi-naam' => $tournament['name'],
                'wapen' => $tournament['conv_weapon'],
                'categorie' => $tournament['conv_category'],
                'toernooi-type' => $tournament['conv_type'],
                'locatie' => $tournament['location'],
                'datum' => ((new \DateTimeImmutable($tournament['start']))->getTimestamp()),
                'eind-datum_612' => ((new \DateTimeImmutable($tournament['end']))->getTimestamp()),
                'live-stream-link' => '',
                'nahouw-resultaten' => '',
                'foto--poster' => '',
                'nahouw-link' => $tournament['uri'],
                'homepagina_event' => 'true',
            ],

        ]);
    }

    private static function weaponToMeta($tournament)
    {
        $hasSa = strpos($tournament['weapons'], 'Sa') !== false;
        $hasEp = strpos($tournament['weapons'], 'Ep') !== false;
        $hasFl = strpos($tournament['weapons'], 'Fl') !== false;
        return [
            "degen" => $hasEp ? 'true' : 'false',
            "floret" => $hasFl ? 'true' : 'false',
            "sabel" => $hasSa ? 'true' : 'false'
        ];
    }

    private static function categoryToMeta($tournament)
    {
        $hasB = false;
        $hasP = false;
        $hasC = false;
        $hasJ = false;
        $hasS = false;
        $hasV = false;
        $hasR = false;
        $hasL = false;
        $hasO = false;
        $bcats = ["benjamins", "pullets", "kuikens"];
        $pcats = ["boys/girls", "pupillen"];
        $ccats = ['cadets', 'cadetten'];
        $jcats = ['juniors', 'junioren'];
        $scats = ['seniors', 'senioren'];
        $vcats = ['veterans', 'veteranen'];

        foreach ($tournament['categories'] as $cat) {
            $age = $cat->age ?? 'none';
            $hasB = $hasB || in_array($age, $bcats);
            $hasP = $hasP || in_array($age, $pcats);
            $hasC = $hasC || in_array($age, $ccats);
            $hasJ = $hasJ || in_array($age, $jcats);
            $hasS = $hasS || in_array($age, $scats);
            $hasV = $hasV || in_array($age, $vcats);
        }
        return [
            "Benjamins" => $hasB ? 'true' : 'false',
            "Pupillen" => $hasP ? 'true' : 'false',
            "Cadetten" => $hasC ? 'true' : 'false',
            "Junioren" => $hasJ ? 'true' : 'false',
            "Senioren" => $hasS ? 'true' : 'false',
            "Veteranen" => $hasV ? 'true' : 'false',
            "Rolstoel" => $hasR ? 'true' : 'false',
            "Lightsaber" => $hasL ? 'true' : 'false',
            "Overig" => $hasO ? 'true' : 'false',
        ];
    }

    private static function typeToMeta($tournament)
    {
        $hasT =   strpos($tournament['name'], "NJK ") !== false
               || strpos($tournament['name'], "NK ") !== false
               || strpos($tournament['name'], "EK ") !== false
               || strpos($tournament['name'], "EJK ") !== false
               || strpos($tournament['name'], "WK ") !== false
               || strpos($tournament['name'], "WJK ") !== false
               ;
        $hasW = false;
        $hasE = false;
        // international when set International, it's a satellite tournament, or an ECC/WC
        $hasI = stripos($tournament['level'], "International") !== false
                || stripos($tournament['level'], "FIE Satellite A") !== false
                || $hasE
                || $hasW
                ;
        // national when set national... or not set international (invitational for example)
        $hasN = strpos("National", $tournament['level']) !== false || !$hasI;
        return [
            "Titeltoernooien" => $hasT ? 'true' : 'false',
            "Wereldbeker" => $hasW ? 'true' : 'false',
            "ECC" => $hasE ? 'true' : 'false',
            "Internationaal" => $hasI ? 'true' : 'false',
            "Nationaal" => $hasN ? 'true' : 'false',
        ];
    }

    private static function sanitizeTournamentName($name, $start)
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $start);
        $year = $date->format('Y');
        $result = sanitize_title($name);
        if (strpos($result, $year) === false) {
            $result .= '-' . $year;
        }
        return $result;
    }

    public static function getConfig()
    {
        $config = json_decode(get_option(Display::PACKAGENAME . "_configuration"), true);
        return array_merge(
            [
                'url' => '',
                'time' => '04:00',
            ],
            $config ?? []
        );
    }

    public static function saveConfig($config)
    {
        update_option(Display::PACKAGENAME . "_configuration", json_encode($config));
    }
}
