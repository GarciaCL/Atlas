<?php

namespace Atlas\WordPress\Hooks;

use Atlas\WordPress\Database\Migrations\CreateDocumentsTable;
use Atlas\WordPress\Database\Migrations\CreateQuestionsTable;

class MigrationRunner
{
    public static function run(): void
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sqlDocuments = CreateDocumentsTable::up($wpdb->prefix, $charsetCollate);
        $sqlQuestions = CreateQuestionsTable::up($wpdb->prefix, $charsetCollate);

        dbDelta($sqlDocuments);
        dbDelta($sqlQuestions);
    }
}