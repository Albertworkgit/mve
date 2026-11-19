ALTER TABLE users MODIFY role
    ENUM(
        'super_admin',
        'admin_zone',
        'chef_pilier',
        'superviseur_pci',
        'responsable_eds',
        'surveillant_epidemiologique'
    ) NOT NULL;

ALTER TABLE rings
    ADD COLUMN IF NOT EXISTS menages_decontamines INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS ess_decontamines INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS ouvert_le DATETIME NULL,
    ADD COLUMN IF NOT EXISTS ferme_le DATETIME NULL;

UPDATE rings
SET ouvert_le = CONCAT(date_debut, ' 00:00:00')
WHERE ouvert_le IS NULL;

UPDATE rings
SET ferme_le = CONCAT(COALESCE(date_fin, CURRENT_DATE), ' 00:00:00')
WHERE statut = 'termine' AND ferme_le IS NULL;

ALTER TABLE rings
    MODIFY ouvert_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

CREATE TABLE activites_riposte (
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
) ENGINE=InnoDB;
