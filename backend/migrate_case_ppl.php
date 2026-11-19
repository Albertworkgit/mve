<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

require_once __DIR__ . '/../data_base/Database.php';

try {
    $db = Database::connection();
    $column = $db->query("SHOW COLUMNS FROM cas_mve LIKE 'ppl_infecte'")->fetch();
    if (!$column) {
        $db->exec('ALTER TABLE cas_mve ADD COLUMN ppl_infecte TINYINT(1) NOT NULL DEFAULT 0 AFTER sexe');
    }

    fwrite(STDOUT, "Indicateur PPL des cas vérifié.\n");
} catch (Throwable $exception) {
    error_log(sprintf('MVE case PPL migration failed: %s', $exception->getMessage()));
    fwrite(STDERR, "Migration impossible. Vérifiez la structure de cas_mve et les permissions SQL.\n");
    exit(1);
}
