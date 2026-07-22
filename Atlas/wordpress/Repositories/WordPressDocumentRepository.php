<?php

namespace Atlas\WordPress\Repositories;

use Atlas\DTO\Document;
use Atlas\Repositories\DocumentRepositoryInterface;

/**
 * Repositorio de documentos para WordPress utilizando consultas de relevancia en MySQL.
 */
class WordPressDocumentRepository implements DocumentRepositoryInterface
{
    private \wpdb $db;
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = $wpdb->prefix . 'atlas_documents';
    }

    public function save(Document $document): int
    {
        $data = [
            'source_id'    => $document->sourceId,
            'source_type'  => $document->sourceType,
            'title'        => $document->title,
            'slug'         => $document->slug,
            'content'      => $document->content,
            'excerpt'      => $document->excerpt,
            'seo'          => json_encode($document->seo),
            'actions'      => json_encode($document->actions),
            'custom_fields'=> json_encode($document->customFields),
            'language'     => $document->language,
            'updated_at'   => $document->updatedAt ?: current_time('mysql'),
        ];

        $format = ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];

        $exists = $this->db->get_var($this->db->prepare(
            "SELECT id FROM {$this->table} WHERE source_id = %d AND source_type = %s",
            $document->sourceId,
            $document->sourceType
        ));

        if ($exists) {
            $this->db->update($this->table, $data, ['id' => $exists], $format, ['%d']);
            return (int) $exists;
        }

        $this->db->insert($this->table, $data, $format);
        return (int) $this->db->insert_id;
    }

    public function delete(int $sourceId, string $sourceType): bool
    {
        $result = $this->db->delete($this->table, [
            'source_id' => $sourceId,
            'source_type' => $sourceType
        ], ['%d', '%s']);

        return $result !== false;
    }

    public function search(string $query, int $limit = 5): array
    {
        $cleanQuery = trim(mb_strtolower($query, 'UTF-8'));
        if (empty($cleanQuery)) {
            return [];
        }

        // Palabras vacías a ignorar en el cálculo de coincidencias
        $stopWords = [
            'de', 'la', 'que', 'el', 'en', 'y', 'a', 'los', 'del', 'se', 'las', 'por', 'un', 'para', 'con', 'no', 'una',
            'su', 'al', 'lo', 'como', 'más', 'pero', 'sus', 'le', 'ya', 'o', 'este', 'sí', 'porque', 'esta', 'entre',
            'cuando', 'muy', 'sin', 'sobre', 'también', 'me', 'hasta', 'hay', 'donde', 'quien', 'desde', 'todo', 'nos',
            'hacen', 'hace', 'tienen', 'puedo', 'pueden', 'ser', 'estar'
        ];

        $rawWords = preg_split('/\s+/', $cleanQuery);
        $keywords = [];

        foreach ($rawWords as $word) {
            $word = preg_replace('/[^\w]/u', '', $word);
            if (mb_strlen($word) > 2 && !in_array($word, $stopWords)) {
                $keywords[] = $word;
            }
        }

        if (empty($keywords)) {
            $keywords = [$cleanQuery];
        }

        $scoreParts = [];
        $params = [];

        // Bono masivo (+50 pts) por coincidencia exacta de la frase en título
        $scoreParts[] = "(CASE WHEN LOWER(title) LIKE %s THEN 50 ELSE 0 END)";
        $params[] = '%' . $this->db->esc_like($cleanQuery) . '%';

        foreach ($keywords as $word) {
            $likeWord = '%' . $this->db->esc_like($word) . '%';

            $scoreParts[] = "(CASE WHEN LOWER(title) LIKE %s THEN 20 ELSE 0 END)";
            $params[] = $likeWord;

            $scoreParts[] = "(CASE WHEN LOWER(custom_fields) LIKE %s THEN 15 ELSE 0 END)";
            $params[] = $likeWord;

            $scoreParts[] = "(CASE WHEN LOWER(excerpt) LIKE %s THEN 8 ELSE 0 END)";
            $params[] = $likeWord;

            $scoreParts[] = "(CASE WHEN LOWER(content) LIKE %s THEN 2 ELSE 0 END)";
            $params[] = $likeWord;
        }

        $scoreSql = implode(' + ', $scoreParts);
        
        $sql = "
            SELECT *, ({$scoreSql}) AS relevance_score 
            FROM {$this->table} 
            WHERE source_type NOT IN ('elementor_library', 'e-landing-page', 'nav_menu_item', 'revision', 'acf-post-type', 'acf-field-group')
            HAVING relevance_score >= 8 
            ORDER BY relevance_score DESC 
            LIMIT %d
        ";

        $params[] = $limit;
        $prepared = $this->db->prepare($sql, $params);
        $rows = $this->db->get_results($prepared);

        if (empty($rows)) {
            return [];
        }

        $documents = [];
        foreach ($rows as $row) {
            $documents[] = $this->mapToDocument($row);
        }

        return $documents;
    }

    public function findRelevant(string $query): ?Document
    {
        $results = $this->search($query, 1);
        return !empty($results) ? $results[0] : null;
    }

    private function mapToDocument(object $row): Document
    {
        return new Document(
            sourceId: (int)$row->source_id,
            sourceType: $row->source_type,
            title: $row->title,
            slug: $row->slug,
            content: $row->content,
            excerpt: $row->excerpt,
            seo: json_decode($row->seo ?? '[]', true) ?: [],
            actions: json_decode($row->actions ?? '[]', true) ?: [],
            customFields: json_decode($row->custom_fields ?? '[]', true) ?: [],
            language: $row->language ?? 'es',
            updatedAt: $row->updated_at
        );
    }
}