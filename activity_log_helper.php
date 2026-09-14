<?php
declare(strict_types=1);

function ensureActivityLogSchema(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS activity_logs (
        log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        actor_id INT UNSIGNED NULL,
        actor_role VARCHAR(30) NOT NULL,
        action VARCHAR(80) NOT NULL,
        description VARCHAR(255) NOT NULL,
        entity_type VARCHAR(50) NULL,
        entity_id BIGINT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_activity_actor (actor_id, created_at),
        INDEX idx_activity_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($sql)) {
        error_log('FixTrack activity log setup error: ' . $conn->error);
    }
}

function logActivity(
    mysqli $conn,
    ?int $actorId,
    string $actorRole,
    string $action,
    string $description,
    ?string $entityType = null,
    ?int $entityId = null
): void {
    try {
        ensureActivityLogSchema($conn);
        if (activityLogHasColumn($conn, 'actor_id')) {
            $stmt = $conn->prepare(
                'INSERT INTO activity_logs (actor_id, actor_role, action, description, entity_type, entity_id)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('issssi', $actorId, $actorRole, $action, $description, $entityType, $entityId);
        } else {
            // Keep compatibility with the original activity_logs schema.
            $legacyUserId = $actorId ?? 0;
            $stmt = $conn->prepare('INSERT INTO activity_logs (user_id, activity, description) VALUES (?, ?, ?)');
            $stmt->bind_param('iss', $legacyUserId, $action, $description);
        }
        $stmt->execute();
    } catch (Throwable $exception) {
        error_log('FixTrack activity log error: ' . $exception->getMessage());
    }
}

function activityLogHasColumn(mysqli $conn, string $column): bool
{
    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM activity_logs LIKE '{$column}'");
    return $result !== false && $result->num_rows > 0;
}
