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
 * ACE Analytics page for teachers and managers.
 *
 * Displays engagement, mastery, and dropout risk analytics for all
 * enrolled students in a course, including all sub-factors used
 * in the dropout risk algorithm.
 *
 * @package    local_ace
 * @copyright  2026 LetStudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);

$course = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/ace:viewanalytics', $context);

$PAGE->set_url(new moodle_url('/local/ace/analytics.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('analytics', 'local_ace'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');
$PAGE->set_secondary_active_tab('local_ace_analytics');
$PAGE->navbar->add(get_string('analytics', 'local_ace'));

$PAGE->add_body_class('local-ace-analytics-page');

// Check if ACE Pro AI analysis is available.
$proaiavailable = class_exists('\local_ace_pro\pro_manager')
    && \local_ace_pro\pro_manager::is_active()
    && \local_ace_pro\ai\ai_provider::is_available()
    && get_config('local_ace_pro', 'ai_dropout_analysis');

// Filter parameters.
$filtername = optional_param('fname', '', PARAM_TEXT);
$filterrisk = optional_param('frisk', '', PARAM_ALPHA);
$filtertrend = optional_param('ftrend', '', PARAM_ALPHA);
$filterengage = optional_param('fengage', '', PARAM_ALPHA);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('analytics', 'local_ace'));

// Filters bar.
$filterurl = new moodle_url('/local/ace/analytics.php', ['courseid' => $courseid]);
echo '<form method="get" action="' . $filterurl . '" class="card card-body mb-3">';
echo '<input type="hidden" name="courseid" value="' . $courseid . '">';
echo '<div class="row align-items-end">';
echo '<div class="col-md-3 mb-2">';
echo '<label class="form-label small">' . get_string('search') . '</label>';
echo '<input type="text" name="fname" class="form-control form-control-sm" value="' .
    s($filtername) . '" placeholder="' . get_string('fullname') . '">';
echo '</div>';
echo '<div class="col-md-2 mb-2">';
echo '<label class="form-label small">' . get_string('analytics_dropout', 'local_ace') . '</label>';
echo '<select name="frisk" class="form-control form-control-sm">';
echo '<option value="">' . get_string('all') . '</option>';
echo '<option value="low"' . ($filterrisk === 'low' ? ' selected' : '') . '>' .
    get_string('dropoutrisk_low', 'local_ace') . '</option>';
echo '<option value="medium"' . ($filterrisk === 'medium' ? ' selected' : '') . '>' .
    get_string('dropoutrisk_medium', 'local_ace') . '</option>';
echo '<option value="high"' . ($filterrisk === 'high' ? ' selected' : '') . '>' .
    get_string('dropoutrisk_high', 'local_ace') . '</option>';
echo '<option value="critical"' . ($filterrisk === 'critical' ? ' selected' : '') . '>' .
    get_string('dropoutrisk_critical', 'local_ace') . '</option>';
echo '</select></div>';
echo '<div class="col-md-2 mb-2">';
echo '<label class="form-label small">' . get_string('analytics_trend', 'local_ace') . '</label>';
echo '<select name="ftrend" class="form-control form-control-sm">';
echo '<option value="">' . get_string('all') . '</option>';
echo '<option value="improving"' . ($filtertrend === 'improving' ? ' selected' : '') . '>' .
    get_string('engagementtrend_improving', 'local_ace') . '</option>';
echo '<option value="stable"' . ($filtertrend === 'stable' ? ' selected' : '') . '>' .
    get_string('engagementtrend_stable', 'local_ace') . '</option>';
echo '<option value="declining"' . ($filtertrend === 'declining' ? ' selected' : '') . '>' .
    get_string('engagementtrend_declining', 'local_ace') . '</option>';
echo '</select></div>';
echo '<div class="col-md-2 mb-2">';
echo '<label class="form-label small">' . get_string('engagementscore', 'local_ace') . '</label>';
echo '<select name="fengage" class="form-control form-control-sm">';
echo '<option value="">' . get_string('all') . '</option>';
echo '<option value="low"' . ($filterengage === 'low' ? ' selected' : '') . '>0-40%</option>';
echo '<option value="medium"' . ($filterengage === 'medium' ? ' selected' : '') . '>40-70%</option>';
echo '<option value="high"' . ($filterengage === 'high' ? ' selected' : '') . '>70-100%</option>';
echo '</select></div>';
echo '<div class="col-md-3 mb-2">';
echo '<button type="submit" class="btn btn-primary btn-sm me-1">' . get_string('search') . '</button>';
echo '<a href="' . $filterurl . '" class="btn btn-outline-secondary btn-sm">' . get_string('reset') . '</a>';
echo '</div>';
echo '</div></form>';

// Get enrolled users with the viewdashboard capability (students).
$namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false);
$enrolledusers = get_enrolled_users(
    $context,
    'local/ace:viewdashboard',
    0,
    'u.id, u.email, ' . $namefields->selects,
    'u.lastname ASC, u.firstname ASC'
);

if (empty($enrolledusers)) {
    echo html_writer::tag('p', get_string('nousers', 'moodle'), ['class' => 'alert alert-info']);
    echo $OUTPUT->footer();
    die;
}

// Initialize the dropout predictor.
$predictor = new \local_ace\analytics\dropout_predictor();

// Build the analytics data table with all dropout risk sub-factors.
$table = new html_table();
$table->attributes['class'] = 'table table-striped table-hover local-ace-analytics-table';
$table->head = [
    get_string('fullname'),
    get_string('analytics_lastaccess', 'local_ace'),
    // Engagement sub-scores.
    get_string('engagementscore', 'local_ace'),
    get_string('analytics_completion', 'local_ace'),
    get_string('analytics_timeliness', 'local_ace'),
    get_string('analytics_participation', 'local_ace'),
    get_string('analytics_consistency', 'local_ace'),
    get_string('analytics_trend', 'local_ace'),
    // Mastery sub-scores.
    get_string('masteryscore', 'local_ace'),
    get_string('analytics_grades', 'local_ace'),
    get_string('analytics_improvement', 'local_ace'),
    get_string('analytics_breadth', 'local_ace'),
    // Summary.
    get_string('completedquests', 'local_ace'),
    get_string('analytics_dropout', 'local_ace'),
];
$tablealign = ['left', 'center', 'center', 'center', 'center', 'center', 'center', 'center',
                 'center', 'center', 'center', 'center', 'center', 'center'];

// Add AI analysis column if Pro AI is available.
if ($proaiavailable) {
    $table->head[] = get_string('ai_analysis_column', 'local_ace_pro');
    $tablealign[] = 'center';
}
$table->align = $tablealign;

$filteredcount = 0;
$totalcount = count($enrolledusers);

foreach ($enrolledusers as $user) {
    // Load engagement data (includes sub-scores and trend).
    $engagement = $DB->get_record('local_ace_engagement', [
        'userid' => $user->id,
        'courseid' => $courseid,
    ]);
    $engagementscore = !empty($engagement) ? round((float) $engagement->score) : 0;
    $completionscore = !empty($engagement) ? round((float) $engagement->completion_score) : 0;
    $timelinessscore = !empty($engagement) ? round((float) $engagement->timeliness_score) : 0;
    $participationscore = !empty($engagement) ? round((float) $engagement->participation_score) : 0;
    $consistencyscore = !empty($engagement) ? round((float) $engagement->consistency_score) : 0;
    $trend = !empty($engagement) ? $engagement->trend : '-';

    // Apply filters before building row.

    // Name filter.
    if ($filtername !== '') {
        $fullnamecheck = fullname($user);
        if (stripos($fullnamecheck, $filtername) === false) {
            continue;
        }
    }

    // Trend filter.
    if ($filtertrend !== '' && $trend !== $filtertrend) {
        continue;
    }

    // Engagement range filter.
    if ($filterengage !== '') {
        if ($filterengage === 'low' && $engagementscore >= 40) {
            continue;
        }
        if ($filterengage === 'medium' && ($engagementscore < 40 || $engagementscore >= 70)) {
            continue;
        }
        if ($filterengage === 'high' && $engagementscore < 70) {
            continue;
        }
    }

    // Trend badge.
    $trendclass = 'badge-secondary';
    $trendlabel = '-';
    if ($trend === 'improving') {
        $trendclass = 'badge-success';
        $trendlabel = get_string('engagementtrend_improving', 'local_ace');
    } else if ($trend === 'stable') {
        $trendclass = 'badge-info';
        $trendlabel = get_string('engagementtrend_stable', 'local_ace');
    } else if ($trend === 'declining') {
        $trendclass = 'badge-danger';
        $trendlabel = get_string('engagementtrend_declining', 'local_ace');
    }
    $trendhtml = html_writer::span($trendlabel, 'badge ' . $trendclass);

    // Load mastery data (includes sub-scores).
    $mastery = $DB->get_record('local_ace_mastery', [
        'userid' => $user->id,
        'courseid' => $courseid,
    ]);
    $masteryscore = !empty($mastery) ? round((float) $mastery->score) : 0;
    $gradescore = !empty($mastery) ? round((float) $mastery->grade_score) : 0;
    $improvementscore = !empty($mastery) ? round((float) $mastery->improvement_score) : 0;
    $breadthscore = !empty($mastery) ? round((float) $mastery->breadth_score) : 0;

    // Last course access.
    $lastaccess = $DB->get_field('user_lastaccess', 'timeaccess', [
        'userid' => $user->id,
        'courseid' => $courseid,
    ]);
    if (!empty($lastaccess)) {
        $daysinactive = (int) floor((time() - $lastaccess) / DAYSECS);
        if ($daysinactive === 0) {
            $lastaccesshtml = html_writer::span(get_string('today'), 'badge badge-success');
        } else if ($daysinactive <= 3) {
            $lastaccesshtml = html_writer::span(get_string('numdays', 'moodle', $daysinactive), 'badge badge-success');
        } else if ($daysinactive <= 7) {
            $lastaccesshtml = html_writer::span(get_string('numdays', 'moodle', $daysinactive), 'badge badge-warning');
        } else {
            $lastaccesshtml = html_writer::span(get_string('numdays', 'moodle', $daysinactive), 'badge badge-danger');
        }
    } else {
        $lastaccesshtml = html_writer::span(get_string('never'), 'badge badge-danger');
    }

    // Count completed quests.
    $completedcount = $DB->count_records('local_ace_quests', [
        'userid' => $user->id,
        'courseid' => $courseid,
        'status' => 'completed',
    ]);

    // Calculate dropout risk (real-time).
    $risk = $predictor->predict($user->id, $courseid);
    $risklabel = $predictor->get_risk_label($risk);
    $riskpercent = round($risk * 100);

    $riskclass = 'badge-success';
    if ($risk >= 0.75) {
        $riskclass = 'badge-danger';
    } else if ($risk >= 0.5) {
        $riskclass = 'badge-warning';
    } else if ($risk >= 0.25) {
        $riskclass = 'badge-info';
    }

    // Risk level filter.
    if ($filterrisk !== '') {
        $riskcat = 'low';
        if ($risk >= 0.75) {
            $riskcat = 'critical';
        } else if ($risk >= 0.5) {
            $riskcat = 'high';
        } else if ($risk >= 0.25) {
            $riskcat = 'medium';
        }
        if ($riskcat !== $filterrisk) {
            continue;
        }
    }

    $filteredcount++;

    $fullname = fullname($user);
    $riskhtml = html_writer::span($risklabel . ' (' . $riskpercent . '%)', 'badge ' . $riskclass);

    $rowdata = [
        $fullname,
        $lastaccesshtml,
        $engagementscore . '%',
        $completionscore . '%',
        $timelinessscore . '%',
        $participationscore . '%',
        $consistencyscore . '%',
        $trendhtml,
        $masteryscore . '%',
        $gradescore . '%',
        $improvementscore . '%',
        $breadthscore . '%',
        $completedcount,
        $riskhtml,
    ];

    // Add AI analysis button if Pro AI is available.
    if ($proaiavailable) {
        $aibtnattrs = [
            'class' => 'btn btn-sm btn-outline-primary',
            'data-action' => 'view-ai-analysis',
            'data-userid' => $user->id,
            'data-studentname' => s($fullname),
            'title' => get_string('ai_analysis_view', 'local_ace_pro'),
        ];
        $rowdata[] = html_writer::tag(
            'button',
            '<i class="fa fa-robot"></i> ' . get_string('ai_analysis_column', 'local_ace_pro'),
            $aibtnattrs
        );
    }

    $table->data[] = $rowdata;
}

if ($filteredcount === 0) {
    echo html_writer::tag('p', get_string('nousers', 'moodle'), ['class' => 'alert alert-info']);
} else {
    if ($filtername !== '' || $filterrisk !== '' || $filtertrend !== '' || $filterengage !== '') {
        echo html_writer::tag(
            'p',
            $filteredcount . ' / ' . $totalcount . ' ' . get_string('users'),
            ['class' => 'text-muted small']
        );
    }
    echo html_writer::table($table);
}

// Legend explaining the dropout risk factors.
echo html_writer::start_tag('div', ['class' => 'card mt-3']);
echo html_writer::start_tag('div', ['class' => 'card-body']);
echo html_writer::tag('h5', get_string('analytics_dropout_factors', 'local_ace'), ['class' => 'card-title']);
echo html_writer::tag('p', get_string('analytics_dropout_factors_desc', 'local_ace'));
echo html_writer::start_tag('ul');
echo html_writer::tag('li', get_string('analytics_factor_inactivity', 'local_ace'));
echo html_writer::tag('li', get_string('analytics_factor_engagement', 'local_ace'));
echo html_writer::tag('li', get_string('analytics_factor_completion', 'local_ace'));
echo html_writer::tag('li', get_string('analytics_factor_grades', 'local_ace'));
echo html_writer::end_tag('ul');
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

// Initialize AI analysis JS module if Pro AI is available.
if ($proaiavailable) {
    $PAGE->requires->js_call_amd('local_ace_pro/ai_analysis', 'init', [$courseid]);
}

echo $OUTPUT->footer();
