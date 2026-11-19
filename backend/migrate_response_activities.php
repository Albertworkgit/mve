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
    if (!$column) {
        throw new RuntimeException('La colonne users.role est introuvable.');
    }

    $roleType = (string) $column['Type'];
    if (!str_contains($roleType, "'responsable_eds'")
        || !str_contains($roleType, "'surveillant_epidemiologique'")) {
        $db->exec(
            "ALTER TABLE users MODIFY role
             ENUM('super_admin', 'admin_zone', 'chef_pilier', 'superviseur_pci', 'responsable_eds', 'surveillant_epidemiologique') NOT NULL"
        );
        fwrite(STDOUT, "Rôles EDS et Surveillance ajoutés.\n");
    }

    $db->exec(
        "CREATE TABLE IF NOT EXISTS activites_riposte (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            zone_id BIGINT UNSIGNED NOT NULL,
            aire_id BIGINT UNSIGNED NULL,
            ess_id BIGINT UNSIGNED NULL,
            cas_mve_id BIGINT UNSIGNED NULL,
            ring_id BIGINT UNSIGNED NULL,
            pilier ENUM('eds', 'pci', 'surveillance', 'vaccination', 'sensibilisation', 'logistique', 'coordination', 'autre') NOT NULL,
            titre VARCHAR(160) NOT NULL,
            description TEXT NULL,
            date_activite DATE NOT NULL,
            indicateur VARCHAR(160) NULL,
            cible DECIMAL(12,2) NULL,
            realise DECIMAL(12,2) NULL,
            menages_decontamines INT UNSIGNED NULL,
            ess_decontamines INT UNSIGNED NULL,
            actions_sur_ring TEXT NULL,
            statut ENUM('planifiee', 'en_cours', 'terminee', 'bloquee') NOT NULL DEFAULT 'planifiee',
            difficultes TEXT NULL,
            actions_correctives TEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            deleted_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_activites_zone_date (zone_id, date_activite),
            KEY idx_activites_area_pillar (aire_id, pilier),
            CONSTRAINT fk_activites_zone FOREIGN KEY (zone_id) REFERENCES zones_sante (id)
                ON UPDATE CASCADE ON DELETE RESTRICT,
            CONSTRAINT fk_activites_area_zone FOREIGN KEY (zone_id, aire_id) REFERENCES aires_sante (zone_id, id)
                ON UPDATE CASCADE ON DELETE RESTRICT,
            CONSTRAINT fk_activites_ess_area FOREIGN KEY (aire_id, ess_id) REFERENCES etablissements_sante (aire_id, id)
                ON UPDATE CASCADE ON DELETE RESTRICT,
            CONSTRAINT fk_activites_case FOREIGN KEY (cas_mve_id) REFERENCES cas_mve (id)
                ON UPDATE CASCADE ON DELETE RESTRICT,
            CONSTRAINT fk_activites_ring FOREIGN KEY (ring_id) REFERENCES rings (id)
                ON UPDATE CASCADE ON DELETE RESTRICT,
            CONSTRAINT fk_activites_created_by FOREIGN KEY (created_by) REFERENCES users (id)
                ON UPDATE CASCADE ON DELETE SET NULL,
            CONSTRAINT chk_activites_values CHECK (
                (cible IS NULL OR cible >= 0) AND (realise IS NULL OR realise >= 0)
            ),
            CONSTRAINT chk_activites_ess_requires_area CHECK (ess_id IS NULL OR aire_id IS NOT NULL)
        ) ENGINE=InnoDB"
    );
    $ringColumns = $db->query('SHOW COLUMNS FROM rings')->fetchAll(PDO::FETCH_COLUMN);
    $ringChanges = [];
    if (!in_array('menages_decontamines', $ringColumns, true)) {
        $ringChanges[] = 'ADD COLUMN menages_decontamines INT UNSIGNED NOT NULL DEFAULT 0';
    }
    if (!in_array('ess_decontamines', $ringColumns, true)) {
        $ringChanges[] = 'ADD COLUMN ess_decontamines INT UNSIGNED NOT NULL DEFAULT 0';
    }
    if (!in_array('ouvert_le', $ringColumns, true)) {
        $ringChanges[] = 'ADD COLUMN ouvert_le DATETIME NULL';
    }
    if (!in_array('ferme_le', $ringColumns, true)) {
        $ringChanges[] = 'ADD COLUMN ferme_le DATETIME NULL';
    }
    if ($ringChanges !== []) {
        $db->exec('ALTER TABLE rings ' . implode(', ', $ringChanges));
    }
    $db->exec("UPDATE rings SET ouvert_le = CONCAT(date_debut, ' 00:00:00') WHERE ouvert_le IS NULL");
    $db->exec("UPDATE rings SET ferme_le = CONCAT(COALESCE(date_fin, CURRENT_DATE), ' 00:00:00') WHERE statut = 'termine' AND ferme_le IS NULL");
    $db->exec('ALTER TABLE rings MODIFY ouvert_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');

    $activityColumns = $db->query('SHOW COLUMNS FROM activites_riposte')->fetchAll(PDO::FETCH_COLUMN);
    $activityChanges = [];
    foreach ([
        'cas_mve_id' => 'BIGINT UNSIGNED NULL',
        'ring_id' => 'BIGINT UNSIGNED NULL',
        'menages_decontamines' => 'INT UNSIGNED NULL',
        'ess_decontamines' => 'INT UNSIGNED NULL',
        'actions_sur_ring' => 'TEXT NULL',
    ] as $column => $definition) {
        if (!in_array($column, $activityColumns, true)) {
            $activityChanges[] = "ADD COLUMN {$column} {$definition}";
        }
    }
    if ($activityChanges !== []) {
        $db->exec('ALTER TABLE activites_riposte ' . implode(', ', $activityChanges));
    }
    $activityConstraints = $db->query(
        "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activites_riposte'"
    )->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('fk_activites_case', $activityConstraints, true)) {
        $db->exec('ALTER TABLE activites_riposte ADD CONSTRAINT fk_activites_case FOREIGN KEY (cas_mve_id) REFERENCES cas_mve (id) ON UPDATE CASCADE ON DELETE RESTRICT');
    }
    if (!in_array('fk_activites_ring', $activityConstraints, true)) {
        $db->exec('ALTER TABLE activites_riposte ADD CONSTRAINT fk_activites_ring FOREIGN KEY (ring_id) REFERENCES rings (id) ON UPDATE CASCADE ON DELETE RESTRICT');
    }
    fwrite(STDOUT, "Module des activités de riposte installé ou déjà à jour.\n");
} catch (Throwable $exception) {
    error_log(sprintf('MVE response activities migration failed: %s', $exception->getMessage()));
    fwrite(STDERR, "Migration impossible. Vérifiez la structure de la base et les permissions SQL.\n");
    exit(1);
}
