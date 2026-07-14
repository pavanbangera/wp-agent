# WP Agent

**The Open Source AI Agent for WordPress**

> Control your WordPress site with any AI IDE using the Model Context Protocol.

[![CI](https://github.com/wp-agent/wp-agent/actions/workflows/ci.yml/badge.svg)](https://github.com/wp-agent/wp-agent/actions)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://php.net)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![MCP 2024-11](https://img.shields.io/badge/MCP-2024--11-purple)](https://spec.modelcontextprotocol.io)

---

## What is WP Agent?

WP Agent is a WordPress plugin that implements a full **Model Context Protocol (MCP) server**, making your WordPress installation fully controllable by AI IDEs and assistants.

Connect your site to:

| AI Client | Transport |
|-----------|-----------|
| **Cursor** | SSE |
| **Claude Code** | SSE / stdio |
| **VS Code** (Continue.dev) | SSE |
| **Windsurf** | SSE |
| **Cline** | SSE |
| **Roo Code** | SSE |
| **ChatGPT** | REST |

---

## What Can AI Do With WP Agent?

```
✅ Install & configure plugins
✅ Install & activate themes
✅ Create pages (Gutenberg & Elementor)
✅ Upload & optimize media
✅ Build WooCommerce stores
✅ Configure SEO (Yoast, RankMath, AIOSEO)
✅ Build navigation menus
✅ Create Custom Post Types & taxonomies
✅ Manage users & roles
✅ Create & restore backups
✅ Audit security & scan vulnerabilities
✅ Generate plugins, themes, & blocks
✅ Read & modify plugin code
```

---

## Quick Start

### Requirements

- WordPress 6.4+
- PHP 8.2+
- HTTPS enabled (required for Application Password auth)

### Installation

**Via Composer (recommended):**

```bash
cd wp-content/plugins
git clone https://github.com/wp-agent/wp-agent.git
cd wp-agent/plugin
composer install --no-dev --optimize-autoloader
```

Then activate **WP Agent** in your WordPress admin.

### Connect Cursor

Add to your `~/.cursor/mcp.json`:

```json
{
  "mcpServers": {
    "wp-agent": {
      "url": "https://your-site.com/wp-json/wp-agent/v1/sse",
      "headers": {
        "Authorization": "Basic BASE64(username:app-password)"
      }
    }
  }
}
```

### Connect Claude Code

```bash
claude mcp add wp-agent \
  --transport sse \
  --url https://your-site.com/wp-json/wp-agent/v1/sse \
  --header "Authorization: Basic BASE64(username:app-password)"
```

### Generate an Application Password

1. Go to **Users → Profile** in WordPress admin
2. Scroll to **Application Passwords**
3. Enter name: `WP Agent - Cursor`
4. Click **Add New Application Password**
5. Copy the password and Base64-encode: `echo -n "username:xxxx xxxx xxxx xxxx xxxx xxxx" | base64`

---

## MCP Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/wp-json/wp-agent/v1/mcp` | POST | Main JSON-RPC endpoint (stateless) |
| `/wp-json/wp-agent/v1/sse` | GET | SSE stream (persistent, for AI IDEs) |
| `/wp-json/wp-agent/v1/sse/messages` | POST | Messages for SSE transport |
| `/wp-json/wp-agent/v1/status` | GET | Health check & capability info |

---

## Available Tools (v0.1)

```
wordpress.site.info          — Get complete site information
wordpress.options.get        — Read a WordPress option value
```

More tools are added with each release — see the [Roadmap](#roadmap).

---

## Authentication

WP Agent supports three authentication methods:

| Method | Header | Best For |
|--------|--------|---------|
| **Application Password** (default) | `Authorization: Basic ...` | Zero-config, production |
| **JWT Bearer** | `Authorization: Bearer ...` | Programmatic clients |
| **API Key** | `X-WP-Agent-Key: ...` | Server-to-server |

---

## Extending WP Agent

Register custom tools from your plugin:

```php
add_action('wpa_register_tools', function (\WpAgent\MCP\Registry\ToolRegistry $registry): void {
    $registry->register(new MyCustomTool());
});
```

Add auth scope grants per user:

```php
add_filter('wpa_auth_user_scopes', function (array $scopes, \WP_User $user): array {
    if ($user->has_cap('my_custom_cap')) {
        $scopes[] = 'wp-agent:admin';
    }
    return $scopes;
}, 10, 2);
```

---

## Roadmap

| Version | Status | Focus |
|---------|--------|-------|
| `v0.1.0` | 🚧 Active | Core MCP server, site tools, CI/CD |
| `v0.2.0` | 📋 Planned | Pages, Posts, Media, Menus |
| `v0.3.0` | 📋 Planned | Plugins & Theme manager |
| `v0.4.0` | 📋 Planned | Elementor & Gutenberg |
| `v0.5.0` | 📋 Planned | WooCommerce |
| `v0.6.0` | 📋 Planned | SEO, Performance, Security |
| `v0.7.0` | 📋 Planned | AI Agent (planner + executor) |
| `v1.0.0` | 📋 Planned | Admin dashboard, stable release |

---

## Architecture

```
AI Client (Cursor / Claude / VS Code)
    │  MCP JSON-RPC 2.0
    ▼
MCP Gateway (Auth + Rate Limiting)
    │
    ▼
Tool Registry → [80+ Tools]
    │
    ▼
Service Layer (Business Logic)
    │
    ▼
WordPress Core APIs + Database
```

See [docs/architecture/](docs/architecture/) for full diagrams.

---

## Development

```bash
# Install dependencies
composer install

# Run all QA checks
composer qa

# Run tests only
composer test

# Static analysis
composer stan

# Fix code style
composer cs:fix
```

---

## Contributing

We welcome contributions! Please read [CONTRIBUTING.md](CONTRIBUTING.md) first.

- 🐛 [Report a bug](https://github.com/wp-agent/wp-agent/issues/new?template=bug_report.md)
- 💡 [Request a feature](https://github.com/wp-agent/wp-agent/issues/new?template=feature_request.md)
- 📖 [Improve the docs](docs/)

**Code of Conduct:** [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md)

---

## Security

Found a security vulnerability? **Do NOT open a public issue.**

Email: security@wp-agent.io

We follow responsible disclosure and will respond within 72 hours.

---

## License

GPL v2 or later — see [LICENSE](LICENSE)

---

## Credits

Built by the WP Agent Contributors. Inspired by the WordPress community and the Model Context Protocol specification by Anthropic.
