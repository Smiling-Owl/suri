<?php

require_once('../../config.php');
require_once('lib.php');

// Security checks
require_login();
$context = context_system::instance();
require_capability('report/suri:view', $context);

// Handle Settings POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_anomaly_settings') {
    require_capability('moodle/site:config', $context);
    require_sesskey();
    set_config('threshold_inactivity', (int)$_POST['threshold_inactivity'], 'report_suri');
    set_config('threshold_dropoff', (int)$_POST['threshold_dropoff'], 'report_suri');
    redirect(new moodle_url('/report/suri/index.php', ['view' => 'dashboard']));
}

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

$has_user_filter = false;
foreach($filters as $f) {
    if (strpos($f['field'], 'user_') === 0 || $f['field'] === 'role_name') {
        $has_user_filter = true;
        break;
    }
}
$unfiltered_badge = $has_user_filter ? '<span class="suri-badge" style="background:var(--bg-light); color:var(--warning); font-size:10px; margin-left:8px; border: 1px solid var(--warning); padding:2px 4px; border-radius:4px;" title="User filters do not apply to this metric">⚠ Unfiltered</span>' : '';

// ==============================================================================
// 2. FETCH ALL DATA
// ==============================================================================
// Data fetches have been moved inside the respective $view blocks for efficiency
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

    /* Semantic Tints & Shades for Cards */
    --bg-primary-tint: #EEEDFE;
    --text-primary-shade: #3C3489;
    --bg-success-tint: #E6F5F0;
    --text-success-shade: #1D9E75;
    --bg-warning-tint: #FFF4E5;
    --text-warning-shade: #D97706;
    --bg-danger-tint: #FCE8E8;
    --text-danger-shade: #E24B4A;
    
    /* Users Tab Specific Tints */
    --bg-users-student-tint: #E1F5EE;
    --text-users-student-shade: #085041;
    --bg-users-teacher-tint: #FAEEDA;
    --text-users-teacher-shade: #633806;
    --bg-users-role-tint: #FBEAF0;
    --text-users-role-shade: #72243E;
}

/* Dark Mode Overrides (Controlled strictly by Moodle Theme) */
[data-bs-theme="dark"] .suri-dashboard,
.theme-dark .suri-dashboard {
    --bg-main: #141414;
    --card-bg: #222222;
    --text-main: #E8E8E8;
    --text-muted: #A0A0A0;
    --border: #3A3A3A;
    --primary-50: #2C285A;

    --bg-primary-tint: #2C285A;
    --text-primary-shade: #AFA9EC;
    --bg-success-tint: #183A2E;
    --text-success-shade: #4ade80;
    --bg-warning-tint: #4A2B0E;
    --text-warning-shade: #fbbf24;
    --bg-danger-tint: #4A1A1A;
    --text-danger-shade: #f87171;

    --bg-users-student-tint: #102B23;
    --text-users-student-shade: #4ade80;
    --bg-users-teacher-tint: #3D260D;
    --text-users-teacher-shade: #fbbf24;
    --bg-users-role-tint: #4A1527;
    --text-users-role-shade: #f472b6;
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
    font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
    color: var(--text-main);
}
.suri-header-actions {
    display: flex;
    gap: 15px;
    align-items: center;
}
.suri-btn-outline {
    background: var(--card-bg);
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
    background: var(--border);
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
.suri-modal-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
    z-index: 9999; display: none; align-items: center; justify-content: center;
}
.suri-modal-overlay.active { display: flex; }
.suri-modal {
    background: white; border-radius: 16px; padding: 30px; width: 400px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
}
.suri-floating-btn {
    position: fixed; bottom: 30px; right: 30px;
    width: 50px; height: 50px; border-radius: 25px;
    background: var(--primary); color: white; border: none;
    box-shadow: 0 4px 12px rgba(83, 74, 183, 0.4);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; z-index: 9998; transition: transform 0.2s;
}
.suri-floating-btn:hover { transform: scale(1.05); }
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
    display: none;
}
.suri-search-wrapper {
    display: inline-flex;
    align-items: center;
    background-color: var(--bg-light, #f1f3f4);
    border-radius: 30px;
    padding: 4px 12px 4px 4px;
    height: 32px;
}
.suri-search-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: var(--card-bg, #ffffff);
    border-radius: 50%;
    width: 24px;
    height: 24px;
    margin-right: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.suri-search-icon svg {
    width: 12px;
    height: 12px;
    fill: var(--text-muted);
}
.suri-search-input {
    border: none !important;
    background: transparent !important;
    outline: none !important;
    box-shadow: none !important;
    font-size: 11px;
    font-family: inherit;
    color: var(--text-main);
    width: 140px;
    padding: 0 !important;
    margin: 0 !important;
}
.suri-search-input:focus {
    box-shadow: none !important;
    border: none !important;
    background: transparent !important;
}
.suri-search-input::placeholder {
    color: var(--text-muted);
}
/* Utility classes */
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
.suri-global-search {
    display: flex;
    align-items: center;
    background: transparent;
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 4px 12px;
}
.suri-global-search input {
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
    font-size: 13px;
    padding: 4px 8px;
    width: 220px;
    color: var(--text-main);
    font-family: inherit;
    background: transparent !important;
}
.suri-global-search button {
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    font-size: 14px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.suri-global-search button:hover {
    color: var(--primary);
}
</style>

<div class="suri-dashboard">
    <div class="suri-header">
        <div style="display: flex; align-items: center; gap: 24px;">
            <h1>Reports & Analytics</h1>
            <form method="GET" action="index.php" class="suri-search-wrapper" style="margin: 0;">
                <button type="submit" class="suri-search-icon" style="border:none; cursor:pointer;" title="Search">
                    <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                </button>
                <input type="hidden" name="view" value="search">
                <input type="text" name="q" class="suri-search-input" style="width: 200px; font-size: 12px;" placeholder="Global search..." value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>" required>
            </form>
        </div>
        <div class="suri-header-actions">
            <?php
            // Pre-fill configured values
            $cfg_inactivity = get_config('report_suri', 'threshold_inactivity');
            if (!$cfg_inactivity) $cfg_inactivity = 14;

            $cfg_dropoff = get_config('report_suri', 'threshold_dropoff');
            if (!$cfg_dropoff) $cfg_dropoff = 20;
            ?>
            <?php if (has_capability('moodle/site:config', $context)): ?>
                <button class="suri-btn-outline" onclick="document.getElementById('suri-settings-modal').classList.add('active')" title="Anomaly Settings" style="display:flex; align-items:center; justify-content:center; padding: 6px 10px; border-radius: 4px; color: var(--text-muted);">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.06-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.06.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .43-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.49-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
                </button>

                <!-- Settings Modal Overlay -->
                <div id="suri-settings-modal" class="suri-modal-overlay">
                    <div class="suri-modal" style="text-align:left;">
                        <h3 style="margin-top:0; color:var(--text-main); font-size:18px;">Anomaly Detection Settings</h3>
                        <p style="font-size:12px; color:var(--text-muted); margin-bottom:20px;">Configure the thresholds that trigger automated flags on your dashboard.</p>
                        <form method="POST" action="index.php">
                            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                            <input type="hidden" name="action" value="save_anomaly_settings">
                            
                            <label style="display:block; font-size:12px; font-weight:600; color:var(--text-main); margin-bottom:4px;">User Inactivity Threshold (Days)</label>
                            <input type="number" name="threshold_inactivity" value="<?php echo $cfg_inactivity; ?>" min="1" style="width:100%; padding:8px; border:1px solid var(--border); border-radius:6px; margin-bottom:15px; outline:none; font-family:inherit;">
                            
                            <label style="display:block; font-size:12px; font-weight:600; color:var(--text-main); margin-bottom:4px;">Course Drop-off Threshold (%)</label>
                            <input type="number" name="threshold_dropoff" value="<?php echo $cfg_dropoff; ?>" min="1" max="100" style="width:100%; padding:8px; border:1px solid var(--border); border-radius:6px; margin-bottom:25px; outline:none; font-family:inherit;">
                            
                            <div style="display:flex; justify-content:flex-end; gap:10px;">
                                <button type="button" onclick="document.getElementById('suri-settings-modal').classList.remove('active')" style="background:transparent; border:none; padding:8px 16px; cursor:pointer; color:var(--text-muted); font-weight:500;">Cancel</button>
                                <button type="submit" style="background:var(--primary); color:white; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-weight:500;">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

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
                    <button class="suri-btn-outline" id="suri-add-filter-btn" title="Add Filter" style="padding: 6px 10px; display:flex; align-items:center; justify-content:center;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                    </button>
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
                    <button class="suri-btn-outline" id="suri-add-sort-btn" title="Sort By" style="padding: 6px 10px; display:flex; align-items:center; justify-content:center;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg>
                    </button>
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
            <div class="suri-filter-builder">
                <button class="suri-btn-outline" id="suri-export-dropdown-btn" style="display: flex; align-items: center; gap: 6px; padding: 6px 12px; color: var(--text-muted); font-weight: 500;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="suri-filter-dropdown" id="suri-export-menu" style="width: 150px; text-align: left;">
                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <button id="suri-export-csv-btn" style="background:none; border:none; text-align:left; padding:8px; cursor:pointer; font-size:13px; color:var(--text-main); width:100%; border-radius:4px; font-weight: 500;" onmouseover="this.style.background='var(--primary-50)'" onmouseout="this.style.background='none'">Export CSV</button>
                        <button id="suri-export-pdf-btn" style="background:none; border:none; text-align:left; padding:8px; cursor:pointer; font-size:13px; color:var(--text-main); width:100%; border-radius:4px; font-weight: 500;" onmouseover="this.style.background='var(--primary-50)'" onmouseout="this.style.background='none'">Export PDF</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="suri-tabs">
        <a href="?view=dashboard" class="suri-tab <?php echo $view == 'dashboard' ? 'active' : ''; ?>">Overview</a>
        <a href="?view=users" class="suri-tab <?php echo $view == 'users' ? 'active' : ''; ?>">Users</a>
        <a href="?view=courses" class="suri-tab <?php echo $view == 'courses' ? 'active' : ''; ?>">Courses</a>
        <a href="?view=cohorts" class="suri-tab <?php echo $view == 'cohorts' ? 'active' : ''; ?>">Cohorts</a>
        <a href="?view=performance" class="suri-tab <?php echo $view == 'performance' ? 'active' : ''; ?>">Performance</a>
        <?php if (has_capability('moodle/site:config', context_system::instance())): ?>
        <a href="?view=customquery" class="suri-tab <?php echo $view == 'customquery' ? 'active' : ''; ?>">Custom Query <span style="font-size:10px; background:var(--primary); color:#fff; padding:2px 6px; border-radius:10px; margin-left:4px;">PRO</span></a>
        <?php endif; ?>
    </div>

<?php
if ($view === 'dashboard') {
    // ---------------------------------------------------------
    // OVERVIEW
    // ---------------------------------------------------------
    
    $total_users = report_suri_get_total_users_count($filters);
    $total_courses = report_suri_get_total_courses_count($filters);
    $completion_rate_all = report_suri_get_completion_rate_all($filters);
    $attendance_trends = report_suri_get_attendance_trends_per_course($filters);
    $quiz_mean_course = report_suri_get_quiz_mean_per_course($filters);
    $perf_segmentation = report_suri_get_performance_segmentation($filters);

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

    // Fetch Active Users Trend
    $trend_days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
    if (!in_array($trend_days, [7, 30, 90])) $trend_days = 7;
    $trend_data = report_suri_get_active_users_trend($trend_days);
    $trend_labels_json = json_encode(array_keys($trend_data));
    $trend_series_json = json_encode(array_values($trend_data));

    // Fetch Engagement Breakdown
    $engagement_data = report_suri_get_engagement_breakdown(30);
    $eng_series = [];
    $eng_colors = [];
    foreach ($engagement_data as $ed) {
        $eng_series[] = $ed['pct'];
        $eng_colors[] = $ed['hex'];
    }
    $eng_series_json = json_encode($eng_series);
    $eng_colors_json = json_encode($eng_colors);

    // Fetch Anomalies
    $courses_needing_attention = report_suri_get_courses_needing_attention();
    $recent_flags = report_suri_get_user_anomalies();

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
            <div class="suri-card-header-small">TOTAL COURSES<?php echo $unfiltered_badge; ?></div>
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
                    <?php if (!empty($courses_needing_attention)): ?>
                        <?php foreach ($courses_needing_attention as $cna): ?>
                            <tr><td><a href="?view=course_detail&courseid=<?php echo $cna['courseid']; ?>" style="color:var(--text-main);text-decoration:none;"><?php echo htmlspecialchars($cna['coursename']); ?></a></td><td class="align-right" style="color:var(--danger);font-weight:500;"><?php echo $cna['issue']; ?></td></tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="2" style="text-align:center;color:var(--text-muted);padding:20px 0;">No struggling courses detected!</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Row 3: Sparkline, List, Donut -->
    <div class="suri-overview-grid" style="grid-template-columns: repeat(3, 1fr);">
        <div class="suri-card-dense">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <div class="suri-card-header-small" style="margin-bottom: 0;">ACTIVE USERS OVER TIME<?php echo $unfiltered_badge; ?></div>
                <form method="GET" action="index.php" style="margin: 0;">
                    <input type="hidden" name="view" value="dashboard">
                    <select name="days" onchange="this.form.submit()" style="font-size: 11px; padding: 2px 4px; border-radius: 4px; border: 1px solid var(--border); color: var(--text-muted); background: transparent; cursor: pointer; outline: none;">
                        <option value="7" <?php echo $trend_days == 7 ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="30" <?php echo $trend_days == 30 ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="90" <?php echo $trend_days == 90 ? 'selected' : ''; ?>>Last 90 Days</option>
                    </select>
                </form>
            </div>
            <div id="chart-active-users"></div>
        </div>
        <div class="suri-card-dense">
            <div class="suri-card-header-small">RECENT FLAGS<?php echo $unfiltered_badge; ?></div>
            <table class="suri-list-table">
                <thead><tr><th>USER</th><th class="align-right">ISSUE</th></tr></thead>
                <tbody>
                    <?php if (!empty($recent_flags)): ?>
                        <?php foreach ($recent_flags as $flag): ?>
                            <tr><td><a href="/user/profile.php?id=<?php echo $flag['userid']; ?>" target="_blank" style="color:var(--text-main);text-decoration:none;"><?php echo htmlspecialchars($flag['name']); ?></a></td><td class="align-right" style="color:var(--danger);font-weight:500;"><?php echo $flag['issue']; ?></td></tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="2" style="text-align:center;color:var(--text-muted);padding:20px 0;">No user anomalies detected!</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="suri-card-dense">
            <div class="suri-card-header-small">ENGAGEMENT BREAKDOWN<?php echo $unfiltered_badge; ?></div>
            <div id="chart-donut-engagement"></div>
            <div class="suri-html-legend">
                <?php foreach ($engagement_data as $ed): ?>
                    <div class="suri-legend-row">
                        <span class="dot <?php echo $ed['color_class']; ?>"></span>
                        <span class="label"><?php echo htmlspecialchars($ed['label']); ?></span>
                        <span class="pct"><?php echo $ed['pct']; ?>%</span>
                        <span class="count"><?php echo number_format($ed['count']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
    window.renderSuriDashboardCharts = function() {
        if (!window.apexchartsConfigured) {
            require.config({ paths: { 'apexcharts': 'https://cdn.jsdelivr.net/npm/apexcharts/dist/apexcharts.min' } });
            window.apexchartsConfigured = true;
        }
        require(['apexcharts'], function(ApexCharts) {
            // Sparklines (Rules 1 & 2)
            var sparkOpts = {
                chart: { type: 'area', sparkline: { enabled: true }, height: 45 },
                stroke: { width: 2 },
                fill: { type: 'solid', opacity: 0.2 },
                tooltip: { fixed: { enabled: false }, x: { show: false }, marker: { show: false } }
            };

            var optUser = Object.assign({}, sparkOpts, { stroke: { colors: ['#7F77DD'] }, fill: { colors: ['#7F77DD'] }, series: [{ data: [12, 14, 18, 17, 22, 28] }] });
            if(document.querySelector("#chart-spark-users")) new ApexCharts(document.querySelector("#chart-spark-users"), optUser).render();

            var optCourse = Object.assign({}, sparkOpts, { stroke: { colors: ['#1D9E75'] }, fill: { colors: ['#1D9E75'] }, series: [{ data: [4, 6, 5, 8, 10, 12] }] });
            if(document.querySelector("#chart-spark-courses")) new ApexCharts(document.querySelector("#chart-spark-courses"), optCourse).render();

            var optComp = Object.assign({}, sparkOpts, { stroke: { colors: ['#E24B4A'] }, fill: { colors: ['#E24B4A'] }, series: [{ data: [88, 86, 85, 84, 83, 84] }] });
            if(document.querySelector("#chart-spark-comp")) new ApexCharts(document.querySelector("#chart-spark-comp"), optComp).render();

            // Stacked Bar (Rule 4)
            var optStacked = {
                chart: { type: 'bar', stacked: true, height: 60, toolbar: { show: false }, sparkline: { enabled: true } },
                plotOptions: { bar: { horizontal: true, barHeight: '25%', borderRadius: 4 } },
                colors: [<?php echo $total_seg == 0 ? "'#E0E0E0'" : "'#1D9E75', '#EF9F27', '#E24B4A'"; ?>],
                series: [
                    <?php if ($total_seg == 0): ?>
                    { name: 'No Data', data: [100] }
                    <?php else: ?>
                    { name: 'High Performers', data: [<?php echo $healthy_pct; ?>] },
                    { name: 'Average Performers', data: [<?php echo $risk_pct; ?>] },
                    { name: 'Low Performers', data: [<?php echo $crit_pct; ?>] }
                    <?php endif; ?>
                ]
            };
            if(document.querySelector("#chart-stacked-health")) new ApexCharts(document.querySelector("#chart-stacked-health"), optStacked).render();

            // Standalone Trend Chart
            var optTrend = {
                chart: { type: 'area', height: 120, toolbar: { show: false } },
                stroke: { curve: 'smooth', width: 2, colors: ['#534AB7'] },
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 100] }, colors: ['#534AB7'] },
                dataLabels: { enabled: false },
                series: [{ name: 'Active Users', data: <?php echo $trend_series_json; ?> }],
                labels: <?php echo $trend_labels_json; ?>,
                xaxis: { 
                    type: 'category', 
                    labels: { style: { colors: 'var(--text-muted)', fontSize: '10px' } },
                    axisBorder: { show: false },
                    axisTicks: { show: false }
                },
                yaxis: { 
                    labels: { style: { colors: 'var(--text-muted)', fontSize: '10px' }, formatter: function(val) { return Math.floor(val); } },
                    min: 0
                },
                grid: { borderColor: 'var(--border)', strokeDashArray: 4, xaxis: { lines: { show: true } }, yaxis: { lines: { show: true } } },
                tooltip: { theme: 'light' }
            };
            if(document.querySelector("#chart-active-users")) new ApexCharts(document.querySelector("#chart-active-users"), optTrend).render();

            // Compact Donut
            var optDonut = {
                chart: { type: 'donut', height: 120 },
                series: <?php echo $eng_series_json; ?>,
                colors: <?php echo $eng_colors_json; ?>,
                legend: { show: false },
                dataLabels: { enabled: false },
                plotOptions: { pie: { donut: { size: '75%' } } },
                stroke: { width: 0 },
                tooltip: { enabled: true, theme: 'light', y: { formatter: function(val) { return val + "%"; } } }
            };
            if(document.querySelector("#chart-donut-engagement")) new ApexCharts(document.querySelector("#chart-donut-engagement"), optDonut).render();
        });
    };
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', window.renderSuriDashboardCharts); } else { window.renderSuriDashboardCharts(); }
    </script>
    <?php
} elseif ($view === 'users') {
    // ---------------------------------------------------------
    // USERS
    // ---------------------------------------------------------
    
    $total_students = report_suri_get_total_students_count($filters);
    $total_teachers = report_suri_get_total_teachers_count($filters);
    $users_per_role = report_suri_get_users_count_per_role($filters);
    $recent_logins = report_suri_get_recent_logins($filters, $sort, $dir);
    $attendance_student = report_suri_get_attendance_trends_per_student($filters);
    $user_directory = report_suri_get_user_enrollment_directory($filters, $sort, $dir);

    // Fetch Custom Profile Fields
    $custom_fields = report_suri_get_custom_profile_fields();
    $user_custom_data = [];
    if (!empty($custom_fields) && !empty($user_directory)) {
        $uids = [];
        foreach ($user_directory as $ud) { $uids[] = $ud->userid; }
        $user_custom_data = report_suri_get_users_custom_data($uids);
    }

    $role_labels = []; $role_series = [];
    if (!empty($users_per_role)) {
        foreach ($users_per_role as $r) { $role_labels[] = ucfirst($r->shortname); $role_series[] = (int)$r->usercount; }
    }
    ?>
    <!-- Row 1: Dense Metrics and Donut -->
    <div class="suri-overview-grid" style="grid-template-columns: repeat(4, 1fr);">
        <div class="suri-card-dense" style="background: var(--bg-primary-tint); color: var(--text-primary-shade); border-color: transparent; position: relative; overflow: hidden;">
            <div style="position: relative; z-index: 2;">
                <div class="suri-card-header-small" style="color: var(--text-primary-shade);">TOTAL USERS</div>
                <div class="suri-metric-value" style="color: var(--text-primary-shade);"><?php echo number_format($total_users); ?></div>
            </div>
            <svg style="position: absolute; right: -15px; bottom: -15px; width: 100px; height: 100px; opacity: 0.15; fill: currentColor; z-index: 1;" viewBox="0 0 24 24">
                <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
            </svg>
        </div>
        <div class="suri-card-dense" style="background: var(--bg-users-student-tint); color: var(--text-users-student-shade); border-color: transparent; position: relative; overflow: hidden;">
            <div style="position: relative; z-index: 2;">
                <div class="suri-card-header-small" style="color: var(--text-users-student-shade);">TOTAL STUDENTS</div>
                <div class="suri-metric-value" style="color: var(--text-users-student-shade);"><?php echo number_format($total_students); ?></div>
            </div>
            <svg style="position: absolute; right: -15px; bottom: -15px; width: 100px; height: 100px; opacity: 0.15; fill: currentColor; z-index: 1;" viewBox="0 0 24 24">
                <path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/>
            </svg>
        </div>
        <div class="suri-card-dense" style="background: var(--bg-users-teacher-tint); color: var(--text-users-teacher-shade); border-color: transparent; position: relative; overflow: hidden;">
            <div style="position: relative; z-index: 2;">
                <div class="suri-card-header-small" style="color: var(--text-users-teacher-shade);">TOTAL TEACHERS</div>
                <div class="suri-metric-value" style="color: var(--text-users-teacher-shade);"><?php echo number_format($total_teachers); ?></div>
            </div>
            <svg style="position: absolute; right: -15px; bottom: -15px; width: 100px; height: 100px; opacity: 0.15; fill: currentColor; z-index: 1;" viewBox="0 0 24 24">
                <path d="M12 11.55C9.64 9.35 6.48 8 3 8v11c3.48 0 6.64 1.35 9 3.55 2.36-2.19 5.52-3.55 9-3.55V8c-3.48 0-6.64 1.35-9 3.55zM12 8c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3z"/>
            </svg>
        </div>
        <div class="suri-card-dense" style="background: var(--bg-users-role-tint); color: var(--text-users-role-shade); border-color: transparent;">
            <div class="suri-card-header-small" style="color: var(--text-users-role-shade);">USERS BY ROLE</div>
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
                            $fullname = s(fullname((object)['firstname' => $row->firstname, 'lastname' => $row->lastname]));
                            $email = s($row->email);
                            echo "<tr><td>{$fullname}</td><td>{$email}</td><td class='align-right'>".userdate($row->lastlogin)."</td></tr>";
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
                            $fullname = s(fullname((object)['firstname' => $row->firstname, 'lastname' => $row->lastname]));
                            $coursename = s($row->coursename);
                            echo "<tr><td>{$fullname}</td><td>{$coursename}</td><td class='align-right'>{$row->active_days} days</td></tr>";
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
            <div style="display:flex; align-items:center; gap:10px;">
                <div class="suri-search-wrapper">
                    <div class="suri-search-icon">
                        <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    </div>
                    <input type="text" id="suri-user-search" class="suri-search-input" placeholder="Search directory...">
                </div>
                <div class="suri-toggle-wrapper">
                    <button class="suri-toggle-btn" id="suri-user-col-btn" title="Toggle Columns" style="background:none; border:none; color:var(--primary); font-size:16px; font-weight:bold; cursor:pointer; padding:0 8px;">+</button>
                    <div class="suri-toggle-dropdown" id="suri-user-col-dropdown">
                        <label><input type="checkbox" class="suri-col-cb" value="department"> Department</label>
                        <label><input type="checkbox" class="suri-col-cb" value="institution"> Institution</label>
                        <label><input type="checkbox" class="suri-col-cb" value="city"> City</label>
                        <label><input type="checkbox" class="suri-col-cb" value="phone"> Phone</label>
                        <label><input type="checkbox" class="suri-col-cb" value="lastaccess"> Last Access</label>
                        <?php
                        if (!empty($custom_fields)) {
                            foreach ($custom_fields as $cf) {
                                echo '<label><input type="checkbox" class="suri-col-cb" value="cf_' . s($cf->shortname) . '"> ' . s($cf->name) . '</label>';
                            }
                        }
                        ?>
                    </div>
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
                    <?php
                    if (!empty($custom_fields)) {
                        foreach ($custom_fields as $cf) {
                            echo '<th data-col="cf_' . s($cf->shortname) . '" class="suri-col-hidden">' . s($cf->name) . '</th>';
                        }
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!empty($user_directory)) {
                    foreach ($user_directory as $row) {
                        $fullname = s(fullname((object)['firstname' => $row->firstname, 'lastname' => $row->lastname]));
                        $email = s($row->email);
                        $coursename = s($row->coursename);
                        $role = s(ucfirst($row->role));
                        $department = s($row->department);
                        $institution = s($row->institution);
                        $city = s($row->city);
                        $phone = s($row->phone1);
                        $lastaccess = $row->lastaccess ? userdate($row->lastaccess) : 'Never';
                        echo "<tr>
                                <td data-col='name'>{$fullname}</td>
                                <td data-col='email'>{$email}</td>
                                <td data-col='course'>{$coursename}</td>
                                <td data-col='role'>{$role}</td>
                                <td data-col='department' class='suri-col-hidden'>{$department}</td>
                                <td data-col='institution' class='suri-col-hidden'>{$institution}</td>
                                <td data-col='city' class='suri-col-hidden'>{$city}</td>
                                <td data-col='phone' class='suri-col-hidden'>{$phone}</td>
                                <td data-col='lastaccess' class='suri-col-hidden'>{$lastaccess}</td>";
                        if (!empty($custom_fields)) {
                            foreach ($custom_fields as $cf) {
                                $cdata = isset($user_custom_data[$row->userid][$cf->shortname]) ? s($user_custom_data[$row->userid][$cf->shortname]) : '';
                                echo "<td data-col='cf_" . s($cf->shortname) . "' class='suri-col-hidden'>{$cdata}</td>";
                            }
                        }
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='9'>No users found.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <script>
    window.renderSuriUsersCharts = function() {
        if (!window.apexchartsConfigured) {
            require.config({ paths: { 'apexcharts': 'https://cdn.jsdelivr.net/npm/apexcharts/dist/apexcharts.min' } });
            window.apexchartsConfigured = true;
        }
        require(['apexcharts'], function(ApexCharts) {
            var rSeries = <?php echo empty($role_series) ? '[1]' : json_encode($role_series); ?>;
            var rLabels = <?php echo empty($role_labels) ? '["No Data"]' : json_encode($role_labels); ?>;
            var optionsRoles = {
                series: rSeries,
                chart: { type: 'donut', height: 240 },
                labels: rLabels,
                colors: ['#7F77DD', '#AFA9EC', '#1D9E75', '#EF9F27', '#E24B4A'],
                legend: { show: true, position: 'bottom', fontSize: '11px', itemMargin: { horizontal: 5, vertical: 2 } },
                dataLabels: { enabled: false },
                plotOptions: { pie: { donut: { size: '65%' } } },
                stroke: { width: 0 },
                tooltip: { enabled: true }
            };
            if(document.querySelector("#chart-roles")) new ApexCharts(document.querySelector("#chart-roles"), optionsRoles).render();
        });
    };
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', window.renderSuriUsersCharts); } else { window.renderSuriUsersCharts(); }
    </script>
    <?php
} elseif ($view === 'courses') {
    // ---------------------------------------------------------
    // COURSES
    // ---------------------------------------------------------
    
    $total_courses = report_suri_get_total_courses_count($filters);
    $courses_per_category = report_suri_get_courses_per_category($filters);
    $active_courses = report_suri_get_most_active_courses($filters);
    $users_per_course = report_suri_get_users_per_course($filters);
    $time_spent_courses = report_suri_get_course_time_spent($time_spent_days, $filters);
    $interaction_trends = report_suri_get_global_interaction_trends($interaction_period, $filters);

    $cat_labels = []; $cat_series = [];
    if (!empty($courses_per_category)) {
        foreach ($courses_per_category as $c) { $cat_labels[] = $c->categoryname; $cat_series[] = (int)$c->coursecount; }
    }
    $active_labels = []; $active_series = [];
    if (!empty($active_courses)) {
        foreach (array_slice($active_courses, 0, 5) as $c) { $active_labels[] = $c->coursename; $active_series[] = (int)$c->views; }
    }
    ?>
    <div class="suri-overview-grid" style="grid-template-columns: repeat(4, 1fr);">
        <div class="suri-card-dense" style="background-color: var(--bg-primary-tint); border: none; position: relative; overflow: hidden;">
            <div class="suri-card-header-small" style="color: var(--text-primary-shade);">TOTAL COURSES</div>
            <div class="suri-metric-value" style="color: var(--text-primary-shade);"><?php echo number_format($total_courses); ?></div>
            <svg style="position: absolute; right: 10px; bottom: 10px; opacity: 0.05; width: 60px; height: 60px;" fill="var(--text-primary-shade)" viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72l5 2.73 5-2.73v3.72z"/></svg>
        </div>
        <div class="suri-card-dense" style="background-color: var(--bg-success-tint); border: none; position: relative; overflow: hidden;">
            <div class="suri-card-header-small" style="color: var(--text-success-shade);">ACTIVE CATEGORIES</div>
            <div class="suri-metric-value" style="color: var(--text-success-shade);"><?php echo count($courses_per_category ?? []); ?></div>
            <svg style="position: absolute; right: 10px; bottom: 10px; opacity: 0.05; width: 60px; height: 60px;" fill="var(--text-success-shade)" viewBox="0 0 24 24"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
        </div>
        <div class="suri-card-dense" style="background-color: var(--bg-warning-tint); border: none; position: relative; overflow: hidden;">
            <div class="suri-card-header-small" style="color: var(--text-warning-shade);">TOP ACTIVE COURSE</div>
            <div class="suri-metric-value" style="color: var(--text-warning-shade); font-size: 13px; font-weight: 600; align-self: center; line-height: 1.3; text-align: left; width: 100%; white-space: normal; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-top: 4px;" title="<?php echo !empty($active_courses) ? htmlspecialchars(reset($active_courses)->coursename) : 'None'; ?>"><?php echo !empty($active_courses) ? htmlspecialchars(reset($active_courses)->coursename) : 'None'; ?></div>
            <svg style="position: absolute; right: 10px; bottom: 10px; opacity: 0.05; width: 60px; height: 60px;" fill="var(--text-warning-shade)" viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/></svg>
        </div>
        <div class="suri-card-dense" style="background-color: var(--bg-danger-tint); border: none; position: relative; overflow: hidden;">
            <div class="suri-card-header-small" style="color: var(--text-danger-shade);">PEAK VIEWS</div>
            <div class="suri-metric-value" style="color: var(--text-danger-shade);"><?php echo !empty($active_courses) ? number_format(reset($active_courses)->views) : '0'; ?></div>
            <svg style="position: absolute; right: 10px; bottom: 10px; opacity: 0.05; width: 60px; height: 60px;" fill="var(--text-danger-shade)" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
        </div>
    </div>
    <div class="suri-grid-2" style="margin-bottom: 12px;">
        <div class="suri-card-dense">
            <div class="suri-card-header-small">COURSES BY CATEGORY<?php echo $unfiltered_badge; ?></div>
            <div id="chart-categories" style="min-height: 240px;"></div>
        </div>
        <div class="suri-card-dense">
            <div class="suri-card-header-small">TOP 5 MOST ACTIVE COURSES<?php echo $unfiltered_badge; ?></div>
            <div id="chart-active" style="min-height: 240px;"></div>
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
    <div class="suri-card-dense" style="margin-bottom: 12px;">
        <div class="suri-card-header-small" style="display:flex; justify-content:space-between; align-items:center;">
            <span>GLOBAL STUDENT INTERACTIONS</span>
            <div style="font-size: 0.85rem;">
                <a href="<?php echo $link_ip_day; ?>" class="suri-btn-outline" style="padding: 2px 8px; font-size: 11px; text-decoration:none; <?php echo $interaction_period == 'day' ? 'background:var(--primary);color:#fff;' : ''; ?>">Daily</a>
                <a href="<?php echo $link_ip_week; ?>" class="suri-btn-outline" style="padding: 2px 8px; font-size: 11px; text-decoration:none; <?php echo $interaction_period == 'week' ? 'background:var(--primary);color:#fff;' : ''; ?>">Weekly</a>
                <a href="<?php echo $link_ip_month; ?>" class="suri-btn-outline" style="padding: 2px 8px; font-size: 11px; text-decoration:none; <?php echo $interaction_period == 'month' ? 'background:var(--primary);color:#fff;' : ''; ?>">Monthly</a>
            </div>
        </div>
        <div id="chart-interactions"></div>
    </div>
    
    <div class="suri-card-dense" style="margin-bottom: 12px;">
        <div class="suri-card-header-small" style="display:flex; justify-content:space-between; align-items:center;">
            <span>COURSE DIRECTORY<?php echo $unfiltered_badge; ?></span>
            <div style="display:flex; align-items:center; gap:10px;">
                <div class="suri-search-wrapper">
                    <div class="suri-search-icon">
                        <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    </div>
                    <input type="text" id="suri-course-dir-search" class="suri-search-input" placeholder="Search courses...">
                </div>
                <div class="suri-toggle-wrapper">
                    <button class="suri-toggle-btn" title="Toggle Columns" style="background:none; border:none; color:var(--primary); font-size:16px; font-weight:bold; cursor:pointer; padding:0 8px;">+</button>
                    <div class="suri-toggle-dropdown">
                        <label><input type="checkbox" class="suri-col-cb" value="startdate"> Start Date</label>
                        <label><input type="checkbox" class="suri-col-cb" value="enddate"> End Date</label>
                        <label><input type="checkbox" class="suri-col-cb" value="visible"> Visible</label>
                    </div>
                </div>
            </div>
        </div>
        <table class="suri-list-table" id="suri-course-dir-table">
            <thead>
                <tr>
                    <th data-col="coursename">Course Name</th>
                    <th data-col="startdate" class="suri-col-hidden">Start Date</th>
                    <th data-col="enddate" class="suri-col-hidden">End Date</th>
                    <th data-col="visible" class="suri-col-hidden">Visible</th>
                    <th style="width: 150px;">Action</th>
                    <th style="width: 40px; text-align: right; padding-right: 16px;"></th>
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
                        $coursename = s($row->coursename);
                        echo "<tr>
                                <td data-col='coursename'>{$coursename}</td>
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

    <div class="suri-card-dense" style="margin-bottom: 12px;">
        <div class="suri-card-header-small" style="display:flex; justify-content:space-between; align-items:center;">
            <span>TOTAL STUDENTS AND TEACHERS PER COURSE</span>
            <div class="suri-toggle-wrapper">
                <button class="suri-toggle-btn" title="Toggle Columns" style="background:none; border:none; color:var(--primary); font-size:16px; font-weight:bold; cursor:pointer; padding:0 8px;">+</button>
                <div class="suri-toggle-dropdown">
                    <label><input type="checkbox" class="suri-col-cb" value="startdate"> Start Date</label>
                    <label><input type="checkbox" class="suri-col-cb" value="enddate"> End Date</label>
                </div>
            </div>
        </div>
        <table class="suri-list-table">
            <thead>
                <tr>
                    <th data-col="coursename">Course Name</th>
                    <th data-col="students">Students Enrolled</th>
                    <th data-col="teachers">Teachers Assigned</th>
                    <th data-col="startdate" class="suri-col-hidden">Start Date</th>
                    <th data-col="enddate" class="suri-col-hidden">End Date</th>
                    <th style="width: 40px; text-align: right; padding-right: 16px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php
                if(!empty($users_per_course)){
                    foreach($users_per_course as $row){
                        $startdate = $row->startdate ? userdate($row->startdate) : 'N/A';
                        $enddate = $row->enddate ? userdate($row->enddate) : 'N/A';
                        $coursename = s($row->coursename);
                        echo "<tr>
                                <td data-col='coursename'>{$coursename}</td>
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

    <div class="suri-card-dense" style="margin-bottom: 12px;">
        <div class="suri-card-header-small" style="display:flex; justify-content:space-between; align-items:center;">
            <span>ACCUMULATIVE TIME SPENT BY STUDENTS (LAST <?php echo $time_spent_days; ?> DAYS)</span>
            <div style="font-size: 0.85rem; font-weight: normal;">
                <a href="<?php echo $link_tsd_7; ?>" class="suri-btn-outline" style="padding: 2px 8px; font-size: 11px; text-decoration:none; <?php echo $time_spent_days == 7 ? 'background:var(--primary);color:#fff;' : ''; ?>">7 Days</a>
                <a href="<?php echo $link_tsd_30; ?>" class="suri-btn-outline" style="padding: 2px 8px; font-size: 11px; text-decoration:none; <?php echo $time_spent_days == 30 ? 'background:var(--primary);color:#fff;' : ''; ?>">30 Days</a>
                <a href="<?php echo $link_tsd_90; ?>" class="suri-btn-outline" style="padding: 2px 8px; font-size: 11px; text-decoration:none; <?php echo $time_spent_days == 90 ? 'background:var(--primary);color:#fff;' : ''; ?>">90 Days</a>
                <a href="<?php echo $link_tsd_365; ?>" class="suri-btn-outline" style="padding: 2px 8px; font-size: 11px; text-decoration:none; <?php echo $time_spent_days == 365 ? 'background:var(--primary);color:#fff;' : ''; ?>">1 Year</a>
            </div>
        </div>
        <table class="suri-list-table">
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
    window.renderSuriCoursesCharts = function() {
        if (!window.apexchartsConfigured) {
            require.config({ paths: { 'apexcharts': 'https://cdn.jsdelivr.net/npm/apexcharts/dist/apexcharts.min' } });
            window.apexchartsConfigured = true;
        }
        require(['apexcharts'], function(ApexCharts) {
            var optionsCat = {
                series: [{ name: 'Courses', data: <?php echo json_encode($cat_series); ?> }],
                chart: { type: 'bar', height: 300, toolbar: { show: false } },
                xaxis: { categories: <?php echo json_encode($cat_labels); ?> },
                colors: ['#7F77DD'],
                plotOptions: { bar: { borderRadius: 4, columnWidth: '40%' } }
            };
            if(document.querySelector("#chart-categories")) new ApexCharts(document.querySelector("#chart-categories"), optionsCat).render();

            var optionsActive = {
                series: [{ name: 'Interactions', data: <?php echo json_encode($active_series); ?> }],
                chart: { type: 'bar', height: 300, toolbar: { show: false } },
                plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
                xaxis: { categories: <?php echo json_encode($active_labels); ?> },
                colors: ['#7F77DD']
            };
            if(document.querySelector("#chart-active")) new ApexCharts(document.querySelector("#chart-active"), optionsActive).render();
            
            var optionsInt = {
                series: <?php echo json_encode($interaction_trends); ?>,
                chart: { type: 'line', height: 300, toolbar: { show: false } },
                stroke: { curve: 'smooth', width: 2 },
                xaxis: { type: 'category', labels: { trim: true, hideOverlappingLabels: true } },
                colors: ['#7F77DD', '#378ADD', '#1D9E75', '#EF9F27', '#E24B4A'],
                tooltip: { shared: true, intersect: false },
                legend: { position: 'top' }
            };
            if(document.querySelector("#chart-interactions")) new ApexCharts(document.querySelector("#chart-interactions"), optionsInt).render();
        });
    };
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', window.renderSuriCoursesCharts); } else { window.renderSuriCoursesCharts(); }
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
    
    echo '<div class="suri-overview-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">';
    echo '<div class="suri-card-dense" style="background-color: #EEEDFE; border: none; position: relative; overflow: hidden;">';
    echo '<div class="suri-card-header-small" style="color: #3C3489;">TOTAL COHORTS</div>';
    echo '<div class="suri-metric-value" style="color: #3C3489;">' . count($cohort_labels) . '</div>';
    echo '<svg style="position: absolute; right: 10px; bottom: 10px; opacity: 0.05; width: 60px; height: 60px;" fill="#3C3489" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>';
    echo '</div>';
    echo '</div>';
    ?>
    <div class="suri-card-dense" style="margin-bottom: 12px;">
        <div class="suri-card-header-small">USERS BY COHORT</div>
        <div id="chart-cohorts" style="min-height: 240px;"></div>
    </div>
    <div class="suri-grid-2" style="margin-bottom: 12px; align-items: start;">
        <div class="suri-card-dense" style="max-height: 400px; overflow-y: auto;">
            <div class="suri-card-header-small" style="display:flex; justify-content:space-between; align-items:center;">
                <span>STUDENTS PER COHORT</span>
                <input type="text" id="suri-cohort-students-search" placeholder="Search cohorts..." style="padding: 4px 10px; border: 1px solid #E8E8E8; border-radius: 20px; font-size: 11px; outline: none; width: 140px; font-family: inherit; color: var(--text-main);">
            </div>
            <table class="suri-list-table" id="suri-cohort-students-table">
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
        <div class="suri-card-dense" style="max-height: 400px; overflow-y: auto;">
            <div class="suri-card-header-small" style="display:flex; justify-content:space-between; align-items:center;">
                <span>TEACHERS PER COHORT</span>
                <div class="suri-search-wrapper">
                    <div class="suri-search-icon">
                        <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    </div>
                    <input type="text" id="suri-cohort-teachers-search" class="suri-search-input" placeholder="Search cohorts...">
                </div>
            </div>
            <table class="suri-list-table" id="suri-cohort-teachers-table">
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
    window.renderSuriCohortsCharts = function() {
        if (!window.apexchartsConfigured) {
            require.config({ paths: { 'apexcharts': 'https://cdn.jsdelivr.net/npm/apexcharts/dist/apexcharts.min' } });
            window.apexchartsConfigured = true;
        }
        require(['apexcharts'], function(ApexCharts) {
            var optionsCohorts = {
                series: [{ name: 'Users', data: <?php echo json_encode($cohort_series); ?> }],
                chart: { type: 'bar', height: 300, toolbar: { show: false } },
                xaxis: { categories: <?php echo json_encode($cohort_labels); ?> },
                colors: ['#7F77DD'],
                plotOptions: { bar: { borderRadius: 4, columnWidth: '40%' } }
            };
            if(document.querySelector("#chart-cohorts")) new ApexCharts(document.querySelector("#chart-cohorts"), optionsCohorts).render();
        });
    };
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', window.renderSuriCohortsCharts); } else { window.renderSuriCohortsCharts(); }
    </script>
    <?php
} elseif ($view === 'performance') {
    // ---------------------------------------------------------
    // PERFORMANCE
    // ---------------------------------------------------------
    $completion_rate_all = report_suri_get_completion_rate_all($filters);
    $quiz_mean_all = report_suri_get_quiz_mean_all($filters);
    $engagement_all = report_suri_get_engagement_all($filters);
    $avg_completion_all = report_suri_get_avg_completion_time_all($filters);
    
    $completion_rate_course = report_suri_get_completion_rate_per_course($filters);
    $quiz_mean_course = report_suri_get_quiz_mean_per_course($filters);
    $engagement_course = report_suri_get_engagement_per_course($filters);
    
    $completion_dir = report_suri_get_course_completion_directory(null, $filters, $sort, $dir);
    $cert_completion = report_suri_get_completion_and_certificates($filters);
    
    echo '<div class="suri-overview-grid" style="grid-template-columns: repeat(4, 1fr);">';
    echo '<div class="suri-card-dense" style="background-color: #EEEDFE; border: none; position: relative; overflow: hidden;">';
    echo '<div class="suri-card-header-small" style="color: #3C3489;">COMPLETION RATE</div>';
    echo '<div class="suri-metric-value" style="color: #3C3489;">' . number_format($completion_rate_all, 1) . '%</div>';
    echo '<svg style="position: absolute; right: 10px; bottom: 10px; opacity: 0.05; width: 60px; height: 60px;" fill="#3C3489" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>';
    echo '</div>';
    echo '<div class="suri-card-dense" style="background-color: #E6F5F0; border: none; position: relative; overflow: hidden;">';
    echo '<div class="suri-card-header-small" style="color: #1D9E75;">AVG QUIZ SCORE</div>';
    echo '<div class="suri-metric-value" style="color: #1D9E75;">' . number_format($quiz_mean_all, 1) . '%</div>';
    echo '<svg style="position: absolute; right: 10px; bottom: 10px; opacity: 0.05; width: 60px; height: 60px;" fill="#1D9E75" viewBox="0 0 24 24"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-5 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>';
    echo '</div>';
    echo '<div class="suri-card-dense" style="background-color: #FFF4E5; border: none; position: relative; overflow: hidden;">';
    echo '<div class="suri-card-header-small" style="color: #D97706;">AVG ENGAGEMENT</div>';
    echo '<div class="suri-metric-value" style="color: #D97706;">' . number_format($engagement_all, 1) . '</div>';
    echo '<svg style="position: absolute; right: 10px; bottom: 10px; opacity: 0.05; width: 60px; height: 60px;" fill="#D97706" viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/></svg>';
    echo '</div>';
    
    $avg_hrs = floor($avg_completion_all / 3600);
    $avg_mins = floor(($avg_completion_all % 3600) / 60);
    echo '<div class="suri-card-dense" style="background-color: #FCE8E8; border: none; position: relative; overflow: hidden;">';
    echo '<div class="suri-card-header-small" style="color: #E24B4A;">AVG COMPLETION TIME</div>';
    echo '<div class="suri-metric-value" style="color: #E24B4A;">' . $avg_hrs . 'h ' . $avg_mins . 'm</div>';
    echo '<svg style="position: absolute; right: 10px; bottom: 10px; opacity: 0.05; width: 60px; height: 60px;" fill="#E24B4A" viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>';
    echo '</div>';
    echo '</div>';

    ?>
    <div class="suri-card-dense" style="margin-bottom: 12px; max-height: 400px; overflow-y: auto;">
        <div class="suri-card-header-small" style="display:flex; justify-content:space-between; align-items:center;">
            <span>PERFORMANCE & ENGAGEMENT BY COURSE</span>
            <div class="suri-search-wrapper">
                <div class="suri-search-icon">
                    <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                </div>
                <input type="text" id="suri-perf-course-search" class="suri-search-input" placeholder="Search courses...">
            </div>
        </div>
        <table class="suri-list-table" id="suri-perf-course-table">
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
                    $course_s = s($course);
                    echo "<tr><td>{$course_s}</td><td>{$comp}</td><td>{$quiz}</td><td>{$eng}</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
    
    <div class="suri-card-dense" style="margin-bottom: 12px; max-height: 400px; overflow-y: auto;">
        <div class="suri-card-header-small" style="display:flex; justify-content:space-between; align-items:center;">
            <span>COURSE COMPLETION DIRECTORY</span>
            <div class="suri-search-wrapper">
                <div class="suri-search-icon">
                    <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                </div>
                <input type="text" id="suri-perf-dir-search" class="suri-search-input" placeholder="Search users/courses...">
            </div>
        </div>
        <table class="suri-list-table" id="suri-perf-dir-table">
            <thead><tr><th>Name</th><th>Course</th><th>Time Enrolled</th><th>Time Completed</th></tr></thead>
            <tbody>
                <?php
                if (!empty($completion_dir)) {
                    foreach ($completion_dir as $row) {
                        $fullname = s(fullname((object)['firstname' => $row->firstname, 'lastname' => $row->lastname]));
                        $coursename = s($row->coursename);
                        $enrolled = $row->timeenrolled ? userdate($row->timeenrolled) : 'Never';
                        $completed = $row->timecompleted ? userdate($row->timecompleted) : 'Incomplete';
                        echo "<tr><td>{$fullname}</td><td>{$coursename}</td><td>{$enrolled}</td><td>{$completed}</td></tr>";
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
    
    <div class="suri-card-dense" style="margin-bottom: 12px; max-height: 400px; overflow-y: auto;">
        <div class="suri-card-header-small" style="display:flex; justify-content:space-between; align-items:center;">
            <span>MODULE COMPLETION & CERTIFICATES</span>
            <div style="display:flex; align-items:center; gap:10px;">
                <div class="suri-search-wrapper">
                    <div class="suri-search-icon">
                        <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    </div>
                    <input type="text" id="suri-perf-cert-search" class="suri-search-input" placeholder="Search users/courses...">
                </div>
                <div class="suri-toggle-wrapper">
                    <button class="suri-toggle-btn" title="Toggle Columns" style="background:none; border:none; color:var(--primary); font-size:16px; font-weight:bold; cursor:pointer; padding:0 8px;">+</button>
                    <div class="suri-toggle-dropdown">
                        <label><input type="checkbox" class="suri-col-cb" value="course"> Course</label>
                        <label><input type="checkbox" class="suri-col-cb" value="certificate"> Certificate Date</label>
                    </div>
                </div>
            </div>
        </div>
        <table class="suri-list-table" id="suri-perf-cert-table">
            <thead>
                <tr>
                    <th data-col="name">Name</th>
                    <th data-col="email">Email</th>
                    <th data-col="course" class="suri-col-hidden">Course</th>
                    <th data-col="modules">Modules Completed</th>
                    <th data-col="certificate" class="suri-col-hidden">Certificate Date</th>
                    <th style="width: 40px; text-align: right; padding-right: 16px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!empty($cert_completion)) {
                    foreach ($cert_completion as $row) {
                        $fullname = s(fullname((object)['firstname' => $row->firstname, 'lastname' => $row->lastname]));
                        $email = s($row->email);
                        $coursename = s($row->coursename);
                        $module_status = "{$row->completed_modules} / {$row->total_modules}";
                        echo "<tr>
                                <td data-col='name'>{$fullname}</td>
                                <td data-col='email'>{$email}</td>
                                <td data-col='course' class='suri-col-hidden'>{$coursename}</td>
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
        $course_users = report_suri_get_user_activity_progress($filters);
        $activity_breakdown = report_suri_get_course_activity_breakdown($filters);
        
        // Fetch Custom Profile Fields
        $custom_fields = report_suri_get_custom_profile_fields();
        $user_custom_data = [];
        if (!empty($custom_fields) && !empty($course_users)) {
            $uids = [];
            foreach ($course_users as $ud) { $uids[] = $ud->userid; }
            $user_custom_data = report_suri_get_users_custom_data($uids);
        }
        
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
        
        echo '<div class="suri-overview-grid" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));">';
        echo '<div class="suri-card-dense" style="background-color: #EEEDFE; border: none; position: relative; overflow: hidden;">';
        echo '<div class="suri-card-header-small" style="color: #3C3489;">ENROLLED STUDENTS</div>';
        echo '<div class="suri-metric-value" style="color: #3C3489;">' . $stats['total_students'] . '</div>';
        echo '<svg style="position: absolute; right: 10px; bottom: 10px; opacity: 0.05; width: 60px; height: 60px;" fill="#3C3489" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>';
        echo '</div>';
        echo '<div class="suri-card-dense" style="background-color: #E6F5F0; border: none; position: relative; overflow: hidden;">';
        echo '<div class="suri-card-header-small" style="color: #1D9E75;">COMPLETION RATE</div>';
        echo '<div class="suri-metric-value" style="color: #1D9E75;">' . number_format($rate, 1) . '%</div>';
        echo '<svg style="position: absolute; right: 10px; bottom: 10px; opacity: 0.05; width: 60px; height: 60px;" fill="#1D9E75" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>';
        echo '</div>';
        echo '<div class="suri-card-dense" style="background-color: #FFF4E5; border: none; position: relative; overflow: hidden;">';
        echo '<div class="suri-card-header-small" style="color: #D97706;">AVG COMPLETION TIME</div>';
        echo '<div class="suri-metric-value" style="color: #D97706;">' . $hours . 'h ' . $minutes . 'm</div>';
        echo '<svg style="position: absolute; right: 10px; bottom: 10px; opacity: 0.05; width: 60px; height: 60px;" fill="#D97706" viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>';
        echo '</div>';
        echo '<div class="suri-card-dense" style="background-color: #FCE8E8; border: none; position: relative; overflow: hidden;">';
        echo '<div class="suri-card-header-small" style="color: #E24B4A;">ATTENDANCE DAYS</div>';
        echo '<div class="suri-metric-value" style="color: #E24B4A;">' . number_format($attendance_trend_val, 1) . '</div>';
        echo '<svg style="position: absolute; right: 10px; bottom: 10px; opacity: 0.05; width: 60px; height: 60px;" fill="#E24B4A" viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>';
        echo '</div>';
        echo '<div class="suri-card-dense" style="background-color: #E3F2FD; border: none; position: relative; overflow: hidden;">';
        echo '<div class="suri-card-header-small" style="color: #1976D2;">TOTAL INTERACTIONS</div>';
        echo '<div class="suri-metric-value" style="color: #1976D2;">' . number_format($stats['total_interactions']) . '</div>';
        echo '<svg style="position: absolute; right: 10px; bottom: 10px; opacity: 0.05; width: 60px; height: 60px;" fill="#1976D2" viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/></svg>';
        echo '</div>';
        echo '</div>';
        
        ?>
        <div class="suri-grid-2" style="margin-bottom: 12px; align-items: start;">
            <div class="suri-card-dense">
                <div class="suri-card-header-small">COMPLETION STATUS RATIO</div>
                <div id="chart-course-comp" style="min-height: 240px;"></div>
            </div>
        </div>
        
        <div class="suri-card-dense" style="margin-bottom: 24px; max-height: 500px; overflow-y: auto;">
                <div class="suri-card-header-small" style="display:flex; justify-content:space-between; align-items:center;">
                    <span>LIST OF ENROLLED USERS</span>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <input type="text" id="suri-course-users-search" placeholder="Search users..." style="padding: 4px 10px; border: 1px solid #E8E8E8; border-radius: 20px; font-size: 11px; outline: none; width: 140px; font-family: inherit; color: var(--text-main);">
                        <div class="suri-toggle-wrapper">
                            <button class="suri-toggle-btn" title="Toggle Columns" style="background:none; border:none; color:var(--primary); font-size:16px; font-weight:bold; cursor:pointer; padding:0 8px;">+</button>
                            <div class="suri-toggle-dropdown">
                                <label><input type="checkbox" class="suri-col-cb" value="progress" checked> Progress</label>
                                <label><input type="checkbox" class="suri-col-cb" value="activity" checked> Last Activity</label>
                                <label><input type="checkbox" class="suri-col-cb" value="score" checked> Score</label>
                                <label><input type="checkbox" class="suri-col-cb" value="lastaccess" checked> Last Access</label>
                                <label><input type="checkbox" class="suri-col-cb" value="department"> Department</label>
                                <label><input type="checkbox" class="suri-col-cb" value="institution"> Institution</label>
                                <label><input type="checkbox" class="suri-col-cb" value="city"> City</label>
                                <label><input type="checkbox" class="suri-col-cb" value="phone"> Phone</label>
                                <?php
                                if (!empty($custom_fields)) {
                                    foreach ($custom_fields as $cf) {
                                        echo '<label><input type="checkbox" class="suri-col-cb" value="cf_' . s($cf->shortname) . '"> ' . s($cf->name) . '</label>';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <table class="suri-list-table" id="suri-course-users-table">
                    <thead>
                        <tr>
                            <th data-col="name">Name</th>
                            <th data-col="email">Email</th>
                            <th data-col="progress">Progress</th>
                            <th data-col="activity">Last Activity</th>
                            <th data-col="score">Score</th>
                            <th data-col="lastaccess">Last Access</th>
                            <th data-col="department" class="suri-col-hidden">Department</th>
                            <th data-col="institution" class="suri-col-hidden">Institution</th>
                            <th data-col="city" class="suri-col-hidden">City</th>
                            <th data-col="phone" class="suri-col-hidden">Phone</th>
                            <?php
                            if (!empty($custom_fields)) {
                                foreach ($custom_fields as $cf) {
                                    echo '<th data-col="cf_' . s($cf->shortname) . '" class="suri-col-hidden">' . s($cf->name) . '</th>';
                                }
                            }
                            ?>
                            <th style="width: 40px; text-align: right; padding-right: 16px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($course_users)) {
                            foreach ($course_users as $u) {
                                $fullname = fullname((object)['firstname' => $u->firstname, 'lastname' => $u->lastname]);
                                $lastaccess = $u->lastaccess ? userdate($u->lastaccess) : 'Never';
                                
                                $pct = $u->total_activities > 0 ? round(($u->completed_activities / $u->total_activities) * 100) : 0;
                                $pb = "<div style='display:flex; align-items:center; gap:12px; width:100%; min-width: 120px;'>
                                        <div style='flex:1; background:#E8E8E8; height:8px; border-radius:4px; overflow:hidden;'>
                                            <div style='background:var(--primary); height:100%; width:{$pct}%; transition: width 0.5s ease-in-out;'></div>
                                        </div>
                                        <span style='font-size:12px; font-weight:600; min-width:55px; text-align:right; color:var(--text-main);'>{$u->completed_activities}/{$u->total_activities} ({$pct}%)</span>
                                       </div>";
                                       
                                $last_act = s($u->last_activity_name);
                                $score = s($u->last_activity_score);
                                
                                echo "<tr>
                                        <td data-col='name'>{$fullname}</td>
                                        <td data-col='email'>{$u->email}</td>
                                        <td data-col='progress' style='width:25%;'>{$pb}</td>
                                        <td data-col='activity'>{$last_act}</td>
                                        <td data-col='score'>{$score}</td>
                                        <td data-col='lastaccess'>{$lastaccess}</td>
                                        <td data-col='department' class='suri-col-hidden'>{$u->department}</td>
                                        <td data-col='institution' class='suri-col-hidden'>{$u->institution}</td>
                                        <td data-col='city' class='suri-col-hidden'>{$u->city}</td>
                                        <td data-col='phone' class='suri-col-hidden'>{$u->phone1}</td>";
                                if (!empty($custom_fields)) {
                                    foreach ($custom_fields as $cf) {
                                        $cdata = isset($user_custom_data[$u->userid][$cf->shortname]) ? s($user_custom_data[$u->userid][$cf->shortname]) : '';
                                        echo "<td data-col='cf_" . s($cf->shortname) . "' class='suri-col-hidden'>{$cdata}</td>";
                                    }
                                }
                                echo "<td></td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='10'>No students enrolled</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        
        <div class="suri-card-dense" style="margin-bottom: 24px;">
            <div class="suri-card-header-small">ACTIVITY BREAKDOWN METRICS</div>
            <table class="suri-list-table">
                <thead><tr><th>Activity Name</th><th>Type</th><th>Total Enrolled</th><th>Completion Rate</th><th class="align-right">Average Score</th></tr></thead>
                <tbody>
                    <?php
                    if (!empty($activity_breakdown)) {
                        foreach ($activity_breakdown as $a) {
                            $name = s($a->name);
                            $type = s(ucfirst($a->modname));
                            $enrolled = (int)$a->total_enrolled;
                            $completed = (int)$a->total_completed;
                            $rate = $enrolled > 0 ? round(($completed / $enrolled) * 100) : 0;
                            $avg_score = s($a->avg_score);
                            
                            echo "<tr><td data-col='activity'>{$name}</td><td data-col='type'>{$type}</td><td data-col='enrolled'>{$enrolled}</td><td data-col='completion'>{$completed} / {$enrolled} ({$rate}%)</td><td data-col='avg_score' class='align-right'>{$avg_score}</td></tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5'>No trackable activities found in this course.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <script>
        window.renderSuriCourseDetailCharts = function() {
            if (!window.apexchartsConfigured) {
                require.config({ paths: { 'apexcharts': 'https://cdn.jsdelivr.net/npm/apexcharts/dist/apexcharts.min' } });
                window.apexchartsConfigured = true;
            }
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
                if(document.querySelector("#chart-course-comp")) new ApexCharts(document.querySelector("#chart-course-comp"), optionsComp).render();
            });
        };
        if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', window.renderSuriCourseDetailCharts); } else { window.renderSuriCourseDetailCharts(); }
        </script>
        <?php
    }
} elseif ($view === 'search') {
    // ---------------------------------------------------------
    // GLOBAL SEARCH RESULTS
    // ---------------------------------------------------------
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $results = report_suri_get_global_search($q);
    
    echo '<h2 style="font-size: 18px; color: var(--text-main); margin-bottom: 20px;">Search Results for "'.htmlspecialchars($q).'"</h2>';
    
    // Users Results
    echo '<div class="suri-card-dense" style="margin-bottom: 20px;">';
    echo '<div class="suri-card-header-small">USERS (' . count($results['users']) . ')</div>';
    if (!empty($results['users'])) {
        echo '<table class="suri-list-table"><thead><tr><th>Name</th><th>Email</th><th>Action</th></tr></thead><tbody>';
        foreach ($results['users'] as $u) {
            $name = fullname((object)['firstname' => $u->firstname, 'lastname' => $u->lastname]);
            $url = new moodle_url('/user/profile.php', ['id' => $u->id]);
            echo "<tr><td>{$name}</td><td>{$u->email}</td><td><a href='{$url}' target='_blank' style='color:var(--primary);text-decoration:none;'>View Profile</a></td></tr>";
        }
        echo '</tbody></table>';
    } else {
        echo '<p style="font-size: 13px; color: var(--text-muted); margin: 0;">No users found.</p>';
    }
    echo '</div>';
    
    // Courses Results
    echo '<div class="suri-card-dense" style="margin-bottom: 20px;">';
    echo '<div class="suri-card-header-small">COURSES (' . count($results['courses']) . ')</div>';
    if (!empty($results['courses'])) {
        echo '<table class="suri-list-table"><thead><tr><th>Course Name</th><th>Short Name</th><th>Action</th></tr></thead><tbody>';
        foreach ($results['courses'] as $c) {
            $url = new moodle_url('/report/suri/index.php', ['view' => 'course_detail', 'courseid' => $c->id]);
            echo "<tr><td>{$c->fullname}</td><td>{$c->shortname}</td><td><a href='{$url}' style='color:var(--primary);text-decoration:none;'>View Insights</a></td></tr>";
        }
        echo '</tbody></table>';
    } else {
        echo '<p style="font-size: 13px; color: var(--text-muted); margin: 0;">No courses found.</p>';
    }
    echo '</div>';
    
    // Cohorts Results
    echo '<div class="suri-card-dense" style="margin-bottom: 20px;">';
    echo '<div class="suri-card-header-small">COHORTS (' . count($results['cohorts']) . ')</div>';
    if (!empty($results['cohorts'])) {
        echo '<table class="suri-list-table"><thead><tr><th>Cohort Name</th><th>ID Number</th><th>Action</th></tr></thead><tbody>';
        foreach ($results['cohorts'] as $h) {
            $url = new moodle_url('/cohort/index.php');
            echo "<tr><td>{$h->name}</td><td>{$h->idnumber}</td><td><a href='{$url}' target='_blank' style='color:var(--primary);text-decoration:none;'>Manage Cohorts</a></td></tr>";
        }
        echo '</tbody></table>';
    } else {
        echo '<p style="font-size: 13px; color: var(--text-muted); margin: 0;">No cohorts found.</p>';
    }
    echo '</div>';
    echo '</div>';
} elseif ($view === 'customquery') {
    // ---------------------------------------------------------
    // CUSTOM SQL QUERY
    // ---------------------------------------------------------
    require_capability('moodle/site:config', context_system::instance());
    
    $custom_sql = optional_param('custom_sql', '', PARAM_RAW);
    $query_results = null;
    $query_error = null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($custom_sql)) {
        require_sesskey();
        $exec = report_suri_execute_custom_sql($custom_sql);
        if ($exec['status']) {
            $query_results = $exec['data'];
        } else {
            $query_error = $exec['data'];
        }
    }
    
    echo $OUTPUT->heading('Power User: Custom SQL Query', 3);
    echo '<p style="color: var(--text-muted); font-size: 13px; margin-bottom: 20px;">Execute custom SELECT queries directly against the Moodle database. Destructive commands (UPDATE, DELETE, etc.) are strictly prohibited.</p>';
    
    echo '<form method="post" action="?view=customquery" style="margin-bottom: 24px;">';
    echo '<input type="hidden" name="sesskey" value="'.sesskey().'">';
    echo '<div style="margin-bottom: 12px;">';
    echo '<textarea name="custom_sql" rows="6" style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 8px; font-family: monospace; font-size: 13px; resize: vertical;" placeholder="SELECT u.id, u.username, u.email FROM {user} u LIMIT 10">'.s($custom_sql).'</textarea>';
    echo '</div>';
    echo '<button type="submit" class="suri-btn-primary">Run Query</button>';
    echo '</form>';
    
    if ($query_error) {
        echo '<div style="background-color: #FCE8E8; color: #E24B4A; padding: 16px; border-radius: 8px; border-left: 4px solid #E24B4A; margin-bottom: 24px; font-family: monospace;">';
        echo '<strong>Error:</strong> ' . s($query_error);
        echo '</div>';
    }
    
    if ($query_results !== null) {
        echo '<div class="suri-card-dense" style="overflow-x: auto;">';
        if (empty($query_results)) {
            echo '<p style="margin: 0; color: var(--text-muted);">Query executed successfully, but returned 0 rows.</p>';
        } else {
            // Extract headers from the first row
            $first_row = reset($query_results);
            $columns = array_keys((array)$first_row);
            
            echo '<div class="suri-card-header-small" style="margin-bottom: 12px;">RESULTS (' . count($query_results) . ' rows)</div>';
            echo '<table class="suri-list-table" style="min-width: 100%; white-space: nowrap;">';
            echo '<thead><tr>';
            foreach ($columns as $col) {
                echo '<th>' . s(strtoupper($col)) . '</th>';
            }
            echo '</tr></thead><tbody>';
            
            foreach ($query_results as $row) {
                echo '<tr>';
                foreach ($columns as $col) {
                    echo '<td>' . s($row->$col) . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
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

function suriPaginateTable(tableId, rowsPerPage) {
    if (typeof rowsPerPage === 'undefined') rowsPerPage = 10;
    var table = document.getElementById(tableId);
    if (!table || table.dataset.paginated) return;
    var tbody = table.querySelector('tbody');
    if (!tbody) return;
    var rows = Array.from(tbody.querySelectorAll('tr'));
    
    if (rows.length <= 1 && rows[0] && rows[0].cells.length === 1 && rows[0].cells[0].hasAttribute('colspan')) return;
    if (rows.length <= rowsPerPage) return;
    
    table.dataset.paginated = "true";
    var currentPage = 1;
    var totalPages = Math.ceil(rows.length / rowsPerPage);
    
    var paginationContainer = document.createElement('div');
    paginationContainer.className = 'suri-pagination';
    paginationContainer.style.display = 'flex';
    paginationContainer.style.justifyContent = 'space-between';
    paginationContainer.style.alignItems = 'center';
    paginationContainer.style.marginTop = '16px';
    paginationContainer.style.paddingTop = '12px';
    paginationContainer.style.borderTop = '1px dashed var(--border)';
    
    var prevBtn = document.createElement('button');
    prevBtn.className = 'suri-btn-outline';
    prevBtn.style.padding = '4px 12px';
    prevBtn.style.fontSize = '12px';
    prevBtn.textContent = 'Previous';
    
    var nextBtn = document.createElement('button');
    nextBtn.className = 'suri-btn-outline';
    nextBtn.style.padding = '4px 12px';
    nextBtn.style.fontSize = '12px';
    nextBtn.textContent = 'Next';
    
    var info = document.createElement('span');
    info.style.fontSize = '12px';
    info.style.fontWeight = '500';
    info.style.color = 'var(--text-muted)';
    
    paginationContainer.appendChild(prevBtn);
    paginationContainer.appendChild(info);
    paginationContainer.appendChild(nextBtn);
    table.parentNode.insertBefore(paginationContainer, table.nextSibling);
    
    function renderPage() {
        var activeRows = rows.filter(function(r) { return r.dataset.filteredOut !== 'true'; });
        totalPages = Math.ceil(activeRows.length / rowsPerPage) || 1;
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;
        
        info.textContent = 'Page ' + currentPage + ' of ' + totalPages;
        prevBtn.disabled = currentPage === 1;
        prevBtn.style.opacity = currentPage === 1 ? '0.5' : '1';
        prevBtn.style.cursor = currentPage === 1 ? 'not-allowed' : 'pointer';
        
        nextBtn.disabled = currentPage === totalPages;
        nextBtn.style.opacity = currentPage === totalPages ? '0.5' : '1';
        nextBtn.style.cursor = currentPage === totalPages ? 'not-allowed' : 'pointer';
        
        rows.forEach(function(r) { r.style.display = 'none'; });
        
        var start = (currentPage - 1) * rowsPerPage;
        var end = start + rowsPerPage;
        for (var i = start; i < end && i < activeRows.length; i++) {
            activeRows[i].style.display = '';
        }
    }
    
    prevBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (currentPage > 1) { currentPage--; renderPage(); }
    });
    
    nextBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (currentPage < totalPages) { currentPage++; renderPage(); }
    });
    
    renderPage();
    table.suriRenderPagination = function() {
        currentPage = 1;
        renderPage();
    };
}

function initSuriDashboard() {
    suriPaginateTable('suri-user-dir-table', 10);
    suriPaginateTable('suri-course-dir-table', 10);
    suriPaginateTable('suri-course-users-table', 10);
    suriPaginateTable('suri-cohort-students-table', 10);
    suriPaginateTable('suri-cohort-teachers-table', 10);
    suriPaginateTable('suri-perf-course-table', 10);
    suriPaginateTable('suri-perf-dir-table', 10);
    suriPaginateTable('suri-perf-cert-table', 10);

    if (window.suriEventsBound) return;
    window.suriEventsBound = true;

    // Event Delegation for toggles, dropdowns, and exports
    document.body.addEventListener('click', function(e) {
        // Column Toggler logic
        var toggleBtn = e.target.closest('.suri-toggle-btn');
        if (toggleBtn) {
            e.stopPropagation();
            var dropdown = toggleBtn.nextElementSibling;
            if (dropdown && dropdown.classList.contains('suri-toggle-dropdown')) {
                dropdown.classList.toggle('show');
            }
        }
        
        // Dynamic Filter Dropdown
        var filterBtn = e.target.closest('#suri-add-filter-btn');
        if (filterBtn) {
            e.stopPropagation();
            var fDropdown = document.getElementById('suri-filter-dropdown');
            if (fDropdown) fDropdown.classList.toggle('show');
        }
        
        // Dynamic Sort Dropdown
        var sortBtn = e.target.closest('#suri-add-sort-btn');
        if (sortBtn) {
            e.stopPropagation();
            var sDropdown = document.getElementById('suri-sort-dropdown');
            if (sDropdown) sDropdown.classList.toggle('show');
        }
        
        // Export Dropdown
        var exportMenuBtn = e.target.closest('#suri-export-dropdown-btn');
        if (exportMenuBtn) {
            e.stopPropagation();
            var menu = document.getElementById('suri-export-menu');
            if (menu) menu.classList.toggle('show');
        }
        
        // Export PDF Logic
        var pdfBtn = e.target.closest('#suri-export-pdf-btn');
        if (pdfBtn) {
            var dashboard = document.querySelector('.suri-dashboard');
            if (!dashboard) return;
            var originalStyles = [];
            var scrollableTables = dashboard.querySelectorAll('.suri-table-wrapper, .suri-card-dense, .suri-card');
            scrollableTables.forEach(function(el) {
                originalStyles.push({ el: el, maxHeight: el.style.maxHeight });
                el.style.maxHeight = 'none';
            });
            var exportBtns = document.getElementById('suri-export-pdf-btn').parentElement;
            if (exportBtns) exportBtns.style.display = 'none';
            
            var opt = {
                margin: 10, filename: 'suri_dashboard.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a3', orientation: 'landscape' },
                pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
            };
            html2pdf().set(opt).from(dashboard).save().then(function() {
                if (exportBtns) exportBtns.style.display = 'flex';
                originalStyles.forEach(function(item) { item.el.style.maxHeight = item.maxHeight; });
            });
        }

        // Export CSV Logic
        var csvBtn = e.target.closest('#suri-export-csv-btn');
        if (csvBtn) {
            var dashboard = document.querySelector('.suri-dashboard');
            if (!dashboard) return;
            var tables = dashboard.querySelectorAll('.suri-list-table, .suri-table');
            if (tables.length === 0) { alert("No tables visible to export."); return; }
            
            var csv = [];
            tables.forEach(function(table) {
                var parent = table.closest('.suri-card-dense, .suri-card, .suri-table-wrapper');
                if (parent) {
                    var header = parent.querySelector('.suri-card-header-small');
                    if (header) {
                        var titleSpan = header.querySelector('span');
                        var titleText = titleSpan ? titleSpan.textContent : header.childNodes[0].textContent;
                        csv.push('"' + titleText.replace(/(\r\n|\n|\r)/gm, ' ').trim() + '"');
                    }
                }
                
                var headerCells = table.querySelectorAll('thead tr th');
                var validCols = [];
                for (var j = 0; j < headerCells.length; j++) {
                    if (headerCells[j].classList.contains('suri-col-hidden')) continue;
                    if (headerCells[j].querySelector('.suri-toggle-wrapper')) continue;
                    if (headerCells[j].textContent.trim() === '') continue; // Skip empty action columns
                    validCols.push(j);
                }

                var rows = table.querySelectorAll('tr');
                for (var i = 0; i < rows.length; i++) {
                    var row = [], cols = rows[i].querySelectorAll('td, th');
                    if (cols.length === 0) continue;
                    
                    if (cols.length === 1 && cols[0].hasAttribute('colspan')) continue; 

                    for (var v = 0; v < validCols.length; v++) {
                        var j = validCols[v];
                        if (cols[j]) {
                            var data = cols[j].textContent.replace(/(\r\n|\n|\r)/gm, ' ').trim();
                            data = data.replace(/"/g, '""');
                            row.push('"' + data + '"');
                        }
                    }
                    if (row.length > 0) csv.push(row.join(','));
                }
                csv.push(""); // Add a blank line between tables
            });
            
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
        }
    });

    // Handle column checkboxes (Event Delegation)
    document.body.addEventListener('change', function(e) {
        if (e.target.classList.contains('suri-col-cb')) {
            var cb = e.target;
            var colId = cb.value;
            var isChecked = cb.checked;
            var wrapper = cb.closest('.suri-table-wrapper') || cb.closest('.suri-card-dense') || cb.closest('.suri-card') || cb.closest('div');
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
        }
    });

    // Handle Directory Search (Event Delegation)
    document.body.addEventListener('input', function(e) {
        var searchMappings = [
            { id: '#suri-user-search', tableId: 'suri-user-dir-table' },
            { id: '#suri-course-dir-search', tableId: 'suri-course-dir-table' },
            { id: '#suri-course-users-search', tableId: 'suri-course-users-table' },
            { id: '#suri-cohort-students-search', tableId: 'suri-cohort-students-table' },
            { id: '#suri-cohort-teachers-search', tableId: 'suri-cohort-teachers-table' },
            { id: '#suri-perf-course-search', tableId: 'suri-perf-course-table' },
            { id: '#suri-perf-dir-search', tableId: 'suri-perf-dir-table' },
            { id: '#suri-perf-cert-search', tableId: 'suri-perf-cert-table' }
        ];

        for (var i = 0; i < searchMappings.length; i++) {
            if (e.target.matches(searchMappings[i].id)) {
                var searchTerm = e.target.value.toLowerCase();
                var table = document.getElementById(searchMappings[i].tableId);
                if (table) {
                    var rows = table.querySelectorAll('tbody tr');
                    rows.forEach(function(row) {
                        var rowText = row.textContent.toLowerCase();
                        if (rowText.includes(searchTerm)) {
                            row.dataset.filteredOut = 'false';
                            if (!table.dataset.paginated) row.style.display = '';
                        } else {
                            row.dataset.filteredOut = 'true';
                            if (!table.dataset.paginated) row.style.display = 'none';
                        }
                    });
                    if (typeof table.suriRenderPagination === 'function') {
                        table.suriRenderPagination();
                    }
                }
                break;
            }
        }
    });
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
            if (!filterDropdown.contains(e.target) && filterBtn && !filterBtn.contains(e.target)) {
                filterDropdown.classList.remove('show');
            }
        }
        
        var sortDropdown = document.getElementById('suri-sort-dropdown');
        var sortBtn = document.getElementById('suri-add-sort-btn');
        if (sortDropdown && sortDropdown.classList.contains('show')) {
            if (!sortDropdown.contains(e.target) && sortBtn && !sortBtn.contains(e.target)) {
                sortDropdown.classList.remove('show');
            }
        }
        
        var exportMenu = document.getElementById('suri-export-menu');
        var exportBtn = document.getElementById('suri-export-dropdown-btn');
        if (exportMenu && exportMenu.classList.contains('show')) {
            if (!exportMenu.contains(e.target) && exportBtn && !exportBtn.contains(e.target)) {
                exportMenu.classList.remove('show');
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
