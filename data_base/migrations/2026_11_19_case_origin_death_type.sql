ALTER TABLE cas_mve
    ADD COLUMN origine ENUM('ess', 'communaute') NULL AFTER ess_origine_id,
    ADD COLUMN adresse_communaute VARCHAR(255) NULL AFTER origine;

UPDATE cas_mve
SET origine = CASE WHEN ess_origine_id IS NULL THEN 'communaute' ELSE 'ess' END
WHERE origine IS NULL;

ALTER TABLE cas_mve
    MODIFY origine ENUM('ess', 'communaute') NOT NULL DEFAULT 'ess',
    ADD CONSTRAINT chk_cas_origine CHECK (
        (origine = 'ess' AND ess_origine_id IS NOT NULL AND adresse_communaute IS NULL)
        OR (origine = 'communaute' AND ess_origine_id IS NULL AND adresse_communaute IS NOT NULL)
    );

ALTER TABLE deces
    ADD COLUMN type_deces ENUM('cte', 'swab_positif') NOT NULL DEFAULT 'cte' AFTER lieu;
