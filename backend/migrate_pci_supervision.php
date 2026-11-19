<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

require_once __DIR__ . '/../data_base/Database.php';

try {
    $db = Database::connection();
    $columns = $db->query('SHOW COLUMNS FROM supervisions_pci')->fetchAll(PDO::FETCH_COLUMN);
    $changes = [];
    foreach ([
        'planifie_par' => 'BIGINT UNSIGNED NULL',
        'activite_planifiee' => 'VARCHAR(160) NULL',
        'description_activite' => 'TEXT NULL',
        'date_planifiee' => 'DATE NULL',
        'statut_visite' => "ENUM('planifiee', 'realisee') NOT NULL DEFAULT 'realisee'",
        'points_saillants' => 'TEXT NULL',
        'points_a_ameliorer' => 'TEXT NULL',
        'action_correctrice' => 'TEXT NULL',
        'scorecard_pourcentage' => 'DECIMAL(5,2) NULL',
        'nombre_prestataires_briefes' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'liste_presence_photo' => 'VARCHAR(255) NULL',
    ] as $column => $definition) {
        if (!in_array($column, $columns, true)) {
            $changes[] = "ADD COLUMN {$column} {$definition}";
        }
    }
    if ($changes !== []) {
        $db->exec('ALTER TABLE supervisions_pci ' . implode(', ', $changes));
    }

    $constraints = $db->query(
        "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'supervisions_pci'"
    )->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('fk_supervisions_planificateur', $constraints, true)) {
        $db->exec(
            'ALTER TABLE supervisions_pci ADD CONSTRAINT fk_supervisions_planificateur
             FOREIGN KEY (planifie_par) REFERENCES users (id) ON UPDATE CASCADE ON DELETE SET NULL'
        );
    }
    if (!in_array('chk_supervisions_scorecard', $constraints, true)) {
        $db->exec(
            'ALTER TABLE supervisions_pci ADD CONSTRAINT chk_supervisions_scorecard
             CHECK (scorecard_pourcentage IS NULL OR scorecard_pourcentage BETWEEN 0 AND 100)'
        );
    }
    if (!in_array('chk_supervisions_planned', $constraints, true)) {
        $db->exec(
            'ALTER TABLE supervisions_pci ADD CONSTRAINT chk_supervisions_planned
             CHECK (statut_visite = \'realisee\' OR (activite_planifiee IS NOT NULL AND date_planifiee IS NOT NULL))'
        );
    }

    fwrite(STDOUT, "Planification PCI et champs de supervision installés ou déjà à jour.\n");
} catch (Throwable $exception) {
    error_log(sprintf('MVE PCI supervision migration failed: %s', $exception->getMessage()));
    fwrite(STDERR, "Migration impossible. Vérifiez la structure de la base et les permissions SQL.\n");
    exit(1);
}
