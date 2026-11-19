CREATE DATABASE IF NOT EXISTS data_mve
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE data_mve;

CREATE TABLE zones_sante (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom VARCHAR(120) NOT NULL,
    code VARCHAR(30) NOT NULL,
    province VARCHAR(120) NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_zones_sante_code (code),
    UNIQUE KEY uq_zones_sante_nom (nom)
) ENGINE=InnoDB;

CREATE TABLE aires_sante (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    zone_id BIGINT UNSIGNED NOT NULL,
    nom VARCHAR(120) NOT NULL,
    code VARCHAR(30) NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_aires_zone_code (zone_id, code),
    UNIQUE KEY uq_aires_zone_nom (zone_id, nom),
    UNIQUE KEY uq_aires_zone_id (zone_id, id),
    CONSTRAINT fk_aires_zone FOREIGN KEY (zone_id) REFERENCES zones_sante (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE etablissements_sante (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    aire_id BIGINT UNSIGNED NOT NULL,
    nom VARCHAR(160) NOT NULL,
    code VARCHAR(40) NULL,
    type VARCHAR(80) NULL,
    adresse VARCHAR(255) NULL,
    telephone VARCHAR(40) NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ess_aire_nom (aire_id, nom),
    UNIQUE KEY uq_ess_code (code),
    UNIQUE KEY uq_ess_aire_id (aire_id, id),
    CONSTRAINT fk_ess_aire FOREIGN KEY (aire_id) REFERENCES aires_sante (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    zone_id BIGINT UNSIGNED NULL,
    nom VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'chef_pilier', 'superviseur_pci') NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_zone_role (zone_id, role),
    CONSTRAINT fk_users_zone FOREIGN KEY (zone_id) REFERENCES zones_sante (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_users_role_zone CHECK (
        (role = 'super_admin' AND zone_id IS NULL)
        OR (role <> 'super_admin' AND zone_id IS NOT NULL)
    )
) ENGINE=InnoDB;

CREATE TABLE cas_mve (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    zone_id BIGINT UNSIGNED NOT NULL,
    aire_id BIGINT UNSIGNED NOT NULL,
    ess_origine_id BIGINT UNSIGNED NULL,
    code_epidemiologique VARCHAR(60) NOT NULL,
    nom_initiales VARCHAR(160) NULL,
    age SMALLINT UNSIGNED NULL,
    sexe ENUM('F', 'M', 'inconnu') NOT NULL DEFAULT 'inconnu',
    date_confirmation DATE NOT NULL,
    statut ENUM('en_traitement_cte', 'gueri', 'decede') NOT NULL DEFAULT 'en_traitement_cte',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cas_code_epidemiologique (code_epidemiologique),
    UNIQUE KEY uq_cas_aire_id (aire_id, id),
    KEY idx_cas_zone_date (zone_id, date_confirmation),
    KEY idx_cas_aire_statut (aire_id, statut),
    CONSTRAINT fk_cas_zone FOREIGN KEY (zone_id) REFERENCES zones_sante (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_cas_aire_zone FOREIGN KEY (zone_id, aire_id) REFERENCES aires_sante (zone_id, id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_cas_ess_aire FOREIGN KEY (aire_id, ess_origine_id) REFERENCES etablissements_sante (aire_id, id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_cas_created_by FOREIGN KEY (created_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT chk_cas_age CHECK (age IS NULL OR age <= 120)
) ENGINE=InnoDB;

CREATE TABLE rings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cas_mve_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(60) NOT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE NULL,
    nombre_menages INT UNSIGNED NOT NULL DEFAULT 0,
    nombre_eglises INT UNSIGNED NOT NULL DEFAULT 0,
    nombre_ecoles INT UNSIGNED NOT NULL DEFAULT 0,
    nombre_marches INT UNSIGNED NOT NULL DEFAULT 0,
    nombre_autres_lieux INT UNSIGNED NOT NULL DEFAULT 0,
    statut_decontamination ENUM('non_demarre', 'en_cours', 'termine') NOT NULL DEFAULT 'non_demarre',
    statut_vaccination ENUM('non_demarre', 'en_cours', 'termine') NOT NULL DEFAULT 'non_demarre',
    statut_sensibilisation ENUM('non_demarre', 'en_cours', 'termine') NOT NULL DEFAULT 'non_demarre',
    statut ENUM('actif', 'termine') NOT NULL DEFAULT 'actif',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rings_code (code),
    KEY idx_rings_cas_statut (cas_mve_id, statut),
    CONSTRAINT fk_rings_cas FOREIGN KEY (cas_mve_id) REFERENCES cas_mve (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_rings_created_by FOREIGN KEY (created_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT chk_rings_dates CHECK (date_fin IS NULL OR date_fin >= date_debut)
) ENGINE=InnoDB;

CREATE TABLE supervisions_pci (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ess_id BIGINT UNSIGNED NOT NULL,
    superviseur_id BIGINT UNSIGNED NOT NULL,
    date_visite DATE NOT NULL,
    points_forts TEXT NULL,
    points_faibles TEXT NULL,
    recommandations TEXT NULL,
    statut_suivi ENUM('en_cours', 'resolu') NOT NULL DEFAULT 'en_cours',
    date_resolution DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_supervisions_ess_date (ess_id, date_visite),
    KEY idx_supervisions_user_date (superviseur_id, date_visite),
    CONSTRAINT fk_supervisions_ess FOREIGN KEY (ess_id) REFERENCES etablissements_sante (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_supervisions_superviseur FOREIGN KEY (superviseur_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_supervisions_resolution CHECK (date_resolution IS NULL OR date_resolution >= date_visite)
) ENGINE=InnoDB;

CREATE TABLE deces (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cas_mve_id BIGINT UNSIGNED NULL,
    aire_id BIGINT UNSIGNED NOT NULL,
    date_deces DATE NOT NULL,
    lieu ENUM('communaute', 'cte_ess') NOT NULL,
    ess_id BIGINT UNSIGNED NULL,
    statut_eds ENUM('a_realiser', 'realise') NOT NULL DEFAULT 'a_realiser',
    date_eds DATE NULL,
    commentaires TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_deces_aire_date (aire_id, date_deces),
    KEY idx_deces_cas (cas_mve_id),
    CONSTRAINT fk_deces_cas_aire FOREIGN KEY (aire_id, cas_mve_id) REFERENCES cas_mve (aire_id, id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_deces_aire FOREIGN KEY (aire_id) REFERENCES aires_sante (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_deces_ess_aire FOREIGN KEY (aire_id, ess_id) REFERENCES etablissements_sante (aire_id, id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_deces_created_by FOREIGN KEY (created_by) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT chk_deces_ess_lieu CHECK (
        (lieu = 'communaute' AND ess_id IS NULL)
        OR (lieu = 'cte_ess' AND ess_id IS NOT NULL)
    ),
    CONSTRAINT chk_deces_eds_date CHECK (
        (statut_eds = 'a_realiser' AND date_eds IS NULL)
        OR (statut_eds = 'realise' AND date_eds IS NOT NULL AND date_eds >= date_deces)
    )
) ENGINE=InnoDB;
