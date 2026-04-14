<?php

declare(strict_types=1);

namespace App\Repository;

use Karhu\Db\Connection;

/**
 * Issue persistence — wraps karhu-db Connection for issue-specific queries.
 */
final class IssueRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {}

    /** @return list<array<string, mixed>> */
    public function all(?string $status = null): array
    {
        if ($status !== null) {
            return $this->db->fetchAll(
                'SELECT * FROM issues WHERE status = :status ORDER BY created_at DESC',
                ['status' => $status],
            );
        }
        return $this->db->fetchAll('SELECT * FROM issues ORDER BY created_at DESC');
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM issues WHERE id = :id', ['id' => $id]);
    }

    /** @return string Last insert ID */
    public function create(string $title, string $body, string $priority, string $author, ?string $assignee = null): string
    {
        return $this->db->insert('issues', [
            'title' => $title,
            'body' => $body,
            'priority' => $priority,
            'author' => $author,
            'assignee' => $assignee,
        ]);
    }

    public function update(int $id, array $data): int
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->update('issues', $data, ['id' => $id]);
    }

    public function delete(int $id): int
    {
        return $this->db->delete('issues', ['id' => $id]);
    }

    public function count(?string $status = null): int
    {
        if ($status !== null) {
            return (int) $this->db->fetchScalar(
                'SELECT COUNT(*) FROM issues WHERE status = :status',
                ['status' => $status],
            );
        }
        return (int) $this->db->fetchScalar('SELECT COUNT(*) FROM issues');
    }
}
