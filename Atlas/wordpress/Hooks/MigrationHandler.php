<?php

namespace Atlas\WordPress\Hooks;

class MigrationHandler
{
    public static function run(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // 1. Tabla Dinámica Documental (ADR-001 / Cambiado a _documents)
        $table_documents = $wpdb->prefix . 'atlas_documents';
        $sql_documents = "CREATE TABLE $table_documents (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            source_id bigint(20) NOT NULL,
            source_type varchar(50) NOT NULL,
            title text NOT NULL,
            slug varchar(200) NOT NULL,
            content longtext NOT NULL,
            excerpt text NULL,
            metadata json NULL,
            language varchar(10) DEFAULT 'en' NOT NULL,
            updated_at datetime NOT NULL,
            embedding longblob NULL,
            PRIMARY KEY  (id),
            KEY source_idx (source_id, source_type)
        ) $charset_collate;";

        // 2. Tabla Enriquecida de Preguntas sin Respuesta (Mina de Oro de Analítica)
        $table_questions = $wpdb->prefix . 'atlas_unanswered_questions';
        $sql_questions = "CREATE TABLE $table_questions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            question text NOT NULL,
            url text NOT NULL,
            user_id bigint(20) DEFAULT 0 NOT NULL,
            created_at datetime NOT NULL,
            hit_count int(11) DEFAULT 1 NOT NULL,
            language varchar(10) DEFAULT 'en' NOT NULL,
            is_resolved tinyint(1) DEFAULT 0 NOT NULL,
            related_document_id bigint(20) NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        dbDelta($sql_documents);
        dbDelta($sql_questions);
    }
}