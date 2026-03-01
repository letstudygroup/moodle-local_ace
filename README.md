# ACE - Adaptive Challenge Engine for Moodle

ACE is a free Moodle local plugin that brings AI-powered learning analytics, adaptive engagement scoring, mastery tracking, and gamification quests to your courses.

## Features

- **Engagement Scoring** - Automatically tracks and scores student engagement based on activity completion, timeliness, participation, and consistency. Configurable weight system.
- **Mastery Tracking** - Calculates mastery scores from grades, improvement trends, and activity breadth across course modules.
- **Dropout Prediction** - Identifies students at risk of dropping out using analytics snapshots.
- **Gamification Quests** - Daily learning quests that reward students with XP for completing course activities.
- **XP & Levels** - Experience points system that motivates students through progression.
- **Analytics Dashboard** - Per-course analytics page showing engagement and mastery metrics for teachers and managers.
- **Adaptive Engine** - Provides adaptive learning recommendations based on student performance.
- **Notifications** - Configurable notifications for quest availability, level-ups, and engagement milestones.
- **Privacy Compliant** - Full GDPR/privacy provider implementation for data export and deletion.
- **Multi-language** - Includes English and Greek language packs.

## Requirements

- Moodle 4.5 or later
- PHP 8.1 or later

## Installation

1. Download the plugin and extract it to `/local/aceengine/` in your Moodle installation.
2. Visit **Site administration > Notifications** to complete the installation.
3. Configure the plugin at **Site administration > Plugins > Local plugins > ACE**.

## Configuration

### Enable Mode
- **Global** - ACE is enabled for all courses on the site.
- **Per-course** - Administrators can enable/disable ACE for individual courses.

### Engagement Weights
Customize how engagement is calculated by adjusting weights for:
- Activity completion (default: 30%)
- Timeliness (default: 25%)
- Participation (default: 25%)
- Consistency (default: 20%)

### Mastery Weights
- Grades (default: 50%)
- Improvement (default: 25%)
- Breadth (default: 25%)

### XP Settings
- XP per quest completed (default: 50)
- XP per activity (default: 10)

## Companion Plugins

- [ACE Dashboard Block](https://plugins.letstudy.gr/moodle-block_aceengine) - Course block displaying engagement and mastery scores.
- [ACE Quests Block](https://plugins.letstudy.gr/moodle-block_ace_quests) - Course block showing daily quests and XP progress.

## Scheduled Tasks

The plugin includes several scheduled tasks:
- **Calculate scores** - Recalculates engagement and mastery scores for all users.
- **Generate daily quests** - Creates new daily quests for enrolled students.
- **Sync license** - Validates the license key with the ACE License Server.
- **Cleanup expired** - Removes expired data.

## ACE Pro

ACE Pro is a premium extension that adds advanced features including:
- AI-powered content adaptation and recommendations
- Advanced dropout prediction models
- Institution-wide analytics dashboard
- Team XP and collaborative quests
- Grade insights and peer matching
- Automated interventions

Learn more at [letstudy.gr](https://letstudy.gr)

## License

This plugin is licensed under the [GNU GPL v3 or later](https://www.gnu.org/copyleft/gpl.html).

## Copyright

2026 Letstudy Group
