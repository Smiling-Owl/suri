<?php
define('CLI_SCRIPT', true);
require('../../config.php');

$USER = $DB->get_record('user', ['id' => 2]); // Admin user usually
\core\session\manager::set_user($USER);

$_GET['view'] = 'course_detail';
$_GET['courseid'] = 8;
$_POST = [];
$_REQUEST = $_GET;

ob_start();
include('index.php');
$html = ob_get_clean();

file_put_contents('test_output.html', $html);
echo "HTML length: " . strlen($html) . "\n";
