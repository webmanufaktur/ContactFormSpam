<?php namespace ProcessWire;

/**
 * Admin Interface for Contact Form Spam Protection Monitoring
 * 
 * This template provides an admin interface to monitor spam attempts,
 * view statistics, and manage spam protection settings.
 */

// Check if user has permission to access this page
if (!$user->hasPermission('admin')) {
    throw new \Exception('You do not have permission to access this page');
}

// Get the spam protection module
$spamProtection = $modules->get('ContactFormSpam');

// Handle actions
$action = $input->get->action;
$message = '';
$error = '';

if ($action === 'cleanup') {
    $days = (int)($input->get->days ?: 30);
    $spamProtection->cleanupLogs($days);
    $message = "Logs older than {$days} days have been cleaned up.";
} elseif ($action === 'export') {
    exportSpamLogs($spamProtection);
    return;
}

// Get statistics
$stats24h = $spamProtection->getSpamStats(24);
$stats7d = $spamProtection->getSpamStats(168); // 7 days
$stats30d = $spamProtection->getSpamStats(720); // 30 days

// Get recent spam attempts
$recentAttempts = getRecentSpamAttempts($spamProtection, 50);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Form Spam Protection - Admin</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 300;
        }
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        .nav-tabs {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .nav-tab {
            padding: 15px 25px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 14px;
            font-weight: 500;
            color: #6c757d;
            transition: all 0.3s ease;
        }
        .nav-tab.active {
            color: #495057;
            background: white;
            border-bottom: 2px solid #007bff;
        }
        .nav-tab:hover {
            color: #495057;
        }
        .tab-content {
            display: none;
            padding: 30px;
        }
        .tab-content.active {
            display: block;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #495057;
            margin-bottom: 5px;
        }
        .stat-card .change {
            font-size: 12px;
            color: #6c757d;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        .table tr:hover {
            background: #f8f9fa;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            font-size: 11px;
            font-weight: bold;
            border-radius: 12px;
            text-transform: uppercase;
        }
        .badge-danger {
            background: #dc3545;
            color: white;
        }
        .badge-warning {
            background: #ffc107;
            color: #212529;
        }
        .badge-info {
            background: #17a2b8;
            color: white;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 500;
            text-align: center;
            text-decoration: none;
            border: 1px solid transparent;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        .btn-primary:hover {
            background: #0056b3;
            border-color: #0056b3;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
            border-color: #6c757d;
        }
        .btn-secondary:hover {
            background: #545b62;
            border-color: #545b62;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-success {
            color: #155724;
            background: #d4edda;
            border-color: #c3e6cb;
        }
        .alert-danger {
            color: #721c24;
            background: #f8d7da;
            border-color: #f5c6cb;
        }
        .actions {
            margin-top: 20px;
            text-align: right;
        }
        .chart-container {
            height: 300px;
            margin: 20px 0;
            position: relative;
        }
        .chart-placeholder {
            height: 100%;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-style: italic;
        }
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .nav-tabs {
                flex-direction: column;
            }
            .nav-tab {
                border-bottom: 1px solid #dee2e6;
            }
            .nav-tab.active {
                border-bottom: 1px solid #dee2e6;
                border-left: 4px solid #007bff;
            }
            .table {
                font-size: 12px;
            }
            .table th,
            .table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Contact Form Spam Protection</h1>
            <p>Monitor and manage spam protection for your contact forms</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="nav-tabs">
            <button class="nav-tab active" onclick="showTab('overview')">Overview</button>
            <button class="nav-tab" onclick="showTab('statistics')">Statistics</button>
            <button class="nav-tab" onclick="showTab('attempts')">Recent Attempts</button>
            <button class="nav-tab" onclick="showTab('settings')">Settings</button>
        </div>
        
        <!-- Overview Tab -->
        <div id="overview" class="tab-content active">
            <h2>Overview</h2>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Last 24 Hours</h3>
                    <div class="value"><?php echo $stats24h['total']; ?></div>
                    <div class="change">spam attempts blocked</div>
                </div>
                <div class="stat-card">
                    <h3>Last 7 Days</h3>
                    <div class="value"><?php echo $stats7d['total']; ?></div>
                    <div class="change">spam attempts blocked</div>
                </div>
                <div class="stat-card">
                    <h3>Last 30 Days</h3>
                    <div class="value"><?php echo $stats30d['total']; ?></div>
                    <div class="change">spam attempts blocked</div>
                </div>
                <div class="stat-card">
                    <h3>Protection Status</h3>
                    <div class="value">Active</div>
                    <div class="change">All systems operational</div>
                </div>
            </div>
            
            <h3>Top Block Reasons (Last 24 Hours)</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Reason</th>
                        <th>Count</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($stats24h['by_reason'])): ?>
                        <?php 
                        $total = $stats24h['total'];
                        foreach ($stats24h['by_reason'] as $reason => $count): 
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $reason))); ?></td>
                            <td><?php echo $count; ?></td>
                            <td><?php echo round(($count / max($total, 1)) * 100, 1); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3">No spam attempts in the last 24 hours</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Statistics Tab -->
        <div id="statistics" class="tab-content">
            <h2>Statistics</h2>
            
            <h3>Hourly Distribution (Last 24 Hours)</h3>
            <div class="chart-container">
                <div class="chart-placeholder">
                    Chart visualization would be implemented here
                </div>
            </div>
            
            <h3>Block Reasons Comparison</h3>
            <div class="stats-grid">
                <?php foreach (array('24h' => $stats24h, '7d' => $stats7d, '30d' => $stats30d) as $period => $stats): ?>
                    <div class="stat-card">
                        <h3>Last <?php echo $period === '24h' ? '24 Hours' : ($period === '7d' ? '7 Days' : '30 Days'); ?></h3>
                        <?php if (!empty($stats['by_reason'])): ?>
                            <?php foreach ($stats['by_reason'] as $reason => $count): ?>
                                <div style="margin-bottom: 5px;">
                                    <small><?php echo ucfirst(str_replace('_', ' ', $reason)); ?></small>
                                    <div style="background: #e9ecef; border-radius: 4px; height: 8px; margin-top: 2px;">
                                        <div style="background: #007bff; height: 100%; border-radius: 4px; width: <?php echo round(($count / max($stats['total'], 1)) * 100); ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="change">No data available</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Recent Attempts Tab -->
        <div id="attempts" class="tab-content">
            <h2>Recent Spam Attempts</h2>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>IP Address</th>
                        <th>Reason</th>
                        <th>User Agent</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recentAttempts)): ?>
                        <?php foreach ($recentAttempts as $attempt): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($attempt['timestamp']); ?></td>
                            <td><?php echo htmlspecialchars($attempt['ip']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo getReasonBadgeClass($attempt['reason']); ?>">
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $attempt['reason']))); ?>
                                </span>
                            </td>
                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($attempt['user_agent']); ?>">
                                <?php echo htmlspecialchars($attempt['user_agent']); ?>
                            </td>
                            <td>
                                <button class="btn btn-primary" onclick="viewDetails(<?php echo htmlspecialchars(json_encode($attempt)); ?>)">
                                    Details
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">No recent spam attempts found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Settings Tab -->
        <div id="settings" class="tab-content">
            <h2>Settings</h2>
            
            <form method="post" action="">
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Rate Limiting</h3>
                        <div style="margin-bottom: 15px;">
                            <label for="rate_limit">Submissions per hour:</label>
                            <input type="number" id="rate_limit" name="rate_limit" value="5" min="1" max="100" style="width: 100%; padding: 8px; margin-top: 5px;">
                        </div>
                        <div>
                            <label for="rate_window">Time window (seconds):</label>
                            <input type="number" id="rate_window" name="rate_window" value="3600" min="300" max="86400" style="width: 100%; padding: 8px; margin-top: 5px;">
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>Timing Validation</h3>
                        <div style="margin-bottom: 15px;">
                            <label for="min_form_time">Minimum form time (seconds):</label>
                            <input type="number" id="min_form_time" name="min_form_time" value="3" min="1" max="60" style="width: 100%; padding: 8px; margin-top: 5px;">
                        </div>
                        <div>
                            <label for="max_form_time">Maximum form time (seconds):</label>
                            <input type="number" id="max_form_time" name="max_form_time" value="3600" min="300" max="86400" style="width: 100%; padding: 8px; margin-top: 5px;">
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>Honeypot Fields</h3>
                        <div>
                            <label for="honeypot_count">Number of honeypot fields:</label>
                            <input type="number" id="honeypot_count" name="honeypot_count" value="3" min="1" max="10" style="width: 100%; padding: 8px; margin-top: 5px;">
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>Logging</h3>
                        <div>
                            <label for="log_level">Log level:</label>
                            <select id="log_level" name="log_level" style="width: 100%; padding: 8px; margin-top: 5px;">
                                <option value="debug">Debug</option>
                                <option value="info" selected>Info</option>
                                <option value="warning">Warning</option>
                                <option value="error">Error</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="actions">
                    <button type="submit" name="save_settings" class="btn btn-primary">Save Settings</button>
                </div>
            </form>
            
            <h3 style="margin-top: 30px;">Maintenance</h3>
            <div class="actions">
                <a href="?action=cleanup&days=7" class="btn btn-secondary">Clean up 7 days</a>
                <a href="?action=cleanup&days=30" class="btn btn-secondary">Clean up 30 days</a>
                <a href="?action=export" class="btn btn-primary">Export Logs</a>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Hide all tabs
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Remove active class from nav tabs
            const navTabs = document.querySelectorAll('.nav-tab');
            navTabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        function viewDetails(attempt) {
            alert('Details:\n\nTimestamp: ' + attempt.timestamp + 
                  '\nIP: ' + attempt.ip + 
                  '\nReason: ' + attempt.reason + 
                  '\nUser Agent: ' + attempt.user_agent +
                  '\n\nData: ' + JSON.stringify(attempt.data, null, 2));
        }
        
        function getReasonBadgeClass(reason) {
            const classes = {
                'rate_limit': 'danger',
                'csrf_invalid': 'danger',
                'honeypot_triggered': 'warning',
                'invalid_headers': 'warning',
                'spam_content': 'danger',
                'math_incorrect': 'info'
            };
            return classes[reason] || 'info';
        }
    </script>
</body>
</html>

<?php

/**
 * Get recent spam attempts from log file
 */
function getRecentSpamAttempts($spamProtection, $limit = 50) {
    $logFile = $spamProtection->logFile;
    $attempts = array();
    
    if (!file_exists($logFile)) {
        return $attempts;
    }
    
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lines = array_slice($lines, -$limit);
    
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if ($entry) {
            $attempts[] = $entry;
        }
    }
    
    return array_reverse($attempts);
}

/**
 * Get CSS class for reason badge
 */
function getReasonBadgeClass($reason) {
    $classes = array(
        'rate_limit' => 'danger',
        'csrf_invalid' => 'danger',
        'honeypot_triggered' => 'warning',
        'invalid_headers' => 'warning',
        'spam_content' => 'danger',
        'math_incorrect' => 'info'
    );
    return $classes[$reason] ?: 'info';
}

/**
 * Export spam logs to CSV
 */
function exportSpamLogs($spamProtection) {
    $logFile = $spamProtection->logFile;
    
    if (!file_exists($logFile)) {
        header('Content-Type: text/plain');
        echo 'No log file found.';
        exit;
    }
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="spam-logs-' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV header
    fputcsv($output, array('Timestamp', 'IP Address', 'Reason', 'User Agent', 'Data'));
    
    // CSV data
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if ($entry) {
            fputcsv($output, array(
                $entry['timestamp'],
                $entry['ip'],
                $entry['reason'],
                $entry['user_agent'],
                json_encode($entry['data'])
            ));
        }
    }
    
    fclose($output);
    exit;
}

?>