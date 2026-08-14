<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Schema;

final class Installer
{
    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();

        $organizations = $wpdb->prefix . 'stageart_organizations';
        $people = $wpdb->prefix . 'stageart_people';
        $memberships = $wpdb->prefix . 'stageart_memberships';
        $projects = $wpdb->prefix . 'stageart_projects';

        dbDelta("CREATE TABLE {$organizations} (
            id CHAR(36) NOT NULL,
            name VARCHAR(255) NOT NULL,
            type VARCHAR(100) NULL,
            description TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$people} (
            id CHAR(36) NOT NULL,
            wp_user_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY wp_user_id (wp_user_id)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$memberships} (
            id CHAR(36) NOT NULL,
            organization_id CHAR(36) NOT NULL,
            person_id CHAR(36) NOT NULL,
            role_key VARCHAR(20) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY organization_id (organization_id),
            KEY person_id (person_id)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$projects} (
            id CHAR(36) NOT NULL,
            organization_id CHAR(36) NOT NULL,
            name VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY organization_id (organization_id)
        ) {$charsetCollate};");
    }
}
