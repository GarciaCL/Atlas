<?php

namespace Atlas\WordPress\Repositories;

use Atlas\Repositories\QuestionRepositoryInterface;

class WordPressQuestionRepository implements QuestionRepositoryInterface
{
    private \wpdb $db;
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = $wpdb->prefix . 'atlas_unanswered_questions';
    }

    public function logUnanswered(string $question, string $url, int $userId): void
    {
        $normalized = mb_strtolower(trim($question));

        // Comprobar si ya existe una pregunta idéntica sin resolver
        $existingId = $this->db->get_var($this->db->prepare(
            "SELECT id FROM {$this->table} WHERE LOWER(question) = %s AND is_resolved = 0",
            $normalized
        ));

        if ($existingId) {
            $this->db->query($this->db->prepare(
                "UPDATE {$this->table} SET hit_count = hit_count + 1, created_at = %s WHERE id = %d",
                current_time('mysql'),
                $existingId
            ));
            return;
        }

        $this->db->insert(
            $this->table,
            [
                'question' => $question,
                'url' => $url,
                'user_id' => $userId,
                'created_at' => current_time('mysql'),
                'hit_count' => 1,
                'is_resolved' => 0
            ],
            ['%s', '%s', '%d', '%s', '%d', '%d']
        );
    }
}