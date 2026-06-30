<?php

require_once('../../config.php');
require_once('lib.php');

// Security checks
require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

// Page parameters
$view = optional_param('view', 'dashboard', PARAM_ALPHANUMEXT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$time_spent_days = optional_param('tsd', 30, PARAM_INT);
$interaction_period = optional_param('ip', 'day', PARAM_ALPHA);
$sort = optional_param('sort', '', PARAM_ALPHAEXT);
$dir = optional_param('dir', '', PARAM_ALPHAEXT);

// Page setup
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/report/suri/index.php', ['view' => $view]));
$PAGE->set_title('Reports & Analytics');
$PAGE->set_pagelayout('report');

echo $OUTPUT->header();

// ==============================================================================
// 1. DYNAMIC FILTERS
// ==============================================================================
$filters = isset($_GET['f']) && is_array($_GET['f']) ? $_GET['f'] : [];

// ==============================================================================
// 2. FETCH ALL DATA
// ==============================================================================
$total_users = report_suri_get_total_users_count($filters);
$users_per_role = report_suri_get_users_count_per_role($filters);
$users_per_cohort = report_suri_get_users_count_per_cohort($filters);
$total_students = report_suri_get_total_students_count($filters);
$students_per_cohort = report_suri_get_student_count_per_cohort($filters);
$total_teachers = report_suri_get_total_teachers_count($filters);
$teachers_per_cohort = report_suri_get_teachers_count_per_cohort($filters);
$student_counts  = report_suri_get_student_count_per_course($filters);
$user_directory  = report_suri_get_user_enrollment_directory($filters, $sort, $dir);
$recent_logins   = report_suri_get_recent_logins($filters, $sort, $dir);
$completion_dir  = report_suri_get_course_completion_directory(null, $filters, $sort, $dir);
$cert_completion = report_suri_get_completion_and_certificates($filters);
$active_courses = report_suri_get_most_active_courses($filters);
$time_spent_courses = report_suri_get_course_time_spent($time_spent_days, $filters);
$interaction_trends = report_suri_get_global_interaction_trends($interaction_period, $filters);
$total_courses = report_suri_get_total_courses_count($filters);
$courses_per_category = report_suri_get_courses_per_category($filters);
$users_per_course = report_suri_get_users_per_course($filters);
$avg_completion_all = report_suri_get_avg_completion_time_all($filters);
$avg_completion_course = report_suri_get_avg_completion_time_per_course($filters);
$completion_rate_all = report_suri_get_completion_rate_all($filters);
$completion_rate_course = report_suri_get_completion_rate_per_course($filters);
$quiz_mean_all = report_suri_get_quiz_mean_all($filters);
$quiz_mean_course = report_suri_get_quiz_mean_per_course($filters);
$engagement_all = report_suri_get_engagement_all($filters);
$engagement_course = report_suri_get_engagement_per_course($filters);
$attendance_trends = report_suri_get_attendance_trends_per_course($filters);
$attendance_student = report_suri_get_attendance_trends_per_student($filters);
$perf_segmentation = report_suri_get_performance_segmentation($filters);

// Inject html2pdf (not AMD compliant, keep as raw script)
// Inject html2pdf
echo html_writer::script('', 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js');
?>

<style>
/* CSS Reset and Variables */
:root {
    --primary-50: #EEEDFE;
    --primary-200: #AFA9EC;
    --primary-400: #7F77DD;
    --primary-600: #534AB7;
    --primary-800: #3C3489;
    
    --success: #1D9E75;
    --warning: #EF9F27;
    --danger: #E24B4A;
    --info: #378ADD;
    
    --bg-main: #F7F7F5;
    --card-bg: #FFFFFF;
    --text-main: #2C2C2A;
    --text-muted: #5F5E5A;
    --border: #E8E8E8;
    
    /* Map original primary to primary-600 for buttons/links */
    --primary: var(--primary-600);
}
.suri-dashboard {
    font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
    background-color: var(--bg-main);
    padding: 30px;
    border-radius: 12px;
    color: var(--text-main);
}
.suri-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.suri-header h1 {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
}
.suri-header-actions {
    display: flex;
    gap: 15px;
    align-items: center;
}
.suri-btn-outline {
    background: #fff;
    border: 1px solid var(--border);
    padding: 8px 16px;
    border-radius: 6px;
    color: var(--text-muted);
    font-size: 0.9rem;
    cursor: pointer;
}
.suri-btn-primary {
    background: var(--primary);
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    color: #fff;
    font-size: 0.9rem;
    cursor: pointer;
    font-weight: 500;
}
.suri-tabs {
    display: flex;
    gap: 30px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 30px;
}
.suri-tab {
    padding: 10px 0;
    color: var(--text-muted);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.95rem;
    border-bottom: 2px solid transparent;
}
.suri-tab:hover {
    color: var(--primary);
    text-decoration: none;
}
.suri-tab.active {
    color: var(--primary);
    border-bottom: 2px solid var(--primary);
}
.suri-scorecards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.suri-scorecard {
    background: var(--primary-50);
    padding: 20px;
    border-radius: 8px;
    border: none;
    text-align: center;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    display: block;
    page-break-inside: avoid;
}
.suri-scorecard:hover {
    text-decoration: none;
    color: inherit;
    background: #E4E2FB;
}

/* NEW OVERVIEW RULES */
.suri-overview-grid {
    display: grid;
    gap: 12px;
    margin-bottom: 12px;
}
.suri-card-dense {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px;
    box-shadow: none;
    page-break-inside: avoid;
    display: flex;
    flex-direction: column;
}
.suri-card-header-small {
    font-size: 11px;
    text-transform: uppercase;
    color: var(--text-muted);
    font-weight: 600;
    margin-bottom: 8px;
    letter-spacing: 0.5px;
}
.suri-metric-value {
    font-size: 26px;
    font-weight: 500;
    color: var(--text-main);
    line-height: 1.1;
    margin-bottom: 4px;
}
.suri-metric-trend {
    font-size: 11px;
    font-weight: 500;
    margin-bottom: 8px;
}
.suri-metric-trend.teal { color: var(--success); }
.suri-metric-trend.red { color: var(--danger); }
.suri-sparkline-container {
    margin-top: auto; /* push to bottom */
    width: 100%;
}
.suri-list-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    color: var(--text-main);
}
.suri-list-table th {
    font-size: 11px;
    text-transform: uppercase;
    color: var(--text-muted);
    font-weight: 600;
    padding: 8px 4px 8px 0;
    border-bottom: 1px solid var(--border);
    text-align: left;
}
.suri-list-table td {
    padding: 10px 4px 10px 0;
    border-bottom: 0.5px solid var(--border);
}
.suri-list-table tr:last-child td { border-bottom: none; }
.suri-list-table th.align-right,
.suri-list-table td.align-right { text-align: right; padding-right: 0; }

.suri-html-legend {
    margin-top: 12px;
    font-size: 13px;
    color: var(--text-main);
}
.suri-legend-row {
    display: flex;
    align-items: center;
    padding: 6px 0;
    border-bottom: 0.5px solid var(--border);
}
.suri-legend-row:last-child { border-bottom: none; }
.suri-legend-row .dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 8px;
}
.suri-legend-row .dot.teal { background: var(--success); }
.suri-legend-row .dot.warning { background: var(--warning); }
.suri-legend-row .dot.red { background: var(--danger); }
.suri-legend-row .dot.primary { background: var(--primary-400); }
.suri-legend-row .dot.secondary { background: var(--primary-200); }
.suri-legend-row .label { flex: 1; }
.suri-legend-row .pct { font-weight: 600; margin-right: 12px; }
.suri-legend-row .count { color: var(--text-muted); font-size: 12px; width: 40px; text-align: right; }

.suri-scorecard h3 {
    font-size: 15px;
    font-weight: 500;
    color: var(--text-muted);
    margin-bottom: 10px;
    text-transform: none;
    line-height: 1.2;
}
.suri-scorecard .suri-value {
    font-size: 28px;
    font-weight: 600;
    color: var(--text-main);
}
.suri-grid-3 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.suri-grid-2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.suri-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    box-shadow: none;
    cursor: pointer;
    page-break-inside: avoid;
}
.suri-card:hover {
    box-shadow: none;
    border-color: var(--primary-200);
}
.suri-card-title {
    font-size: 15px;
    font-weight: 500;
    margin-bottom: 20px;
    color: var(--text-main);
    line-height: 1.2;
}
/* Table Styles */
.suri-table-wrapper {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    box-shadow: none;
    overflow-x: auto;
    margin-bottom: 30px;
    page-break-inside: avoid;
}
.suri-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}
.suri-table th {
    font-weight: 500;
    color: var(--text-muted);
    padding: 12px 16px;
    border-bottom: 1px solid var(--border);
    font-size: 11px;
    text-transform: none;
    line-height: 1.2;
    page-break-inside: avoid;
}
.suri-table td {
    padding: 16px;
    border-bottom: 1px solid var(--border);
    color: var(--text-main);
    font-size: 14px;
    page-break-inside: avoid;
    vertical-align: middle;
    font-weight: 400;
    line-height: 1.5;
}
.suri-table tr {
    page-break-inside: avoid;
}
.suri-table tr:last-child td {
    border-bottom: none;
}
/* Custom legend for Donut */
.suri-legend {
    margin-top: 15px;
    font-size: 0.85rem;
}
.suri-legend-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    color: var(--text-muted);
}
.suri-legend-color {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin-right: 8px;
}

/* Column Toggle Styles */
.suri-col-hidden {
    display: none !important;
}
.suri-toggle-wrapper {
    position: relative;
    display: inline-block;
    float: right;
}
.suri-toggle-btn {
    background: transparent;
    border: 1px solid var(--border);
    border-radius: 4px;
    cursor: pointer;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    font-weight: bold;
    font-size: 14px;
    transition: background 0.2s;
}
.suri-toggle-btn:hover {
    background: #f1f5f9;
    color: var(--text-main);
}
.suri-toggle-dropdown {
    display: none;
    position: absolute;
    right: 0;
    top: 30px;
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    min-width: 150px;
    z-index: 100;
    padding: 8px 0;
}
.suri-toggle-dropdown.show {
    display: block;
}
.suri-toggle-dropdown label {
    display: block;
    padding: 8px 16px;
    cursor: pointer;
    font-size: 0.85rem;
    color: var(--text-main);
    font-weight: normal;
}
.suri-toggle-dropdown label:hover {
    background: #f8fafc;
}
.suri-toggle-dropdown input {
    margin-right: 8px;
}
/* Dynamic Filters Styles */
.suri-filter-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}
.suri-filter-chip {
    background: #e2e8f0;
    padding: 6px 12px;
    border-radius: 16px;
    font-size: 0.85rem;
    color: var(--text-main);
    display: flex;
    align-items: center;
    gap: 6px;
}
.suri-filter-chip a {
    color: var(--text-muted);
    text-decoration: none;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
}
.suri-filter-chip a:hover {
    color: #e91e63;
}
.suri-filter-builder {
    position: relative;
}
.suri-filter-dropdown {
    display: none;
    position: absolute;
    top: 40px;
    right: 0;
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    width: 250px;
    z-index: 200;
    padding: 16px;
    text-align: left;
}
.suri-filter-dropdown.show {
    display: block;
}
.suri-filter-dropdown select, .suri-filter-dropdown input {
    width: 100%;
    padding: 8px;
    border: 1px solid var(--border);
    border-radius: 4px;
    margin-bottom: 12px;
    font-size: 0.9rem;
    box-sizing: border-box;
}

/* ApexCharts Custom Tooltip Styles */
.apexcharts-tooltip {
    background: #FFFFFF !important;
    border: 0.5px solid #E8E8E8 !important;
    border-radius: 8px !important;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05) !important;
    padding: 12px !important;
}
.apexcharts-tooltip-title {
    background: transparent !important;
    border-bottom: none !important;
    font-size: 12px !important;
    font-weight: 400 !important;
    color: var(--text-muted) !important;
    padding: 0 0 4px 0 !important;
    margin-bottom: 0 !important;
}
.apexcharts-tooltip-series-group {
    padding: 0 !important;
}
.apexcharts-tooltip-text-y-value {
    font-size: 14px !important;
    font-weight: 600 !important;
    color: var(--text-main) !important;
}
.apexcharts-tooltip-text-y-label {
    font-size: 12px !important;
    font-weight: 400 !important;
    color: var(--text-muted) !important;
}
</style>

<div class="suri-dashboard">
    <div class="suri-header">
        <h1>Reports & Analytics</h1>
        <div class="suri-header-actions">
            <div class="suri-filter-bar">
                <?php
                if (!empty($filters)) {
                    $labels = [
                        'user_department' => 'Dept',
                        'user_institution' => 'Inst',
                        'user_city' => 'City',
                        'user_email' => 'Email',
                        'user_country' => 'Country',
                        'course_name' => 'Course',
                        'role_name' => 'Role'
                    ];
                    foreach ($filters as $index => $f) {
                        $fld = isset($labels[$f['field']]) ? $labels[$f['field']] : htmlspecialchars($f['field']);
                        $op = $f['op'] === 'eq' ? '=' : '≈';
                        $val = htmlspecialchars($f['val']);
                        $display_str = "{$fld} {$op} {$val}";
                        
                        $new_filters = $filters;
                        unset($new_filters[$index]);
                        $query = ['view' => $view];
                        if ($courseid) $query['courseid'] = $courseid;
                        $i = 0;
                        foreach($new_filters as $nf) {
                            $query["f[$i][field]"] = $nf['field'];
                            $query["f[$i][op]"] = $nf['op'];
                            $query["f[$i][val]"] = $nf['val'];
                            $i++;
                        }
                        $remove_url = new moodle_url('/report/suri/index.php', $query);
                        echo "<div class='suri-filter-chip'>{$display_str} <a href='{$remove_url}'>&times;</a></div>";
                    }
                }
                
                if (!empty($sort)) {
                    $sort_labels = [
                        'user_firstname' => 'First Name',
                        'user_lastname' => 'Last Name',
                        'course_name' => 'Course Name',
                        'role' => 'Role',
                        'lastaccess' => 'Last Access',
                        'lastlogin' => 'Last Login',
                        'timecompleted' => 'Time Completed',
                        'progress' => 'Module Progress',
                        'avg_quiz_score' => 'Avg Score'
                    ];
                    $lbl = isset($sort_labels[$sort]) ? $sort_labels[$sort] : htmlspecialchars($sort);
                    $d = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
                    
                    $query = ['view' => $view];
                    if ($courseid) $query['courseid'] = $courseid;
                    if (!empty($filters)) {
                        $i = 0;
                        foreach($filters as $nf) {
                            $query["f[$i][field]"] = $nf['field'];
                            $query["f[$i][op]"] = $nf['op'];
                            $query["f[$i][val]"] = $nf['val'];
                            $i++;
                        }
                    }
                    $remove_url = new moodle_url('/report/suri/index.php', $query);
                    echo "<div class='suri-filter-chip' style='background-color: var(--info); color: white; border-color: var(--info);'>Sort: {$lbl} ({$d}) <a style='color: white;' href='{$remove_url}'>&times;</a></div>";
                }
                ?>
                <div class="suri-filter-builder">
                    <button class="suri-btn-outline" id="suri-add-filter-btn">+ Add Filter</button>
                    <div class="suri-filter-dropdown" id="suri-filter-dropdown">
                        <form method="GET" action="index.php">
                            <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
                            <?php if ($courseid): ?>
                                <input type="hidden" name="courseid" value="<?php echo (int)$courseid; ?>">
                            <?php endif; ?>
                            
                            <?php
                            if (!empty($filters)) {
                                foreach ($filters as $index => $f) {
                                    echo "<input type='hidden' name='f[{$index}][field]' value='" . htmlspecialchars($f['field']) . "'>";
                                    echo "<input type='hidden' name='f[{$index}][op]' value='" . htmlspecialchars($f['op']) . "'>";
                                    echo "<input type='hidden' name='f[{$index}][val]' value='" . htmlspecialchars($f['val']) . "'>";
                                }
                            }
                            $next_index = !empty($filters) ? max(array_keys($filters)) + 1 : 0;
                            ?>
                            
                            <label style="font-size:0.85rem;color:var(--text-muted);display:block;margin-bottom:4px;">Field</label>
                            <select name="f[<?php echo $next_index; ?>][field]" required>
                                <option value="user_department">User Department</option>
                                <option value="user_institution">User Institution</option>
                                <option value="user_city">User City</option>
                                <option value="user_email">User Email</option>
                                <option value="user_country">User Country</option>
                                <option value="course_name">Course Name</option>
                                <option value="role_name">Role Name</option>
                            </select>
                            
                            <label style="font-size:0.85rem;color:var(--text-muted);display:block;margin-bottom:4px;">Operator</label>
                            <select name="f[<?php echo $next_index; ?>][op]" required>
                                <option value="eq">Equals</option>
                                <option value="contains">Contains</option>
                            </select>
                            
                            <label style="font-size:0.85rem;color:var(--text-muted);display:block;margin-bottom:4px;">Value</label>
                            <input type="text" name="f[<?php echo $next_index; ?>][val]" required placeholder="Enter value...">
                            
                            <button type="submit" class="suri-btn-primary" style="width:100%;">Apply</button>
                        </form>
                    </div>
                </div>
                
                <div class="suri-filter-builder">
                    <button class="suri-btn-outline" id="suri-add-sort-btn">Sort By</button>
                    <div class="suri-filter-dropdown" id="suri-sort-dropdown">
                        <form method="GET" action="index.php">
                            <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
                            <?php if ($courseid): ?>
                                <input type="hidden" name="courseid" value="<?php echo (int)$courseid; ?>">
                            <?php endif; ?>
                            
                            <?php
                            if (!empty($filters)) {
                                foreach ($filters as $index => $f) {
                                    echo "<input type='hidden' name='f[{$index}][field]' value='" . htmlspecialchars($f['field']) . "'>";
                                    echo "<input type='hidden' name='f[{$index}][op]' value='" . htmlspecialchars($f['op']) . "'>";
                                    echo "<input type='hidden' name='f[{$index}][val]' value='" . htmlspecialchars($f['val']) . "'>";
                                }
                            }
                            ?>
                            
                            <label style="font-size:0.85rem;color:var(--text-muted);display:block;margin-bottom:4px;">Sort By</label>
                            <select name="sort" required>
                                <option value="user_firstname" <?php echo $sort == 'user_firstname' ? 'selected' : ''; ?>>First Name</option>
                                <option value="user_lastname" <?php echo $sort == 'user_lastname' ? 'selected' : ''; ?>>Last Name</option>
                                <option value="course_name" <?php echo $sort == 'course_name' ? 'selected' : ''; ?>>Course Name</option>
                                <option value="role" <?php echo $sort == 'role' ? 'selected' : ''; ?>>Role</option>
                                <option value="lastaccess" <?php echo $sort == 'lastaccess' ? 'selected' : ''; ?>>Last Access</option>
                                <option value="progress" <?php echo $sort == 'progress' ? 'selected' : ''; ?>>Module Progress</option>
                                <option value="avg_quiz_score" <?php echo $sort == 'avg_quiz_score' ? 'selected' : ''; ?>>Avg Quiz Score</option>
                            </select>
                            
                            <label style="font-size:0.85rem;color:var(--text-muted);display:block;margin-bottom:4px;">Direction</label>
                            <select name="dir" required>
                                <option value="ASC" <?php echo strtoupper($dir) == 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                                <option value="DESC" <?php echo strtoupper($dir) == 'DESC' ? 'selected' : ''; ?>>Descending</option>
                            </select>
                            
                            <button type="submit" class="suri-btn-primary" style="width:100%;">Apply Sort</button>
                        </form>
                    </div>
                </div>
            </div>
            <div style="display: flex; gap: 8px;">
                <button class="suri-btn-outline" id="suri-export-csv-btn">&#10515; Export CSV</button>
                <button class="suri-btn-primary" id="suri-export-pdf-btn">&#10515; Export PDF</button>
            </div>
        </div>
    </div>

    <div class="suri-tabs">
        <a href="?view=dashboard" class="suri-tab <?php echo $view == 'dashboard' ? 'active' : ''; ?>">Overview</a>
        <a href="?view=users" class="suri-tab <?php echo $view == 'users' ? 'active' : ''; ?>">Users</a>
        <a href="?view=courses" class="suri-tab <?php echo $view == 'courses' ? 'active' : ''; ?>">Courses</a>
        <a href="?view=cohorts" class="suri-tab <?php echo $view == 'cohorts' ? 'active' : ''; ?>">Cohorts</a>
        <a href="?view=performance" class="suri-tab <?php echo $view == 'performance' ? 'active' : ''; ?>">Performance</a>
    </div>

<?php
if ($view === 'dashboard') {
    // ---------------------------------------------------------
    // OVERVIEW
    // ---------------------------------------------------------
    
    // Overview Data Prep
    $att_labels = []; $att_series = [];
    if (!empty($attendance_trends)) {
        foreach ($attendance_trends as $row) {
            $att_labels[] = $row->coursename;
            $avg_days = $row->active_students > 0 ? $row->total_attendance_days / $row->active_students : 0;
            $att_series[] = round($avg_days, 1);
        }
    }
    $quiz_labels = []; $quiz_series = [];
    if (!empty($quiz_mean_course)) {
        foreach ($quiz_mean_course as $row) {
            $quiz_labels[] = $row->coursename;
            $quiz_series[] = round($row->mean_score, 1);
        }
    }
    $seg_data = ['High Performers' => 0, 'Average Performers' => 0, 'Low Performers' => 0];
    if (!empty($perf_segmentation)) {
        foreach ($perf_segmentation as $row) {
            $seg_data[$row->segment] = (int)$row->students;
        }
    }
    $total_seg = array_sum($seg_data);
    $healthy_pct = $total_seg > 0 ? round(($seg_data['High Performers']/$total_seg)*100) : 0;
    $risk_pct = $total_seg > 0 ? round(($seg_data['Average Performers']/$total_seg)*100) : 0;
    $crit_pct = $total_seg > 0 ? round(($seg_data['Low Performers']/$total_seg)*100) : 0;

    ?>
    <!-- Row 1: Metrics -->
    <div class="suri-overview-grid" style="grid-template-columns: repeat(3, 1fr);">
        <div class="suri-card-dense" onclick="window.location.href='?view=users'" style="cursor:pointer;">
            <div class="suri-card-header-small">TOTAL USERS</div>
            <div class="suri-metric-value"><?php echo number_format($total_users); ?></div>
            <div class="suri-metric-trend teal">&#9650; +5.2% than last month</div>
            <div id="chart-spark-users" class="suri-sparkline-container"></div>
        </div>
        <div class="suri-card-dense" onclick="window.location.href='?view=courses'" style="cursor:pointer;">
            <div class="suri-card-header-small">TOTAL COURSES</div>
            <div class="suri-metric-value"><?php echo number_format($total_courses); ?></div>
            <div class="suri-metric-trend teal">&#9650; +2 new courses</div>
            <div id="chart-spark-courses" class="suri-sparkline-container"></div>
        </div>
        <div class="suri-card-dense" onclick="window.location.href='?view=performance'" style="cursor:pointer;">
            <div class="suri-card-header-small">COMPLETION RATE</div>
            <div class="suri-metric-value"><?php echo number_format($completion_rate_all, 1); ?>%</div>
            <div class="suri-metric-trend red">&#9660; -1.2% than last month</div>
            <div id="chart-spark-comp" class="suri-sparkline-container"></div>
        </div>
    </div>

    <!-- Row 2: Stacked Bar & Table -->
    <div class="suri-overview-grid" style="grid-template-columns: 2fr 1fr;">
        <div class="suri-card-dense">
            <div class="suri-card-header-small">COMPLETION HEALTH STATUS</div>
            <div id="chart-stacked-health"></div>
            <div class="suri-html-legend">
                 <div class="suri-legend-row"><span class="dot teal"></span><span class="label">High Performers</span><span class="pct"><?php echo $healthy_pct; ?>%</span><span class="count"><?php echo $seg_data['High Performers']; ?></span></div>
                 <div class="suri-legend-row"><span class="dot warning"></span><span class="label">Average Performers</span><span class="pct"><?php echo $risk_pct; ?>%</span><span class="count"><?php echo $seg_data['Average Performers']; ?></span></div>
                 <div class="suri-legend-row"><span class="dot red"></span><span class="label">Low Performers</span><span class="pct"><?php echo $crit_pct; ?>%</span><span class="count"><?php echo $seg_data['Low Performers']; ?></span></div>
            </div>
        </div>
        <div class="suri-card-dense">
            <div class="suri-card-header-small">COURSES NEEDING ATTENTION</div>
            <table class="suri-list-table">
                <thead>
                    <tr><th>COURSE</th><th class="align-right">DROPOFFS</th></tr>
                </thead>
                <tbody>
                    <tr><td>Data Science 101</td><td class="align-right">15</td></tr>
                    <tr><td>Marketing Intro</td><td class="align-right">8</td></tr>
                    <tr><td>UX Design</td><td class="align-right">4</td></tr>
                    <tr><td>Leadership Basics</td><td class="align-right">2</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Row 3: Sparkline, List, Donut -->
    <div class="suri-overview-grid" style="grid-template-columns: repeat(3, 1fr);">
        <div class="suri-card-dense">
            <div class="suri-card-header-small">ACTIVE USERS OVER TIME</div>
            <div id="chart-active-users"></div>
        </div>
        <div class="suri-card-dense">
            <div class="suri-card-header-small">RECENT FLAGS</div>
            <table class="suri-list-table">
                <thead><tr><th>USER</th><th class="align-right">ISSUE</th></tr></thead>
                <tbody>
                    <tr><td>Jane Doe</td><td class="align-right">Failed Quiz 3x</td></tr>
                    <tr><td>John Smith</td><td class="align-right">Inactive 14d</td></tr>
                    <tr><td>Sarah Lee</td><td class="align-right">Inactive 10d</td></tr>
                </tbody>
            </table>
        </div>
        <div class="suri-card-dense">
            <div class="suri-card-header-small">ENGAGEMENT BREAKDOWN</div>
            <div id="chart-donut-engagement"></div>
            <div class="suri-html-legend">
                <div class="suri-legend-row"><span class="dot primary"></span><span class="label">Video Views</span><span class="pct">45%</span><span class="count">4.5k</span></div>
                <div class="suri-legend-row"><span class="dot secondary"></span><span class="label">Quiz Attempts</span><span class="pct">35%</span><span class="count">3.5k</span></div>
                <div class="suri-legend-row"><span class="dot teal"></span><span class="label">Forum Posts</span><span class="pct">20%</span><span class="count">2.0k</span></div>
            </div>
        </div>
    </div>

    <script>
    require(['apexcharts'], function(ApexCharts) {
        // Sparklines (Rules 1 & 2)
        var sparkOpts = {
            chart: { type: 'area', sparkline: { enabled: true }, height: 45 },
            stroke: { width: 2 },
            fill: { type: 'solid', opacity: 0.2 },
            tooltip: { fixed: { enabled: false }, x: { show: false }, marker: { show: false } }
        };

        var optUser = Object.assign({}, sparkOpts, { stroke: { colors: ['#7F77DD'] }, fill: { colors: ['#7F77DD'] }, series: [{ data: [12, 14, 18, 17, 22, 28] }] });
        new ApexCharts(document.querySelector("#chart-spark-users"), optUser).render();

        var optCourse = Object.assign({}, sparkOpts, { stroke: { colors: ['#1D9E75'] }, fill: { colors: ['#1D9E75'] }, series: [{ data: [4, 6, 5, 8, 10, 12] }] });
        new ApexCharts(document.querySelector("#chart-spark-courses"), optCourse).render();

        var optComp = Object.assign({}, sparkOpts, { stroke: { colors: ['#E24B4A'] }, fill: { colors: ['#E24B4A'] }, series: [{ data: [88, 86, 85, 84, 83, 84] }] });
        new ApexCharts(document.querySelector("#chart-spark-comp"), optComp).render();

        // Stacked Bar (Rule 4)
        var optStacked = {
            chart: { type: 'bar', stacked: true, height: 60, toolbar: { show: false }, sparkline: { enabled: true } },
            plotOptions: { bar: { horizontal: true, barHeight: '25%', borderRadius: 4 } },
            colors: ['#1D9E75', '#EF9F27', '#E24B4A'],
            series: [
                { name: 'High Performers', data: [<?php echo $healthy_pct; ?>] },
                { name: 'Average Performers', data: [<?php echo $risk_pct; ?>] },
                { name: 'Low Performers', data: [<?php echo $crit_pct; ?>] }
            ]
        };
        new ApexCharts(document.querySelector("#chart-stacked-health"), optStacked).render();

        // Standalone Trend Chart
        var optTrend = Object.assign({}, sparkOpts, { chart: { type: 'line', sparkline: { enabled: true }, height: 70 }, fill: { opacity: 1 }, stroke: { colors: ['#534AB7'] }, series: [{ data: [120, 140, 135, 150, 180, 200] }] });
        new ApexCharts(document.querySelector("#chart-active-users"), optTrend).render();

        // Compact Donut
        var optDonut = {
            chart: { type: 'donut', height: 120 },
            series: [45, 35, 20],
            colors: ['#7F77DD', '#AFA9EC', '#1D9E75'],
            legend: { show: false },
            dataLabels: { enabled: false },
            plotOptions: { pie: { donut: { size: '75%' } } },
            stroke: { width: 0 },
            tooltip: { enabled: false }
        };
        new ApexCharts(document.querySelector("#chart-donut-engagement"), optDonut).render();
    });
    </script>
    <?php
} elseif ($view === 'users') {
    // ---------------------------------------------------------
    // USERS
    // ---------------------------------------------------------
    
    $role_labels = []; $role_series = [];
    if (!empty($users_per_role)) {
        foreach ($users_per_role as $r) { $role_labels[] = ucfirst($r->shortname); $role_series[] = (int)$r->usercount; }
    }
    ?>
    <!-- Row 1: Dense Metrics and Donut -->
    <div class="suri-overview-grid" style="grid-template-columns: repeat(4, 1fr);">
        <div class="suri-card-dense" style="background: #EEEDFE; color: #3C3489; border-color: transparent;">
            <div class="suri-card-header-small" style="color: #3C3489;">TOTAL USERS</div>
            <div class="suri-metric-value" style="color: #3C3489;"><?php echo number_format($total_users); ?></div>
            <div class="suri-sparkline-container" id="chart-spark-tab-users" style="margin-top: 10px;"></div>
        </div>
        <div class="suri-card-dense" style="background: #E1F5EE; color: #085041; border-color: transparent;">
            <div class="suri-card-header-small" style="color: #085041;">TOTAL STUDENTS</div>
            <div class="suri-metric-value" style="color: #085041;"><?php echo number_format($total_students); ?></div>
            <div class="suri-sparkline-container" id="chart-spark-tab-students" style="margin-top: 10px;"></div>
        </div>
        <div class="suri-card-dense" style="background: #FAEEDA; color: #633806; border-color: transparent;">
            <div class="suri-card-header-small" style="color: #633806;">TOTAL TEACHERS</div>
            <div class="suri-metric-value" style="color: #633806;"><?php echo number_format($total_teachers); ?></div>
            <div class="suri-sparkline-container" id="chart-spark-tab-teachers" style="margin-top: 10px;"></div>
        </div>
        <div class="suri-card-dense" style="background: #FBEAF0; color: #72243E; border-color: transparent;">
            <div class="suri-card-header-small" style="color: #72243E;">USERS BY ROLE</div>
            <div id="chart-roles" style="margin-top: -10px;"></div>
        </div>
    </div>

    <!-- Row 2: Dense Tables -->
    <div class="suri-overview-grid" style="grid-template-columns: repeat(2, 1fr);">
        <div class="suri-card-dense">
            <div class="suri-card-header-small">RECENT LOGINS (LAST 7 DAYS)</div>
            <table class="suri-list-table">
                <thead><tr><th>Name</th><th>Email</th><th class="align-right">Latest Login</th></tr></thead>
                <tbody>
                    <?php
                    if (!empty($recent_logins)) {
                        foreach (array_slice($recent_logins, 0, 5) as $row) {
                            $fullname = fullname((object)['firstname' => $row->firstname, 'lastname' => $row->lastname]);
                            echo "<tr><td>{$fullname}</td><td>{$row->email}</td><td class='align-right'>".userdate($row->lastlogin)."</td></tr>";
                        }
                    } else {
                        echo "<tr><td colspan='3'>No recent logins</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <div class="suri-card-dense">
            <div class="suri-card-header-small">ATTENDANCE TRENDS</div>
            <table class="suri-list-table">
                <thead><tr><th>Name</th><th>Course</th><th class="align-right">Active Days</th></tr></thead>
                <tbody>
                    <?php
                    if (!empty($attendance_student)) {
                        foreach (array_slice($attendance_student, 0, 5) as $row) { 
                            $fullname = fullname((object)['firstname' => $row->firstname, 'lastname' => $row->lastname]);
                            echo "<tr><td>{$fullname}</td><td>{$row->coursename}</td><td class='align-right'>{$row->active_days} days</td></tr>";
                        }
                    } else {
                        echo "<tr><td colspan='3'>No attendance data</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="suri-card-dense" style="margin-top: 12px;">
        <div class="suri-card-header-small" style="display:flex; justify-content:space-between; align-items:center;">
            <span>COMPLETE USER DIRECTORY</span>
            <div class="suri-toggle-wrapper">
                <button class="suri-toggle-btn" id="suri-user-col-btn" title="Toggle Columns" style="background:none; border:none; color:var(--primary); font-size:16px; font-weight:bold; cursor:pointer; padding:0 8px;">+</button>
                <div class="suri-toggle-dropdown" id="suri-user-col-dropdown">
                    <label><input type="checkbox" class="suri-col-cb" value="department"> Department</label>
                    <label><input type="checkbox" class="suri-col-cb" value="institution"> Institution</label>
                    <label><input type="checkbox" class="suri-col-cb" value="city"> City</label>
                    <label><input type="checkbox" class="suri-col-cb" value="phone"> Phone</label>
                    <label><input type="checkbox" class="suri-col-cb" value="lastaccess"> Last Access</label>
                </div>
            </div>
        </div>
        <table class="suri-list-table" id="suri-user-dir-table">
            <thead>
                <tr>
                    <th data-col="name">Name</th>
                    <th data-col="email">Email</th>
                    <th data-col="course">Course Enrolled</th>
                    <th data-col="role">Role</th>
                    <th data-col="department" class="suri-col-hidden">Department</th>
                    <th data-col="institution" class="suri-col-hidden">Institution</th>
                    <th data-col="city" class="suri-col-hidden">City</th>
                    <th data-col="phone" class="suri-col-hidden">Phone</th>
                    <th data-col="lastaccess" class="suri-col-hidden">Last Access</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!empty($user_directory)) {
                    foreach ($user_directory as $row) {
                        $fullname = fullname((object)['firstname' => $row->firstname, 'lastname' => $row->lastname]);
                        $lastaccess = $row->lastaccess ? userdate($row->lastaccess) : 'Never';
                        echo "<tr>
                                <td data-col='name'>{$fullname}</td>
                                <td data-col='email'>{$row->email}</td>
                                <td data-col='course'>{$row->coursename}</td>
                                <td data-col='role'>" . ucfirst($row->role) . "</td>
                                <td data-col='department' class='suri-col-hidden'>{$row->department}</td>
                                <td data-col='institution' class='suri-col-hidden'>{$row->institution}</td>
                                <td data-col='city' class='suri-col-hidden'>{$row->city}</td>
                                <td data-col='phone' class='suri-col-hidden'>{$row->phone1}</td>
                                <td data-col='lastaccess' class='suri-col-hidden'>{$lastaccess}</td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='9'>No users found.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <script>
    require(['apexcharts'], function(ApexCharts) {
        try {
            var rSeries = <?php echo empty($role_series) ? '[1]' : json_encode($role_series); ?>;
            var rLabels = <?php echo empty($role_labels) ? '["No Data"]' : json_encode($role_labels); ?>;
            var optionsRoles = {
                series: rSeries,
                chart: { type: 'donut', height: 160 },
                labels: rLabels,
                colors: ['#7F77DD', '#AFA9EC', '#1D9E75', '#EF9F27', '#E24B4A'],
                legend: { show: true, position: 'bottom', fontSize: '11px', itemMargin: { horizontal: 5, vertical: 2 } },
                dataLabels: { enabled: false },
                plotOptions: { pie: { donut: { size: '65%' } } },
                stroke: { width: 0 },
                tooltip: { enabled: true }
            };
            if (document.getElementById("chart-roles")) {
                new ApexCharts(document.getElementById("chart-roles"), optionsRoles).render();
            }
        } catch(e) { console.error("Roles chart error:", e); }

        var sparkOpts = {
            chart: { type: 'area', sparkline: { enabled: true }, height: 45 },
            stroke: { width: 2 },
            fill: { type: 'solid', opacity: 0.2 },
            tooltip: { fixed: { enabled: false }, x: { show: false }, marker: { show: false } }
        };

        try {
            var optUserTab = Object.assign({}, sparkOpts, { stroke: { colors: ['#3C3489'] }, fill: { colors: ['#3C3489'] }, series: [{ data: [15, 18, 16, 20, 24, 28] }] });
            if (document.getElementById("chart-spark-tab-users")) new ApexCharts(document.getElementById("chart-spark-tab-users"), optUserTab).render();
        } catch(e) { console.error(e); }

        try {
            var optStudentTab = Object.assign({}, sparkOpts, { stroke: { colors: ['#085041'] }, fill: { colors: ['#085041'] }, series: [{ data: [12, 14, 13, 16, 20, 25] }] });
            if (document.getElementById("chart-spark-tab-students")) new ApexCharts(document.getElementById("chart-spark-tab-students"), optStudentTab).render();
        } catch(e) { console.error(e); }

        try {
            var optTeacherTab = Object.assign({}, sparkOpts, { stroke: { colors: ['#633806'] }, fill: { colors: ['#633806'] }, series: [{ data: [3, 4, 3, 4, 4, 3] }] });
            if (document.getElementById("chart-spark-tab-teachers")) new ApexCharts(document.getElementById("chart-spark-tab-teachers"), optTeacherTab).render();
        } catch(e) { console.error(e); }
    });
    </script>
    <?php
} elseif ($view === 'courses') {
    // ---------------------------------------------------------
    // COURSES
    // ---------------------------------------------------------
    
    echo '<div class="suri-scorecards">';
    echo '<div class="suri-scorecard" style="cursor:default;"><h3>Total Courses</h3><div class="suri-value">' . number_format($total_courses) . '</div></div>';
    echo '</div>';

    $cat_labels = []; $cat_series = [];
    if (!empty($courses_per_category)) {
        foreach ($courses_per_category as $c) { $cat_labels[] = $c->categoryname; $cat_series[] = (int)$c->coursecount; }
    }
    $active_labels = []; $active_series = [];
    if (!empty($active_courses)) {
        foreach (array_slice($active_courses, 0, 5) as $c) { $active_labels[] = $c->coursename; $active_series[] = (int)$c->views; }
    }
    ?>
    <div class="suri-grid-2">
        <div class="suri-card" style="cursor:default;">
            <div class="suri-card-title">Courses by Category</div>
            <div id="chart-categories"></div>
        </div>
        <div class="suri-card" style="cursor:default;">
            <div class="suri-card-title">Top 5 Most Active Courses</div>
            <div id="chart-active"></div>
        </div>
    </div>
    
    <?php
    $base_query = "?view=courses";
    if ($courseid) $base_query .= "&courseid=" . $courseid;
    if (!empty($filters)) {
        $i = 0;
        foreach($filters as $nf) {
            $base_query .= "&f[$i][field]=" . urlencode($nf['field']) . "&f[$i][op]=" . urlencode($nf['op']) . "&f[$i][val]=" . urlencode($nf['val']);
            $i++;
        }
    }
    $link_ip_day = $base_query . "&tsd=" . $time_spent_days . "&ip=day";
    $link_ip_week = $base_query . "&tsd=" . $time_spent_days . "&ip=week";
    $link_ip_month = $base_query . "&tsd=" . $time_spent_days . "&ip=month";

    $link_tsd_7 = $base_query . "&ip=" . $interaction_period . "&tsd=7";
    $link_tsd_30 = $base_query . "&ip=" . $interaction_period . "&tsd=30";
    $link_tsd_90 = $base_query . "&ip=" . $interaction_period . "&tsd=90";
    $link_tsd_365 = $base_query . "&ip=" . $interaction_period . "&tsd=365";
    ?>
    <div class="suri-card" style="cursor:default; margin-bottom: 30px;">
        <div class="suri-card-title" style="display:flex; justify-content:space-between; align-items:center;">
            <span>Global Student Interactions</span>
            <div style="font-size: 0.85rem;">
                <a href="<?php echo $link_ip_day; ?>" class="suri-btn-outline" style="padding: 4px 8px; text-decoration:none; <?php echo $interaction_period == 'day' ? 'background:var(--primary);color:#fff;' : ''; ?>">Daily</a>
                <a href="<?php echo $link_ip_week; ?>" class="suri-btn-outline" style="padding: 4px 8px; text-decoration:none; <?php echo $interaction_period == 'week' ? 'background:var(--primary);color:#fff;' : ''; ?>">Weekly</a>
                <a href="<?php echo $link_ip_month; ?>" class="suri-btn-outline" style="padding: 4px 8px; text-decoration:none; <?php echo $interaction_period == 'month' ? 'background:var(--primary);color:#fff;' : ''; ?>">Monthly</a>
            </div>
        </div>
        <div id="chart-interactions"></div>
    </div>
    
    <div class="suri-table-wrapper">
        <div class="suri-card-title">Course Directory</div>
        <table class="suri-table" id="suri-course-dir-table">
            <thead>
                <tr>
                    <th data-col="coursename">Course Name</th>
                    <th data-col="startdate" class="suri-col-hidden">Start Date</th>
                    <th data-col="enddate" class="suri-col-hidden">End Date</th>
                    <th data-col="visible" class="suri-col-hidden">Visible</th>
                    <th style="width: 150px;">Action</th>
                    <th style="width: 40px; text-align: right; padding-right: 16px;">
                        <div class="suri-toggle-wrapper">
                            <button class="suri-toggle-btn" title="Toggle Columns">+</button>
                            <div class="suri-toggle-dropdown">
                                <label><input type="checkbox" class="suri-col-cb" value="startdate"> Start Date</label>
                                <label><input type="checkbox" class="suri-col-cb" value="enddate"> End Date</label>
                                <label><input type="checkbox" class="suri-col-cb" value="visible"> Visible</label>
                            </div>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php
                if(!empty($users_per_course)){
                    foreach($users_per_course as $row){
                        $courselink = html_writer::link("?view=course_detail&courseid={$row->id}", 'View Insights &rarr;', ['class' => 'suri-btn-outline', 'style' => 'text-decoration: none; font-size: 0.8rem; padding: 4px 10px;']);
                        $startdate = $row->startdate ? userdate($row->startdate) : 'N/A';
                        $enddate = $row->enddate ? userdate($row->enddate) : 'N/A';
                        $visible = $row->visible ? 'Yes' : 'No';
                        echo "<tr>
                                <td data-col='coursename'><strong>{$row->coursename}</strong></td>
                                <td data-col='startdate' class='suri-col-hidden'>{$startdate}</td>
                                <td data-col='enddate' class='suri-col-hidden'>{$enddate}</td>
                                <td data-col='visible' class='suri-col-hidden'>{$visible}</td>
                                <td>{$courselink}</td>
                                <td></td>
                              </tr>";
                    }
                }
                ?>
            </tbody>
        </table>
    </div>

    <div class="suri-table-wrapper">
        <div class="suri-card-title">Total Students and Teachers per Course</div>
        <table class="suri-table">
            <thead>
                <tr>
                    <th data-col="coursename">Course Name</th>
                    <th data-col="students">Students Enrolled</th>
                    <th data-col="teachers">Teachers Assigned</th>
                    <th data-col="startdate" class="suri-col-hidden">Start Date</th>
                    <th data-col="enddate" class="suri-col-hidden">End Date</th>
                    <th style="width: 40px; text-align: right; padding-right: 16px;">
                        <div class="suri-toggle-wrapper">
                            <button class="suri-toggle-btn" title="Toggle Columns">+</button>
                            <div class="suri-toggle-dropdown">
                                <label><input type="checkbox" class="suri-col-cb" value="startdate"> Start Date</label>
                                <label><input type="checkbox" class="suri-col-cb" value="enddate"> End Date</label>
                            </div>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php
                if(!empty($users_per_course)){
                    foreach($users_per_course as $row){
                        $startdate = $row->startdate ? userdate($row->startdate) : 'N/A';
                        $enddate = $row->enddate ? userdate($row->enddate) : 'N/A';
                        echo "<tr>
                                <td data-col='coursename'>{$row->coursename}</td>
                                <td data-col='students'>{$row->studentcount}</td>
                                <td data-col='teachers'>{$row->teachercount}</td>
                                <td data-col='startdate' class='suri-col-hidden'>{$startdate}</td>
                                <td data-col='enddate' class='suri-col-hidden'>{$enddate}</td>
                                <td></td>
                              </tr>";
                    }
                }
                ?>
            </tbody>
        </table>
    </div>

    <div class="suri-table-wrapper">
        <div class="suri-card-title" style="display:flex; justify-content:space-between; align-items:center;">
            <span>Accumulative Time Spent by Students (Last <?php echo $time_spent_days; ?> days)</span>
            <div style="font-size: 0.85rem; font-weight: normal;">
                <a href="<?php echo $link_tsd_7; ?>" class="suri-btn-outline" style="padding: 4px 8px; text-decoration:none; <?php echo $time_spent_days == 7 ? 'background:var(--primary);color:#fff;' : ''; ?>">7 Days</a>
                <a href="<?php echo $link_tsd_30; ?>" class="suri-btn-outline" style="padding: 4px 8px; text-decoration:none; <?php echo $time_spent_days == 30 ? 'background:var(--primary);color:#fff;' : ''; ?>">30 Days</a>
                <a href="<?php echo $link_tsd_90; ?>" class="suri-btn-outline" style="padding: 4px 8px; text-decoration:none; <?php echo $time_spent_days == 90 ? 'background:var(--primary);color:#fff;' : ''; ?>">90 Days</a>
                <a href="<?php echo $link_tsd_365; ?>" class="suri-btn-outline" style="padding: 4px 8px; text-decoration:none; <?php echo $time_spent_days == 365 ? 'background:var(--primary);color:#fff;' : ''; ?>">1 Year</a>
            </div>
        </div>
        <table class="suri-table">
            <thead><tr><th>Course Name</th><th>Total Estimated Time Spent</th></tr></thead>
            <tbody>
                <?php
                if(!empty($time_spent_courses)){
                    foreach($time_spent_courses as $row){
                        $hours = floor($row->totalseconds / 3600);
                        $minutes = floor(($row->totalseconds % 3600) / 60);
                        echo "<tr><td>{$row->coursename}</td><td>{$hours} hr {$minutes} min</td></tr>";
                    }
                }
                ?>
            </tbody>
        </table>
    </div>

    <script>
    require(['apexcharts'], function(ApexCharts) {
        var optionsCat = {
            series: [{ name: 'Courses', data: <?php echo json_encode($cat_series); ?> }],
            chart: { type: 'bar', height: 300, toolbar: { show: false } },
            xaxis: { categories: <?php echo json_encode($cat_labels); ?> },
            colors: ['#7F77DD'],
            plotOptions: { bar: { borderRadius: 4, columnWidth: '40%' } }
        };
        new ApexCharts(document.querySelector("#chart-categories"), optionsCat).render();

        var optionsActive = {
            series: [{ name: 'Interactions', data: <?php echo json_encode($active_series); ?> }],
            chart: { type: 'bar', height: 300, toolbar: { show: false } },
            plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
            xaxis: { categories: <?php echo json_encode($active_labels); ?> },
            colors: ['#7F77DD']
        };
        new ApexCharts(document.querySelector("#chart-active"), optionsActive).render();
        
        var optionsInt = {
            series: <?php echo json_encode($interaction_trends); ?>,
            chart: { type: 'line', height: 300, toolbar: { show: false } },
            stroke: { curve: 'smooth', width: 2 },
            xaxis: { type: 'category', labels: { trim: true, hideOverlappingLabels: true } },
            colors: ['#7F77DD', '#378ADD', '#1D9E75', '#EF9F27', '#E24B4A'],
            tooltip: { shared: true, intersect: false },
            legend: { position: 'top' }
        };
        new ApexCharts(document.querySelector("#chart-interactions"), optionsInt).render();
    });
    </script>
    <?php
} elseif ($view === 'cohorts') {
    // ---------------------------------------------------------
    // COHORTS
    // ---------------------------------------------------------

    $cohort_labels = []; $cohort_series = [];
    if (!empty($users_per_cohort)) {
        foreach ($users_per_cohort as $c) { $cohort_labels[] = $c->name; $cohort_series[] = (int)$c->usercount; }
    }
    
    echo '<div class="suri-scorecards">';
    echo '<div class="suri-scorecard" style="cursor:default;"><h3>Total Cohorts</h3><div class="suri-value">' . count($cohort_labels) . '</div></div>';
    echo '</div>';
    ?>
    <div class="suri-card" style="margin-bottom: 30px; cursor:default;">
        <div class="suri-card-title">Users by Cohort</div>
        <div id="chart-cohorts"></div>
    </div>
    <div class="suri-grid-2">
        <div class="suri-table-wrapper" style="margin-bottom:0;">
            <div class="suri-card-title">Students per Cohort</div>
            <table class="suri-table">
                <thead><tr><th>Cohort Name</th><th>Students</th></tr></thead>
                <tbody>
                    <?php
                    if(!empty($students_per_cohort)){
                        foreach($students_per_cohort as $row){ echo "<tr><td>{$row->name}</td><td>{$row->studentcount}</td></tr>"; }
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <div class="suri-table-wrapper" style="margin-bottom:0;">
            <div class="suri-card-title">Teachers per Cohort</div>
            <table class="suri-table">
                <thead><tr><th>Cohort Name</th><th>Teachers</th></tr></thead>
                <tbody>
                    <?php
                    if(!empty($teachers_per_cohort)){
                        foreach($teachers_per_cohort as $row){ echo "<tr><td>{$row->name}</td><td>{$row->teachercount}</td></tr>"; }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
    require(['apexcharts'], function(ApexCharts) {
        var optionsCohorts = {
            series: [{ name: 'Users', data: <?php echo json_encode($cohort_series); ?> }],
            chart: { type: 'bar', height: 300, toolbar: { show: false } },
            xaxis: { categories: <?php echo json_encode($cohort_labels); ?> },
            colors: ['#7F77DD'],
            plotOptions: { bar: { borderRadius: 4, columnWidth: '40%' } }
        };
        new ApexCharts(document.querySelector("#chart-cohorts"), optionsCohorts).render();
    });
    </script>
    <?php
} elseif ($view === 'performance') {
    // ---------------------------------------------------------
    // PERFORMANCE
    // ---------------------------------------------------------
    
    echo '<div class="suri-scorecards">';
    echo '<div class="suri-scorecard" style="cursor:default;"><h3>Completion Rate</h3><div class="suri-value">' . number_format($completion_rate_all, 1) . '%</div></div>';
    echo '<div class="suri-scorecard" style="cursor:default;"><h3>Avg Quiz Score</h3><div class="suri-value">' . number_format($quiz_mean_all, 1) . '%</div></div>';
    echo '<div class="suri-scorecard" style="cursor:default;"><h3>Avg Engagement</h3><div class="suri-value">' . number_format($engagement_all, 1) . '</div></div>';
    
    $avg_hrs = floor($avg_completion_all / 3600);
    $avg_mins = floor(($avg_completion_all % 3600) / 60);
    echo '<div class="suri-scorecard" style="cursor:default;"><h3>Avg Completion Time</h3><div class="suri-value">' . $avg_hrs . 'h ' . $avg_mins . 'm</div></div>';
    echo '</div>';

    ?>
    <div class="suri-table-wrapper" style="margin-bottom: 30px;">
        <div class="suri-card-title">Performance & Engagement by Course</div>
        <table class="suri-table">
            <thead>
                <tr>
                    <th>Course Name</th>
                    <th>Completion Rate</th>
                    <th>Average Quiz Mean</th>
                    <th>Engagement (Interactions/Student)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $perf_table = [];
                if(!empty($completion_rate_course)){
                    foreach($completion_rate_course as $row){
                        $rate = $row->total_tracked > 0 ? ($row->total_completed / $row->total_tracked) * 100 : 0;
                        $perf_table[$row->coursename]['comp'] = number_format($rate, 1).'%';
                    }
                }
                if(!empty($quiz_mean_course)){
                    foreach($quiz_mean_course as $row){ $perf_table[$row->coursename]['quiz'] = number_format($row->mean_score, 1).'%'; }
                }
                if(!empty($engagement_course)){
                    foreach($engagement_course as $row){
                        $eng = $row->active_students > 0 ? $row->total_logs / $row->active_students : 0;
                        $perf_table[$row->coursename]['eng'] = number_format($eng, 1);
                    }
                }
                foreach ($perf_table as $course => $data) {
                    $comp = $data['comp'] ?? '0.0%';
                    $quiz = $data['quiz'] ?? '0.0%';
                    $eng = $data['eng'] ?? '0.0';
                    echo "<tr><td>{$course}</td><td>{$comp}</td><td>{$quiz}</td><td>{$eng}</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
    
    <div class="suri-table-wrapper">
        <div class="suri-card-title">Course Completion Directory</div>
        <table class="suri-table">
            <thead><tr><th>Name</th><th>Course</th><th>Time Enrolled</th><th>Time Completed</th></tr></thead>
            <tbody>
                <?php
                if (!empty($completion_dir)) {
                    foreach ($completion_dir as $row) {
                        $fullname = fullname((object)['firstname' => $row->firstname, 'lastname' => $row->lastname]);
                        $enrolled = $row->timeenrolled ? userdate($row->timeenrolled) : 'Never';
                        $completed = $row->timecompleted ? userdate($row->timecompleted) : 'Incomplete';
                        echo "<tr><td>{$fullname}</td><td>{$row->coursename}</td><td>{$enrolled}</td><td>{$completed}</td></tr>";
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
    
    <div class="suri-table-wrapper">
        <div class="suri-card-title">Module Completion & Certificates</div>
        <table class="suri-table">
            <thead>
                <tr>
                    <th data-col="name">Name</th>
                    <th data-col="email">Email</th>
                    <th data-col="course" class="suri-col-hidden">Course</th>
                    <th data-col="modules">Modules Completed</th>
                    <th data-col="certificate" class="suri-col-hidden">Certificate Date</th>
                    <th style="width: 40px; text-align: right; padding-right: 16px;">
                        <div class="suri-toggle-wrapper">
                            <button class="suri-toggle-btn" title="Toggle Columns">+</button>
                            <div class="suri-toggle-dropdown">
                                <label><input type="checkbox" class="suri-col-cb" value="course"> Course</label>
                                <label><input type="checkbox" class="suri-col-cb" value="certificate"> Certificate Date</label>
                            </div>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!empty($cert_completion)) {
                    foreach ($cert_completion as $row) {
                        $fullname = fullname((object)['firstname' => $row->firstname, 'lastname' => $row->lastname]);
                        $module_status = "{$row->completed_modules} / {$row->total_modules}";
                        echo "<tr>
                                <td data-col='name'>{$fullname}</td>
                                <td data-col='email'>{$row->email}</td>
                                <td data-col='course' class='suri-col-hidden'>{$row->coursename}</td>
                                <td data-col='modules'>{$module_status}</td>
                                <td data-col='certificate' class='suri-col-hidden'>".($row->certificate_date ? userdate($row->certificate_date) : 'Not Issued')."</td>
                                <td></td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='6'>No data available</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
} elseif ($view === 'course_detail') {
    // ---------------------------------------------------------
    // SINGLE COURSE DETAIL
    // ---------------------------------------------------------
    $stats = report_suri_get_single_course_summary($courseid);
    if (!$stats) {
        global $DB, $USER;
        $params = ['courseid' => $courseid];
        $check_sql = "SELECT c.id FROM {course} c WHERE c.id = :courseid";
        $check_sql = report_suri_apply_role_scope($check_sql, $params, $USER->id);
        
        echo "<div style='background:red; color:white; padding:20px; font-weight:bold;'>";
        echo "COURSE NOT FOUND OR ACCESS DENIED.<br>";
        echo "CourseID Passed: " . htmlspecialchars($courseid) . "<br>";
        echo "Check SQL: " . htmlspecialchars($check_sql) . "<br>";
        echo "Params: <pre>" . print_r($params, true) . "</pre><br>";
        $course_exists = $DB->get_record('course', ['id' => $courseid]);
        echo "Does course exist in DB? " . ($course_exists ? "YES" : "NO") . "<br>";
        echo "</div>";
        
        echo $OUTPUT->notification('Course not found or access denied.', 'notifyproblem');
    } else {
        $course_users = report_suri_get_single_course_users($courseid);
        
        // Find attendance trend for this course from the globally fetched array
        $attendance_trend_val = 0;
        if (!empty($attendance_trends)) {
            foreach ($attendance_trends as $att) {
                if ($att->coursename === $stats['coursename']) {
                    $attendance_trend_val = $att->active_students > 0 ? $att->total_attendance_days / $att->active_students : 0;
                    break;
                }
            }
        }

        echo '<a href="?view=courses" class="suri-btn-outline" style="margin-bottom: 20px; display: inline-block;">&larr; Back to Courses</a>';
        echo $OUTPUT->heading('Course Insights: ' . $stats['coursename'], 3);
        
        $rate = $stats['total_students'] > 0 ? ($stats['completed_students'] / $stats['total_students']) * 100 : 0;
        $hours = floor($stats['avg_time_seconds'] / 3600);
        $minutes = floor(($stats['avg_time_seconds'] % 3600) / 60);
        
        echo '<div class="suri-scorecards">';
        echo '<div class="suri-scorecard" style="cursor:default;"><h3>Enrolled Students</h3><div class="suri-value">' . $stats['total_students'] . '</div></div>';
        echo '<div class="suri-scorecard" style="cursor:default;"><h3>Overall Completion Rate</h3><div class="suri-value">' . number_format($rate, 1) . '%</div></div>';
        echo '<div class="suri-scorecard" style="cursor:default;"><h3>Avg Completion Time</h3><div class="suri-value">' . $hours . 'h ' . $minutes . 'm</div></div>';
        echo '<div class="suri-scorecard" style="cursor:default;"><h3>Attendance (Active Days)</h3><div class="suri-value">' . number_format($attendance_trend_val, 1) . '</div></div>';
        echo '<div class="suri-scorecard" style="cursor:default;"><h3>Total Interactions</h3><div class="suri-value">' . number_format($stats['total_interactions']) . '</div></div>';
        echo '</div>';
        
        ?>
        <div class="suri-grid-2">
            <div class="suri-card" style="cursor:default;">
                <div class="suri-card-title">Completion Status Ratio</div>
                <div id="chart-course-comp"></div>
            </div>
                       <div class="suri-table-wrapper" style="margin-bottom:0; max-height: 400px; overflow-y: auto;">
                <div class="suri-card-title">List of Enrolled Users</div>
                <table class="suri-table">
                    <thead>
                        <tr>
                            <th data-col="name">Name</th>
                            <th data-col="email">Email</th>
                            <th data-col="progress">Progress</th>
                            <th data-col="score">Avg Score</th>
                            <th data-col="lastaccess">Last Access</th>
                            <th data-col="department" class="suri-col-hidden">Department</th>
                            <th data-col="institution" class="suri-col-hidden">Institution</th>
                            <th data-col="city" class="suri-col-hidden">City</th>
                            <th data-col="phone" class="suri-col-hidden">Phone</th>
                            <th style="width: 40px; text-align: right; padding-right: 16px;">
                                <div class="suri-toggle-wrapper">
                                    <button class="suri-toggle-btn" title="Toggle Columns">+</button>
                                    <div class="suri-toggle-dropdown">
                                        <label><input type="checkbox" class="suri-col-cb" value="progress" checked> Progress</label>
                                        <label><input type="checkbox" class="suri-col-cb" value="score" checked> Avg Score</label>
                                        <label><input type="checkbox" class="suri-col-cb" value="lastaccess" checked> Last Access</label>
                                        <label><input type="checkbox" class="suri-col-cb" value="department"> Department</label>
                                        <label><input type="checkbox" class="suri-col-cb" value="institution"> Institution</label>
                                        <label><input type="checkbox" class="suri-col-cb" value="city"> City</label>
                                        <label><input type="checkbox" class="suri-col-cb" value="phone"> Phone</label>
                                    </div>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($course_users)) {
                            foreach ($course_users as $u) {
                                $fullname = fullname((object)['firstname' => $u->firstname, 'lastname' => $u->lastname]);
                                $lastaccess = $u->lastaccess ? userdate($u->lastaccess) : 'Never';
                                
                                $prog = "0 / 0 (0%)";
                                if (isset($u->total_modules) && $u->total_modules > 0) {
                                    $comp = isset($u->completed_modules) ? $u->completed_modules : 0;
                                    $pct = round(($comp / $u->total_modules) * 100);
                                    $prog = "{$comp} / {$u->total_modules} ({$pct}%)";
                                }
                                
                                $score = "N/A";
                                if (isset($u->avg_quiz_score) && $u->avg_quiz_score !== null) {
                                    $score = number_format($u->avg_quiz_score, 1) . "%";
                                }
                                
                                echo "<tr>
                                        <td data-col='name'>{$fullname}</td>
                                        <td data-col='email'>{$u->email}</td>
                                        <td data-col='progress'>{$prog}</td>
                                        <td data-col='score'>{$score}</td>
                                        <td data-col='lastaccess'>{$lastaccess}</td>
                                        <td data-col='department' class='suri-col-hidden'>{$u->department}</td>
                                        <td data-col='institution' class='suri-col-hidden'>{$u->institution}</td>
                                        <td data-col='city' class='suri-col-hidden'>{$u->city}</td>
                                        <td data-col='phone' class='suri-col-hidden'>{$u->phone1}</td>
                                        <td></td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='10'>No students enrolled</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <script>
        require(['apexcharts'], function(ApexCharts) {
            var compStudents = <?php echo $stats['completed_students']; ?>;
            var incompStudents = <?php echo $stats['incomplete_students']; ?>;
            if (compStudents === 0 && incompStudents === 0) {
                // To avoid empty chart errors if no data is present
                compStudents = 0;
                incompStudents = 1; // Faux data to render empty state cleanly
            }
            var optionsComp = {
                series: [compStudents, incompStudents],
                chart: { type: 'donut', height: 300 },
                labels: ['Completed', 'Incomplete'],
                colors: ['#7F77DD', '#378ADD'],
                dataLabels: { enabled: true }
            };
            new ApexCharts(document.querySelector("#chart-course-comp"), optionsComp).render();
        });
        </script>
        <?php
    }
}
?>
</div>

<script>
window.Apex = {
    grid: {
        show: true,
        borderColor: '#E8E8E8',
        strokeDashArray: 0,
        xaxis: { lines: { show: false } },
        yaxis: { lines: { show: true } }
    },
    tooltip: {
        theme: 'light',
        style: {
            fontSize: '12px',
            fontFamily: 'Inter, system-ui, sans-serif'
        },
        marker: { show: true },
        x: { show: true },
        y: {
            title: {
                formatter: function (seriesName) {
                    return seriesName;
                }
            }
        }
    }
};

function executeScripts(container) {
    const scripts = container.querySelectorAll("script");
    scripts.forEach(oldScript => {
        const newScript = document.createElement("script");
        Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
        newScript.textContent = oldScript.textContent;
        oldScript.parentNode.replaceChild(newScript, oldScript);
    });
}

// Add require.config globally so it only executes once
require.config({
    paths: {
        'apexcharts': 'https://cdn.jsdelivr.net/npm/apexcharts/dist/apexcharts.min'
    }
});

function initSuriDashboard() {
    // Column Toggler logic
    document.querySelectorAll('.suri-toggle-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var dropdown = this.nextElementSibling;
            if (dropdown && dropdown.classList.contains('suri-toggle-dropdown')) {
                dropdown.classList.toggle('show');
            }
        });
    });

    // Handle column checkboxes
    document.querySelectorAll('.suri-col-cb').forEach(function(cb) {
        cb.addEventListener('change', function() {
            var colId = this.value;
            var isChecked = this.checked;
            var wrapper = this.closest('.suri-table-wrapper');
            if (wrapper) {
                var table = wrapper.querySelector('table');
                if (table) {
                    table.querySelectorAll('[data-col="' + colId + '"]').forEach(function(cell) {
                        if (isChecked) {
                            cell.classList.remove('suri-col-hidden');
                        } else {
                            cell.classList.add('suri-col-hidden');
                        }
                    });
                }
            }
        });
    });
    
    // Dynamic Filter Dropdown logic
    var filterBtn = document.getElementById('suri-add-filter-btn');
    if (filterBtn) {
        filterBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            var dropdown = document.getElementById('suri-filter-dropdown');
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        });
    }

    // Dynamic Sort Dropdown logic
    var sortBtn = document.getElementById('suri-add-sort-btn');
    if (sortBtn) {
        sortBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            var dropdown = document.getElementById('suri-sort-dropdown');
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        });
    }

    // Export PDF Logic
    var pdfBtn = document.getElementById('suri-export-pdf-btn');
    if (pdfBtn) {
        pdfBtn.addEventListener('click', function() {
            var dashboard = document.querySelector('.suri-dashboard');
            if (!dashboard) return;
            
            // Prepare for PDF snapshot
            var originalStyles = [];
            var scrollableTables = dashboard.querySelectorAll('.suri-table-wrapper');
            scrollableTables.forEach(function(el) {
                originalStyles.push({ el: el, maxHeight: el.style.maxHeight });
                el.style.maxHeight = 'none';
            });
            var exportBtns = document.getElementById('suri-export-pdf-btn').parentElement;
            if (exportBtns) exportBtns.style.display = 'none';
            
            var opt = {
                margin:       10,
                filename:     'suri_dashboard.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a3', orientation: 'landscape' },
                pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
            };
            
            html2pdf().set(opt).from(dashboard).save().then(function() {
                // Restore styles
                if (exportBtns) exportBtns.style.display = 'flex';
                originalStyles.forEach(function(item) {
                    item.el.style.maxHeight = item.maxHeight;
                });
            });
        });
    }

    // Export CSV Logic
    var csvBtn = document.getElementById('suri-export-csv-btn');
    if (csvBtn) {
        csvBtn.addEventListener('click', function() {
            var table = document.querySelector('.suri-table');
            if (!table) {
                alert("No table visible to export.");
                return;
            }
            
            var csv = [];
            var rows = table.querySelectorAll('tr');
            
            for (var i = 0; i < rows.length; i++) {
                var row = [], cols = rows[i].querySelectorAll('td, th');
                for (var j = 0; j < cols.length; j++) {
                    // Skip hidden columns and toggle dropdown column
                    if (cols[j].classList.contains('suri-col-hidden')) continue;
                    if (cols[j].querySelector('.suri-toggle-wrapper')) continue;
                    // Skip empty filler column
                    if (cols[j].innerText.trim() === '' && j === cols.length - 1 && cols[j].tagName.toLowerCase() === 'td') continue;
                    
                    var data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, ' ').trim();
                    data = data.replace(/"/g, '""');
                    row.push('"' + data + '"');
                }
                if (row.length > 0) csv.push(row.join(','));
            }
            
            var csvString = csv.join('\n');
            var blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement("a");
            if (link.download !== undefined) {
                var url = URL.createObjectURL(blob);
                link.setAttribute("href", url);
                link.setAttribute("download", "suri_export.csv");
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        });
    }
}

function suriNavigate(url, pushState = true) {
    const dashboard = document.querySelector('.suri-dashboard');
    if (!dashboard) return;
    
    dashboard.style.opacity = '0.5';
    dashboard.style.pointerEvents = 'none';

    fetch(url)
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newDashboard = doc.querySelector('.suri-dashboard');
            
            if (newDashboard) {
                dashboard.innerHTML = newDashboard.innerHTML;
                if (pushState) {
                    history.pushState(null, '', url);
                }
                initSuriDashboard();
                executeScripts(dashboard);
            }
        })
        .catch(err => console.error('Suri PJAX Error:', err))
        .finally(() => {
            dashboard.style.opacity = '1';
            dashboard.style.pointerEvents = 'auto';
        });
}

document.addEventListener("DOMContentLoaded", function() {
    initSuriDashboard();
    
    // Close toggles when clicking outside (bind once globally)
    document.addEventListener('click', function(e) {
        document.querySelectorAll('.suri-toggle-dropdown.show').forEach(function(dropdown) {
            if (!dropdown.parentElement.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });
        
        var filterDropdown = document.getElementById('suri-filter-dropdown');
        var filterBtn = document.getElementById('suri-add-filter-btn');
        if (filterDropdown && filterDropdown.classList.contains('show')) {
            if (!filterDropdown.contains(e.target) && e.target !== filterBtn) {
                filterDropdown.classList.remove('show');
            }
        }
        
        var sortDropdown = document.getElementById('suri-sort-dropdown');
        var sortBtn = document.getElementById('suri-add-sort-btn');
        if (sortDropdown && sortDropdown.classList.contains('show')) {
            if (!sortDropdown.contains(e.target) && e.target !== sortBtn) {
                sortDropdown.classList.remove('show');
            }
        }
    });

    // Intercept clicks on links for PJAX
    document.body.addEventListener('click', function(e) {
        const link = e.target.closest('a');
        if (link && link.href && link.href.includes('report/suri/index.php') && !link.hasAttribute('download')) {
            e.preventDefault();
            suriNavigate(link.href);
        }
    });

    // Intercept form submissions for PJAX
    document.body.addEventListener('submit', function(e) {
        const form = e.target.closest('form');
        if (form && form.action.includes('index.php') && form.method.toUpperCase() === 'GET' && form.closest('.suri-dashboard')) {
            e.preventDefault();
            const url = new URL(form.action);
            const formData = new FormData(form);
            for (const [key, value] of formData.entries()) {
                url.searchParams.append(key, value);
            }
            suriNavigate(url.toString());
        }
    });
    
    // Handle browser back/forward navigation
    window.addEventListener('popstate', function() {
        suriNavigate(location.href, false);
    });
});
</script>

<?php echo $OUTPUT->footer(); ?>
