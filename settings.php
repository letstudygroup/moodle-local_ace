<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Admin settings for local_aceengine.
 *
 * @package    local_aceengine
 * @copyright  2026 Letstudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_aceengine', get_string('pluginname', 'local_aceengine'));

    if ($ADMIN->fulltree) {
        // General settings heading.
        $settings->add(new admin_setting_heading(
            'local_aceengine/settings_general',
            get_string('settings_general', 'local_aceengine'),
            get_string('settings_general_desc', 'local_aceengine')
        ));

        // Enable plugin.
        $settings->add(new admin_setting_configcheckbox(
            'local_aceengine/enableplugin',
            get_string('enableplugin', 'local_aceengine'),
            get_string('enableplugin_desc', 'local_aceengine'),
            1
        ));

        // Enable mode (global vs per-course).
        $settings->add(new admin_setting_configselect(
            'local_aceengine/enablemode',
            get_string('enablemode', 'local_aceengine'),
            get_string('enablemode_desc', 'local_aceengine'),
            'global',
            [
                'global' => get_string('enablemode_global', 'local_aceengine'),
                'percourse' => get_string('enablemode_percourse', 'local_aceengine'),
            ]
        ));

        // Daily quest count.
        $settings->add(new admin_setting_configtext(
            'local_aceengine/dailyquestcount',
            get_string('dailyquestcount', 'local_aceengine'),
            get_string('dailyquestcount_desc', 'local_aceengine'),
            3,
            PARAM_INT
        ));

        // Engagement weights.
        $settings->add(new admin_setting_configtext(
            'local_aceengine/engagementweight_completion',
            get_string('engagementweight_completion', 'local_aceengine'),
            get_string('engagementweight_completion_desc', 'local_aceengine'),
            30,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            'local_aceengine/engagementweight_timeliness',
            get_string('engagementweight_timeliness', 'local_aceengine'),
            get_string('engagementweight_timeliness_desc', 'local_aceengine'),
            25,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            'local_aceengine/engagementweight_participation',
            get_string('engagementweight_participation', 'local_aceengine'),
            get_string('engagementweight_participation_desc', 'local_aceengine'),
            25,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            'local_aceengine/engagementweight_consistency',
            get_string('engagementweight_consistency', 'local_aceengine'),
            get_string('engagementweight_consistency_desc', 'local_aceengine'),
            20,
            PARAM_INT
        ));

        // Mastery weights.
        $settings->add(new admin_setting_configtext(
            'local_aceengine/masteryweight_grades',
            get_string('masteryweight_grades', 'local_aceengine'),
            get_string('masteryweight_grades_desc', 'local_aceengine'),
            50,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            'local_aceengine/masteryweight_improvement',
            get_string('masteryweight_improvement', 'local_aceengine'),
            get_string('masteryweight_improvement_desc', 'local_aceengine'),
            25,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            'local_aceengine/masteryweight_breadth',
            get_string('masteryweight_breadth', 'local_aceengine'),
            get_string('masteryweight_breadth_desc', 'local_aceengine'),
            25,
            PARAM_INT
        ));

        // XP settings.
        $settings->add(new admin_setting_configtext(
            'local_aceengine/xp_per_quest',
            get_string('xp_per_quest', 'local_aceengine'),
            get_string('xp_per_quest_desc', 'local_aceengine'),
            50,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            'local_aceengine/xp_per_activity',
            get_string('xp_per_activity', 'local_aceengine'),
            get_string('xp_per_activity_desc', 'local_aceengine'),
            10,
            PARAM_INT
        ));

        // License settings heading.
        $settings->add(new admin_setting_heading(
            'local_aceengine/settings_license',
            get_string('settings_license', 'local_aceengine'),
            get_string('settings_license_desc', 'local_aceengine')
        ));

        // License key.
        $settings->add(new admin_setting_configtext(
            'local_aceengine/licensekey',
            get_string('licensekey', 'local_aceengine'),
            get_string('licensekey_desc', 'local_aceengine'),
            '',
            PARAM_TEXT
        ));

        // License status display.
        $licensestatushtml = '';
        $licensekey = get_config('local_aceengine', 'licensekey');
        if (!empty($licensekey)) {
            $lm = new \local_aceengine\licensing\license_manager();
            $status = $lm->get_license_status();
            $statusclass = ($status === 'active') ? 'badge-success' : 'badge-warning';
            $statustext = get_string('licensestatus_' . $status, 'local_aceengine');
            $licensestatushtml = '<span class="badge ' . $statusclass . '">' . $statustext . '</span>';
        } else {
            $licensestatushtml = '<span class="badge badge-secondary">' .
                get_string('licensestatus_none', 'local_aceengine') . '</span>';
        }

        // Pro plugin install hint.
        $proinstalled = array_key_exists('ace_pro', \core_component::get_plugin_list('local'));
        if (!$proinstalled) {
            $licensestatushtml .= '<br><small class="text-muted">' .
                get_string('settings_pro_install_hint', 'local_aceengine') . '</small>';
        }

        $settings->add(new admin_setting_heading(
            'local_aceengine/settings_pro_status',
            get_string('licensestatus', 'local_aceengine'),
            $licensestatushtml
        ));

        // ACE Pro promotion banner (only when Pro is NOT installed).
        if (!$proinstalled) {
            $earlyaccessurl = rtrim(\local_aceengine\licensing\license_manager::LICENSE_SERVER_URL, '/') . '/early-access';

            $promohtml = '<div style="background: linear-gradient(135deg, #f0faf7 0%, #fff0ed 100%); '
                . 'border: 2px solid #e0e0e0; border-radius: 12px; padding: 24px; margin-top: 8px;">';

            // Header with badge.
            $promohtml .= '<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">';
            $promohtml .= '<span style="background: linear-gradient(135deg, #fc6c4d, #e5593d); color: #fff; '
                . 'padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; '
                . 'text-transform: uppercase; letter-spacing: 0.5px;">Coming Soon</span>';
            $promohtml .= '<span style="font-size: 20px; font-weight: 700;">'
                . get_string('pro_banner_title', 'local_aceengine') . '</span>';
            $promohtml .= '</div>';

            // Features grid.
            $profeatures = [
                ['icon' => '&#129302;', 'text' => get_string('pro_feature_ai_adaptation', 'local_aceengine')],
                ['icon' => '&#128200;', 'text' => get_string('pro_feature_dropout', 'local_aceengine')],
                ['icon' => '&#128101;', 'text' => get_string('pro_feature_team_xp', 'local_aceengine')],
                ['icon' => '&#128202;', 'text' => get_string('pro_feature_grade_insights', 'local_aceengine')],
                ['icon' => '&#128218;', 'text' => get_string('pro_feature_content_suggestions', 'local_aceengine')],
                ['icon' => '&#129309;', 'text' => get_string('pro_feature_peer_matching', 'local_aceengine')],
                ['icon' => '&#128232;', 'text' => get_string('pro_feature_interventions', 'local_aceengine')],
                ['icon' => '&#127942;', 'text' => get_string('pro_feature_mission_templates', 'local_aceengine')],
                ['icon' => '&#127963;', 'text' => get_string('pro_feature_institution_analytics', 'local_aceengine')],
                ['icon' => '&#128218;', 'text' => get_string('pro_feature_course_recommendations', 'local_aceengine')],
                ['icon' => '&#9989;', 'text' => get_string('pro_feature_activity_recommendations', 'local_aceengine')],
                ['icon' => '&#128200;', 'text' => get_string('pro_feature_advanced_reporting', 'local_aceengine')],
            ];

            $promohtml .= '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 20px;">';
            foreach ($profeatures as $f) {
                $promohtml .= '<div style="display: flex; align-items: center; gap: 8px; '
                    . 'padding: 6px 10px; background: rgba(255,255,255,0.7); border-radius: 8px; font-size: 13px;">';
                $promohtml .= '<span>' . $f['icon'] . '</span>';
                $promohtml .= '<span>' . $f['text'] . '</span>';
                $promohtml .= '</div>';
            }
            $promohtml .= '</div>';

            // Early access offer.
            $promohtml .= '<div style="background: #fff; border: 1px solid #e5593d; border-radius: 10px; '
                . 'padding: 16px; text-align: center;">';
            $promohtml .= '<div style="font-size: 15px; font-weight: 700; color: #e5593d; margin-bottom: 6px;">'
                . get_string('pro_early_access_title', 'local_aceengine') . '</div>';
            $promohtml .= '<div style="font-size: 13px; color: #555; margin-bottom: 12px;">'
                . get_string('pro_early_access_desc', 'local_aceengine') . '</div>';
            $promohtml .= '<a href="' . $earlyaccessurl . '" target="_blank" '
                . 'style="display: inline-block; background: linear-gradient(135deg, #fc6c4d, #e5593d); '
                . 'color: #fff; padding: 10px 28px; border-radius: 8px; text-decoration: none; '
                . 'font-weight: 700; font-size: 14px;">'
                . get_string('pro_early_access_cta', 'local_aceengine') . '</a>';
            $promohtml .= '</div>';

            $promohtml .= '</div>';

            $settings->add(new admin_setting_heading(
                'local_aceengine/pro_promo',
                '',
                $promohtml
            ));
        }
    }

    $ADMIN->add('localplugins', $settings);
}
