<?php

namespace Atlas\WordPress\Database\Migrations;

class CreateQuestionsTable
{
    public static function up(string $prefix, string $charsetCollate): string
    {
        $table = $prefix . 'atlas_unanswered_questions';
        return "CREATE TABLE $table (
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
        ) $charsetCollate;";
    }
}