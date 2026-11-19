<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

require_once __DIR__ . '/../data_base/Database.php';

$columns = [
    'cas_mve' => 'deleted_at',
    'rings' => 'deleted_at',
    'deces' => 'deleted_at',
    'supervisions_pci' => 'deleted_at',
];

try {
    $db = Database::connection();
    foreach ($columns as $table => $column) {
        $statement = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $statement->execute([$table, $column]);
        if ((int) $statement->fetchColumn() === 0) {
            $db->exec("ALTER TABLE {$table} ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
        }
    }

    fwrite(STDOUT, "Colonnes d’archivage vérifiées.\n");
} catch (Throwable $exception) {
    error_log(sprintf('MVE soft-delete migration failed: %s', $exception->getMessage()));
    fwrite(STDERR, "Migration impossible. Vérifiez le schéma et les permissions SQL.\n");
    exit(1);
}
