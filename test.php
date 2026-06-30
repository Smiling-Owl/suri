<?php
define('CLI_SCRIPT', true);
require('../../config.php');
require('lib.php');

$USER = $DB->get_record('user', ['id' => 2]); // Admin user usually
\core\session\manager::set_user($USER);

$courses = $DB->get_records('course', [], '', 'id, fullname');
$real_course = null;
foreach($courses as $c) {
    if ($c->id != 1) { // Skip site course
        $real_course = $c;
        break;
    }
}

if (!$real_course) {
    echo "No courses found!\n";
    exit;
}

$courseid = $real_course->id;
echo "Testing course ID: $courseid \n";

try { 
    $res = report_suri_get_single_course_summary($courseid); 
    echo "Summary Success: \n";
    var_dump($res);
} catch(Exception $e) { 
    echo "Summary Error: " . $e->getMessage() . "\n"; 
}

try { 
    $res = report_suri_get_single_course_users($courseid); 
    echo "Users Success: \n"; 
    echo "User count: " . count($res) . "\n";
} catch(Exception $e) { 
    echo "Users Error: " . $e->getMessage() . "\n"; 
}
