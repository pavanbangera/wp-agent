<?php

declare(strict_types=1);

namespace WpAgent\Database;

/**
 * Database schema migrator.
 *
 * Creates and updates WP Agent custom tables using dbDelta().
 * Versioned schema — checks installed version before running.
 *
 * @package WpAgent\Database
 * @since   0.1.0
 */
final class Migrator
{
    /** Schema version — bump when tables change. */
    private const SCHEMA_VERSION = '0.1.0';

    private const OPTION_KEY = 'wpa_db_version';

    /**
     * Runs migrations if the schema version has changed.
     */
    public function run(): void
    {
        $installed = get_option(self::OPTION_KEY, '0.0.0');

        if ( version_compare($installed, self::SCHEMA_VERSION, '>=' ) ) {
            return;
        }

        $this->createTables();

        update_option(self::OPTION_KEY, self::SCHEMA_VERSION);
    }

    /**
     * Forces migration regardless of installed version (for CLI/testing).
     */
    public function forceRun(): void
    {
        $this->createTables();
        update_option(self::OPTION_KEY, self::SCHEMA_VERSION);
    }

    // -------------------------------------------------------------------------
    // Private
    // -------------------------------------------------------------------------

    private function createTables(): void
    {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();
        $prefix  = $wpdb->prefix;

        // phpcs:disable Generic.WhiteSpace.DisallowSpaceIndent
        $sql = [];

        // --- Execution Log ---------------------------------------------------
        $sql[] = "CREATE TABLE {$prefix}wpa_execution_log (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id    VARCHAR(64)     NOT NULL DEFAULT '',
            tool_name     VARCHAR(128)    NOT NULL DEFAULT '',
            tool_input    LONGTEXT,
            tool_output   LONGTEXT,
            status        VARCHAR(32)     NOT NULL DEFAULT 'info',
            user_id       BIGINT UNSIGNED NOT NULL DEFAULT 0,
            client_name   VARCHAR(64)     NOT NULL DEFAULT '',
            duration_ms   INT UNSIGNED    NOT NULL DEFAULT 0,
            created_at    DATETIME        NOT NULL,
            updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_session (session_id),
            KEY idx_tool    (tool_name(64)),
            KEY idx_status  (status),
            KEY idx_created (created_at)
        ) {$charset};";

        // --- Workflows -------------------------------------------------------
        $sql[] = "CREATE TABLE {$prefix}wpa_workflows (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            workflow_id   VARCHAR(64)     NOT NULL,
            title         VARCHAR(255)    NOT NULL DEFAULT '',
            steps         LONGTEXT        NOT NULL,
            status        VARCHAR(32)     NOT NULL DEFAULT 'draft',
            current_step  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_by    BIGINT UNSIGNED NOT NULL DEFAULT 0,
            approved_by   BIGINT UNSIGNED NOT NULL DEFAULT 0,
            approved_at   DATETIME,
            completed_at  DATETIME,
            created_at    DATETIME        NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY idx_workflow_id (workflow_id),
            KEY idx_status (status)
        ) {$charset};";

        // --- Sessions --------------------------------------------------------
        $sql[] = "CREATE TABLE {$prefix}wpa_sessions (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id    VARCHAR(64)     NOT NULL,
            client_name   VARCHAR(128)    NOT NULL DEFAULT '',
            client_version VARCHAR(32)    NOT NULL DEFAULT '',
            auth_driver   VARCHAR(32)     NOT NULL DEFAULT '',
            user_id       BIGINT UNSIGNED NOT NULL DEFAULT 0,
            scopes        TEXT,
            last_active   DATETIME,
            ip_address    VARCHAR(45)     NOT NULL DEFAULT '',
            created_at    DATETIME        NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY idx_session_id  (session_id),
            KEY idx_last_active        (last_active)
        ) {$charset};";

        // --- Rate Limits -----------------------------------------------------
        $sql[] = "CREATE TABLE {$prefix}wpa_rate_limits (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            identifier    VARCHAR(128)    NOT NULL,
            bucket        VARCHAR(64)     NOT NULL,
            hits          INT UNSIGNED    NOT NULL DEFAULT 1,
            window_start  DATETIME        NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY idx_bucket (identifier(64), bucket, window_start)
        ) {$charset};";

        // --- Backups ---------------------------------------------------------
        $sql[] = "CREATE TABLE {$prefix}wpa_backups (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            backup_id     VARCHAR(64)     NOT NULL,
            label         VARCHAR(255)    NOT NULL DEFAULT '',
            type          VARCHAR(32)     NOT NULL DEFAULT 'full',
            storage       VARCHAR(64)     NOT NULL DEFAULT 'local',
            path          TEXT,
            size_bytes    BIGINT UNSIGNED NOT NULL DEFAULT 0,
            checksum      VARCHAR(64)     NOT NULL DEFAULT '',
            status        VARCHAR(32)     NOT NULL DEFAULT 'creating',
            created_at    DATETIME        NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY idx_backup_id (backup_id),
            KEY idx_type   (type),
            KEY idx_status (status)
        ) {$charset};";
        // phpcs:enable

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ( $sql as $statement ) {
            dbDelta($statement);
        }
    }
}
