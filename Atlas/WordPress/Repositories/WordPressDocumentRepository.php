<?php

namespace Atlas\WordPress\Repositories;

use Atlas\DTO\Document;
use Atlas\Repositories\DocumentRepositoryInterface;

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

        // Comprobar si ya existe para hacer UPDATE o INSERT de forma segura
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
}