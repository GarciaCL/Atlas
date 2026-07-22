<?php

namespace Atlas\WordPress\Repositories;

use Atlas\Repositories\AnalyticsRepositoryInterface;

class WordPressAnalyticsRepository implements AnalyticsRepositoryInterface
{
    private \wpdb $db;
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = $wpdb->prefix . 'atlas_unanswered_questions';
    }

    public function getTopUnansweredQuestions(int $limit): array
    {
        $sql = "SELECT id, question, url, hit_count, created_at 
                FROM {$this->table} 
                WHERE is_resolved = 0 
                ORDER BY hit_count DESC, created_at DESC 
                LIMIT %d";

        return $this->db->get_results($this->db->prepare($sql, $limit), ARRAY_A) ?: [];
    }
}