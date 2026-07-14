<?php

namespace Atlas\WordPress\Repositories;

use Atlas\Repositories\RetrievalRepositoryInterface;

class WordPressRetrievalRepository implements RetrievalRepositoryInterface
{
    private \wpdb $db;
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = $wpdb->prefix . 'atlas_documents';
    }

    public function searchDocuments(string $query): array
    {
        // Dividimos la query en palabras clave para buscar coincidencias parciales
        $words = array_filter(explode(' ', $query), fn($word) => mb_strlen($word) > 2);
        
        if (empty($words)) {
            $words = [$query];
        }

        $conditions = [];
        $parameters = [];

        // Generamos un SQL dinámico y seguro contra inyecciones SQL
        foreach ($words as $word) {
            $conditions[] = "(title LIKE %s OR content LIKE %s)";
            $likeWord = '%' . $this->db->esc_like($word) . '%';
            $parameters[] = $likeWord;
            $parameters[] = $likeWord;
        }

        $sql = "SELECT id, source_id, source_type, title, slug, content, excerpt, actions, custom_fields 
                FROM {$this->table} 
                WHERE " . implode(' OR ', $conditions) . " 
                LIMIT 10";

        $preparedSql = $this->db->prepare($sql, $parameters);
        $results = $this->db->get_results($preparedSql, ARRAY_A);

        return $results ?: [];
    }
}