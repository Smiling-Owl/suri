<?php
define('CLI_SCRIPT', true);
require_once('../../config.php');
require_once('lib.php');

global $DB, $USER;
$params = [];
$sql = "SELECT 1 AS id, COUNT(DISTINCT u.id) AS totalusers
        FROM {user} u
        LEFT JOIN {user_enrolments} ue ON ue.userid = u.id
        LEFT JOIN {enrol} e ON e.id = ue.enrolid
        LEFT JOIN {course} c ON c.id = e.courseid
        WHERE u.deleted = 0 AND u.suspended = 0 AND u.id <> 1";

$sql = report_suri_apply_role_scope($sql, $params, 2); // Force admin id 2
$sql = report_suri_apply_dynamic_filters($sql, $params, [], ['u', 'c']);

echo "SQL: " . $sql . "\n";
echo "PARAMS: " . print_r($params, true) . "\n";

try {
    $result = $DB->get_record_sql($sql, $params);
    echo "RESULT: " . print_r($result, true) . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
