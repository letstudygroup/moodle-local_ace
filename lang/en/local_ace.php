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
 * Language strings for local_ace.
 *
 * @package    local_ace
 * @copyright  2026 LetStudy Group
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['ace:exportdata'] = 'Export analytics data';
$string['ace:managelicense'] = 'Manage ACE license';
$string['ace:managequests'] = 'Manage quests and missions';
$string['ace:manageteamxp'] = 'Manage team XP pools';
$string['ace:managetemplates'] = 'Manage mission templates';
$string['ace:viewanalytics'] = 'View ACE analytics';
$string['ace:viewdashboard'] = 'View ACE dashboard';
$string['ace:viewinstitutionanalytics'] = 'View institution analytics';
$string['ace:viewteamxp'] = 'View team XP';
$string['activequests'] = 'Active quests';
$string['adaptivedifficulty'] = 'Adaptive difficulty';
$string['adaptivedifficulty_desc'] = 'Enable adaptive difficulty adjustment for quests and challenges.';
$string['analytics'] = 'Analytics';
$string['analytics_breadth'] = 'Breadth';
$string['analytics_completion'] = 'Completion';
$string['analytics_consistency'] = 'Consistency';
$string['analytics_dropout'] = 'Dropout risk';
$string['analytics_dropout_factors'] = 'Dropout risk factors';
$string['analytics_dropout_factors_desc'] = 'The dropout risk score is calculated using the following weighted factors:';
$string['analytics_engagement'] = 'Engagement trends';
$string['analytics_export'] = 'Export data';
$string['analytics_factor_completion'] = '<strong>Completion gap (20%)</strong>: Difference between actual completion rate and expected rate based on course timeline.';
$string['analytics_factor_engagement'] = '<strong>Engagement decline (30%)</strong>: Engagement trend (improving/stable/declining) combined with overall engagement score.';
$string['analytics_factor_grades'] = '<strong>Grade trend (10%)</strong>: Whether mastery scores are declining compared to previous snapshots.';
$string['analytics_factor_inactivity'] = '<strong>Inactivity (40%)</strong>: Days since last course access. 30+ days = maximum risk.';
$string['analytics_grades'] = 'Grades';
$string['analytics_improvement'] = 'Improvement';
$string['analytics_institution'] = 'Institution dashboard';
$string['analytics_lastaccess'] = 'Last access';
$string['analytics_mastery'] = 'Mastery evolution';
$string['analytics_participation'] = 'Participation';
$string['analytics_timeliness'] = 'Timeliness';
$string['analytics_trend'] = 'Trend';
$string['completedquests'] = 'Completed quests';
$string['completequest'] = 'Complete quest';
$string['content_suggestions_generated'] = 'Generated:';
$string['content_suggestions_search'] = 'Search';
$string['content_suggestions_student_title'] = 'Suggested resources for you';
$string['content_suggestions_visit'] = 'Visit resource';
$string['courseconfig'] = 'ACE Settings';
$string['courseconfig_enable'] = 'Enable ACE for this course';
$string['courseconfig_enable_desc'] = 'When enabled, ACE gamification features (quests, XP, levels, engagement scores) will be active for students in this course.';
$string['courseconfig_globalmode'] = 'ACE is currently in global mode — it is active for all courses. This setting only applies in per-course mode.';
$string['courseconfig_saved'] = 'ACE settings saved.';
$string['createmission'] = 'Create mission template';
$string['dailyquestcount'] = 'Daily quests per user';
$string['dailyquestcount_desc'] = 'Number of quests generated daily for each user.';
$string['dailyquests'] = 'Daily quests';
$string['dashboard'] = 'ACE Dashboard';
$string['dashboard_desc'] = 'Your adaptive challenge engine dashboard.';
$string['deletemission'] = 'Delete mission template';
$string['dropoutrisk_critical'] = 'Critical risk';
$string['dropoutrisk_high'] = 'High risk';
$string['dropoutrisk_low'] = 'Low risk';
$string['dropoutrisk_medium'] = 'Medium risk';
$string['editmission'] = 'Edit mission template';
$string['enablemode'] = 'Enable mode';
$string['enablemode_desc'] = 'Global: active for all courses. Per course: enabled from each course\'s settings page.';
$string['enablemode_global'] = 'Global (all courses)';
$string['enablemode_percourse'] = 'Per course (from course settings)';
$string['enableplugin'] = 'Enable ACE';
$string['enableplugin_desc'] = 'Enable or disable the Adaptive Challenge Engine across the site.';
$string['engagementscore'] = 'Engagement score';
$string['engagementtrend_declining'] = 'Declining';
$string['engagementtrend_improving'] = 'Improving';
$string['engagementtrend_stable'] = 'Stable';
$string['engagementweight_completion'] = 'Engagement weight: Activity completion';
$string['engagementweight_completion_desc'] = 'Weight for activity completion in engagement score (0-100).';
$string['engagementweight_consistency'] = 'Engagement weight: Consistency';
$string['engagementweight_consistency_desc'] = 'Weight for login consistency in engagement score (0-100).';
$string['engagementweight_participation'] = 'Engagement weight: Participation';
$string['engagementweight_participation_desc'] = 'Weight for forum/activity participation in engagement score (0-100).';
$string['engagementweight_timeliness'] = 'Engagement weight: Timeliness';
$string['engagementweight_timeliness_desc'] = 'Weight for timely submissions in engagement score (0-100).';
$string['error_ace_disabled_for_course'] = 'ACE is not enabled for this course.';
$string['error_licenserequired'] = 'A valid ACE Pro license is required for this feature.';
$string['error_nocourse'] = 'Course not found.';
$string['error_nopermission'] = 'You do not have permission to access this page.';
$string['error_questalreadycompleted'] = 'This quest has already been completed.';
$string['error_questnotfound'] = 'Quest not found.';
$string['event_engagement_updated'] = 'Engagement score updated';
$string['event_level_up'] = 'Level up achieved';
$string['event_mastery_updated'] = 'Mastery score updated';
$string['event_quest_completed'] = 'Quest completed';
$string['event_xp_earned'] = 'XP earned';
$string['gotoactivity'] = 'Go to activity';
$string['learning_path_estimated'] = 'Estimated time:';
$string['learning_path_generated'] = 'Generated:';
$string['learning_path_priority_high'] = 'High priority';
$string['learning_path_priority_low'] = 'Low priority';
$string['learning_path_priority_medium'] = 'Medium priority';
$string['learning_path_student_title'] = 'Your personalised learning path';
$string['learning_path_why'] = 'Why:';
$string['level'] = 'Level';
$string['licensekey'] = 'License key';
$string['licensekey_desc'] = 'Enter your ACE Pro license key to unlock advanced features.';
$string['licenseserver'] = 'License server URL';
$string['licenseserver_desc'] = 'URL of the ACE license verification server.';
$string['licensestatus'] = 'License status';
$string['licensestatus_active'] = 'Active — Pro features enabled';
$string['licensestatus_expired'] = 'Expired — Pro features in grace period';
$string['licensestatus_grace'] = 'Grace period active ({$a} days remaining)';
$string['licensestatus_inactive'] = 'Inactive';
$string['licensestatus_invalid'] = 'Invalid — Pro features disabled';
$string['licensestatus_none'] = 'No license key configured';
$string['licensestatus_revoked'] = 'Revoked — Pro features disabled';
$string['licensestatus_valid'] = 'Valid — Pro features enabled';
$string['masteryscore'] = 'Mastery score';
$string['masteryweight_breadth'] = 'Mastery weight: Breadth';
$string['masteryweight_breadth_desc'] = 'Weight for breadth of activity completion in mastery score (0-100).';
$string['masteryweight_grades'] = 'Mastery weight: Grades';
$string['masteryweight_grades_desc'] = 'Weight for grade performance in mastery score (0-100).';
$string['masteryweight_improvement'] = 'Mastery weight: Improvement';
$string['masteryweight_improvement_desc'] = 'Weight for grade improvement trend in mastery score (0-100).';
$string['messageprovider:levelup'] = 'Level up achieved';
$string['messageprovider:questcompleted'] = 'Quest completed';
$string['messageprovider:questgenerated'] = 'New quest generated';
$string['messageprovider:recommendation'] = 'Activity recommendations';
$string['missiontemplates'] = 'Mission templates';
$string['myquests'] = 'My Quests';
$string['myquests_desc'] = 'View all your quests across all courses';
$string['noactivequests'] = 'No active quests. Check back tomorrow!';
$string['noquestsyet'] = 'No quests yet. Quests are generated daily for your courses.';
$string['notification_levelup_body'] = 'Congratulations! You reached <strong>Level {$a->level}</strong> in <strong>{$a->coursename}</strong>! Keep completing quests to level up even more.';
$string['notification_levelup_subject'] = 'You reached level {$a}!';
$string['notification_newquest_body'] = 'You have a new quest in <strong>{$a->coursename}</strong>: <strong>{$a->title}</strong><br>Reward: +{$a->xpreward} XP<br>Complete it before it expires!';
$string['notification_newquest_subject'] = 'New quest: {$a}';
$string['notification_questcompleted_body'] = 'Congratulations! You completed a quest in <strong>{$a->coursename}</strong> and earned <strong>+{$a->xp} XP</strong>.<br>Total XP: {$a->totalxp} | Level: {$a->level}';
$string['notification_questcompleted_subject'] = 'Quest completed! +{$a} XP';
$string['notification_recommendation_body'] = 'You have <strong>{$a->count}</strong> new activity recommendations in <strong>{$a->coursename}</strong>.<br>Check your ACE dashboard to see personalised suggestions for improving your progress!';
$string['notification_recommendation_subject'] = '{$a} new recommendations for you';
$string['pluginname'] = 'Adaptive Challenge Engine (ACE)';
$string['pro_banner_title'] = 'ACE Pro';
$string['pro_feature_ai_adaptation'] = 'AI-powered quest adaptation';
$string['pro_feature_dropout'] = 'AI dropout risk analysis';
$string['pro_feature_team_xp'] = 'Team XP pools & leaderboards';
$string['pro_feature_grade_insights'] = 'Grade insights';
$string['pro_feature_content_suggestions'] = 'AI content suggestions';
$string['pro_feature_peer_matching'] = 'AI peer matching';
$string['pro_feature_interventions'] = 'Teacher intervention dashboard';
$string['pro_feature_mission_templates'] = 'Mission templates';
$string['pro_feature_institution_analytics'] = 'Institution-wide analytics';
$string['pro_feature_course_recommendations'] = 'AI course recommendations';
$string['pro_feature_activity_recommendations'] = 'AI activity recommendations';
$string['pro_feature_advanced_reporting'] = 'Advanced reporting';
$string['pro_early_access_title'] = 'Early Access — Limited Offer';
$string['pro_early_access_desc'] = 'Be one of the first to get ACE Pro at the exclusive early access price.';
$string['pro_early_access_cta'] = 'Reserve Your Spot';
$string['privacy:metadata:courseid'] = 'The ID of the course.';
$string['privacy:metadata:local_ace_analytics'] = 'Stores analytics snapshot data.';
$string['privacy:metadata:local_ace_engagement'] = 'Stores engagement score data for users.';
$string['privacy:metadata:local_ace_mastery'] = 'Stores mastery score data for users.';
$string['privacy:metadata:local_ace_quests'] = 'Stores quest assignment and completion data.';
$string['privacy:metadata:local_ace_team_xp'] = 'Stores team XP contribution data.';
$string['privacy:metadata:local_ace_xp'] = 'Stores XP and level data for users.';
$string['privacy:metadata:score'] = 'The computed score value.';
$string['privacy:metadata:timecreated'] = 'The time the record was created.';
$string['privacy:metadata:timemodified'] = 'The time the record was last modified.';
$string['privacy:metadata:userid'] = 'The ID of the user.';
$string['privacy:metadata:xp'] = 'The XP amount.';
$string['pro_badge'] = 'Pro';
$string['quest_autocompletable'] = 'Auto-completes';
$string['quest_recommended'] = 'Recommended for you';
$string['questcompleted'] = 'Quest completed! +{$a} XP';
$string['questcompleted_badge'] = 'Completed';
$string['questexpired'] = 'This quest has expired.';
$string['questprogress'] = 'Quest progress';
$string['questtype_activity'] = 'Complete activity';
$string['questtype_forum'] = 'Forum participation';
$string['questtype_grade'] = 'Grade target';
$string['questtype_login'] = 'Daily login streak';
$string['questtype_quiz'] = 'Quiz challenge';
$string['questtype_resource'] = 'Resource exploration';
$string['rec_needs_improvement'] = 'Needs improvement';
$string['rec_reason_improve'] = 'Needs improvement based on your grades';
$string['rec_reason_next'] = 'Next up in your course';
$string['recommendations_title'] = 'Recommended for you';
$string['settings_general'] = 'General settings';
$string['settings_general_desc'] = 'Configure the Adaptive Challenge Engine general settings.';
$string['settings_license'] = 'License';
$string['settings_license_desc'] = 'Configure ACE license for Pro features.';
$string['settings_pro_install_hint'] = 'Install ACE Pro for advanced features like AI-driven adaptation, team XP, institution analytics, and more.';
$string['task_calculate_scores'] = 'Calculate engagement and mastery scores';
$string['task_cleanup_expired'] = 'Clean up expired quests';
$string['task_generate_daily_quests'] = 'Generate daily quests';
$string['task_sync_license'] = 'Sync license status';
$string['teamxp'] = 'Team XP pools';
$string['teamxp_contribution'] = 'Your contribution';
$string['teamxp_desc'] = 'Enable team-based XP pooling and team leaderboards.';
$string['teamxp_leaderboard'] = 'Team leaderboard';
$string['teamxp_pool'] = 'Team XP pool';
$string['totalacrossallcourses'] = 'Total across all courses';
$string['totalxp'] = 'Total XP';
$string['xp_label'] = 'XP';
$string['xp_per_activity'] = 'XP per activity completion';
$string['xp_per_activity_desc'] = 'Base XP awarded for completing a course activity.';
$string['xp_per_quest'] = 'XP per quest completion';
$string['xp_per_quest_desc'] = 'Base XP awarded for completing a quest.';
