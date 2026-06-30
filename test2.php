<?php
define('CLI_SCRIPT', true);
require('../../config.php');
require('lib.php');

$USER = $DB->get_record('user', ['id' => 2]); // Admin user usually
\core\session\manager::set_user($USER);

$courseid = 8;
$stats = report_suri_get_single_course_summary($courseid);
$course_users = report_suri_get_single_course_users($courseid);

$attendance_trends = report_suri_get_attendance_trends_per_course();

$attendance_trend_val = 0;
if (!empty($attendance_trends)) {
    foreach ($attendance_trends as $att) {
        if ($att->coursename === $stats['coursename']) {
            $attendance_trend_val = $att->active_students > 0 ? $att->total_attendance_days / $att->active_students : 0;
            break;
        }
    }
}

echo "attendance_trend_val: $attendance_trend_val\n";

$rate = $stats['total_students'] > 0 ? ($stats['completed_students'] / $stats['total_students']) * 100 : 0;
$hours = floor($stats['avg_time_seconds'] / 3600);
$minutes = floor(($stats['avg_time_seconds'] % 3600) / 60);

echo "rate: $rate, hours: $hours, mins: $minutes\n";

foreach ($course_users as $u) {
    $fullname = fullname((object)['firstname' => $u->firstname, 'lastname' => $u->lastname]);
    echo "user: $fullname\n";
}
echo "Done\n";
