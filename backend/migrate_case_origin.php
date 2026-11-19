<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

require_once __DIR__ . '/../data_base/Database.php';

try {
    $db = Database::connection();
    $caseColumns = $db->query("SHOW COLUMNS FROM cas_mve LIKE 'origine'")->fetch();
    if (!$caseColumns) {
        $db->exec("ALTER TABLE cas_mve ADD COLUMN origine ENUM('ess', 'communaute') NULL AFTER ess_origine_id");
        $db->exec('ALTER TABLE cas_mve ADD COLUMN adresse_communaute VARCHAR(255) NULL AFTER origine');
    }

    $caseColumns = $db->query("SHOW COLUMNS FROM cas_mve LIKE 'origine'")->fetch();
    if ($caseColumns && $caseColumns['Null'] === 'YES') {
        $db->exec(
            "UPDATE cas_mve SET origine = CASE WHEN ess_origine_id IS NULL THEN 'communaute' ELSE 'ess' END
             WHERE origine IS NULL"
        );
        $db->exec("ALTER TABLE cas_mve MODIFY origine ENUM('ess', 'communaute') NOT NULL DEFAULT 'ess'");
    }

    $constraint = $db->query(
        "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'cas_mve' AND CONSTRAINT_NAME = 'chk_cas_origine'"
    )->fetchColumn();
    if ((int) $constraint === 0) {
        $invalidRows = (int) $db->query(
            "SELECT COUNT(*) FROM cas_mve
             WHERE NOT (
                (origine = 'ess' AND ess_origine_id IS NOT NULL AND adresse_communaute IS NULL)
                OR (origine = 'communaute' AND ess_origine_id IS NULL AND adresse_communaute IS NOT NULL)
             )"
        )->fetchColumn();
        if ($invalidRows === 0) {
        $db->exec(
            "ALTER TABLE cas_mve ADD CONSTRAINT chk_cas_origine CHECK (
                (origine = 'ess' AND ess_origine_id IS NOT NULL AND adresse_communaute IS NULL)
                OR (origine = 'communaute' AND ess_origine_id IS NULL AND adresse_communaute IS NOT NULL)
            )"
        );
        } else {
            fwrite(STDOUT, "Avertissement : des cas historiques doivent être complétés avant d’ajouter la contrainte d’origine; l’API valide les nouvelles saisies.\n");
        }
    }

    $deathColumn = $db->query("SHOW COLUMNS FROM deces LIKE 'type_deces'")->fetch();
    if (!$deathColumn) {
        $db->exec("ALTER TABLE deces ADD COLUMN type_deces ENUM('cte', 'swab_positif') NOT NULL DEFAULT 'cte' AFTER lieu");
    }

    fwrite(STDOUT, "Origine des cas et type des décès vérifiés.\n");
} catch (Throwable $exception) {
    error_log(sprintf('MVE case-origin migration failed: %s', $exception->getMessage()));
    fwrite(STDERR, "Migration impossible. Vérifiez le schéma et l’état des anciennes données.\n");
    exit(1);
}
