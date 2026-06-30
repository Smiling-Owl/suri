<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Helper function to apply role scoping for non-admins.
 */
function report_suri_apply_role_scope($sql, &$params, $userid) {
    $context = context_system::instance();
    
    // Explicit Course Filter for Course Insights
    $courseid = optional_param('courseid', 0, PARAM_INT);
    if ($courseid > 0) {
        $sql .= " AND c.id = :req_courseid ";
        $params['req_courseid'] = $courseid;
    }

    if (has_capability('report/suri:viewall', $context, $userid)) {
        return $sql;
    }
    
    // Added a leading space for safety during concatenation
    $scopingsql = " AND c.id IN (
        SELECT e.courseid
          FROM {role_assignments} ra
          JOIN {context} ctx ON ra.contextid = ctx.id
          JOIN {enrol} e ON ctx.instanceid = e.courseid
         WHERE ra.userid = :scope_userid 
           AND ra.roleid IN (SELECT id FROM {role} WHERE shortname IN ('teacher', 'editingteacher'))
           AND ctx.contextlevel = 50
    )";
    $params['scope_userid'] = $userid;
    return $sql . $scopingsql;
}

/**
 * Helper function to apply dynamic user/course filters.
 */
function report_suri_apply_dynamic_filters($sql, &$params, $filters, $available_aliases = []) {
    if (empty($filters) || !is_array($filters)) {
        return $sql;
    }
    
    $allowed_fields = [
        'user_department' => ['col' => 'department', 'prefix' => 'user_', 'alias' => 'u'],
        'user_institution' => ['col' => 'institution', 'prefix' => 'user_', 'alias' => 'u'],
        'user_city' => ['col' => 'city', 'prefix' => 'user_', 'alias' => 'u'],
        'user_email' => ['col' => 'email', 'prefix' => 'user_', 'alias' => 'u'],
        'user_country' => ['col' => 'country', 'prefix' => 'user_', 'alias' => 'u'],
        'course_name' => ['col' => 'fullname', 'prefix' => 'course_', 'alias' => 'c'],
        'role_name' => ['col' => 'shortname', 'prefix' => 'role_', 'alias' => 'r']
    ];
    
    $filtersql = "";
    $fcount = count($params); // Unique param names
    
    foreach ($filters as $f) {
        if (empty($f['field']) || empty($f['op']) || !isset($f['val'])) continue;
        
        $field_key = $f['field'];
        if (!array_key_exists($field_key, $allowed_fields)) continue;
        
        $conf = $allowed_fields[$field_key];
        $db_col = $conf['col'];
        $req_alias = $conf['alias'];
        
        if (!in_array($req_alias, $available_aliases)) {
            // Table is not available in this query, skip applying this filter
            continue;
        }
        
        $alias = $req_alias . '.';
        $pname = 'dfilt_' . $fcount;
        $val = $f['val'];
        
        if ($f['op'] === 'eq') {
            $filtersql .= " AND {$alias}{$db_col} = :{$pname}";
            $params[$pname] = $val;
        } elseif ($f['op'] === 'contains') {
            $filtersql .= " AND " . $GLOBALS['DB']->sql_like("{$alias}{$db_col}", ":{$pname}", false, false);
            $params[$pname] = '%' . $GLOBALS['DB']->sql_like_escape($val) . '%';
        }
        $fcount++;
    }
    
    return $sql . $filtersql;
}

/**
 * Helper function to apply dynamic sorting safely.
 */
function report_suri_apply_sorting($sql, $sort, $dir, $allowed_columns) {
    if (empty($sort) || !array_key_exists($sort, $allowed_columns)) {
        return $sql;
    }
    
    $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
    $db_col = $allowed_columns[$sort];
    
    // Replace existing ORDER BY or append one
    if (preg_match('/ORDER BY/i', $sql)) {
        $sql = preg_replace('/ORDER BY\s+.*?$/is', "ORDER BY {$db_col} {$dir}", $sql);
    } else {
        $sql .= " ORDER BY {$db_col} {$dir}";
    }
    
    return $sql;
}

/**
 * 1. Counts unique students enrolled per course.
 */
function report_suri_get_student_count_per_course($year = null, $filters = []) {
    global $DB, $USER;
    $params = [];

    // Added u.deleted = 0 AND u.suspended = 0
    $sql = "SELECT c.id, c.fullname AS coursename, COUNT(DISTINCT u.id) AS studentcount
            FROM {role_assignments} ra
            JOIN {context} ctx ON ra.contextid = ctx.id AND ctx.contextlevel = 50
            JOIN {user} u ON u.id = ra.userid
            JOIN {course} c on c.id = ctx.instanceid
            WHERE ra.roleid = (SELECT id FROM {role} WHERE shortname = 'student')
              AND u.deleted = 0 AND u.suspended = 0";

    if (!empty($year)) {
        $sql .= " AND c.fullname LIKE :year";
        $params['year'] = '%' . $year . '%';
    }

    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['u', 'c']);
    $sql .= " GROUP BY c.id, c.fullname ORDER BY studentcount DESC";

    return $DB->get_records_sql($sql, $params);
}

/**
 * 2. Fetches the site user directory.
 */
function report_suri_get_user_enrollment_directory($filters = [], $sort = '', $dir = ''){
    global $DB, $USER;
    $params = [];
    
    // Added u.deleted = 0 AND u.suspended = 0
    $sql = "SELECT ra.id AS unique_mapping_id
            , u.id AS userid
            , u.firstname
            , u.lastname
            , u.email
            , u.department
            , u.institution
            , u.city
            , u.phone1
            , u.lastaccess
            , c.id AS courseid
            , c.fullname AS coursename
            , r.shortname AS role
        FROM {user} u
        JOIN {role_assignments} ra ON ra.userid = u.id
        JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
        JOIN {course} c ON c.id = ctx.instanceid
        JOIN {role} r ON r.id = ra.roleid
        WHERE r.shortname IN ('student', 'teacher', 'editingteacher')
          AND u.deleted = 0 AND u.suspended = 0";

    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['u', 'c']);
    
    $allowed_sorts = [
        'user_firstname' => 'u.firstname',
        'user_lastname' => 'u.lastname',
        'course_name' => 'c.fullname',
        'role' => 'r.shortname',
        'lastaccess' => 'u.lastaccess'
    ];
    $sql = report_suri_apply_sorting($sql, $sort, $dir, $allowed_sorts);

    return $DB->get_records_sql($sql, $params);
} 

/**
 * 3. Fetches users who have logged into the platform within the last 7 days.
 */
function report_suri_get_recent_logins($filters = [], $sort = '', $dir = ''){
    global $DB, $USER;

    $cutofftime = time() - (7 * 24*60*60);
    $params = ['cutofftime' => $cutofftime];

    // Switched to action = 'loggedin' and ensured u.deleted = 0
    $sql = "SELECT u.id, u.firstname, u.lastname, u.email, MAX(l.timecreated) AS lastlogin
                FROM {logstore_standard_log} l
                JOIN {user} u ON l.userid = u.id
                JOIN {user_enrolments} ue ON ue.userid = u.id
                JOIN {enrol} e ON e.id = ue.enrolid
                JOIN {course} c ON c.id = e.courseid
            WHERE l.action = 'loggedin'
                AND l.timecreated >= :cutofftime
                AND u.deleted = 0 AND u.suspended = 0";
                
    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['u', 'c']);

    // Added a space before GROUP BY
    $sql .= " GROUP BY u.id, u.firstname, u.lastname, u.email";
    
    $allowed_sorts = [
        'user_firstname' => 'u.firstname',
        'user_lastname' => 'u.lastname',
        'lastlogin' => 'lastlogin'
    ];
    $sql = report_suri_apply_sorting($sql, $sort, $dir, $allowed_sorts);
    if (strpos($sql, 'ORDER BY') === false) {
        $sql .= " ORDER BY lastlogin DESC";
    }

    return $DB->get_records_sql($sql, $params);
}

/**
 * 4. Compiles a roster of students and completion timestamps.
 */
function report_suri_get_course_completion_directory($courseid = null, $filters = [], $sort = '', $dir = ''){
    global $DB, $USER;
    $params = [];

    // Unique mapping ID changed to ue.id to ensure users who haven't started completion don't get dropped.
    // Changed to LEFT JOIN for course_completions.
    $sql = "SELECT ue.id AS unique_mapping_id, u.firstname, u.lastname, u.idnumber, u.institution, u.department, u.email, c.fullname AS coursename, cc.timeenrolled, cc.timecompleted
                FROM {user} u
                JOIN {user_enrolments} ue ON ue.userid = u.id
                JOIN {enrol} e ON e.id = ue.enrolid
                JOIN {course} c ON c.id = e.courseid
                JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id
                LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id
            WHERE ra.roleid = (SELECT id FROM {role} WHERE shortname = 'student')
              AND u.deleted = 0 AND u.suspended = 0";

    if(!empty($courseid)){
        $sql .= " AND c.id = :courseid";
        $params['courseid'] = $courseid;
    }

    $sql = report_suri_apply_role_scope($sql, $params, $USER->id); 
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['u', 'c']);
    
    $allowed_sorts = [
        'user_firstname' => 'u.firstname',
        'user_lastname' => 'u.lastname',
        'course_name' => 'c.fullname',
        'timecompleted' => 'cc.timecompleted'
    ];
    $sql = report_suri_apply_sorting($sql, $sort, $dir, $allowed_sorts);
    if (strpos($sql, 'ORDER BY') === false) {
        $sql .= " ORDER BY c.fullname ASC, u.lastname ASC";
    }

    return $DB->get_records_sql($sql, $params);
}

/**
 * 5. Fetches course completion status tracking metrics alongside custom certificate records.
 */
/**
 * 5. Fetches course completion status tracking metrics alongside custom certificate records.
 * Dynamically checks for the existence of the customcert plugin table to prevent fatal DB errors.
 */
function report_suri_get_completion_and_certificates($filters = []){
    global $DB, $USER;
    $params = [];

    // Safely check if the customcert table exists in this Moodle environment
    $dbman = $DB->get_manager();
    $has_customcert = $dbman->table_exists('customcert_issues');

    // Dynamically build the SQL based on the plugin's presence
    $cert_field = $has_customcert ? ", MAX(ci.timecreated) AS certificate_date" : ", NULL AS certificate_date";
    $cert_join  = $has_customcert ? "LEFT JOIN {customcert_issues} ci ON ci.userid = u.id AND ci.customcertid = cm.instance AND m.name = 'customcert'" : "";

    $sql = "SELECT ue.id AS unique_mapping_id, u.id AS userid, u.firstname, u.lastname, u.username, u.email, c.fullname AS coursename,
                    COUNT(DISTINCT cm.id) AS total_modules,
                    COUNT(DISTINCT cmc.id) AS completed_modules
                    {$cert_field}
                FROM {user} u
                JOIN {user_enrolments} ue ON ue.userid = u.id
                JOIN {enrol} e ON e.id = ue.enrolid
                JOIN {course} c ON c.id = e.courseid
                LEFT JOIN {course_modules} cm ON cm.course = c.id AND cm.completion > 0
                LEFT JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id AND cmc.userid = u.id AND cmc.completionstate IN (1, 2)
                LEFT JOIN {modules} m ON m.id = cm.module
                {$cert_join}
            WHERE u.deleted = 0 AND u.suspended = 0";

    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['u', 'c']);
    
    $sql .= " GROUP BY ue.id, u.id, u.firstname, u.lastname, u.username, u.email, c.id, c.fullname
             ORDER BY c.fullname ASC, u.lastname ASC";
    
    return $DB->get_records_sql($sql, $params);
}

/*
    Fetches the  total number of unique active users within scope
*/
function report_suri_get_total_users_count($filters = []){
    global $DB, $USER;
    $params = [];

    $sql = "SELECT 1 AS id, COUNT(DISTINCT u.id) AS totalusers
            FROM {user} u
            LEFT JOIN {user_enrolments} ue ON ue.userid = u.id
            LEFT JOIN {enrol} e ON e.id = ue.enrolid
            LEFT JOIN {course} c ON c.id = e.courseid
            WHERE u.deleted = 0 AND u.suspended = 0 AND u.id <> 1";

    $sql = report_suri_apply_role_scope($sql,$params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['u', 'c']);
    $result = $DB->get_record_sql($sql, $params);
    return $result ? $result->totalusers : 0;
}

/*
    Fetches user count partitions via their assigned roles
*/
function report_suri_get_users_count_per_role($filters = []){
    global $DB, $USER;
    $params = [];

    $sql = "SELECT r.id, r.shortname, r.name, COUNT(DISTINCT ra.userid) AS usercount
            FROM {role} r
            JOIN {role_assignments} ra ON ra.roleid = r.id
            JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
            JOIN {course} c ON c.id = ctx.instanceid
            JOIN {user} u ON u.id = ra.userid
            WHERE u.deleted = 0 AND u.suspended = 0";

    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['u', 'c']);
    $sql .= " GROUP BY r.id, r.shortname, r.name";
    return $DB->get_records_sql($sql, $params);
}

/*
    Fetche  unique active user counts per cohort within scope
*/

function report_suri_get_users_count_per_cohort($filters = []){
    global $DB, $USER;
    $params = [];

    $sql = "SELECT h.id, h.name, COUNT(DISTINCT cm.userid) AS usercount
            FROM {cohort} h
            JOIN {cohort_members} cm ON cm.cohortid = h.id
            JOIN {user} u ON u.id = cm.userid
            JOIN {user_enrolments} ue ON ue.userid = u.id
            JOIN {enrol} e ON e.id = ue.enrolid
            JOIN {course} c ON c.id = e.courseid
            WHERE u.deleted = 0  AND u.suspended = 0";

    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['u', 'c']);
    $sql .= " GROUP BY h.id, h.name ORDER BY h.name ASC";
    return $DB->get_records_sql($sql, $params);
}

/*
    Fetches the absolute total number of unique students within scope   
*/
function report_suri_get_total_students_count($filters = []){
    global $DB, $USER;
    $params = [];

    $sql = "SELECT 1 AS id, COUNT(DISTINCT ra.userid) AS studentcount
            FROM {role_assignments} ra
            JOIN {role} r ON r.id = ra.roleid
            JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
            JOIN {course} c ON c.id = ctx.instanceid
            JOIN {user} u ON u.id = ra.userid
            WHERE r.shortname = 'student' AND u.deleted = 0 AND u.suspended = 0";
    
    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['u', 'c']);
    $result = $DB->get_record_sql($sql, $params);
    return $result ? $result->studentcount : 0;
}

/*
    Fetches the student counts partitioned across cohorts
*/
function report_suri_get_student_count_per_cohort($filters = []){
    global $DB, $USER;
    $params = [];

    $sql = "SELECT h.id, h.name, COUNT(DISTINCT cm.userid) AS studentcount
            FROM {cohort} h 
            JOIN {cohort_members} cm ON cm.cohortid = h.id
            JOIN {user} u ON u.id = cm.userid
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {role} r ON r.id = ra.roleid
            JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
            JOIN {course} c ON c.id = ctx.instanceid
            WHERE r.shortname = 'student' AND u.deleted = 0 AND u.suspended = 0";

    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['u', 'c']);
    $sql .= " GROUP BY h.id, h.name ORDER BY h.name ASC";
    return $DB->get_records_sql($sql, $params);
}

/*
    Fetches the absolute total number of unique teachers within scope 
*/
function report_suri_get_total_teachers_count($filters = []){
    global $DB, $USER;
    $params = [];

    $sql = "SELECT 1 AS id, COUNT(DISTINCT ra.userid) AS teachercount
            FROM {role_assignments} ra
            JOIN {role} r ON r.id = ra.roleid
            JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
            JOIN {course} c ON c.id = ctx.instanceid
            JOIN {user} u ON u.id = ra.userid
            WHERE r.shortname  IN ('teacher', 'editingteacher') AND u.deleted = 0 AND u.suspended = 0";
    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['u', 'c']);
    $result = $DB->get_record_sql($sql, $params);
    return $result ? $result->teachercount : 0;
}

/*
    Fetches the teacher counts partitioned across cohorts
*/
function report_suri_get_teachers_count_per_cohort($filters = []){
    global $DB, $USER;
    $params = [];

    $sql = "SELECT h.id, h.name, COUNT(DISTINCT cm.userid) AS teachercount
            FROM {cohort} h
            JOIN {cohort_members} cm ON cm.cohortid = h.id
            JOIN {user} u ON u.id = cm.userid
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {role} r ON r.id = ra.roleid
            JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
            JOIN {course} c ON c.id = ctx.instanceid
            WHERE r.shortname IN ('teacher', 'editingteacher') AND u.deleted = 0 AND u.suspended = 0";

    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['u', 'c']);
    $sql .= " GROUP BY h.id, h.name ORDER BY h.name ASC";
    return $DB->get_records_sql($sql, $params);
}

/**
 * Fetches the most active courses based on student interactions (log counts).
 */
function report_suri_get_most_active_courses($filters = []){
    global $DB, $USER;
    $params = [];

    $sql = "SELECT c.id, c.fullname AS coursename, COUNT(l.id) AS views
            FROM {course} c
            JOIN {logstore_standard_log} l ON l.courseid = c.id
            JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
            JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = l.userid
            JOIN {role} r ON r.id = ra.roleid
            WHERE r.shortname = 'student' AND c.id <> 1";

    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    // User u is not joined here directly. We could join it if we really wanted user filtering, 
    // but the alias available is just c for now.
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['c']);
    $sql .= " GROUP BY c.id, c.fullname ORDER BY views DESC";
    return $DB->get_records_sql($sql, $params);
}

/**
 * Estimates accumulative time spent by students in courses over the last 30 days.
 * (Estimation: 5 minutes = 300 seconds per log action)
 */
function report_suri_get_course_time_spent($days = 30, $filters = []){
    global $DB, $USER;
    
    // Dynamic days cutoff
    $cutoff = time() - ($days * 24 * 60 * 60);
    $params = ['cutoff' => $cutoff];

    $sql = "SELECT c.id, c.fullname AS coursename, (COUNT(l.id) * 300) AS totalseconds
            FROM {course} c
            JOIN {logstore_standard_log} l ON l.courseid = c.id
            JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
            JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = l.userid
            JOIN {role} r ON r.id = ra.roleid
            WHERE r.shortname = 'student' AND l.timecreated > :cutoff AND c.id <> 1";

    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['c']);
    $sql .= " GROUP BY c.id, c.fullname ORDER BY totalseconds DESC";
    return $DB->get_records_sql($sql, $params);
}

/**
 * 1. Fetches total number of courses
 */
function report_suri_get_total_courses_count($filters = []) {
    global $DB, $USER;
    $params = [];
    $sql = "SELECT 1 AS id, COUNT(DISTINCT c.id) AS totalcourses
            FROM {course} c
            WHERE c.id <> 1";
    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['c']);
    $result = $DB->get_record_sql($sql, $params);
    return $result ? $result->totalcourses : 0;
}

/**
 * 2. Fetches number of courses per category
 */
function report_suri_get_courses_per_category($filters = []) {
    global $DB, $USER;
    $params = [];
    $sql = "SELECT cat.id, cat.name AS categoryname, COUNT(DISTINCT c.id) AS coursecount
            FROM {course_categories} cat
            JOIN {course} c ON c.category = cat.id
            WHERE c.id <> 1";
    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['c']);
    $sql .= " GROUP BY cat.id, cat.name ORDER BY coursecount DESC";
    return $DB->get_records_sql($sql, $params);
}

/**
 * 3. Fetches total number of students and teachers per course
 */
function report_suri_get_users_per_course($filters = []) {
    global $DB, $USER;
    $params = [];
    $sql = "SELECT c.id, c.fullname AS coursename, c.startdate, c.enddate, c.visible,
                   COUNT(DISTINCT CASE WHEN r.shortname = 'student' THEN u.id END) AS studentcount,
                   COUNT(DISTINCT CASE WHEN r.shortname IN ('teacher', 'editingteacher') THEN u.id END) AS teachercount
            FROM {course} c
            JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
            JOIN {role_assignments} ra ON ra.contextid = ctx.id
            JOIN {role} r ON r.id = ra.roleid AND r.shortname IN ('student', 'teacher', 'editingteacher')
            JOIN {user} u ON u.id = ra.userid AND u.deleted = 0 AND u.suspended = 0
            WHERE c.id <> 1";
    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['u', 'c']);
    $sql .= " GROUP BY c.id, c.fullname ORDER BY c.fullname ASC";
    return $DB->get_records_sql($sql, $params);
}

/**
 * 4a. Average completion time of all courses
 */
function report_suri_get_avg_completion_time_all($filters = []) {
    global $DB, $USER;
    $params = [];
    $sql = "SELECT 1 AS id, AVG(cc.timecompleted - cc.timeenrolled) AS avg_seconds
            FROM {course_completions} cc
            JOIN {course} c ON c.id = cc.course
            WHERE cc.timecompleted IS NOT NULL AND cc.timeenrolled > 0
              AND cc.timecompleted > cc.timeenrolled AND c.id <> 1";
    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['c']);
    $result = $DB->get_record_sql($sql, $params);
    return $result ? $result->avg_seconds : 0;
}

/**
 * 4b. Average completion time per course
 */
function report_suri_get_avg_completion_time_per_course($filters = []) {
    global $DB, $USER;
    $params = [];
    $sql = "SELECT c.id, c.fullname AS coursename, AVG(cc.timecompleted - cc.timeenrolled) AS avg_seconds
            FROM {course} c
            JOIN {course_completions} cc ON cc.course = c.id
            WHERE cc.timecompleted IS NOT NULL AND cc.timeenrolled > 0
              AND cc.timecompleted > cc.timeenrolled AND c.id <> 1";
    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['c']);
    $sql .= " GROUP BY c.id, c.fullname ORDER BY c.fullname ASC";
    return $DB->get_records_sql($sql, $params);
}

/**
 * 5a. Average rate of student completion all courses
 */
function report_suri_get_completion_rate_all($filters = []) {
    global $DB, $USER;
    $params = [];
    $sql = "SELECT 1 AS id,
                   COUNT(cc.id) AS total_tracked,
                   SUM(CASE WHEN cc.timecompleted IS NOT NULL THEN 1 ELSE 0 END) AS total_completed
            FROM {course_completions} cc
            JOIN {course} c ON c.id = cc.course
            WHERE c.id <> 1";
    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['c']);
    $result = $DB->get_record_sql($sql, $params);
    if ($result && $result->total_tracked > 0) {
        return ($result->total_completed / $result->total_tracked) * 100;
    }
    return 0;
}

/**
 * 5b. Average rate of student completion per course
 */
function report_suri_get_completion_rate_per_course($filters = []) {
    global $DB, $USER;
    $params = [];
    $sql = "SELECT c.id, c.fullname AS coursename,
                   COUNT(cc.id) AS total_tracked,
                   SUM(CASE WHEN cc.timecompleted IS NOT NULL THEN 1 ELSE 0 END) AS total_completed
            FROM {course} c
            JOIN {course_completions} cc ON cc.course = c.id
            WHERE c.id <> 1";
    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['c']);
    $sql .= " GROUP BY c.id, c.fullname ORDER BY c.fullname ASC";
    return $DB->get_records_sql($sql, $params);
}

/**
 * Quiz Mean (Global Average)
 */
function report_suri_get_quiz_mean_all($filters = []) {
    global $DB, $USER;
    $params = [];
    $sql = "SELECT 1 AS id, AVG((qg.grade / q.grade) * 100) AS mean_score
            FROM {quiz_grades} qg
            JOIN {quiz} q ON q.id = qg.quiz
            JOIN {course} c ON c.id = q.course
            WHERE q.grade > 0 AND c.id <> 1";
    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['c']);
    $result = $DB->get_record_sql($sql, $params);
    return $result ? $result->mean_score : 0;
}

/**
 * Quiz Mean (Per Course)
 */
function report_suri_get_quiz_mean_per_course($filters = []) {
    global $DB, $USER;
    $params = [];
    $sql = "SELECT c.id, c.fullname AS coursename, AVG((qg.grade / q.grade) * 100) AS mean_score
            FROM {quiz_grades} qg
            JOIN {quiz} q ON q.id = qg.quiz
            JOIN {course} c ON c.id = q.course
            WHERE q.grade > 0 AND c.id <> 1";
    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['c']);
    $sql .= " GROUP BY c.id, c.fullname ORDER BY c.fullname ASC";
    return $DB->get_records_sql($sql, $params);
}

/**
 * Engagement (Global Average)
 */
function report_suri_get_engagement_all($filters = []) {
    global $DB, $USER;
    $params = [];
    $sql = "SELECT 1 AS id,
                   COUNT(l.id) AS total_logs,
                   COUNT(DISTINCT l.userid) AS active_students
            FROM {logstore_standard_log} l
            JOIN {course} c ON c.id = l.courseid
            JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
            JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = l.userid
            JOIN {role} r ON r.id = ra.roleid
            WHERE r.shortname = 'student' AND c.id <> 1";
    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['c']);
    $result = $DB->get_record_sql($sql, $params);
    if ($result && $result->active_students > 0) {
        return $result->total_logs / $result->active_students;
    }
    return 0;
}

/**
 * Engagement (Per Course)
 */
function report_suri_get_engagement_per_course($filters = []) {
    global $DB, $USER;
    $params = [];
    $sql = "SELECT c.id, c.fullname AS coursename,
                   COUNT(l.id) AS total_logs,
                   COUNT(DISTINCT l.userid) AS active_students
            FROM {logstore_standard_log} l
            JOIN {course} c ON c.id = l.courseid
            JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
            JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = l.userid
            JOIN {role} r ON r.id = ra.roleid
            WHERE r.shortname = 'student' AND c.id <> 1";
    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['c']);
    $sql .= " GROUP BY c.id, c.fullname ORDER BY c.fullname ASC";
    return $DB->get_records_sql($sql, $params);
}

/**
 * Attendance Trends (Per Course) - Alternative Proxy
 * Since mod_attendance is not installed, we calculate the average number
 * of unique active days per student in each course using standard logs.
 */
function report_suri_get_attendance_trends_per_course($filters = []) {
    global $DB, $USER;
    $params = [];
    
    // MariaDB/MySQL FROM_UNIXTIME to get unique date strings
    $sql = "SELECT c.id, c.fullname AS coursename,
                   COUNT(DISTINCT CONCAT(l.userid, '-', FROM_UNIXTIME(l.timecreated, '%Y-%m-%d'))) AS total_attendance_days,
                   COUNT(DISTINCT l.userid) AS active_students
            FROM {logstore_standard_log} l
            JOIN {course} c ON c.id = l.courseid
            JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
            JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = l.userid
            JOIN {role} r ON r.id = ra.roleid
            WHERE r.shortname = 'student' AND c.id <> 1";
            
    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['c']);
    $sql .= " GROUP BY c.id, c.fullname ORDER BY c.fullname ASC";
    
    return $DB->get_records_sql($sql, $params);
}

/**
 * Attendance Trends (Per Student)
 * Calculates the total number of unique active days per student per course.
 */
function report_suri_get_attendance_trends_per_student($filters = []) {
    global $DB, $USER;
    $params = [];
    
    $sql = "SELECT CONCAT(u.id, '-', c.id) AS uniqueid,
                   u.firstname, u.lastname, u.email,
                   c.fullname AS coursename,
                   COUNT(DISTINCT FROM_UNIXTIME(l.timecreated, '%Y-%m-%d')) AS active_days
            FROM {logstore_standard_log} l
            JOIN {user} u ON u.id = l.userid
            JOIN {course} c ON c.id = l.courseid
            JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
            JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = l.userid
            JOIN {role} r ON r.id = ra.roleid
            WHERE r.shortname = 'student' AND c.id <> 1";
            
    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['u', 'c']);
    $sql .= " GROUP BY u.id, u.firstname, u.lastname, u.email, c.id, c.fullname
              ORDER BY c.fullname ASC, u.lastname ASC, u.firstname ASC";
              
    return $DB->get_records_sql($sql, $params);
}

/**
 * Performance Segmentation
 * Categorizes users into High (>=80%), Average (50-79%), and Low (<50%) performers.
 */
function report_suri_get_performance_segmentation($filters = []) {
    global $DB, $USER;
    $params = [];
    
    $inner_where = "WHERE q.grade > 0 AND c.id <> 1 AND r.shortname = 'student'";
    $inner_where = report_suri_apply_role_scope($inner_where, $params, $USER->id);
    $inner_where = report_suri_apply_dynamic_filters($inner_where, $params, $filters, ['u', 'c']);
    
    $sql = "
        SELECT 
            CASE 
                WHEN avg_score >= 80 THEN 'High Performers'
                WHEN avg_score >= 50 THEN 'Average Performers'
                ELSE 'Low Performers'
            END AS segment,
            COUNT(userid) AS students,
            AVG(avg_score) AS average_quiz_score
        FROM (
            SELECT 
                u.id AS userid,
                AVG((qg.grade / q.grade) * 100) AS avg_score
            FROM {quiz_grades} qg
            JOIN {quiz} q ON q.id = qg.quiz
            JOIN {course} c ON c.id = q.course
            JOIN {user} u ON u.id = qg.userid
            JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
            JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id
            JOIN {role} r ON r.id = ra.roleid
            $inner_where
            GROUP BY u.id
        ) user_scores
        GROUP BY 
            CASE 
                WHEN avg_score >= 80 THEN 'High Performers'
                WHEN avg_score >= 50 THEN 'Average Performers'
                ELSE 'Low Performers'
            END
    ";
    
    return $DB->get_records_sql($sql, $params);
}

/**
 * Get detailed statistics for a single course.
 */
function report_suri_get_single_course_summary($courseid) {
    global $DB, $USER;
    
    // 1. Get Course Info
    $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname');
    if (!$course) return false;
    
    // Ensure the course is within the user's scope
    $params = ['courseid' => $courseid];
    $check_sql = "SELECT c.id FROM {course} c WHERE c.id = :courseid";
    $check_sql = report_suri_apply_role_scope($check_sql, $params, $USER->id);
    if (!$DB->get_record_sql($check_sql, $params)) {
        return false;
    }
    
    // 2. Get Instructors
    $sql_teachers = "SELECT u.id, u.firstname, u.lastname
                     FROM {user} u
                     JOIN {role_assignments} ra ON ra.userid = u.id
                     JOIN {role} r ON r.id = ra.roleid
                     JOIN {context} ctx ON ctx.id = ra.contextid
                     WHERE ctx.instanceid = ? AND ctx.contextlevel = 50 
                     AND r.shortname IN ('editingteacher', 'teacher')";
    $teachers = $DB->get_records_sql($sql_teachers, [$courseid]);
    $instructor_names = [];
    foreach ($teachers as $t) {
        $instructor_names[] = fullname($t);
    }
    
    // 3. Get Student Completion Stats
    $sql_comp = "SELECT 
                    COUNT(cc.id) as total_enrolled,
                    SUM(CASE WHEN cc.timecompleted IS NOT NULL THEN 1 ELSE 0 END) as total_completed,
                    AVG(CASE WHEN cc.timecompleted IS NOT NULL THEN (cc.timecompleted - cc.timeenrolled) ELSE NULL END) as avg_time
                 FROM {course_completions} cc
                 JOIN {user} u ON u.id = cc.userid
                 JOIN {role_assignments} ra ON ra.userid = u.id
                 JOIN {role} r ON r.id = ra.roleid
                 JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE cc.course = ? AND ctx.instanceid = ? AND ctx.contextlevel = 50 AND r.shortname = 'student'";
    $comp_stats = $DB->get_record_sql($sql_comp, [$courseid, $courseid]);
    
    $total = $comp_stats ? ($comp_stats->total_enrolled ?? 0) : 0;
    $completed = $comp_stats ? ($comp_stats->total_completed ?? 0) : 0;
    $avg_time = $comp_stats ? ($comp_stats->avg_time ?? 0) : 0;
    
    // 4. Get Total Interactions (Logs)
    $sql_interactions = "SELECT COUNT(l.id) as total_interactions
                         FROM {logstore_standard_log} l
                         JOIN {context} ctx ON ctx.instanceid = l.courseid AND ctx.contextlevel = 50
                         JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = l.userid
                         JOIN {role} r ON r.id = ra.roleid
                         WHERE l.courseid = ? AND r.shortname = 'student'";
    $interactions_stat = $DB->get_record_sql($sql_interactions, [$courseid]);
    $total_interactions = $interactions_stat ? ($interactions_stat->total_interactions ?? 0) : 0;

    return [
        'coursename' => $course->fullname,
        'total_students' => $total,
        'completed_students' => $completed,
        'incomplete_students' => $total - $completed,
        'avg_time_seconds' => $avg_time,
        'total_interactions' => $total_interactions
    ];
}

/**
 * Get the list of users for a single course.
 */
function report_suri_get_single_course_users($courseid, $filters = [], $sort = '', $dir = '') {
    global $DB;
    $params = [
        'courseid' => $courseid,
        'courseid_mod1' => $courseid,
        'courseid_mod2' => $courseid,
        'courseid_quiz' => $courseid
    ];
    $sql = "SELECT ue.id, u.firstname, u.lastname, u.email, u.department, u.institution, u.city, u.phone1, u.lastaccess,
            (SELECT COUNT(cm.id) FROM {course_modules} cm WHERE cm.course = :courseid_mod1 AND cm.completion > 0) AS total_modules,
            (SELECT COUNT(cmc.id) FROM {course_modules_completion} cmc JOIN {course_modules} cm2 ON cm2.id = cmc.coursemoduleid WHERE cm2.course = :courseid_mod2 AND cmc.userid = u.id AND cmc.completionstate IN (1, 2)) AS completed_modules,
            (SELECT AVG((qg.grade / q.grade) * 100) FROM {quiz_grades} qg JOIN {quiz} q ON q.id = qg.quiz WHERE q.course = :courseid_quiz AND qg.userid = u.id AND q.grade > 0) AS avg_quiz_score
            FROM {user} u
            JOIN {user_enrolments} ue ON ue.userid = u.id
            JOIN {enrol} e ON e.id = ue.enrolid
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50 AND ctx.instanceid = e.courseid
            JOIN {role} r ON r.id = ra.roleid
            WHERE e.courseid = :courseid 
              AND r.shortname = 'student'
              AND u.deleted = 0 AND u.suspended = 0";
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['u']);
    
    $allowed_sorts = [
        'user_firstname' => 'u.firstname',
        'user_lastname' => 'u.lastname',
        'lastaccess' => 'u.lastaccess',
        'progress' => 'completed_modules',
        'avg_quiz_score' => 'avg_quiz_score'
    ];
    $sql = report_suri_apply_sorting($sql, $sort, $dir, $allowed_sorts);

    return $DB->get_records_sql($sql, $params);
}

/**
 * Fetches global student interaction trends over time grouped by day, week, or month,
 * plotting the top 5 most active courses in that timeframe.
 */
function report_suri_get_global_interaction_trends($period = 'day', $filters = []) {
    global $DB, $USER;
    $params = [];
    
    $days_back = ($period === 'month') ? 365 : 30;
    $cutoff = time() - ($days_back * 24 * 60 * 60);
    $params['cutoff'] = $cutoff;
    
    $divider = 86400; // default day
    if ($period === 'week') $divider = 604800;
    if ($period === 'month') $divider = 2592000;
    
    // 1. Get top 5 courses in this timeframe
    $top_sql = "SELECT c.id, c.fullname, COUNT(l.id) AS interactions
                FROM {logstore_standard_log} l
                JOIN {course} c ON c.id = l.courseid
                JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = l.userid
                JOIN {role} r ON r.id = ra.roleid
                WHERE l.timecreated > :cutoff AND c.id <> 1 AND r.shortname = 'student'";
    
    $top_sql = report_suri_apply_role_scope($top_sql, $params, $USER->id);
    $top_sql = report_suri_apply_dynamic_filters($top_sql, $params, $filters, ['c']);
    $top_sql .= " GROUP BY c.id, c.fullname ORDER BY interactions DESC";
    
    $top_courses = $DB->get_records_sql($top_sql, $params, 0, 5);
    if (empty($top_courses)) {
        return [];
    }
    
    $cids = implode(',', array_keys($top_courses));
    
    // 2. Fetch log counts for these top courses grouped by time block
    // We use a CONCAT for unique ID required by Moodle's get_records_sql
    $sql = "SELECT CONCAT(c.id, '-', FLOOR(l.timecreated / {$divider})) AS unique_id,
                   c.fullname AS coursename,
                   FLOOR(l.timecreated / {$divider}) AS time_block,
                   COUNT(l.id) AS interactions
            FROM {logstore_standard_log} l
            JOIN {course} c ON c.id = l.courseid
            JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
            JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = l.userid
            JOIN {role} r ON r.id = ra.roleid
            WHERE l.timecreated > :cutoff AND c.id IN ({$cids}) AND r.shortname = 'student'";
            
    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['c']);
    $sql .= " GROUP BY c.id, c.fullname, FLOOR(l.timecreated / {$divider}) ORDER BY time_block ASC";
    
    $records = $DB->get_records_sql($sql, $params);
    
    // 3. Process into ApexCharts series format
    $aggregated = [];
    if ($records) {
        foreach ($records as $rec) {
            $timestamp = $rec->time_block * $divider;
            $label = ($period === 'month') ? date('Y M', $timestamp) : date('Y-m-d', $timestamp);
            if (!isset($aggregated[$rec->coursename][$label])) {
                $aggregated[$rec->coursename][$label] = 0;
            }
            $aggregated[$rec->coursename][$label] += $rec->interactions;
        }
    }
    
    $final_series = [];
    foreach ($top_courses as $c) {
        $data = [];
        if (isset($aggregated[$c->fullname])) {
            foreach ($aggregated[$c->fullname] as $x => $y) {
                $data[] = ['x' => $x, 'y' => $y];
            }
        }
        $final_series[] = ['name' => $c->fullname, 'data' => $data];
    }
    return $final_series;
}

function report_suri_get_global_search($query) {
    global $DB, $USER;
    $results = ['users' => [], 'courses' => [], 'cohorts' => []];
    
    if (empty($query)) return $results;
    
    $query = trim($query);
    if (strlen($query) < 2) return $results;
    
    $like = '%' . $DB->sql_like_escape(strtolower($query)) . '%';
    
    // Search Users
    $params_users = ['q1' => $like, 'q2' => $like, 'q3' => $like];
    $sql_users = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email 
                  FROM {user} u 
                  JOIN {user_enrolments} ue ON ue.userid = u.id
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} c ON c.id = e.courseid
                  WHERE u.deleted = 0 
                    AND (LOWER(u.firstname) LIKE :q1 OR LOWER(u.lastname) LIKE :q2 OR LOWER(u.email) LIKE :q3)";
    $sql_users = report_suri_apply_role_scope($sql_users, $params_users, $USER->id);
    $sql_users .= " ORDER BY u.firstname ASC, u.lastname ASC";
    $results['users'] = $DB->get_records_sql($sql_users, $params_users, 0, 15);
    
    // Search Courses
    $params_courses = ['q1' => $like, 'q2' => $like];
    $sql_courses = "SELECT c.id, c.fullname, c.shortname 
                    FROM {course} c 
                    WHERE c.category > 0 
                      AND (LOWER(c.fullname) LIKE :q1 OR LOWER(c.shortname) LIKE :q2)";
    $sql_courses = report_suri_apply_role_scope($sql_courses, $params_courses, $USER->id);
    $sql_courses .= " ORDER BY c.fullname ASC";
    $results['courses'] = $DB->get_records_sql($sql_courses, $params_courses, 0, 15);
    
    // Search Cohorts
    $params_cohorts = ['q1' => $like, 'q2' => $like];
    $sql_cohorts = "SELECT DISTINCT h.id, h.name, h.idnumber 
                    FROM {cohort} h 
                    JOIN {cohort_members} cm ON cm.cohortid = h.id
                    JOIN {user_enrolments} ue ON ue.userid = cm.userid
                    JOIN {enrol} e ON e.id = ue.enrolid
                    JOIN {course} c ON c.id = e.courseid
                    WHERE LOWER(h.name) LIKE :q1 OR LOWER(h.idnumber) LIKE :q2";
    $sql_cohorts = report_suri_apply_role_scope($sql_cohorts, $params_cohorts, $USER->id);
    $sql_cohorts .= " ORDER BY h.name ASC";
    $results['cohorts'] = $DB->get_records_sql($sql_cohorts, $params_cohorts, 0, 15);
    
    return $results;
}

function report_suri_get_active_users_trend($days = 7) {
    global $DB, $USER;
    $trend_data = [];
    $start_time = time() - ($days * 24 * 60 * 60);

    try {
        $params = ['start_time' => $start_time];
        $sql = "SELECT FLOOR(l.timecreated / 86400) AS day_val, COUNT(DISTINCT l.userid) AS active_users 
                FROM {logstore_standard_log} l
                JOIN {course} c ON c.id = l.courseid
                WHERE l.timecreated >= :start_time AND l.userid > 0";
        $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
        $sql .= " GROUP BY FLOOR(l.timecreated / 86400) ORDER BY day_val ASC";
        $records = $DB->get_records_sql($sql, $params);
    } catch (Exception $e) {
        $records = [];
    }

    // Fill the array with 0s for every day in the range to ensure no gaps in the chart
    $current_day = floor($start_time / 86400);
    $end_day = floor(time() / 86400);
    
    for ($i = $current_day; $i <= $end_day; $i++) {
        $date_str = date('M j', $i * 86400); // e.g. "Jun 24"
        $trend_data[$date_str] = 0;
    }

    if (!empty($records)) {
        foreach ($records as $r) {
            $date_str = date('M j', $r->day_val * 86400);
            if (isset($trend_data[$date_str])) {
                $trend_data[$date_str] = (int)$r->active_users;
            }
        }
    }

    return $trend_data;
}

function report_suri_get_engagement_breakdown($days = 30) {
    global $DB, $USER;
    $start_time = time() - ($days * 24 * 60 * 60);

    try {
        $params = ['start_time' => $start_time];
        $sql = "SELECT l.component, COUNT(l.id) AS hits 
                FROM {logstore_standard_log} l
                JOIN {course} c ON c.id = l.courseid
                WHERE l.timecreated >= :start_time AND l.component LIKE 'mod_%'";
        $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
        $sql .= " GROUP BY l.component ORDER BY hits DESC";
        $records = $DB->get_records_sql($sql, $params);
    } catch (Exception $e) {
        $records = [];
    }
    
    if (empty($records)) {
        return [
            ['label' => 'No Data Yet', 'count' => 0, 'pct' => 100, 'color_class' => 'primary', 'hex' => '#534AB7']
        ];
    }
    
    $total_hits = 0;
    foreach ($records as $r) {
        $total_hits += $r->hits;
    }
    
    $results = [];
    $colors = ['primary', 'secondary', 'teal'];
    $hex_colors = ['#534AB7', '#E24B4A', '#1D9E75']; 
    
    $i = 0;
    $other_hits = 0;
    foreach ($records as $r) {
        if ($i < 3) {
            $label_raw = str_replace('mod_', '', $r->component);
            $label = ucfirst($label_raw);
            if (!in_array(substr($label, -1), ['s'])) $label .= 's'; 
            
            $results[] = [
                'label' => $label,
                'count' => (int)$r->hits,
                'pct' => round(($r->hits / $total_hits) * 100),
                'color_class' => $colors[$i],
                'hex' => $hex_colors[$i]
            ];
            $i++;
        } else {
            $other_hits += $r->hits;
        }
    }
    
    if ($other_hits > 0) {
        $results[] = [
            'label' => 'Other',
            'count' => (int)$other_hits,
            'pct' => round(($other_hits / $total_hits) * 100),
            'color_class' => 'warning',
            'hex' => '#EF9F27'
        ];
    }
    
    return $results;
}

function report_suri_get_courses_needing_attention() {
    global $DB, $USER;
    $dropoff_threshold = (int)get_config('report_suri', 'threshold_dropoff');
    if (!$dropoff_threshold) $dropoff_threshold = 20;
    
    $params = [];
    $sql = "SELECT c.id, c.fullname as coursename, c.shortname, 
                   COUNT(DISTINCT e.userid) as enrolled_students
            FROM {course} c
            JOIN {enrol} en ON en.courseid = c.id
            JOIN {user_enrolments} e ON e.enrolid = en.id
            WHERE c.category > 0";
    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql .= " GROUP BY c.id, c.fullname, c.shortname
            HAVING COUNT(DISTINCT e.userid) > 0";
    $courses = $DB->get_records_sql($sql, $params);
    
    $flagged = [];
    if ($courses) {
        $active_since = time() - (14 * 24 * 60 * 60);
        foreach ($courses as $c) {
            $active_sql = "SELECT COUNT(DISTINCT userid) as active 
                           FROM {logstore_standard_log} 
                           WHERE courseid = ? AND timecreated > ?";
            try {
                $active_count = $DB->get_field_sql($active_sql, [$c->id, $active_since]);
            } catch (Exception $e) {
                $active_count = 0;
            }
            
            $dropoff_rate = 100 - round(($active_count / $c->enrolled_students) * 100);
            
            if ($dropoff_rate >= $dropoff_threshold) {
                $flagged[] = [
                    'coursename' => $c->coursename,
                    'shortname' => $c->shortname,
                    'courseid' => $c->id,
                    'dropoff_rate' => $dropoff_rate,
                    'issue' => "{$dropoff_rate}% Drop-off"
                ];
            }
        }
    }
    
    usort($flagged, function($a, $b) {
        return $b['dropoff_rate'] <=> $a['dropoff_rate'];
    });
    
    return array_slice($flagged, 0, 5);
}

function report_suri_get_user_anomalies() {
    global $DB, $USER;
    $inactivity_threshold = (int)get_config('report_suri', 'threshold_inactivity');
    if (!$inactivity_threshold) $inactivity_threshold = 14; 
    
    $flagged = [];
    $cutoff = time() - ($inactivity_threshold * 24 * 60 * 60);
    
    $params = ['cutoff' => $cutoff];
    $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.lastaccess 
            FROM {user} u
            JOIN {user_enrolments} ue ON ue.userid = u.id
            JOIN {enrol} e ON e.id = ue.enrolid
            JOIN {course} c ON c.id = e.courseid
            WHERE u.deleted = 0 AND u.lastaccess > 0 AND u.lastaccess < :cutoff";
    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql .= " ORDER BY u.lastaccess DESC";
    $users = $DB->get_records_sql($sql, $params, 0, 5);
    
    if ($users) {
        foreach ($users as $u) {
            $days_inactive = round((time() - $u->lastaccess) / (24 * 60 * 60));
            $name = fullname((object)['firstname' => $u->firstname, 'lastname' => $u->lastname]);
            $flagged[] = [
                'userid' => $u->id,
                'name' => $name,
                'email' => $u->email,
                'issue' => "Inactive {$days_inactive}d"
            ];
        }
    }
    
    return $flagged;
}

/**
 * Fetches user activity progress for a specific course.
 */
function report_suri_get_user_activity_progress($filters = []) {
    global $DB, $USER;
    $params = [];
    
    // First fetch user completion aggregates
    $sql = "SELECT ue.id as unique_mapping_id, u.id AS userid, u.firstname, u.lastname, u.email, u.department, u.institution, u.city, u.phone1, u.lastaccess, c.id AS courseid, c.fullname AS coursename,
                   COUNT(DISTINCT cm.id) AS total_activities,
                   COUNT(DISTINCT cmc.id) AS completed_activities
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
            JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
            JOIN {course} c ON c.id = ctx.instanceid
            JOIN {enrol} e ON e.courseid = c.id
            JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = u.id
            LEFT JOIN {course_modules} cm ON cm.course = c.id AND cm.completion > 0
            LEFT JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id AND cmc.userid = u.id AND cmc.completionstate IN (1, 2)
            WHERE u.deleted = 0 AND u.suspended = 0";
            
    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['u', 'c']);
    
    $sql .= " GROUP BY ue.id, u.id, u.firstname, u.lastname, u.email, u.department, u.institution, u.city, u.phone1, u.lastaccess, c.id, c.fullname
              ORDER BY u.lastname ASC, u.firstname ASC";
              
    $users = $DB->get_records_sql($sql, $params);
    
    if ($users) {
        $first = reset($users);
        $cid = $first->courseid;
        
        $sql_last_act = "SELECT cmc.userid, cm.id AS cmid, m.name AS modname, cm.instance AS moduleinstance, cmc.timemodified
                         FROM {course_modules_completion} cmc
                         JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                         JOIN {modules} m ON m.id = cm.module
                         WHERE cm.course = ? AND cmc.completionstate IN (1, 2)
                         ORDER BY cmc.timemodified DESC";
                         
        $records = $DB->get_recordset_sql($sql_last_act, [$cid]);
        $user_last_acts = [];
        foreach ($records as $r) {
            if (!isset($user_last_acts[$r->userid])) {
                $user_last_acts[$r->userid] = $r;
            }
        }
        $records->close();
        
        $sql_all_grades = "SELECT gg.id, gg.userid, gi.itemmodule, gi.iteminstance, gg.finalgrade
                           FROM {grade_items} gi
                           JOIN {grade_grades} gg ON gg.itemid = gi.id
                           WHERE gi.courseid = ? AND gi.itemtype = 'mod'";
        $all_grades = $DB->get_records_sql($sql_all_grades, [$cid]);
        
        $grade_map = [];
        foreach ($all_grades as $g) {
            $grade_map[$g->userid . '_' . $g->itemmodule . '_' . $g->iteminstance] = $g->finalgrade;
        }
        
        $modinfo = get_fast_modinfo($cid);
        
        foreach ($users as $u) {
            $u->last_activity_name = 'None';
            $u->last_activity_score = '-';
            
            if (isset($user_last_acts[$u->userid])) {
                $la = $user_last_acts[$u->userid];
                if (isset($modinfo->cms[$la->cmid])) {
                    $u->last_activity_name = $modinfo->cms[$la->cmid]->name;
                }
                $gkey = $u->userid . '_' . $la->modname . '_' . $la->moduleinstance;
                if (isset($grade_map[$gkey]) && $grade_map[$gkey] !== null) {
                    $u->last_activity_score = round($grade_map[$gkey], 2);
                }
            }
        }
    }
    
    return $users;
}

/**
 * Fetches activity completion breakdowns for a specific course.
 */
function report_suri_get_course_activity_breakdown($filters = []) {
    global $DB, $USER;
    $courseid = optional_param('courseid', 0, PARAM_INT);
    if (!$courseid) return [];
    
    $params = ['cid' => $courseid];
    $sql = "SELECT cm.id AS unique_mapping_id, cm.id AS cmid, m.name AS modname, cm.instance AS moduleinstance,
                   COUNT(DISTINCT u.id) AS total_enrolled,
                   COUNT(DISTINCT cmc.id) AS total_completed
            FROM {course_modules} cm
            JOIN {modules} m ON m.id = cm.module
            JOIN {course} c ON c.id = cm.course
            JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
            JOIN {role_assignments} ra ON ra.contextid = ctx.id
            JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
            JOIN {user} u ON u.id = ra.userid AND u.deleted = 0 AND u.suspended = 0
            LEFT JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id AND cmc.userid = u.id AND cmc.completionstate IN (1, 2)
            WHERE cm.course = :cid AND cm.completion > 0";
            
    $sql = report_suri_apply_role_scope($sql, $params, $USER->id);
    $sql = report_suri_apply_dynamic_filters($sql, $params, $filters, ['u', 'c']);
    $sql .= " GROUP BY cm.id, m.name, cm.instance ORDER BY cm.id ASC";
    
    $activities = $DB->get_records_sql($sql, $params);
    
    if ($activities) {
        $modinfo = get_fast_modinfo($courseid);
        $sql_avg_grades = "SELECT gi.id, gi.itemmodule, gi.iteminstance, AVG(gg.finalgrade) as avg_grade
                           FROM {grade_items} gi
                           JOIN {grade_grades} gg ON gg.itemid = gi.id
                           WHERE gi.courseid = ? AND gi.itemtype = 'mod'
                           GROUP BY gi.id, gi.itemmodule, gi.iteminstance";
        $avg_grades = $DB->get_records_sql($sql_avg_grades, [$courseid]);
        
        $grade_map = [];
        foreach ($avg_grades as $ag) {
            $grade_map[$ag->itemmodule . '_' . $ag->iteminstance] = $ag->avg_grade;
        }
        
        foreach ($activities as $a) {
            $a->name = isset($modinfo->cms[$a->cmid]) ? $modinfo->cms[$a->cmid]->name : 'Unknown Activity';
            $gkey = $a->modname . '_' . $a->moduleinstance;
            $a->avg_score = isset($grade_map[$gkey]) && $grade_map[$gkey] !== null ? round($grade_map[$gkey], 2) : '-';
        }
    }
    
    return $activities;
}

/**
 * Fetches all custom user profile fields configured on the Moodle site.
 */
function report_suri_get_custom_profile_fields() {
    global $DB;
    // Check if the table exists just in case
    $dbman = $DB->get_manager();
    if (!$dbman->table_exists('user_info_field')) {
        return [];
    }
    return $DB->get_records('user_info_field', null, 'sortorder ASC', 'id, shortname, name, datatype');
}

/**
 * Fetches custom profile data for a specific batch of users.
 */
function report_suri_get_users_custom_data($userids) {
    global $DB;
    if (empty($userids)) {
        return [];
    }
    
    $user_custom_data = [];
    $chunks = array_chunk($userids, 1000); // Prevent hitting DB parameter limits
    
    foreach ($chunks as $chunk) {
        list($in, $params) = $DB->get_in_or_equal($chunk);
        $sql = "SELECT d.id, d.userid, f.shortname, d.data
                FROM {user_info_data} d
                JOIN {user_info_field} f ON d.fieldid = f.id
                WHERE d.userid $in";
        
        $custom_data = $DB->get_records_sql($sql, $params);
        if ($custom_data) {
            foreach ($custom_data as $cd) {
                if (!isset($user_custom_data[$cd->userid])) {
                    $user_custom_data[$cd->userid] = [];
                }
                $user_custom_data[$cd->userid][$cd->shortname] = $cd->data;
            }
        }
    }
    
    return $user_custom_data;
}

/**
 * Hook to inject the report into the course navigation block.
 * This allows teachers to access "Course Insights" directly from within a course.
 */
function report_suri_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('report/suri:view', $context)) {
        $url = new moodle_url('/report/suri/index.php', ['courseid' => $course->id]);
        $node = $navigation->add(
            'Course Insights',
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'report_suri',
            new pix_icon('i/report', '')
        );
        $node->showinflatnavigation = true;
    }
}

/**
 * Safely executes a custom SQL query provided by the admin.
 * Returns an array with ['status' => true/false, 'data' => records/error].
 */
function report_suri_execute_custom_sql($sql) {
    global $DB;
    
    // 1. Basic capability check (Admin only)
    if (!has_capability('moodle/site:config', context_system::instance())) {
        return ['status' => false, 'data' => 'Access Denied: Only site administrators can run custom SQL queries.'];
    }
    
    $sql = trim($sql);
    if (empty($sql)) {
        return ['status' => false, 'data' => 'Query is empty.'];
    }
    
    // 2. Validate Read-Only (Must start with SELECT or WITH)
    if (!preg_match('/^(SELECT|WITH)\s/i', $sql)) {
        return ['status' => false, 'data' => 'Security Error: Only SELECT queries are permitted.'];
    }
    
    // 3. Blacklist dangerous keywords
    $blacklist = ['/\bUPDATE\b/i', '/\bDELETE\b/i', '/\bINSERT\b/i', '/\bDROP\b/i', '/\bALTER\b/i', '/\bTRUNCATE\b/i', '/\bGRANT\b/i', '/\bREVOKE\b/i'];
    foreach ($blacklist as $pattern) {
        if (preg_match($pattern, $sql)) {
            return ['status' => false, 'data' => 'Security Error: Destructive SQL keywords are not permitted.'];
        }
    }
    
    // 4. Force a limit to prevent memory exhaustion (if not already present)
    if (!preg_match('/LIMIT\s+\d+/i', $sql)) {
        // Moodle's get_records_sql has a built in limit/offset parameter we can use instead of injecting it manually
        // But for safety against complex nested queries we will just pass limit to get_records_sql
    }
    
    try {
        // Limit to 1000 rows max
        $records = $DB->get_records_sql($sql, [], 0, 1000);
        return ['status' => true, 'data' => $records];
    } catch (Exception $e) {
        // Safely catch DB exceptions
        return ['status' => false, 'data' => 'Database Error: ' . $e->getMessage()];
    }
}