<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

require_once __DIR__ . '/../data_base/Database.php';

try {
    $db = Database::connection();
    $column = $db->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
    if (!$column || !str_contains((string) $column['Type'], "'admin_zone'")) {
        $db->exec(
            "ALTER TABLE users MODIFY role
             ENUM('super_admin', 'admin_zone', 'chef_pilier', 'superviseur_pci') NOT NULL"
        );
        fwrite(STDOUT, "Rôle admin_zone ajouté à la table users.\n");
    } else {
        fwrite(STDOUT, "Le rôle admin_zone est déjà disponible.\n");
    }
} catch (Throwable $exception) {
    error_log(sprintf('MVE admin_zone migration failed: %s', $exception->getMessage()));
    fwrite(STDERR, "Migration impossible. Vérifiez la structure de users et les permissions SQL.\n");
    exit(1);
}
