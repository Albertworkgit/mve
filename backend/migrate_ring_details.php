<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

require_once __DIR__ . '/../data_base/Database.php';

try {
    $db = Database::connection();
    $columns = $db->query('SHOW COLUMNS FROM rings')->fetchAll(PDO::FETCH_COLUMN);
    $changes = [];
    foreach ([
        'nombre_ess' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'eglises_decontaminees' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'ecoles_decontaminees' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'marches_decontamines' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'parking_decontamines' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'autres_lieux_decontamines' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'nombre_parking' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'noms_eglises' => 'TEXT NULL',
        'noms_ecoles' => 'TEXT NULL',
        'noms_marches' => 'TEXT NULL',
        'noms_parking' => 'TEXT NULL',
        'noms_autres_lieux' => 'TEXT NULL',
    ] as $column => $definition) {
        if (!in_array($column, $columns, true)) {
            $changes[] = "ADD COLUMN {$column} {$definition}";
        }
    }
    if ($changes !== []) {
        $db->exec('ALTER TABLE rings ' . implode(', ', $changes));
    }

    fwrite(STDOUT, "Champs détaillés de décontamination des Rings vérifiés.\n");
} catch (Throwable $exception) {
    error_log(sprintf('MVE ring-details migration failed: %s', $exception->getMessage()));
    fwrite(STDERR, "Migration impossible. Vérifiez la structure de rings et les permissions SQL.\n");
    exit(1);
}
