ALTER TABLE rings
    ADD COLUMN nombre_ess INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN eglises_decontaminees INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN ecoles_decontaminees INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN marches_decontamines INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN parking_decontamines INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN autres_lieux_decontamines INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN nombre_parking INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN noms_eglises TEXT NULL,
    ADD COLUMN noms_ecoles TEXT NULL,
    ADD COLUMN noms_marches TEXT NULL,
    ADD COLUMN noms_parking TEXT NULL,
    ADD COLUMN noms_autres_lieux TEXT NULL;
