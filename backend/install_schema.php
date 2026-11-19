<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

if (!in_array('--confirm', $argv, true)) {
    fwrite(STDERR, "Cette opération ajoute les tables MVE à la base configurée. Relancez avec --confirm après avoir vérifié votre sauvegarde.\n");
    exit(2);
}

require_once __DIR__ . '/../data_base/Database.php';

$tables = [
    'zones_sante',
    'aires_sante',
    'etablissements_sante',
    'users',
    'cas_mve',
    'rings',
    'supervisions_pci',
    'deces',
];

try {
    $db = Database::connection();
    $existingTables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $existing = array_values(array_intersect($tables, $existingTables));
    if (count($existing) === count($tables)) {
        fwrite(STDOUT, "Les tables du schéma MVE existent déjà ; aucune modification effectuée.\n");
        exit(0);
    }
    if ($existing !== []) {
        fwrite(STDERR, 'Installation interrompue : schéma MVE partiel détecté (' . implode(', ', $existing) . "). Vérifiez la base avant toute reprise.\n");
        exit(1);
    }

    $schemaPath = __DIR__ . '/../data_base/schema.sql';
    $schema = file_get_contents($schemaPath);
    if ($schema === false) {
        throw new RuntimeException('Impossible de lire data_base/schema.sql.');
    }

    foreach (explode(';', $schema) as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $db->exec($statement);
        }
    }

    fwrite(STDOUT, "Schéma MVE installé. Les éventuelles tables préexistantes, comme zone_s, ont été conservées.\n");
} catch (Throwable $exception) {
    error_log(sprintf('MVE schema installation failed: %s', $exception->getMessage()));
    fwrite(STDERR, "Échec de l’installation. Consultez le journal PHP et vérifiez l’état de la base avant de réessayer.\n");
    exit(1);
}
