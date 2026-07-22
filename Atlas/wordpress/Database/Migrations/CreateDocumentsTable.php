<?php

namespace Atlas\WordPress\Database\Migrations;

class CreateDocumentsTable
{
    public static function up(string $prefix, string $charsetCollate): string
    {
        $table = $prefix . 'atlas_documents';
        return "CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            source_id bigint(20) NOT NULL,
            source_type varchar(50) NOT NULL,
            title text NOT NULL,
            slug varchar(200) NOT NULL,
            content longtext NOT NULL,
            excerpt text NULL,
            seo json NULL,
            actions json NULL,
            custom_fields json NULL,
            language varchar(10) DEFAULT 'en' NOT NULL,
            updated_at datetime NOT NULL,
            embedding text NULL, -- Guardado como definición abstracta de texto/futuro vector
            PRIMARY KEY  (id),
            KEY source_idx (source_id, source_type)
        ) $charsetCollate;";
    }
}