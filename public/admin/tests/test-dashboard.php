<?php
/**
 * Test Dashboard - Visual test results page
 * Shows all test results in a user-friendly format
 * 
 * To add a new test module, simply add it to the $testModules array below
 */
require_once __DIR__ . '/../../includes/auth.php';

// Require admin access
if (!is_admin()) {
    header('Location: ' . get_base_path() . '/index.php');
    exit;
}

$assetPath = get_asset_path();

// Test modules configuration - Easy to add new tests!
$testModules = [
    [
        'id' => 'env',
        'title' => 'Environment & API Configuration',
        'endpoint' => 'test-env.php',
        'type' => 'auto', // 'auto' = auto-loads, 'manual' = requires button click
        'description' => 'Tests .env file loading, API keys, and OpenAI connectivity'
    ],
    [
        'id' => 'auth',
        'title' => 'Authentication System',
        'endpoint' => 'test-auth.php',
        'type' => 'auto',
        'description' => 'Tests user authentication, sessions, and database storage'
    ],
    [
        'id' => 'db',
        'title' => 'Database Connection',
        'endpoint' => 'test-db.php',
        'type' => 'auto',
        'description' => 'Tests database connectivity, tables, and schema integrity'
    ],
    [
        'id' => 'google-oauth',
        'title' => 'Google OAuth Configuration',
        'endpoint' => 'test-google-oauth.php',
        'type' => 'auto',
        'description' => 'Tests Google OAuth credentials and configuration'
    ],
    [
        'id' => 'image',
        'title' => 'Image Generation',
        'endpoint' => 'test-image.php',
        'type' => 'link', // Opens in same window
        'description' => 'Interactive page for testing image generation with visual interface'
    ],
    [
        'id' => 'manual',
        'title' => 'Manual Testing',
        'endpoint' => null,
        'type' => 'manual',
        'description' => 'Manual testing checklist and links',
        'links' => [
            ['url' => '../../login.php', 'text' => 'Test Login Page'],
            ['url' => '../../index.php', 'text' => 'Test Main App'],
            ['url' => '../../logout.php', 'text' => 'Test Logout']
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Card-o-Bot Test Dashboard</title>
    <link rel="stylesheet" href="<?php echo $assetPath; ?>/assets/css/base.css">
</head>
<body class="test-dashboard-page">
    <div class="container">
        <header class="test-dashboard-header">
            <h1>🧪 Card-o-Bot Test Dashboard</h1>
            <p class="subtitle">Comprehensive testing for all system components</p>
        </header>

        <!-- Test Summary -->
        <div id="test-summary" class="test-summary admin-section">
            <div class="test-summary-content">
                <div class="summary-stats">
                    <div class="stat-item">
                        <span class="stat-label">Total Tests</span>
                        <span class="stat-value" id="total-tests">0</span>
                    </div>
                    <div class="stat-item stat-pass">
                        <span class="stat-label">Passed</span>
                        <span class="stat-value" id="passed-tests">0</span>
                    </div>
                    <div class="stat-item stat-warning">
                        <span class="stat-label">Warnings</span>
                        <span class="stat-value" id="warning-tests">0</span>
                    </div>
                    <div class="stat-item stat-fail">
                        <span class="stat-label">Failed</span>
                        <span class="stat-value" id="failed-tests">0</span>
                    </div>
                </div>
                <div class="summary-status" id="summary-status">
                    <span class="status-icon">⏳</span>
                    <span class="status-text">Loading tests...</span>
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="instructions alert alert-info">
            <h3>How to Use This Dashboard</h3>
            <ul>
                <li>Tests automatically load when the page opens</li>
                <li>Results update every 30 seconds automatically</li>
                <li>Click any test button to run it manually or view details</li>
                <li><strong>Green</strong> = Pass, <strong>Yellow</strong> = Warning, <strong>Red</strong> = Fail</li>
                <li>For detailed testing instructions, see <code>TESTING_GUIDE.md</code></li>
            </ul>
        </div>

        <!-- Test Modules -->
        <div class="test-modules">
            <?php foreach ($testModules as $index => $module): ?>
                <div class="test-section admin-section" data-test-id="<?php echo htmlspecialchars($module['id']); ?>">
                    <div class="admin-section-header">
                        <div class="test-header-content">
                            <h2>
                                <span class="test-number"><?php echo $index + 1; ?>.</span>
                                <?php echo htmlspecialchars($module['title']); ?>
                            </h2>
                            <div class="test-status-indicator" id="status-<?php echo htmlspecialchars($module['id']); ?>">
                                <span class="status-dot"></span>
                            </div>
                        </div>
                        <?php if (!empty($module['description'])): ?>
                            <p class="test-description"><?php echo htmlspecialchars($module['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="admin-section-body">
                        <?php if ($module['type'] === 'link'): ?>
                            <a href="<?php echo htmlspecialchars($module['endpoint']); ?>" class="test-link btn btn-secondary">
                                Open <?php echo htmlspecialchars($module['title']); ?>
                            </a>
                        <?php elseif ($module['type'] === 'manual'): ?>
                            <?php if (!empty($module['links'])): ?>
                                <div class="test-links">
                                    <?php foreach ($module['links'] as $link): ?>
                                        <a href="<?php echo htmlspecialchars($link['url']); ?>" class="test-link btn btn-secondary" target="_blank">
                                            <?php echo htmlspecialchars($link['text']); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($module['id'] === 'manual'): ?>
                                <div class="manual-test-info">
                                    <p><strong>Test Flow:</strong></p>
                                    <ol>
                                        <li><strong>Visit Login Page</strong> → Create account or login<br>
                                            <small>Expected: See login form, can create account</small></li>
                                        <li><strong>Visit Main App</strong> (while logged in) → Should show welcome message<br>
                                            <small>Expected: See "Welcome to Card-o-Bot!" message</small></li>
                                        <li><strong>Visit Logout</strong> → Should redirect to login<br>
                                            <small>Expected: Redirected to login page</small></li>
                                        <li><strong>Try accessing Main App while logged out</strong> → Should redirect to login<br>
                                            <small>Expected: Automatically redirected to login page (this is correct behavior!)</small></li>
                                    </ol>
                                    <div class="alert alert-warning">
                                        <strong>Note:</strong> The "Test Main App" link will redirect to login if you're not logged in. This is expected behavior - it means authentication protection is working!
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="<?php echo htmlspecialchars($module['endpoint']); ?>" target="_blank" class="test-link btn btn-secondary">
                                Run <?php echo htmlspecialchars($module['title']); ?>
                            </a>
                            <div id="<?php echo htmlspecialchars($module['id']); ?>-results" class="test-results">
                                <span class="loading">Loading test results...</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
        // Test modules configuration (matches PHP array)
        const testModules = <?php echo json_encode(array_filter($testModules, function($m) { return $m['type'] === 'auto'; })); ?>;
        
        // Store test results for summary calculation
        const testResults = {};
        
        // Auto-load test results when page loads
        async function loadTestResults() {
            const promises = testModules.map(module => loadTestModule(module));
            await Promise.all(promises);
            updateSummary();
        }
        
        async function loadTestModule(module) {
            const resultId = module.id + '-results';
            const statusId = 'status-' + module.id;
            
            try {
                const response = await fetch(module.endpoint);
                if (response.ok) {
                    const data = await response.json();
                    testResults[module.id] = data;
                    displayResults(resultId, data);
                    updateTestStatus(statusId, data.overall || 'unknown');
                } else {
                    const text = await response.text();
                    testResults[module.id] = { overall: 'fail' };
                    showError(resultId, 'Failed to load: HTTP ' + response.status, text);
                    updateTestStatus(statusId, 'fail');
                }
            } catch (error) {
                testResults[module.id] = { overall: 'fail' };
                showError(resultId, 'Error loading test', error.message);
                updateTestStatus(statusId, 'fail');
            }
        }
        
        function displayResults(elementId, data) {
            const element = document.getElementById(elementId);
            if (!element) return;
            
            const overall = data.overall || 'unknown';
            const badgeClass = overall === 'pass' ? 'status-pass' : 
                             overall === 'fail' ? 'status-fail' : 'status-warning';
            
            let html = `<div class="test-result-header">`;
            html += `<span class="status-badge ${badgeClass}">${overall.toUpperCase()}</span>`;
            html += `<strong class="test-summary-text">${data.summary || 'Test completed'}</strong>`;
            html += `</div>`;
            html += `<details class="test-details">`;
            html += `<summary class="test-details-toggle">View Full Results</summary>`;
            html += `<pre class="test-json">${JSON.stringify(data, null, 2)}</pre>`;
            html += `</details>`;
            
            element.innerHTML = html;
        }
        
        function showError(elementId, title, details) {
            const element = document.getElementById(elementId);
            if (!element) return;
            
            let html = `<div class="test-result-header">`;
            html += `<span class="status-badge status-fail">ERROR</span>`;
            html += `<strong class="test-summary-text">${title}</strong>`;
            html += `</div>`;
            html += `<details class="test-details">`;
            html += `<summary class="test-details-toggle">View Error Details</summary>`;
            html += `<pre class="test-json error">${details}</pre>`;
            html += `<p>Try opening the test page directly in a new tab to see the full error.</p>`;
            html += `</details>`;
            
            element.innerHTML = html;
        }
        
        function updateTestStatus(statusId, status) {
            const indicator = document.getElementById(statusId);
            if (!indicator) return;
            
            const dot = indicator.querySelector('.status-dot');
            if (!dot) return;
            
            dot.className = 'status-dot status-' + status;
        }
        
        function updateSummary() {
            const results = Object.values(testResults);
            const total = results.length;
            let passed = 0;
            let warnings = 0;
            let failed = 0;
            
            results.forEach(result => {
                const overall = result.overall || 'unknown';
                if (overall === 'pass') passed++;
                else if (overall === 'warning') warnings++;
                else if (overall === 'fail') failed++;
            });
            
            document.getElementById('total-tests').textContent = total;
            document.getElementById('passed-tests').textContent = passed;
            document.getElementById('warning-tests').textContent = warnings;
            document.getElementById('failed-tests').textContent = failed;
            
            // Update summary status
            const summaryStatus = document.getElementById('summary-status');
            let statusIcon = '✅';
            let statusText = 'All tests passed!';
            let statusClass = 'status-pass';
            
            if (failed > 0) {
                statusIcon = '❌';
                statusText = `${failed} test(s) failed`;
                statusClass = 'status-fail';
            } else if (warnings > 0) {
                statusIcon = '⚠️';
                statusText = `${warnings} warning(s) detected`;
                statusClass = 'status-warning';
            }
            
            summaryStatus.className = 'summary-status ' + statusClass;
            summaryStatus.innerHTML = `<span class="status-icon">${statusIcon}</span><span class="status-text">${statusText}</span>`;
        }
        
        // Load results on page load
        loadTestResults();
        
        // Refresh results every 30 seconds
        setInterval(loadTestResults, 30000);
    </script>
</body>
</html>
