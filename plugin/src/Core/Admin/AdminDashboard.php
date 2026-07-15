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
        $sseUrl      = get_rest_url(null, 'wp-agent/v1/sse');

        // Enqueue Google Font styling inline for convenience.
        ?>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap');

            .wpa-dashboard-wrap {
                font-family: 'Outfit', sans-serif;
                background: radial-gradient(circle at top right, #1e1b4b, #0f172a 60%);
                color: #f8fafc;
                margin: 20px 20px 20px 0;
                border-radius: 20px;
                padding: 35px;
                box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.5);
                border: 1px solid rgba(255, 255, 255, 0.05);
            }

            .wpa-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 1px solid rgba(255, 255, 255, 0.08);
                padding-bottom: 25px;
                margin-bottom: 35px;
            }

            .wpa-title-wrap {
                display: flex;
                flex-direction: column;
            }

            .wpa-title {
                font-size: 36px;
                font-weight: 800;
                background: linear-gradient(135deg, #60a5fa, #a78bfa, #f472b6);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                margin: 0;
                letter-spacing: -0.5px;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .wpa-title::before {
                content: "🤖";
                font-size: 32px;
            }

            .wpa-subtitle {
                color: #94a3b8;
                margin-top: 6px;
                font-size: 14px;
                font-weight: 400;
            }

            .wpa-badge {
                background: rgba(16, 185, 129, 0.1);
                border: 1px solid rgba(16, 185, 129, 0.3);
                padding: 6px 16px;
                border-radius: 9999px;
                font-size: 12px;
                font-weight: 600;
                color: #34d399;
                display: flex;
                align-items: center;
                gap: 6px;
                box-shadow: 0 0 15px rgba(16, 185, 129, 0.15);
            }

            .wpa-badge::before {
                content: "";
                display: inline-block;
                width: 8px;
                height: 8px;
                background-color: #10b981;
                border-radius: 50%;
                box-shadow: 0 0 8px #10b981;
            }

            .wpa-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 24px;
                margin-bottom: 35px;
            }

            .wpa-card {
                background: rgba(30, 41, 59, 0.7);
                backdrop-filter: blur(10px);
                border-radius: 16px;
                padding: 24px;
                border: 1px solid rgba(255, 255, 255, 0.05);
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            }

            .wpa-card:hover {
                transform: translateY(-4px);
                border-color: rgba(96, 165, 250, 0.4);
                box-shadow: 0 10px 20px -5px rgba(96, 165, 250, 0.1);
                background: rgba(30, 41, 59, 0.9);
            }

            .wpa-card-title {
                font-size: 13px;
                text-transform: uppercase;
                color: #94a3b8;
                font-weight: 700;
                margin-bottom: 12px;
                letter-spacing: 0.5px;
            }

            .wpa-card-value {
                font-size: 32px;
                font-weight: 700;
                color: #f1f5f9;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .wpa-section {
                background: rgba(30, 41, 59, 0.4);
                backdrop-filter: blur(10px);
                border-radius: 16px;
                border: 1px solid rgba(255, 255, 255, 0.05);
                padding: 30px;
                margin-bottom: 30px;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            }

            .wpa-section-title {
                font-size: 22px;
                font-weight: 700;
                margin-top: 0;
                margin-bottom: 20px;
                color: #f1f5f9;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .wpa-input-group {
                margin-bottom: 24px;
            }

            .wpa-label {
                display: block;
                font-weight: 600;
                margin-bottom: 10px;
                color: #cbd5e1;
                font-size: 14px;
            }

            .wpa-input {
                width: 100%;
                background-color: #0f172a;
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 10px;
                padding: 12px 16px;
                color: #f8fafc;
                font-family: inherit;
                font-size: 14px;
                transition: all 0.2s ease;
                box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
            }

            .wpa-input:focus {
                border-color: #60a5fa;
                outline: none;
                box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.15), inset 0 2px 4px rgba(0,0,0,0.2);
            }

            .wpa-textarea {
                width: 100%;
                height: 160px;
                background-color: #0b0f19;
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 10px;
                padding: 16px;
                color: #34d399;
                font-family: 'JetBrains Mono', monospace;
                font-size: 13px;
                resize: none;
                line-height: 1.6;
                transition: all 0.2s ease;
            }

            .wpa-textarea:focus {
                border-color: #34d399;
                outline: none;
                box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.1);
            }

            .wpa-button {
                background: linear-gradient(135deg, #60a5fa, #4f46e5);
                color: white;
                border: none;
                border-radius: 10px;
                padding: 12px 24px;
                font-weight: 700;
                font-size: 14px;
                cursor: pointer;
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
            }

            .wpa-button:hover {
                transform: translateY(-1px);
                box-shadow: 0 6px 16px rgba(79, 70, 229, 0.4);
                background: linear-gradient(135deg, #3b82f6, #4338ca);
            }

            .wpa-button:active {
                transform: translateY(1px);
            }

            .wpa-table {
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
                text-align: left;
            }

            .wpa-table th {
                border-bottom: 1px solid rgba(255, 255, 255, 0.08);
                padding: 14px 18px;
                color: #94a3b8;
                font-weight: 700;
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .wpa-table td {
                border-bottom: 1px solid rgba(255, 255, 255, 0.04);
                padding: 16px 18px;
                color: #cbd5e1;
                font-size: 14px;
            }

            .wpa-table tr:last-child td {
                border-bottom: none;
            }

            .wpa-table tr:hover td {
                background-color: rgba(255, 255, 255, 0.02);
            }

            .wpa-status-pill {
                padding: 4px 12px;
                border-radius: 9999px;
                font-size: 12px;
                font-weight: 600;
                display: inline-block;
            }

            .wpa-status-ok {
                background-color: rgba(16, 185, 129, 0.1);
                border: 1px solid rgba(16, 185, 129, 0.2);
                color: #34d399;
            }

            .wpa-status-warn {
                background-color: rgba(245, 158, 11, 0.1);
                border: 1px solid rgba(245, 158, 11, 0.2);
                color: #fbbf24;
            }

            /* --- Generate Password & Auth Mode --- */
            .wpa-input-row {
                display: flex;
                gap: 12px;
                align-items: flex-end;
            }

            .wpa-input-row .wpa-input {
                flex: 1;
            }

            .wpa-button-outline {
                background: rgba(96, 165, 250, 0.05);
                color: #60a5fa;
                border: 1px solid rgba(96, 165, 250, 0.3);
                border-radius: 10px;
                padding: 12px 20px;
                font-weight: 700;
                cursor: pointer;
                white-space: nowrap;
                transition: all 0.2s ease;
                font-family: inherit;
                font-size: 14px;
            }

            .wpa-button-outline:hover {
                background: rgba(96, 165, 250, 0.1);
                border-color: #60a5fa;
                color: #e0f2fe;
            }

            .wpa-button-outline:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }

            .wpa-reveal-box {
                display: none;
                background: rgba(52, 211, 153, 0.05);
                border: 1px solid rgba(52, 211, 153, 0.2);
                border-radius: 12px;
                padding: 16px 20px;
                margin-top: 16px;
                font-size: 14px;
                color: #34d399;
                box-shadow: 0 4px 12px rgba(52, 211, 153, 0.05);
            }

            .wpa-reveal-box strong {
                display: block;
                margin-bottom: 8px;
                color: #6ee7b7;
                font-size: 15px;
            }

            .wpa-reveal-pass {
                font-family: 'JetBrains Mono', monospace;
                font-size: 16px;
                font-weight: 500;
                letter-spacing: 1px;
                color: #f8fafc;
                background: #090d16;
                border-radius: 8px;
                padding: 10px 14px;
                display: inline-block;
                margin: 6px 0 12px;
                word-break: break-all;
                border: 1px solid rgba(255, 255, 255, 0.05);
            }

            .wpa-tabs {
                display: flex;
                gap: 10px;
                margin-bottom: 24px;
                background: rgba(15, 23, 42, 0.4);
                padding: 6px;
                border-radius: 12px;
                border: 1px solid rgba(255, 255, 255, 0.04);
                align-self: flex-start;
                display: inline-flex;
            }

            .wpa-tab {
                padding: 8px 20px;
                border-radius: 8px;
                border: none;
                background: transparent;
                color: #94a3b8;
                cursor: pointer;
                font-weight: 600;
                font-size: 13px;
                font-family: inherit;
                transition: all 0.2s ease;
            }

            .wpa-tab:hover {
                color: #cbd5e1;
            }

            .wpa-tab.active {
                background: rgba(255, 255, 255, 0.08);
                color: #f8fafc;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }

            .wpa-spinner {
                display: inline-block;
                width: 14px;
                height: 14px;
                border: 2px solid rgba(96, 165, 250, 0.3);
                border-top-color: #60a5fa;
                border-radius: 50%;
                animation: wpa-spin 0.7s linear infinite;
                vertical-align: middle;
                margin-right: 8px;
            }

            @keyframes wpa-spin {
                to { transform: rotate(360deg); }
            }

            .wpa-notice {
                padding: 12px 16px;
                border-radius: 10px;
                font-size: 14px;
                margin-top: 14px;
                display: none;
            }

            .wpa-notice-error {
                background: rgba(239, 68, 68, 0.05);
                border: 1px solid rgba(239, 68, 68, 0.2);
                color: #f87171;
            }
        </style>

        <div class="wpa-dashboard-wrap">
            <div class="wpa-header">
                <div class="wpa-title-wrap">
                    <h1 class="wpa-title">WP Agent Dashboard</h1>
                    <div class="wpa-subtitle">Open Source AI Agent for WordPress (Spec 1.0.0 Active)</div>
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
                <input type="hidden" id="wpa-nonce" value="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>">
                <input type="hidden" id="wpa-user-id" value="<?php echo esc_attr((string) get_current_user_id()); ?>">

                <!-- Auth Mode Tabs -->
                <div class="wpa-tabs">
                    <button class="wpa-tab active" id="wpa-tab-basic" onclick="wpaSetMode('basic')">🔑 Basic Auth</button>
                    <button class="wpa-tab" id="wpa-tab-jwt" onclick="wpaSetMode('jwt')">🔒 JWT Bearer Token</button>
                </div>

                <!-- Basic Auth Panel -->
                <div id="wpa-panel-basic">
                    <div class="wpa-input-group">
                        <label class="wpa-label" for="wpa-pass">WordPress Application Password</label>
                        <div class="wpa-input-row">
                            <input class="wpa-input" type="text" id="wpa-pass" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx" oninput="wpaUpdateConfig()">
                            <button class="wpa-button-outline" id="wpa-gen-btn" type="button" onclick="wpaGeneratePassword()">⚡ Generate Password</button>
                        </div>
                        <span style="font-size: 12px; color: #94a3b8; margin-top: 6px; display: block;">
                            Click <strong style="color:#38bdf8;">⚡ Generate Password</strong> to auto-create one, or paste an existing password from Users → Profile → Application Passwords.
                        </span>
                    </div>

                    <!-- Password reveal box (shown after generation) -->
                    <div class="wpa-reveal-box" id="wpa-reveal-box">
                        <strong>✅ Application Password Created!</strong>
                        <div style="color: #94a3b8; font-size: 12px; margin-bottom: 4px;">Save this — it won't be shown again:</div>
                        <div class="wpa-reveal-pass" id="wpa-reveal-pass"></div>
                        <button class="wpa-button-outline" style="padding: 5px 12px; font-size: 12px;" onclick="wpaCopyRevealPass()">Copy Password</button>
                    </div>

                    <div class="wpa-notice wpa-notice-error" id="wpa-gen-error"></div>
                </div>

                <!-- JWT Panel -->
                <div id="wpa-panel-jwt" style="display:none;">
                    <div class="wpa-input-group">
                        <label class="wpa-label" for="wpa-jwt-token">JWT Bearer Token</label>
                        <input class="wpa-input" type="text" id="wpa-jwt-token" placeholder="eyJhbGciOiJIUzI1NiIs..." oninput="wpaUpdateConfig()">
                        <span style="font-size: 12px; color: #94a3b8; margin-top: 6px; display: block;">
                            Obtain a token via <code style="color:#38bdf8;">POST /wp-json/wp-agent/v1/auth/token</code> with your credentials.
                        </span>
                    </div>
                </div>

                <div class="wpa-input-group" style="margin-top: 20px;">
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
            
            <!-- Dashboard Footer -->
            <div class="wpa-footer" style="margin-top: 40px; padding-top: 20px; border-top: 1px solid rgba(255, 255, 255, 0.05); text-align: center; font-size: 13px; color: #64748b;">
                <span>Created by <a href="https://pavanbangera.com" target="_blank" style="color: #60a5fa; text-decoration: none; font-weight: 600; transition: color 0.2s;" onmouseover="this.style.color='#93c5fd'" onmouseout="this.style.color='#60a5fa'">Pavan Bangera</a></span>
            </div>
        </div>

        <script>
            let wpaAuthMode = 'basic';

            function wpaSetMode(mode) {
                wpaAuthMode = mode;
                document.getElementById('wpa-panel-basic').style.display = mode === 'basic' ? '' : 'none';
                document.getElementById('wpa-panel-jwt').style.display   = mode === 'jwt'   ? '' : 'none';
                document.getElementById('wpa-tab-basic').classList.toggle('active', mode === 'basic');
                document.getElementById('wpa-tab-jwt').classList.toggle('active', mode === 'jwt');
                wpaUpdateConfig();
            }

            function wpaUpdateConfig() {
                const username = document.getElementById('wpa-user').value;
                const sseUrl   = document.getElementById('wpa-url').value;
                let authHeader;

                if (wpaAuthMode === 'jwt') {
                    const token = (document.getElementById('wpa-jwt-token').value || '').trim();
                    authHeader = token ? 'Bearer ' + token : 'Bearer <YOUR_JWT_TOKEN>';
                } else {
                    const rawPass  = document.getElementById('wpa-pass').value;
                    const cleanPass = rawPass.replace(/\s+/g, '');
                    authHeader = cleanPass
                        ? 'Basic ' + btoa(username + ':' + cleanPass)
                        : 'Basic <YOUR_BASE64_CREDENTIALS>';
                }

                const configObj = {
                    mcpServers: {
                        'wp-agent': {
                            url: sseUrl,
                            headers: { Authorization: authHeader }
                        }
                    }
                };

                document.getElementById('wpa-json-output').value = JSON.stringify(configObj, null, 2);
            }

            function wpaCopyConfig() {
                const el = document.getElementById('wpa-json-output');
                el.select();
                el.setSelectionRange(0, 99999);
                navigator.clipboard.writeText(el.value).then(() => {
                    const btn = event.target;
                    const orig = btn.textContent;
                    btn.textContent = '✅ Copied!';
                    setTimeout(() => { btn.textContent = orig; }, 2000);
                });
            }

            function wpaCopyRevealPass() {
                const pass = document.getElementById('wpa-reveal-pass').textContent;
                navigator.clipboard.writeText(pass).then(() => {
                    const btn = event.target;
                    btn.textContent = '✅ Copied!';
                    setTimeout(() => { btn.textContent = 'Copy Password'; }, 2000);
                });
            }

            /**
             * Generates a WordPress Application Password via the WP REST API.
             * Uses the current user's ID and a WP nonce for authentication — no
             * external credentials needed, works entirely within the admin context.
             */
            async function wpaGeneratePassword() {
                const btn     = document.getElementById('wpa-gen-btn');
                const errBox  = document.getElementById('wpa-gen-error');
                const userId  = document.getElementById('wpa-user-id').value;
                const nonce   = document.getElementById('wpa-nonce').value;

                // Reset state.
                errBox.style.display = 'none';
                document.getElementById('wpa-reveal-box').style.display = 'none';
                btn.disabled = true;
                btn.innerHTML = '<span class="wpa-spinner"></span> Generating...';

                try {
                    const res = await fetch(
                        `/wp-json/wp/v2/users/${userId}/application-passwords`,
                        {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': nonce
                            },
                            body: JSON.stringify({ name: 'WP Agent MCP — ' + new Date().toLocaleDateString() })
                        }
                    );

                    if (!res.ok) {
                        const err = await res.json().catch(() => ({}));
                        throw new Error(err.message || `HTTP ${res.status}`);
                    }

                    const data = await res.json();
                    const rawPassword = data.password; // Only shown once by WP REST API.

                    // Auto-fill the password field and update config.
                    document.getElementById('wpa-pass').value = rawPassword;
                    wpaUpdateConfig();

                    // Show the one-time reveal box.
                    document.getElementById('wpa-reveal-pass').textContent = rawPassword;
                    document.getElementById('wpa-reveal-box').style.display = 'block';

                } catch (err) {
                    errBox.textContent = '❌ Failed to generate password: ' + err.message;
                    errBox.style.display = 'block';
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = '⚡ Generate Password';
                }
            }

            // Initialize on load.
            wpaUpdateConfig();
        </script>
        <?php
    }
}
