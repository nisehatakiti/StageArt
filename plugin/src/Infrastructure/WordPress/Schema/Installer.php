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
        $userAccounts = $wpdb->prefix . 'stageart_user_accounts';
        $emailCredentials = $wpdb->prefix . 'stageart_email_credentials';
        $externalIdentities = $wpdb->prefix . 'stageart_external_identities';

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
            wp_user_id BIGINT UNSIGNED NULL,
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

        dbDelta("CREATE TABLE {$userAccounts} (
            id CHAR(36) NOT NULL,
            person_id CHAR(36) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY person_id (person_id)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$emailCredentials} (
            id CHAR(36) NOT NULL,
            user_account_id CHAR(36) NOT NULL,
            email VARCHAR(255) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            email_verified_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_account_id (user_account_id),
            UNIQUE KEY email (email)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$externalIdentities} (
            id CHAR(36) NOT NULL,
            user_account_id CHAR(36) NOT NULL,
            provider VARCHAR(50) NOT NULL,
            provider_user_id VARCHAR(255) NOT NULL,
            linked_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY user_account_id (user_account_id),
            UNIQUE KEY provider_identity (provider, provider_user_id)
        ) {$charsetCollate};");
    }
}
