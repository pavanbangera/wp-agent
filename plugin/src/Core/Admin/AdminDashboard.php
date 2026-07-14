<?php

declare(strict_types=1);

namespace WpAgent\Core\Admin;

use WpAgent\MCP\Registry\ToolRegistry;

/**
 * Premium Admin Dashboard UI controller for WP Agent.
 *
 * Hooks into the WordPress administrative panel.
 *
 * @package WpAgent\Core\Admin
 * @since   0.1.0
 */
final class AdminDashboard
{
    public function __construct(
        private readonly ToolRegistry $toolRegistry,
    ) {}

    /**
     * Registers menu triggers.
     */
    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addAdminMenu']);
    }

    /**
     * Adds the WP Agent menu section to WP Admin dashboard.
     */
    public function addAdminMenu(): void
    {
        add_menu_page(
            'WP Agent',
            'WP Agent 🤖',
            'manage_options',
            'wp-agent-dashboard',
            [$this, 'renderDashboard'],
            'dashicons-superhero',
            65
        );
    }

    /**
     * Renders the premium administrative panel.
     */
    public function renderDashboard(): void
    {
        global $wpdb;

        // Query total log records.
        $logTable  = $wpdb->prefix . 'wpa_logs';
        $logExists = $wpdb->get_var("SHOW TABLES LIKE '{$logTable}'") === $logTable;
        $logs      = [];

        if ( $logExists ) {
            $logs = $wpdb->get_results(
                "SELECT * FROM {$logTable} ORDER BY created_at DESC LIMIT 15",
                ARRAY_A
            );
        }

        // Fetch tool collection.
        $tools = $this->toolRegistry->all();

        // Get current user details.
        $currentUser = wp_get_current_user();
        $username    = $currentUser instanceof \WP_User ? $currentUser->user_login : 'admin';
        $sseUrl      = get_rest_url(null, 'wp-agent/v1/mcp/sse');

        // Enqueue Google Font styling inline for convenience.
        ?>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');

            .wpa-dashboard-wrap {
                font-family: 'Outfit', sans-serif;
                background-color: #0f172a;
                color: #f8fafc;
                margin: 20px 20px 0 0;
                border-radius: 16px;
                padding: 30px;
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.3);
            }

            .wpa-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 1px solid #1e293b;
                padding-bottom: 20px;
                margin-bottom: 30px;
            }

            .wpa-title {
                font-size: 32px;
                font-weight: 700;
                background: linear-gradient(135deg, #38bdf8, #818cf8);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                margin: 0;
            }

            .wpa-badge {
                background: linear-gradient(135deg, #10b981, #059669);
                padding: 6px 14px;
                border-radius: 9999px;
                font-size: 13px;
                font-weight: 600;
                color: white;
            }

            .wpa-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 24px;
                margin-bottom: 35px;
            }

            .wpa-card {
                background-color: #1e293b;
                border-radius: 12px;
                padding: 24px;
                border: 1px solid #334155;
                transition: transform 0.2s ease, border-color 0.2s ease;
            }

            .wpa-card:hover {
                transform: translateY(-2px);
                border-color: #4f46e5;
            }

            .wpa-card-title {
                font-size: 15px;
                text-transform: uppercase;
                color: #94a3b8;
                font-weight: 600;
                margin-bottom: 10px;
            }

            .wpa-card-value {
                font-size: 38px;
                font-weight: 700;
                color: #f1f5f9;
            }

            .wpa-section {
                background-color: #1e293b;
                border-radius: 12px;
                border: 1px solid #334155;
                padding: 24px;
                margin-bottom: 30px;
            }

            .wpa-section-title {
                font-size: 20px;
                font-weight: 600;
                margin-top: 0;
                margin-bottom: 20px;
                color: #f1f5f9;
            }

            .wpa-input-group {
                margin-bottom: 20px;
            }

            .wpa-label {
                display: block;
                font-weight: 500;
                margin-bottom: 8px;
                color: #cbd5e1;
            }

            .wpa-input {
                width: 100%;
                background-color: #0f172a;
                border: 1px solid #475569;
                border-radius: 6px;
                padding: 10px 14px;
                color: #f8fafc;
                font-family: inherit;
            }

            .wpa-input:focus {
                border-color: #38bdf8;
                outline: none;
            }

            .wpa-textarea {
                width: 100%;
                height: 150px;
                background-color: #0f172a;
                border: 1px solid #475569;
                border-radius: 6px;
                padding: 12px;
                color: #38bdf8;
                font-family: monospace;
                font-size: 13px;
                resize: none;
            }

            .wpa-button {
                background: linear-gradient(135deg, #38bdf8, #4f46e5);
                color: white;
                border: none;
                border-radius: 6px;
                padding: 10px 20px;
                font-weight: 600;
                cursor: pointer;
                transition: opacity 0.2s ease;
            }

            .wpa-button:hover {
                opacity: 0.9;
            }

            .wpa-table {
                width: 100%;
                border-collapse: collapse;
                text-align: left;
            }

            .wpa-table th {
                border-bottom: 2px solid #334155;
                padding: 12px 16px;
                color: #94a3b8;
                font-weight: 600;
            }

            .wpa-table td {
                border-bottom: 1px solid #334155;
                padding: 14px 16px;
                color: #cbd5e1;
            }

            .wpa-table tr:hover td {
                background-color: #334155;
            }

            .wpa-status-pill {
                padding: 4px 10px;
                border-radius: 9999px;
                font-size: 11px;
                font-weight: 600;
            }

            .wpa-status-ok {
                background-color: rgba(16, 185, 129, 0.15);
                color: #10b981;
            }

            .wpa-status-warn {
                background-color: rgba(245, 158, 11, 0.15);
                color: #f59e0b;
            }
        </style>

        <div class="wpa-dashboard-wrap">
            <div class="wpa-header">
                <div>
                    <h1 class="wpa-title">WP Agent Dashboard</h1>
                    <div style="color: #94a3b8; margin-top: 4px;">Open Source AI Agent for WordPress (Spec 1.0.0 Active)</div>
                </div>
                <div>
                    <span class="wpa-badge">SSE Active Stream</span>
                </div>
            </div>

            <!-- Stats grid -->
            <div class="wpa-grid">
                <div class="wpa-card">
                    <div class="wpa-card-title">Registered MCP Tools</div>
                    <div class="wpa-card-value"><?php echo count($tools); ?></div>
                </div>
                <div class="wpa-card">
                    <div class="wpa-card-title">Database Connections</div>
                    <div class="wpa-card-value">Active</div>
                </div>
                <div class="wpa-card">
                    <div class="wpa-card-title">Transport Channel</div>
                    <div class="wpa-card-value" style="font-size: 26px;">Server-Sent Events</div>
                </div>
            </div>

            <!-- MCP Configurator -->
            <div class="wpa-section">
                <h2 class="wpa-section-title">IDE MCP Configurator</h2>
                <p style="color: #94a3b8; margin-bottom: 20px;">Generate your ready-to-copy client configuration file context for Cursor, Claude Code, Cline, etc. securely on the fly.</p>
                
                <input type="hidden" id="wpa-user" value="<?php echo esc_attr($username); ?>">
                <input type="hidden" id="wpa-url" value="<?php echo esc_url($sseUrl); ?>">
                
                <div class="wpa-input-group">
                    <label class="wpa-label" for="wpa-pass">WordPress Application Password</label>
                    <input class="wpa-input" type="text" id="wpa-pass" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx" oninput="wpaUpdateConfig()">
                    <span style="font-size: 12px; color: #94a3b8; margin-top: 6px; display: block;">
                        Create this in your profile page under Users -> Profile -> Application Passwords.
                    </span>
                </div>

                <div class="wpa-input-group">
                    <label class="wpa-label" for="wpa-json-output">Generated MCP Config (JSON)</label>
                    <textarea class="wpa-textarea" id="wpa-json-output" readonly></textarea>
                </div>

                <button class="wpa-button" type="button" onclick="wpaCopyConfig()">Copy Configuration</button>
            </div>

            <!-- Tools catalog -->
            <div class="wpa-section">
                <h2 class="wpa-section-title">MCP Tools Directory</h2>
                <div style="max-height: 400px; overflow-y: auto;">
                    <table class="wpa-table">
                        <thead>
                            <tr>
                                <th>Tool Name</th>
                                <th>Description</th>
                                <th>Scope Required</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $tools as $tool ) : ?>
                                <tr>
                                    <td style="font-family: monospace; color: #38bdf8; font-weight: 600;"><?php echo esc_html($tool->getName()); ?></td>
                                    <td><?php echo esc_html(wp_trim_words($tool->getDescription(), 12)); ?></td>
                                    <td>
                                        <span class="wpa-status-pill wpa-status-ok">
                                            <?php echo esc_html(implode(', ', $tool->getRequiredScopes())); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent logs -->
            <div class="wpa-section">
                <h2 class="wpa-section-title">Security & Operations Log (Recent 15 Runs)</h2>
                <?php if ( empty($logs) ) : ?>
                    <p style="color: #94a3b8;">No operations log entries found.</p>
                <?php else : ?>
                    <table class="wpa-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Level</th>
                                <th>Message</th>
                                <th>Category</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $logs as $log ) : ?>
                                <tr>
                                    <td><?php echo esc_html($log['created_at']); ?></td>
                                    <td>
                                        <span class="wpa-status-pill <?php echo strtolower($log['level']) === 'error' ? 'wpa-status-warn' : 'wpa-status-ok'; ?>">
                                            <?php echo esc_html($log['level']); ?>
                                        </span>
                                    </td>
                                    <td style="font-family: monospace; font-size: 13px;"><?php echo esc_html($log['message']); ?></td>
                                    <td><?php echo esc_html($log['category'] ?? 'System'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <script>
            function wpaUpdateConfig() {
                const username = document.getElementById('wpa-user').value;
                const rawPass = document.getElementById('wpa-pass').value;
                const cleanPass = rawPass.replace(/\s+/g, '');
                const sseUrl = document.getElementById('wpa-url').value;

                let authHeader = "Basic <YOUR_BASE64_CREDENTIALS>";
                if (cleanPass) {
                    authHeader = "Basic " + btoa(username + ":" + cleanPass);
                }

                const configObj = {
                    "mcpServers": {
                        "wp-agent": {
                            "url": sseUrl,
                            "headers": {
                                "Authorization": authHeader
                            }
                        }
                    }
                };

                document.getElementById('wpa-json-output').value = JSON.stringify(configObj, null, 2);
            }

            function wpaCopyConfig() {
                const copyText = document.getElementById('wpa-json-output');
                copyText.select();
                copyText.setSelectionRange(0, 99999);
                navigator.clipboard.writeText(copyText.value);
                alert("MCP Configuration copied to clipboard!");
            }

            // Initialize default values
            wpaUpdateConfig();
        </script>
        <?php
    }
}
