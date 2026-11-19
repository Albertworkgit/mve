<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function routeSegments(): array
{
    $path = $_SERVER['PATH_INFO'] ?? ($_GET['path'] ?? '');
    if (!is_string($path)) {
        apiError(404, 'Route introuvable.');
    }

    return array_values(array_filter(explode('/', trim($path, '/')), static fn (string $part): bool => $part !== ''));
}

function applyZoneFilter(array $user, ?string $requestedZone): ?int
{
    if ($user['role'] !== 'super_admin') {
        return $user['zone_id'];
    }
    if ($requestedZone === null || $requestedZone === '') {
        return null;
    }
    $zoneId = filter_var($requestedZone, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($zoneId === false) {
        apiError(422, 'Filtre Zone de Santé invalide.');
    }

    return (int) $zoneId;
}

function records(PDO $db, array $user, string $resource, ?int $id, array $query): array
{
    $requestedZone = isset($query['zone_id']) ? (string) $query['zone_id'] : null;
    $zoneId = applyZoneFilter($user, $requestedZone);
    $limit = isset($query['limit']) ? filter_var($query['limit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5000]]) : 100;
    if ($limit === false) {
        apiError(422, 'Le paramètre limit doit être compris entre 1 et 5000.');
    }

    $queries = [
        'zones' => [
            'sql' => 'SELECT z.id, z.nom, z.code, z.province, z.active, z.created_at, z.updated_at
                      FROM zones_sante z WHERE 1 = 1',
            'zone' => 'z',
            'record' => 'z',
            'order' => 'z.nom',
        ],
        'aires' => [
            'sql' => 'SELECT a.id, a.zone_id, z.nom AS zone_nom, a.nom, a.code, a.active, a.created_at, a.updated_at
                      FROM aires_sante a JOIN zones_sante z ON z.id = a.zone_id
                      WHERE 1 = 1',
            'zone' => 'a',
            'record' => 'a',
            'order' => 'z.nom, a.nom',
        ],
        'ess' => [
            'sql' => 'SELECT e.id, e.aire_id, a.zone_id, z.nom AS zone_nom, a.nom AS aire_nom,
                             e.nom, e.code, e.type, e.adresse, e.telephone, e.active, e.created_at, e.updated_at
                      FROM etablissements_sante e
                      JOIN aires_sante a ON a.id = e.aire_id
                      JOIN zones_sante z ON z.id = a.zone_id
                      WHERE 1 = 1',
            'zone' => 'a',
            'record' => 'e',
            'order' => 'z.nom, a.nom, e.nom',
        ],
        'cases' => [
            'sql' => 'SELECT c.id, c.zone_id, z.nom AS zone_nom, c.aire_id, a.nom AS aire_nom,
                             c.ess_origine_id, e.nom AS ess_origine_nom, c.origine, c.adresse_communaute, c.code_epidemiologique,
                             c.nom_initiales, c.age, c.sexe, c.ppl_infecte, c.date_confirmation, c.statut,
                             c.created_by, c.created_at, c.updated_at
                      FROM cas_mve c
                      JOIN zones_sante z ON z.id = c.zone_id
                      JOIN aires_sante a ON a.id = c.aire_id
                      LEFT JOIN etablissements_sante e ON e.id = c.ess_origine_id
                      WHERE c.deleted_at IS NULL',
            'zone' => 'c',
            'record' => 'c',
            'order' => 'c.date_confirmation DESC, c.id DESC',
        ],
        'rings' => [
            'sql' => 'SELECT r.*, c.code_epidemiologique, c.zone_id, z.nom AS zone_nom,
                             c.aire_id, a.nom AS aire_nom
                      FROM rings r JOIN cas_mve c ON c.id = r.cas_mve_id
                      JOIN zones_sante z ON z.id = c.zone_id
                      JOIN aires_sante a ON a.id = c.aire_id
                      WHERE r.deleted_at IS NULL AND c.deleted_at IS NULL',
            'zone' => 'c',
            'record' => 'r',
            'order' => 'r.date_debut DESC, r.id DESC',
        ],
        'supervisions' => [
            'sql' => 'SELECT s.*, e.nom AS ess_nom, e.aire_id, a.nom AS aire_nom, a.zone_id,
                             z.nom AS zone_nom, u.nom AS superviseur_nom
                      FROM supervisions_pci s
                      JOIN etablissements_sante e ON e.id = s.ess_id
                      JOIN aires_sante a ON a.id = e.aire_id
                      JOIN zones_sante z ON z.id = a.zone_id
                      JOIN users u ON u.id = s.superviseur_id
                      WHERE s.deleted_at IS NULL',
            'zone' => 'a',
            'record' => 's',
            'order' => 's.date_visite DESC, s.id DESC',
        ],
        'deaths' => [
            'sql' => 'SELECT d.*, a.zone_id, z.nom AS zone_nom, a.nom AS aire_nom,
                             e.nom AS ess_nom, c.code_epidemiologique
                      FROM deces d
                      JOIN aires_sante a ON a.id = d.aire_id
                      JOIN zones_sante z ON z.id = a.zone_id
                      LEFT JOIN etablissements_sante e ON e.id = d.ess_id
                      LEFT JOIN cas_mve c ON c.id = d.cas_mve_id
                      WHERE d.deleted_at IS NULL',
            'zone' => 'a',
            'record' => 'd',
            'order' => 'd.date_deces DESC, d.id DESC',
        ],
        'users' => [
            'sql' => 'SELECT u.id, u.zone_id, z.nom AS zone_nom, u.nom, u.email, u.role,
                             u.active, u.created_at, u.updated_at
                      FROM users u LEFT JOIN zones_sante z ON z.id = u.zone_id
                      WHERE 1 = 1',
            'zone' => 'u',
            'record' => 'u',
            'order' => 'u.nom',
        ],
        'pci-supervisors' => [
            'sql' => "SELECT u.id, u.zone_id, u.nom, u.role
                      FROM users u WHERE u.active = 1 AND u.role = 'superviseur_pci'",
            'zone' => 'u',
            'record' => 'u',
            'order' => 'u.nom',
        ],
        'activities' => [
            'sql' => 'SELECT ar.*, z.nom AS zone_nom, a.nom AS aire_nom, e.nom AS ess_nom,
                             c.code_epidemiologique, r.code AS ring_code, u.nom AS auteur_nom
                      FROM activites_riposte ar
                      JOIN zones_sante z ON z.id = ar.zone_id
                      LEFT JOIN aires_sante a ON a.id = ar.aire_id
                      LEFT JOIN etablissements_sante e ON e.id = ar.ess_id
                      LEFT JOIN cas_mve c ON c.id = ar.cas_mve_id
                      LEFT JOIN rings r ON r.id = ar.ring_id
                      LEFT JOIN users u ON u.id = ar.created_by
                      WHERE ar.deleted_at IS NULL',
            'zone' => 'ar',
            'record' => 'ar',
            'order' => 'ar.date_activite DESC, ar.id DESC',
        ],
    ];

    if (!isset($queries[$resource])) {
        apiError(404, 'Ressource introuvable.');
    }
    if ($resource === 'users') {
        requireRoles($user, ['super_admin', 'admin_zone', 'chef_pilier']);
        if ($user['role'] === 'chef_pilier') {
            $sql .= " AND u.role IN ('superviseur_pci', 'responsable_eds')";
        }
    }
    if ($resource === 'pci-supervisors') {
        requireRoles($user, ['super_admin', 'admin_zone', 'chef_pilier']);
    }
    if ($resource === 'deaths') {
        requireRoles($user, ['super_admin', 'admin_zone', 'chef_pilier', 'responsable_eds']);
    }
    if ($resource === 'supervisions') {
        requireRoles($user, ['super_admin', 'admin_zone', 'chef_pilier', 'superviseur_pci']);
    }
    if ($resource === 'activities') {
        requireRoles($user, ['super_admin', 'admin_zone', 'chef_pilier', 'superviseur_pci', 'responsable_eds', 'surveillant_epidemiologique']);
    }
    if ($resource === 'cases' || $resource === 'rings') {
        requireRoles($user, ['super_admin', 'admin_zone', 'chef_pilier', 'superviseur_pci', 'responsable_eds', 'surveillant_epidemiologique']);
    }

    $definition = $queries[$resource];
    $sql = $definition['sql'];
    $bindings = [];
    if ($zoneId !== null) {
        if ($resource === 'zones') {
            $sql .= ' AND z.id = :zone_id';
        } else {
            $sql .= ' AND ' . $definition['zone'] . '.zone_id = :zone_id';
        }
        $bindings['zone_id'] = $zoneId;
    }
    if ($id !== null) {
        $sql .= ' AND ' . $definition['record'] . '.id = :record_id';
        $bindings['record_id'] = $id;
    }
    if (isset($query['aire_id']) && in_array($resource, ['ess', 'cases', 'deaths', 'activities', 'rings', 'supervisions'], true)) {
        $areaId = filter_var($query['aire_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($areaId === false) {
            apiError(422, 'Filtre Aire de Santé invalide.');
        }
        assertAreaAccess($db, $user, (int) $areaId);
        $areaColumn = [
            'ess' => 'e.aire_id', 'deaths' => 'd.aire_id', 'activities' => 'ar.aire_id',
            'cases' => 'c.aire_id', 'rings' => 'c.aire_id', 'supervisions' => 'a.id',
        ][$resource];
        $sql .= ' AND ' . $areaColumn . ' = :aire_id';
        $bindings['aire_id'] = (int) $areaId;
    }
    if ($resource === 'activities') {
        $rolePillars = allowedActivityPillars($user);
        if ($rolePillars !== null) {
            $placeholders = [];
            foreach ($rolePillars as $index => $pillar) {
                $key = 'role_pillar_' . $index;
                $placeholders[] = ':' . $key;
                $bindings[$key] = $pillar;
            }
            $sql .= ' AND ar.pilier IN (' . implode(', ', $placeholders) . ')';
        }
        if (isset($query['pilier'])) {
            $allowedPillars = ['eds', 'pci', 'surveillance', 'vaccination', 'sensibilisation', 'logistique', 'coordination', 'autre'];
            if (!in_array($query['pilier'], $allowedPillars, true)) {
                apiError(422, 'Filtre pilier invalide.');
            }
            $sql .= ' AND ar.pilier = :pilier';
            $bindings['pilier'] = $query['pilier'];
        }
    }
    if ($resource === 'supervisions' && $user['role'] === 'superviseur_pci') {
        $sql .= ' AND s.superviseur_id = :superviseur_id';
        $bindings['superviseur_id'] = $user['id'];
    }
    $dateColumns = [
        'cases' => 'c.date_confirmation', 'deaths' => 'd.date_deces',
        'rings' => 'r.date_debut', 'supervisions' => 's.date_visite',
        'activities' => 'ar.date_activite',
    ];
    if (isset($dateColumns[$resource])) {
        foreach (['date_from' => '>=', 'date_to' => '<='] as $dateKey => $operator) {
            if (!isset($query[$dateKey]) || $query[$dateKey] === '') {
                continue;
            }
            $date = dateValue($query, $dateKey);
            $sql .= ' AND ' . $dateColumns[$resource] . ' ' . $operator . ' :' . $dateKey;
            $bindings[$dateKey] = $date;
        }
    }
    if ($resource === 'cases' && isset($query['statut'])) {
        $allowedStatuses = ['en_traitement_cte', 'gueri', 'decede'];
        if (!in_array($query['statut'], $allowedStatuses, true)) {
            apiError(422, 'Filtre statut invalide.');
        }
        $sql .= ' AND c.statut = :statut';
        $bindings['statut'] = $query['statut'];
    }

    $sql .= ' ORDER BY ' . $definition['order'] . ' LIMIT ' . (int) $limit;
    $statement = $db->prepare($sql);
    foreach ($bindings as $key => $value) {
        $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $statement->bindValue(':' . $key, $value, $type);
    }
    $statement->execute();
    $result = $statement->fetchAll();

    if ($resource === 'zones' && $user['role'] !== 'super_admin') {
        $result = array_values(array_filter($result, static fn (array $row): bool => (int) $row['id'] === $user['zone_id']));
    }
    if ($id !== null) {
        if (!$result) {
            apiError(404, 'Ressource introuvable.');
        }
        return $result[0];
    }

    return $result;
}

function dashboard(PDO $db, array $user, array $query): array
{
    $zoneId = applyZoneFilter($user, isset($query['zone_id']) ? (string) $query['zone_id'] : null);
    $areaId = null;
    if (isset($query['aire_id']) && $query['aire_id'] !== 'all') {
        $validatedAreaId = filter_var($query['aire_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($validatedAreaId === false) {
            apiError(422, 'Filtre Aire de Santé invalide.');
        }
        $areaId = (int) $validatedAreaId;
        $areaZoneId = assertAreaAccess($db, $user, $areaId);
        if ($zoneId !== null && $areaZoneId !== $zoneId) {
            apiError(422, 'L’Aire de Santé sélectionnée ne dépend pas de cette Zone de Santé.');
        }
    }

    $buildFilter = static function (?int $filterZoneId, ?int $filterAreaId, string $zoneColumn, string $areaColumn, string $prefix): array {
        $conditions = [];
        $bindings = [];
        if ($filterZoneId !== null) {
            $conditions[] = "{$zoneColumn} = :{$prefix}_zone";
            $bindings[$prefix . '_zone'] = $filterZoneId;
        }
        if ($filterAreaId !== null) {
            $conditions[] = "{$areaColumn} = :{$prefix}_area";
            $bindings[$prefix . '_area'] = $filterAreaId;
        }

        return ['sql' => $conditions === [] ? '1 = 1' : implode(' AND ', $conditions), 'bindings' => $bindings];
    };

    $caseFilter = $buildFilter($zoneId, $areaId, 'c.zone_id', 'c.aire_id', 'cases');
    $pplCaseFilter = $buildFilter($zoneId, $areaId, 'c.zone_id', 'c.aire_id', 'ppl_cases');
    $deathFilter = $buildFilter($zoneId, $areaId, 'a.zone_id', 'a.id', 'deaths');
    $ringFilter = $buildFilter($zoneId, $areaId, 'c.zone_id', 'c.aire_id', 'rings');
    $supervisionFilter = $buildFilter($zoneId, $areaId, 'a.zone_id', 'a.id', 'supervisions');
    $facilityFilter = $buildFilter($zoneId, $areaId, 'a.zone_id', 'a.id', 'facilities');
    $areaFilter = $buildFilter($zoneId, $areaId, 'a.zone_id', 'a.id', 'areas');

    $statement = $db->prepare(
        "SELECT
            (SELECT COUNT(*) FROM cas_mve c WHERE c.deleted_at IS NULL AND {$caseFilter['sql']}) AS total_cas,
            (SELECT COUNT(*) FROM cas_mve c WHERE c.deleted_at IS NULL AND c.ppl_infecte = 1 AND {$pplCaseFilter['sql']}) AS total_ppl_infectes,
            (SELECT COUNT(*) FROM deces d JOIN aires_sante a ON a.id = d.aire_id WHERE d.deleted_at IS NULL AND {$deathFilter['sql']}) AS total_deces,
            (SELECT COUNT(*) FROM rings r JOIN cas_mve c ON c.id = r.cas_mve_id WHERE r.deleted_at IS NULL AND c.deleted_at IS NULL AND r.statut = 'actif' AND {$ringFilter['sql']}) AS rings_actifs,
            (SELECT COUNT(DISTINCT s.ess_id)
                FROM supervisions_pci s
                JOIN etablissements_sante e ON e.id = s.ess_id
                JOIN aires_sante a ON a.id = e.aire_id
                WHERE s.deleted_at IS NULL AND s.statut_visite = 'realisee' AND {$supervisionFilter['sql']}) AS ess_supervises,
            (SELECT COUNT(*) FROM etablissements_sante e JOIN aires_sante a ON a.id = e.aire_id WHERE {$facilityFilter['sql']} AND e.active = 1) AS ess_total"
    );
    $dashboardBindings = array_merge(
        $caseFilter['bindings'],
        $pplCaseFilter['bindings'],
        $deathFilter['bindings'],
        $ringFilter['bindings'],
        $supervisionFilter['bindings'],
        $facilityFilter['bindings']
    );
    $statement->execute($dashboardBindings);
    $kpis = $statement->fetch();
    foreach ($kpis as $key => $value) {
        $kpis[$key] = (int) $value;
    }

    $statement = $db->prepare(
        'SELECT a.id AS aire_id, a.nom AS aire_nom, COUNT(c.id) AS total
         FROM aires_sante a
         LEFT JOIN cas_mve c ON c.aire_id = a.id AND c.deleted_at IS NULL
         WHERE ' . $areaFilter['sql'] . '
         GROUP BY a.id, a.nom ORDER BY a.nom'
    );
    $statement->execute($areaFilter['bindings']);
    $casesByArea = $statement->fetchAll();
    foreach ($casesByArea as &$area) {
        $area['aire_id'] = (int) $area['aire_id'];
        $area['total'] = (int) $area['total'];
    }
    unset($area);

    $statement = $db->prepare(
        'SELECT COALESCE(SUM(r.nombre_menages), 0) AS menages,
                COALESCE(SUM(r.nombre_eglises), 0) AS eglises,
                COALESCE(SUM(r.nombre_ecoles), 0) AS ecoles,
                COALESCE(SUM(r.nombre_marches), 0) AS marches,
                COALESCE(SUM(r.nombre_parking), 0) AS parkings,
                COALESCE(SUM(r.nombre_autres_lieux), 0) AS autres_lieux
         FROM rings r JOIN cas_mve c ON c.id = r.cas_mve_id
         WHERE r.deleted_at IS NULL AND c.deleted_at IS NULL AND ' . $ringFilter['sql']
    );
    $statement->execute($ringFilter['bindings']);
    $ringStructures = $statement->fetch();
    foreach ($ringStructures as $key => $value) {
        $ringStructures[$key] = (int) $value;
    }

    return [
        'filtres' => ['zone_id' => $zoneId, 'aire_id' => $areaId],
        'kpis' => $kpis,
        'cas_par_aire' => $casesByArea,
        'structures_rings' => $ringStructures,
    ];
}

function notifications(PDO $db, array $user): array
{
    requireRoles($user, ['super_admin', 'admin_zone', 'chef_pilier', 'superviseur_pci']);
    if ($user['role'] !== 'superviseur_pci') {
        return [];
    }

    $statement = $db->prepare(
        "SELECT c.id AS cas_mve_id, c.code_epidemiologique, c.date_confirmation,
                c.aire_id, a.nom AS aire_nom, r.id AS ring_id, r.code AS ring_code, r.ouvert_le
         FROM cas_mve c
         JOIN users reporter ON reporter.id = c.created_by
         JOIN aires_sante a ON a.id = c.aire_id
         LEFT JOIN rings r ON r.cas_mve_id = c.id AND r.deleted_at IS NULL AND r.statut = 'actif'
         WHERE c.zone_id = :zone_id AND c.deleted_at IS NULL
           AND reporter.role = 'surveillant_epidemiologique'
           AND (r.id IS NULL OR NOT EXISTS (
               SELECT 1 FROM activites_riposte ar
               WHERE ar.ring_id = r.id AND ar.pilier = 'pci' AND ar.deleted_at IS NULL
           ))
         ORDER BY c.created_at DESC, c.id DESC
         LIMIT 100"
    );
    $statement->execute(['zone_id' => $user['zone_id']]);
    return $statement->fetchAll();
}

function syncRingDecontamination(PDO $db, int $ringId): void
{
    $latest = $db->prepare(
        "SELECT menages_decontamines, ess_decontamines
         FROM activites_riposte
         WHERE ring_id = ? AND pilier = 'pci' AND deleted_at IS NULL
         ORDER BY date_activite DESC, id DESC LIMIT 1"
    );
    $latest->execute([$ringId]);
    $counts = $latest->fetch();
    $update = $db->prepare(
        'UPDATE rings SET menages_decontamines = ?, ess_decontamines = ? WHERE id = ?'
    );
    $update->execute([
        (int) ($counts['menages_decontamines'] ?? 0),
        (int) ($counts['ess_decontamines'] ?? 0),
        $ringId,
    ]);
}

function reportData(PDO $db, array $user, array $query): array
{
    $filters = [];
    foreach (['zone_id', 'aire_id', 'date_from', 'date_to'] as $key) {
        if (isset($query[$key]) && $query[$key] !== '' && $query[$key] !== 'all') {
            $filters[$key] = $query[$key];
        }
    }
    if (isset($filters['date_from'], $filters['date_to']) && $filters['date_from'] > $filters['date_to']) {
        apiError(422, 'La date de début doit précéder ou être égale à la date de fin.');
    }

    $operationalRoles = ['super_admin', 'admin_zone', 'chef_pilier', 'superviseur_pci', 'surveillant_epidemiologique'];
    $deathRoles = ['super_admin', 'admin_zone', 'chef_pilier', 'responsable_eds'];
    $supervisionRoles = ['super_admin', 'admin_zone', 'chef_pilier', 'superviseur_pci'];
    $report = [
        'filtres' => $filters,
        'genere_le' => date(DATE_ATOM),
        'role' => $user['role'],
        'activites' => records($db, $user, 'activities', null, $filters + ['limit' => 5000]),
    ];
    if (in_array($user['role'], $operationalRoles, true)) {
        $report['cas'] = records($db, $user, 'cases', null, $filters + ['limit' => 5000]);
        $report['rings'] = records($db, $user, 'rings', null, $filters + ['limit' => 5000]);
    }
    if (in_array($user['role'], $deathRoles, true)) {
        $report['deces'] = records($db, $user, 'deaths', null, $filters + ['limit' => 5000]);
    }
    if (in_array($user['role'], $supervisionRoles, true)) {
        $report['supervisions'] = records($db, $user, 'supervisions', null, $filters + ['limit' => 5000]);
    }

    $cases = $report['cas'] ?? [];
    $deaths = $report['deces'] ?? [];
    $rings = $report['rings'] ?? [];
    $activities = $report['activites'];
    $supervisions = $report['supervisions'] ?? [];
    $report['indicateurs'] = [
        'cas_confirmes' => count($cases),
        'ppl_infectes' => count(array_filter($cases, static fn (array $row): bool => (int) $row['ppl_infecte'] === 1)),
        'deces' => count($deaths),
        'eds_realises' => count(array_filter($deaths, static fn (array $row): bool => $row['statut_eds'] === 'realise')),
        'rings_actifs' => count(array_filter($rings, static fn (array $row): bool => $row['statut'] === 'actif')),
        'supervisions' => count($supervisions),
        'activites' => count($activities),
        'activites_terminees' => count(array_filter($activities, static fn (array $row): bool => $row['statut'] === 'terminee')),
    ];

    return $report;
}

function requireRecord(PDO $db, string $table, int $id): array
{
    $allowed = [
        'zones_sante' => 'id, nom, code, province, active',
        'aires_sante' => 'id, zone_id, nom, code, active',
        'etablissements_sante' => 'id, aire_id, nom, code, type, adresse, telephone, active',
        'cas_mve' => 'id, zone_id, aire_id, ess_origine_id, origine, adresse_communaute, code_epidemiologique, nom_initiales, age, sexe, ppl_infecte, date_confirmation, statut',
        'rings' => 'id, cas_mve_id, code, date_debut, date_fin, nombre_menages, menages_decontamines, nombre_ess, ess_decontamines, ouvert_le, ferme_le, nombre_eglises, eglises_decontaminees, noms_eglises, nombre_ecoles, ecoles_decontaminees, noms_ecoles, nombre_marches, marches_decontamines, noms_marches, nombre_parking, parking_decontamines, noms_parking, nombre_autres_lieux, autres_lieux_decontamines, noms_autres_lieux, statut_decontamination, statut_vaccination, statut_sensibilisation, statut',
        'deces' => 'id, cas_mve_id, aire_id, date_deces, lieu, type_deces, ess_id, statut_eds, date_eds, commentaires',
        'supervisions_pci' => 'id, ess_id, superviseur_id, planifie_par, activite_planifiee, description_activite, date_planifiee, date_visite, statut_visite, points_forts, points_saillants, points_faibles, points_a_ameliorer, recommandations, action_correctrice, scorecard_pourcentage, nombre_prestataires_briefes, liste_presence_photo, statut_suivi, date_resolution',
        'users' => 'id, zone_id, nom, email, role, active',
        'activites_riposte' => 'id, zone_id, aire_id, ess_id, cas_mve_id, ring_id, pilier, titre, description, date_activite, indicateur, cible, realise, menages_decontamines, ess_decontamines, actions_sur_ring, statut, difficultes, actions_correctives, created_by',
    ];
    if (!isset($allowed[$table])) {
        apiError(404, 'Ressource introuvable.');
    }
    $statement = $db->prepare('SELECT ' . $allowed[$table] . ' FROM ' . $table . ' WHERE id = ?');
    $statement->execute([$id]);
    $row = $statement->fetch();
    if (!$row) {
        apiError(404, 'Ressource introuvable.');
    }

    return $row;
}

function saveGeography(PDO $db, array $user, string $resource, ?int $id, array $input): array
{
    requireRoles($user, $resource === 'zones' ? ['super_admin'] : ['super_admin', 'admin_zone']);
    $tables = ['zones' => 'zones_sante', 'aires' => 'aires_sante', 'ess' => 'etablissements_sante'];
    $table = $tables[$resource] ?? null;
    if ($table === null) {
        apiError(404, 'Ressource introuvable.');
    }

    if ($resource === 'zones') {
        $values = [
            'nom' => requiredString($input, 'nom', 120),
            'code' => requiredString($input, 'code', 30),
            'province' => optionalString($input, 'province', 120),
            'active' => booleanValue($input, 'active'),
        ];
        $columns = ['nom', 'code', 'province', 'active'];
        if ($id === null) {
            $administrator = $input['admin'] ?? null;
            if (!is_array($administrator)) {
                apiError(422, 'Un compte administrateur doit être fourni à la création de la zone.');
            }
            $adminName = requiredString($administrator, 'nom', 120);
            $adminEmail = requiredString($administrator, 'email', 190);
            if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                apiError(422, 'Adresse e-mail de l’administrateur de zone invalide.');
            }
            $adminPassword = $administrator['password'] ?? null;
            if (!is_string($adminPassword) || strlen($adminPassword) < 4) {
                apiError(422, 'Le mot de passe de l’administrateur de zone doit comporter au moins 4 caractères.');
            }

            $db->beginTransaction();
            try {
                $statement = $db->prepare('INSERT INTO zones_sante (nom, code, province, active) VALUES (:nom, :code, :province, :active)');
                $statement->execute($values);
                $zoneId = (int) $db->lastInsertId();
                $statement = $db->prepare(
                    "INSERT INTO users (zone_id, nom, email, password, role, active)
                     VALUES (:zone_id, :nom, :email, :password, 'admin_zone', 1)"
                );
                $statement->execute([
                    'zone_id' => $zoneId,
                    'nom' => $adminName,
                    'email' => $adminEmail,
                    'password' => password_hash($adminPassword, PASSWORD_DEFAULT),
                ]);
                $db->commit();
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $exception;
            }

            $zone = requireRecord($db, $table, $zoneId);
            $zone['administrateur'] = ['nom' => $adminName, 'email' => $adminEmail, 'role' => 'admin_zone'];
            return $zone;
        }
    } elseif ($resource === 'aires') {
        $zoneId = $user['role'] === 'admin_zone'
            ? (int) $user['zone_id']
            : requiredId($input, 'zone_id');
        if ($user['role'] === 'admin_zone' && isset($input['zone_id'])
            && (int) $input['zone_id'] !== $zoneId) {
            apiError(403, 'Un administrateur de zone ne peut gérer que les Aires de Santé de sa zone.');
        }
        $statement = $db->prepare('SELECT id FROM zones_sante WHERE id = ? AND active = 1');
        $statement->execute([$zoneId]);
        if (!$statement->fetchColumn()) {
            apiError(422, 'Zone de Santé inconnue ou désactivée.');
        }
        if ($id !== null && $user['role'] === 'admin_zone') {
            $existingAreaZoneId = assertAreaAccess($db, $user, $id);
            if ($existingAreaZoneId !== $zoneId) {
                apiError(422, 'Une Aire de Santé ne peut pas être déplacée vers une autre Zone.');
            }
        }
        $values = ['zone_id' => $zoneId, 'nom' => requiredString($input, 'nom', 120), 'code' => requiredString($input, 'code', 30), 'active' => booleanValue($input, 'active')];
        $columns = ['zone_id', 'nom', 'code', 'active'];
    } else {
        $areaId = requiredId($input, 'aire_id');
        assertAreaAccess($db, $user, $areaId);
        if ($id !== null) {
            $existingFacilityAreaId = assertFacilityAccess($db, $user, $id);
            assertAreaAccess($db, $user, $existingFacilityAreaId);
        }
        $values = [
            'aire_id' => $areaId,
            'nom' => requiredString($input, 'nom', 160),
            'code' => optionalString($input, 'code', 40),
            'type' => optionalString($input, 'type', 80),
            'adresse' => optionalString($input, 'adresse', 255),
            'telephone' => optionalString($input, 'telephone', 40),
            'active' => booleanValue($input, 'active'),
        ];
        $columns = ['aire_id', 'nom', 'code', 'type', 'adresse', 'telephone', 'active'];
    }

    if ($id === null) {
        $columnSql = implode(', ', $columns);
        $placeholderSql = implode(', ', array_map(static fn (string $column): string => ':' . $column, $columns));
        $statement = $db->prepare("INSERT INTO {$table} ({$columnSql}) VALUES ({$placeholderSql})");
        $statement->execute($values);
        $id = (int) $db->lastInsertId();
        return requireRecord($db, $table, $id);
    }

    requireRecord($db, $table, $id);
    $setSql = implode(', ', array_map(static fn (string $column): string => "{$column} = :{$column}", $columns));
    $values['id'] = $id;
    $statement = $db->prepare("UPDATE {$table} SET {$setSql} WHERE id = :id");
    $statement->execute($values);
    return requireRecord($db, $table, $id);
}

function saveUser(PDO $db, array $user, ?int $id, array $input): array
{
    requireRoles($user, ['super_admin', 'admin_zone', 'chef_pilier']);
    $name = requiredString($input, 'nom', 120);
    $email = requiredString($input, 'email', 190);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        apiError(422, 'Adresse e-mail invalide.');
    }
    $assignableRoles = $user['role'] === 'chef_pilier'
        ? ['superviseur_pci', 'responsable_eds']
        : ['chef_pilier', 'superviseur_pci', 'responsable_eds', 'surveillant_epidemiologique'];
    $role = enumValue($input, 'role', $assignableRoles);
    $zoneId = $user['role'] !== 'super_admin'
        ? (int) $user['zone_id']
        : optionalId($input, 'zone_id');
    if ($zoneId === null) {
        apiError(422, 'Un compte de zone doit être rattaché à une Zone de Santé.');
    }
    if ($user['role'] !== 'super_admin' && isset($input['zone_id'])
        && (int) $input['zone_id'] !== $zoneId) {
        apiError(403, 'Vous ne pouvez gérer que les comptes de votre Zone de Santé.');
    }
    if ($zoneId !== null) {
        $statement = $db->prepare('SELECT id FROM zones_sante WHERE id = ? AND active = 1');
        $statement->execute([$zoneId]);
        if (!$statement->fetchColumn()) {
            apiError(422, 'Zone de Santé inconnue ou désactivée.');
        }
    }
    $existing = $id === null ? null : requireRecord($db, 'users', $id);
    if ($existing !== null) {
        if (!in_array($existing['role'], $assignableRoles, true)) {
            apiError(403, 'Ce compte d’administration ne peut pas être modifié depuis cette interface.');
        }
        if ($user['role'] !== 'super_admin' && (int) $existing['zone_id'] !== $user['zone_id']) {
            apiError(404, 'Compte introuvable.');
        }
    }
    $active = array_key_exists('active', $input)
        ? filter_var($input['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
        : ($existing === null ? true : (bool) $existing['active']);
    if ($active === null) {
        apiError(422, 'Le champ active doit être booléen.');
    }

    if ($id === null) {
        $password = $input['password'] ?? null;
        if (!is_string($password) || strlen($password) < 4) {
            apiError(422, 'Le mot de passe doit comporter au moins 4 caractères.');
        }
        $statement = $db->prepare(
            'INSERT INTO users (zone_id, nom, email, password, role, active) VALUES (:zone_id, :nom, :email, :password, :role, :active)'
        );
        $statement->execute([
            'zone_id' => $zoneId,
            'nom' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'active' => (int) $active,
        ]);
        return requireRecord($db, 'users', (int) $db->lastInsertId());
    }

    $password = $input['password'] ?? null;
    if ($password !== null && (!is_string($password) || strlen($password) < 4)) {
        apiError(422, 'Le mot de passe doit comporter au moins 4 caractères.');
    }
    $sql = 'UPDATE users SET zone_id = :zone_id, nom = :nom, email = :email, role = :role, active = :active';
    $values = ['zone_id' => $zoneId, 'nom' => $name, 'email' => $email, 'role' => $role, 'active' => (int) $active, 'id' => $id];
    if (is_string($password) && $password !== '') {
        $sql .= ', password = :password';
        $values['password'] = password_hash($password, PASSWORD_DEFAULT);
    }
    $sql .= ' WHERE id = :id';
    $statement = $db->prepare($sql);
    $statement->execute($values);
    return requireRecord($db, 'users', $id);
}

function allowedActivityPillars(array $user): ?array
{
    return match ($user['role']) {
        'superviseur_pci' => ['pci', 'sensibilisation'],
        'responsable_eds' => ['eds'],
        'surveillant_epidemiologique' => ['surveillance'],
        default => null,
    };
}

function activityValue(array $input, string $key): ?string
{
    $value = $input[$key] ?? null;
    if ($value === null || $value === '') {
        return null;
    }
    if ((!is_string($value) && !is_int($value) && !is_float($value))
        || !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', (string) $value)) {
        apiError(422, "Le champ « {$key} » doit être un nombre positif ou nul avec au maximum deux décimales.");
    }

    return number_format((float) $value, 2, '.', '');
}

function nullableUnsignedNumber(array $input, string $key): ?int
{
    if (!isset($input[$key]) || $input[$key] === '') {
        return null;
    }
    if (filter_var($input[$key], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
        apiError(422, "Le champ « {$key} » doit être un entier positif ou nul.");
    }

    return (int) $input[$key];
}

function requiredSemicolonListString(array $input, string $key, int $maxLength = 10000): string
{
    $value = requiredString($input, $key, $maxLength);
    $items = array_filter(
        array_map('trim', explode(';', $value)),
        static fn (string $item): bool => $item !== ''
    );
    if ($items === []) {
        apiError(422, "Le champ « {$key} » doit contenir au moins une information.");
    }

    return implode('; ', $items);
}

function saveActivity(PDO $db, array $user, ?int $id, array $input): array
{
    requireRoles($user, ['super_admin', 'admin_zone', 'chef_pilier', 'superviseur_pci', 'responsable_eds', 'surveillant_epidemiologique']);
    $zoneId = $user['role'] === 'super_admin'
        ? requiredId($input, 'zone_id')
        : (int) $user['zone_id'];
    requireZoneAccess($user, $zoneId);
    $zoneStatement = $db->prepare('SELECT active FROM zones_sante WHERE id = ?');
    $zoneStatement->execute([$zoneId]);
    if (!(bool) $zoneStatement->fetchColumn()) {
        apiError(422, 'Zone de Santé inconnue ou désactivée.');
    }
    $pillar = enumValue($input, 'pilier', ['eds', 'pci', 'surveillance', 'vaccination', 'sensibilisation', 'logistique', 'coordination', 'autre']);
    $allowedPillars = allowedActivityPillars($user);
    if ($allowedPillars !== null && !in_array($pillar, $allowedPillars, true)) {
        apiError(403, 'Votre rôle ne peut gérer que les activités de son pilier.');
    }
    if ($pillar === 'pci' && !in_array($user['role'], ['super_admin', 'admin_zone', 'superviseur_pci'], true)) {
        apiError(403, 'Seul le Superviseur PCI peut saisir les actions et résultats de décontamination d’un Ring.');
    }

    $areaId = optionalId($input, 'aire_id');
    if ($areaId !== null && assertAreaAccess($db, $user, $areaId) !== $zoneId) {
        apiError(422, 'L’Aire de Santé ne dépend pas de la Zone de Santé indiquée.');
    }
    $caseId = optionalId($input, 'cas_mve_id');
    $ringId = optionalId($input, 'ring_id');
    if ($pillar === 'pci') {
        if ($caseId === null || $ringId === null) {
            apiError(422, 'Une activité PCI doit être liée à un cas enregistré par la Surveillance et à son Ring.');
        }
        $case = assertCaseAccess($db, $user, $caseId);
        $ringStatement = $db->prepare(
            "SELECT cas_mve_id, statut FROM rings WHERE id = ? AND deleted_at IS NULL"
        );
        $ringStatement->execute([$ringId]);
        $ring = $ringStatement->fetch();
        if (!$ring || (int) $ring['cas_mve_id'] !== $caseId) {
            apiError(422, 'Le Ring sélectionné doit être ouvert autour du cas indiqué.');
        }
        if ($ring['statut'] !== 'actif') {
            apiError(422, 'Les activités PCI ne peuvent plus être ajoutées à un Ring fermé.');
        }
        if ($case['zone_id'] !== $zoneId || ($areaId !== null && $areaId !== $case['aire_id'])) {
            apiError(422, 'Le cas, le Ring et l’activité doivent appartenir à la même Zone et Aire de Santé.');
        }
        $areaId = $case['aire_id'];
    } elseif ($caseId !== null || $ringId !== null
        || isset($input['menages_decontamines']) || isset($input['ess_decontamines']) || isset($input['actions_sur_ring'])) {
        apiError(422, 'Les détails de décontamination et de Ring ne sont autorisés que pour une activité PCI.');
    }
    $facilityId = optionalId($input, 'ess_id');
    if ($facilityId !== null) {
        $facilityAreaId = assertFacilityAccess($db, $user, $facilityId);
        $facilityStatement = $db->prepare('SELECT a.zone_id FROM etablissements_sante e JOIN aires_sante a ON a.id = e.aire_id WHERE e.id = ?');
        $facilityStatement->execute([$facilityId]);
        if ((int) $facilityStatement->fetchColumn() !== $zoneId) {
            apiError(422, 'L’Établissement de Santé ne dépend pas de la Zone de Santé indiquée.');
        }
        if ($areaId !== null && $areaId !== $facilityAreaId) {
            apiError(422, 'L’Établissement de Santé ne dépend pas de l’Aire de Santé indiquée.');
        }
        $areaId = $facilityAreaId;
    }

    $values = [
        'zone_id' => $zoneId,
        'aire_id' => $areaId,
        'ess_id' => $facilityId,
        'cas_mve_id' => $caseId,
        'ring_id' => $ringId,
        'pilier' => $pillar,
        'titre' => requiredString($input, 'titre', 160),
        'description' => optionalString($input, 'description', 10000),
        'date_activite' => dateValue($input, 'date_activite'),
        'indicateur' => optionalString($input, 'indicateur', 160),
        'cible' => activityValue($input, 'cible'),
        'realise' => activityValue($input, 'realise'),
        'menages_decontamines' => $pillar === 'pci' ? (nullableUnsignedNumber($input, 'menages_decontamines') ?? 0) : null,
        'ess_decontamines' => $pillar === 'pci' ? (nullableUnsignedNumber($input, 'ess_decontamines') ?? 0) : null,
        'actions_sur_ring' => $pillar === 'pci' ? optionalString($input, 'actions_sur_ring', 10000) : null,
        'statut' => enumValue($input, 'statut', ['planifiee', 'en_cours', 'terminee', 'bloquee'], 'planifiee'),
        'difficultes' => optionalString($input, 'difficultes', 10000),
        'actions_correctives' => optionalString($input, 'actions_correctives', 10000),
    ];

    $ringIdsToSync = $ringId === null ? [] : [$ringId];
    if ($id === null) {
        $values['created_by'] = $user['id'];
        $statement = $db->prepare(
            'INSERT INTO activites_riposte
             (zone_id, aire_id, ess_id, cas_mve_id, ring_id, pilier, titre, description, date_activite, indicateur, cible, realise, menages_decontamines, ess_decontamines, actions_sur_ring, statut, difficultes, actions_correctives, created_by)
              VALUES (:zone_id, :aire_id, :ess_id, :cas_mve_id, :ring_id, :pilier, :titre, :description, :date_activite, :indicateur, :cible, :realise, :menages_decontamines, :ess_decontamines, :actions_sur_ring, :statut, :difficultes, :actions_correctives, :created_by)'
        );
        $statement->execute($values);
        $id = (int) $db->lastInsertId();
    } else {
        $existing = requireRecord($db, 'activites_riposte', $id);
        requireZoneAccess($user, (int) $existing['zone_id']);
        if ($allowedPillars !== null && !in_array($existing['pilier'], $allowedPillars, true)) {
            apiError(404, 'Activité introuvable.');
        }
        if ($existing['pilier'] !== $pillar) {
            apiError(422, 'Le pilier d’une activité existante ne peut pas être modifié.');
        }
        if ($existing['ring_id'] !== null) {
            $ringIdsToSync[] = (int) $existing['ring_id'];
        }
        $assignments = [];
        foreach (array_keys($values) as $column) {
            $assignments[] = "{$column} = :{$column}";
        }
        $values['id'] = $id;
        $statement = $db->prepare('UPDATE activites_riposte SET ' . implode(', ', $assignments) . ' WHERE id = :id AND deleted_at IS NULL');
        $statement->execute($values);
    }

    foreach (array_unique($ringIdsToSync) as $linkedRingId) {
        syncRingDecontamination($db, $linkedRingId);
    }

    return records($db, $user, 'activities', $id, []);
}

function saveCase(PDO $db, array $user, ?int $id, array $input): array
{
    requireRoles($user, ['super_admin', 'admin_zone', 'surveillant_epidemiologique']);
    $zoneId = $user['role'] === 'super_admin' ? requiredId($input, 'zone_id') : (int) $user['zone_id'];
    requireZoneAccess($user, $zoneId);
    $areaId = requiredId($input, 'aire_id');
    if (assertAreaAccess($db, $user, $areaId) !== $zoneId) {
        apiError(422, 'L’Aire de Santé ne dépend pas de la Zone de Santé indiquée.');
    }
    $origin = enumValue($input, 'origine', ['ess', 'communaute']);
    $facilityId = optionalId($input, 'ess_origine_id');
    $communityAddress = optionalString($input, 'adresse_communaute', 255);
    if ($origin === 'ess' && $facilityId === null) {
        apiError(422, 'Un Établissement de Santé doit être indiqué pour un cas provenant d’un ESS.');
    }
    if ($origin === 'communaute' && $facilityId !== null) {
        apiError(422, 'Un cas d’origine communautaire ne peut pas être associé à un ESS.');
    }
    if ($origin === 'communaute' && $communityAddress === null) {
        apiError(422, 'L’adresse du cas est obligatoire pour une provenance communautaire.');
    }
    if ($origin === 'ess' && $communityAddress !== null) {
        apiError(422, 'L’adresse communautaire ne doit être saisie que pour une provenance communautaire.');
    }
    if ($facilityId !== null) {
        assertFacilityAccess($db, $user, $facilityId, $areaId);
    }
    $age = $input['age'] ?? null;
    if ($age !== null && $age !== '') {
        if (filter_var($age, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
            apiError(422, 'L’âge doit être un entier positif ou nul.');
        }
        $age = (int) $age;
    } else {
        $age = null;
    }
    if ($age !== null && $age > 120) {
        apiError(422, 'L’âge doit être compris entre 0 et 120.');
    }
    $values = [
        'zone_id' => $zoneId,
        'aire_id' => $areaId,
        'ess_origine_id' => $facilityId,
        'origine' => $origin,
        'adresse_communaute' => $communityAddress,
        'code_epidemiologique' => requiredString($input, 'code_epidemiologique', 60),
        'nom_initiales' => optionalString($input, 'nom_initiales', 160),
        'age' => $age,
        'sexe' => enumValue($input, 'sexe', ['F', 'M', 'inconnu'], 'inconnu'),
        'ppl_infecte' => booleanValue($input, 'ppl_infecte', false),
        'date_confirmation' => dateValue($input, 'date_confirmation'),
        'statut' => enumValue($input, 'statut', ['en_traitement_cte', 'gueri', 'decede'], 'en_traitement_cte'),
    ];
    if ($id === null) {
        $values['created_by'] = $user['id'];
        $statement = $db->prepare(
            'INSERT INTO cas_mve (zone_id, aire_id, ess_origine_id, origine, adresse_communaute, code_epidemiologique, nom_initiales, age, sexe, ppl_infecte, date_confirmation, statut, created_by)
             VALUES (:zone_id, :aire_id, :ess_origine_id, :origine, :adresse_communaute, :code_epidemiologique, :nom_initiales, :age, :sexe, :ppl_infecte, :date_confirmation, :statut, :created_by)'
        );
        $statement->execute($values);
        $id = (int) $db->lastInsertId();
    } else {
        assertCaseAccess($db, $user, $id);
        $statement = $db->prepare(
            'UPDATE cas_mve SET zone_id = :zone_id, aire_id = :aire_id, ess_origine_id = :ess_origine_id,
             origine = :origine, adresse_communaute = :adresse_communaute,
             code_epidemiologique = :code_epidemiologique, nom_initiales = :nom_initiales, age = :age,
             sexe = :sexe, ppl_infecte = :ppl_infecte, date_confirmation = :date_confirmation, statut = :statut WHERE id = :id'
        );
        $values['id'] = $id;
        $statement->execute($values);
    }

    return records($db, $user, 'cases', $id, []);
}

function saveRing(PDO $db, array $user, ?int $id, array $input): array
{
    requireRoles($user, ['super_admin', 'admin_zone', 'chef_pilier', 'superviseur_pci', 'surveillant_epidemiologique']);

    $existing = $id === null ? null : requireRecord($db, 'rings', $id);
    if ($existing !== null) {
        assertCaseAccess($db, $user, (int) $existing['cas_mve_id']);
        if ($existing['statut'] === 'termine') {
            apiError(409, 'Ce Ring est déjà fermé et ne peut plus être modifié.');
        }
    }
    if ($existing !== null && ($input['statut'] ?? null) === 'termine') {
        if (!in_array($user['role'], ['super_admin', 'admin_zone', 'chef_pilier', 'superviseur_pci'], true)) {
            apiError(403, 'Votre rôle ne peut pas fermer ce Ring.');
        }
        $eligibility = $db->prepare('SELECT ouvert_le <= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 48 HOUR) FROM rings WHERE id = ?');
        $eligibility->execute([$id]);
        if (!(bool) $eligibility->fetchColumn()) {
            apiError(422, 'Un Ring ne peut être fermé qu’après 48 heures complètes depuis son ouverture.');
        }
        $statement = $db->prepare(
            "UPDATE rings SET statut = 'termine', date_fin = CURRENT_DATE, ferme_le = CURRENT_TIMESTAMP
             WHERE id = ? AND statut = 'actif' AND ouvert_le <= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 48 HOUR)"
        );
        $statement->execute([$id]);
        if ($statement->rowCount() !== 1) {
            apiError(409, 'Le Ring n’est pas encore éligible à la fermeture ou a déjà été fermé.');
        }
        return records($db, $user, 'rings', $id, []);
    }

    if ($user['role'] === 'superviseur_pci' && $id !== null) {
        apiError(403, 'Le Superviseur PCI peut ouvrir un Ring et fermer un Ring après 48 heures, mais ne peut pas modifier un Ring existant.');
    }

    $caseId = requiredId($input, 'cas_mve_id');
    assertCaseAccess($db, $user, $caseId);
    if ($id === null || (int) $existing['cas_mve_id'] !== $caseId) {
        $activeRingStatement = $db->prepare(
            "SELECT COUNT(*) FROM rings
             WHERE cas_mve_id = ? AND statut = 'actif' AND deleted_at IS NULL"
        );
        $activeRingStatement->execute([$caseId]);
        if ((int) $activeRingStatement->fetchColumn() >= 5) {
            apiError(409, 'Ce cas possède déjà cinq Rings actifs. Fermez-en un avant d’en ouvrir un nouveau.');
        }
    }
    $values = [
        'cas_mve_id' => $caseId,
        'code' => requiredString($input, 'code', 60),
        'date_debut' => dateValue($input, 'date_debut'),
        'date_fin' => null,
        'nombre_menages' => unsignedNumber($input, 'nombre_menages'),
        'menages_decontamines' => unsignedNumber($input, 'menages_decontamines'),
        'nombre_ess' => unsignedNumber($input, 'nombre_ess'),
        'ess_decontamines' => unsignedNumber($input, 'ess_decontamines'),
        'nombre_eglises' => unsignedNumber($input, 'nombre_eglises'),
        'eglises_decontaminees' => unsignedNumber($input, 'eglises_decontaminees'),
        'noms_eglises' => separatedNamesValue($input, 'noms_eglises', unsignedNumber($input, 'nombre_eglises')),
        'nombre_ecoles' => unsignedNumber($input, 'nombre_ecoles'),
        'ecoles_decontaminees' => unsignedNumber($input, 'ecoles_decontaminees'),
        'noms_ecoles' => separatedNamesValue($input, 'noms_ecoles', unsignedNumber($input, 'nombre_ecoles')),
        'nombre_marches' => unsignedNumber($input, 'nombre_marches'),
        'marches_decontamines' => unsignedNumber($input, 'marches_decontamines'),
        'noms_marches' => separatedNamesValue($input, 'noms_marches', unsignedNumber($input, 'nombre_marches')),
        'nombre_parking' => unsignedNumber($input, 'nombre_parking'),
        'parking_decontamines' => unsignedNumber($input, 'parking_decontamines'),
        'noms_parking' => separatedNamesValue($input, 'noms_parking', unsignedNumber($input, 'nombre_parking')),
        'nombre_autres_lieux' => unsignedNumber($input, 'nombre_autres_lieux'),
        'autres_lieux_decontamines' => unsignedNumber($input, 'autres_lieux_decontamines'),
        'noms_autres_lieux' => separatedNamesValue($input, 'noms_autres_lieux', unsignedNumber($input, 'nombre_autres_lieux')),
        'statut_decontamination' => enumValue($input, 'statut_decontamination', ['non_demarre', 'en_cours', 'termine'], 'non_demarre'),
        'statut_vaccination' => enumValue($input, 'statut_vaccination', ['non_demarre', 'en_cours', 'termine'], 'non_demarre'),
        'statut_sensibilisation' => enumValue($input, 'statut_sensibilisation', ['non_demarre', 'en_cours', 'termine'], 'non_demarre'),
        'statut' => 'actif',
    ];
    $decontaminationTargets = [
        'nombre_menages' => 'menages_decontamines',
        'nombre_ess' => 'ess_decontamines',
        'nombre_eglises' => 'eglises_decontaminees',
        'nombre_ecoles' => 'ecoles_decontaminees',
        'nombre_marches' => 'marches_decontamines',
        'nombre_parking' => 'parking_decontamines',
        'nombre_autres_lieux' => 'autres_lieux_decontamines',
    ];
    foreach ($decontaminationTargets as $targetKey => $doneKey) {
        if ($values[$doneKey] > $values[$targetKey]) {
            apiError(422, 'Le nombre décontaminé ne peut pas dépasser le nombre ciblé.');
        }
    }
    if ($id === null) {
        $values['created_by'] = $user['id'];
        $statement = $db->prepare(
            'INSERT INTO rings (cas_mve_id, code, date_debut, date_fin,
             nombre_menages, menages_decontamines, nombre_ess, ess_decontamines,
             nombre_eglises, eglises_decontaminees, noms_eglises, nombre_ecoles, ecoles_decontaminees, noms_ecoles,
             nombre_marches, marches_decontamines, noms_marches, nombre_parking, parking_decontamines, noms_parking,
             nombre_autres_lieux, autres_lieux_decontamines, noms_autres_lieux,
             statut_decontamination, statut_vaccination, statut_sensibilisation, statut, created_by)
             VALUES (:cas_mve_id, :code, :date_debut, :date_fin,
             :nombre_menages, :menages_decontamines, :nombre_ess, :ess_decontamines,
             :nombre_eglises, :eglises_decontaminees, :noms_eglises, :nombre_ecoles, :ecoles_decontaminees, :noms_ecoles,
             :nombre_marches, :marches_decontamines, :noms_marches, :nombre_parking, :parking_decontamines, :noms_parking,
             :nombre_autres_lieux, :autres_lieux_decontamines, :noms_autres_lieux,
             :statut_decontamination, :statut_vaccination, :statut_sensibilisation, :statut, :created_by)'
        );
        $statement->execute($values);
        $id = (int) $db->lastInsertId();
    } else {
        if ($existing === null) {
            apiError(404, 'Ring introuvable.');
        }
        $statement = $db->prepare(
            "UPDATE rings SET cas_mve_id = :cas_mve_id, code = :code, date_debut = :date_debut,
             nombre_menages = :nombre_menages, menages_decontamines = :menages_decontamines,
             nombre_ess = :nombre_ess, ess_decontamines = :ess_decontamines,
             nombre_eglises = :nombre_eglises, eglises_decontaminees = :eglises_decontaminees, noms_eglises = :noms_eglises,
             nombre_ecoles = :nombre_ecoles, ecoles_decontaminees = :ecoles_decontaminees, noms_ecoles = :noms_ecoles,
             nombre_marches = :nombre_marches, marches_decontamines = :marches_decontamines, noms_marches = :noms_marches,
             nombre_parking = :nombre_parking, parking_decontamines = :parking_decontamines, noms_parking = :noms_parking,
             nombre_autres_lieux = :nombre_autres_lieux, autres_lieux_decontamines = :autres_lieux_decontamines, noms_autres_lieux = :noms_autres_lieux,
             statut_decontamination = :statut_decontamination, statut_vaccination = :statut_vaccination,
             statut_sensibilisation = :statut_sensibilisation
             WHERE id = :id AND statut = 'actif'"
        );
        unset($values['date_fin'], $values['statut']);
        $values['id'] = $id;
        $statement->execute($values);
    }

    return records($db, $user, 'rings', $id, []);
}

function saveDeath(PDO $db, array $user, ?int $id, array $input): array
{
    requireRoles($user, ['super_admin', 'admin_zone', 'responsable_eds']);
    $areaId = requiredId($input, 'aire_id');
    assertAreaAccess($db, $user, $areaId);
    $caseId = optionalId($input, 'cas_mve_id');
    if ($caseId !== null) {
        $case = assertCaseAccess($db, $user, $caseId);
        if ($case['aire_id'] !== $areaId) {
            apiError(422, 'Le cas associé doit appartenir à la même Aire de Santé.');
        }
    }
    $place = enumValue($input, 'lieu', ['communaute', 'cte_ess']);
    $deathType = enumValue($input, 'type_deces', ['cte', 'swab_positif']);
    $facilityId = optionalId($input, 'ess_id');
    if ($place === 'communaute' && $facilityId !== null) {
        apiError(422, 'Un décès communautaire ne peut pas être associé à un ESS.');
    }
    if ($place === 'cte_ess' && $facilityId === null) {
        apiError(422, 'Un ESS doit être précisé pour un décès au CTE/ESS.');
    }
    if ($facilityId !== null) {
        assertFacilityAccess($db, $user, $facilityId, $areaId);
    }
    $edsStatus = enumValue($input, 'statut_eds', ['a_realiser', 'realise'], 'a_realiser');
    $deathDate = dateValue($input, 'date_deces');
    $edsDate = dateValue($input, 'date_eds', false);
    if (($edsStatus === 'realise' && $edsDate === null) || ($edsStatus === 'a_realiser' && $edsDate !== null)) {
        apiError(422, 'La date EDS est obligatoire uniquement lorsque le statut est « réalisé ».');
    }
    if ($edsDate !== null && $edsDate < $deathDate) {
        apiError(422, 'La date EDS ne peut pas précéder la date du décès.');
    }
    $values = [
        'cas_mve_id' => $caseId,
        'aire_id' => $areaId,
        'date_deces' => $deathDate,
        'lieu' => $place,
        'type_deces' => $deathType,
        'ess_id' => $facilityId,
        'statut_eds' => $edsStatus,
        'date_eds' => $edsDate,
        'commentaires' => optionalString($input, 'commentaires', 10000),
    ];
    if ($id === null) {
        $values['created_by'] = $user['id'];
        $statement = $db->prepare(
            'INSERT INTO deces (cas_mve_id, aire_id, date_deces, lieu, type_deces, ess_id, statut_eds, date_eds, commentaires, created_by)
             VALUES (:cas_mve_id, :aire_id, :date_deces, :lieu, :type_deces, :ess_id, :statut_eds, :date_eds, :commentaires, :created_by)'
        );
        $statement->execute($values);
        $id = (int) $db->lastInsertId();
    } else {
        $existing = requireRecord($db, 'deces', $id);
        assertAreaAccess($db, $user, (int) $existing['aire_id']);
        $statement = $db->prepare(
            'UPDATE deces SET cas_mve_id = :cas_mve_id, aire_id = :aire_id, date_deces = :date_deces,
             lieu = :lieu, type_deces = :type_deces, ess_id = :ess_id, statut_eds = :statut_eds, date_eds = :date_eds, commentaires = :commentaires
             WHERE id = :id'
        );
        $values['id'] = $id;
        $statement->execute($values);
    }

    return records($db, $user, 'deaths', $id, []);
}

function photoPresencePath(array $input, array $existing): ?string
{
    $photo = $input['liste_presence_photo'] ?? null;
    if ($photo === null || $photo === '') {
        return $existing['liste_presence_photo'] ?? null;
    }
    if (!is_string($photo) || !preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$#', $photo, $matches)) {
        apiError(422, 'La liste de présence doit être une image JPEG, PNG ou WebP.');
    }
    $binary = base64_decode($matches[2], true);
    if ($binary === false || strlen($binary) > 8 * 1024 * 1024) {
        apiError(422, 'La photo de la liste de présence doit avoir une taille maximale de 8 Mo.');
    }
    $directory = dirname(__DIR__) . '/uploads/liste-presence';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        apiError(500, 'Impossible de préparer le dossier de stockage des photos.');
    }
    $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
    $filename = 'presence-' . bin2hex(random_bytes(12)) . '.' . $extension;
    $target = $directory . '/' . $filename;
    if (file_put_contents($target, $binary) === false) {
        apiError(500, 'Impossible d’enregistrer la photo de la liste de présence.');
    }
    $oldPhoto = $existing['liste_presence_photo'] ?? null;
    if (is_string($oldPhoto) && str_starts_with($oldPhoto, 'uploads/liste-presence/')
        && is_file(dirname(__DIR__) . '/' . $oldPhoto)) {
        @unlink(dirname(__DIR__) . '/' . $oldPhoto);
    }
    return 'uploads/liste-presence/' . $filename;
}

function saveSupervision(PDO $db, array $user, ?int $id, array $input): array
{
    requireRoles($user, ['super_admin', 'admin_zone', 'chef_pilier', 'superviseur_pci']);
    $existing = $id === null ? null : requireRecord($db, 'supervisions_pci', $id);
    $isPlanner = in_array($user['role'], ['super_admin', 'admin_zone', 'chef_pilier'], true);

    if ($isPlanner) {
        if ($existing !== null && $existing['statut_visite'] !== 'planifiee') {
            apiError(409, 'Une visite déjà réalisée ne peut pas être replanifiée.');
        }
        if ($existing !== null && $user['role'] === 'chef_pilier'
            && (int) $existing['planifie_par'] !== $user['id']) {
            apiError(404, 'Planification introuvable.');
        }
        $facilityId = requiredId($input, 'ess_id');
        $facilityAreaId = assertFacilityAccess($db, $user, $facilityId);
        $supervisorId = requiredId($input, 'superviseur_id');
        $supervisorStatement = $db->prepare(
            "SELECT id, zone_id FROM users WHERE id = ? AND role = 'superviseur_pci' AND active = 1"
        );
        $supervisorStatement->execute([$supervisorId]);
        $supervisor = $supervisorStatement->fetch();
        $facilityZoneId = assertAreaAccess($db, $user, $facilityAreaId);
        if (!$supervisor || (int) $supervisor['zone_id'] !== $facilityZoneId) {
            apiError(422, 'Le superviseur PCI doit être actif et rattaché à la même Zone de Santé que l’ESS.');
        }
        $plannedDate = dateValue($input, 'date_planifiee');
        $values = [
            'ess_id' => $facilityId,
            'superviseur_id' => $supervisorId,
            'planifie_par' => $existing['planifie_par'] ?? $user['id'],
            'activite_planifiee' => requiredString($input, 'activite_planifiee', 160),
            'description_activite' => optionalString($input, 'description_activite', 10000),
            'date_planifiee' => $plannedDate,
            'date_visite' => $plannedDate,
            'statut_visite' => 'planifiee',
            'statut_suivi' => 'en_cours',
            'date_resolution' => null,
        ];
        if ($id === null) {
            $statement = $db->prepare(
                'INSERT INTO supervisions_pci
                 (ess_id, superviseur_id, planifie_par, activite_planifiee, description_activite, date_planifiee,
                  date_visite, statut_visite, statut_suivi, date_resolution)
                 VALUES (:ess_id, :superviseur_id, :planifie_par, :activite_planifiee, :description_activite,
                  :date_planifiee, :date_visite, :statut_visite, :statut_suivi, :date_resolution)'
            );
            $statement->execute($values);
            $id = (int) $db->lastInsertId();
        } else {
            unset($values['planifie_par'], $values['statut_visite'], $values['statut_suivi'], $values['date_resolution']);
            $values['id'] = $id;
            $statement = $db->prepare(
                'UPDATE supervisions_pci SET ess_id = :ess_id, superviseur_id = :superviseur_id,
                 activite_planifiee = :activite_planifiee, description_activite = :description_activite,
                 date_planifiee = :date_planifiee, date_visite = :date_visite
                 WHERE id = :id AND statut_visite = \'planifiee\''
            );
            $statement->execute($values);
        }
    } else {
        if ($existing === null) {
            apiError(403, 'Le Superviseur PCI ne peut compléter que les visites planifiées qui lui sont assignées.');
        }
        if ((int) $existing['superviseur_id'] !== $user['id']) {
            apiError(404, 'Planification introuvable ou non assignée à votre compte.');
        }
        assertFacilityAccess($db, $user, (int) $existing['ess_id']);
        $visitDate = dateValue($input, 'date_visite');
        $followUpStatus = enumValue($input, 'statut_suivi', ['en_cours', 'resolu'], 'en_cours');
        $resolutionDate = dateValue($input, 'date_resolution', false);
        if (($followUpStatus === 'resolu' && $resolutionDate === null) || ($followUpStatus === 'en_cours' && $resolutionDate !== null)) {
            apiError(422, 'La date de résolution est obligatoire uniquement pour un suivi résolu.');
        }
        if ($resolutionDate !== null && $resolutionDate < $visitDate) {
            apiError(422, 'La date de résolution ne peut pas précéder la visite.');
        }
        $scorecard = $input['scorecard_pourcentage'] ?? null;
        if ($scorecard !== null && $scorecard !== '') {
            if (!is_numeric($scorecard) || (float) $scorecard < 0 || (float) $scorecard > 100) {
                apiError(422, 'Le scorecard doit être compris entre 0 et 100 %.');
            }
            $scorecard = number_format((float) $scorecard, 2, '.', '');
        } else {
            $scorecard = null;
        }
        $briefed = unsignedNumber($input, 'nombre_prestataires_briefes');
        $presencePhoto = photoPresencePath($input, $existing ?? []);
        if ($briefed > 0 && $presencePhoto === null) {
            apiError(422, 'La photo de la liste de présence est obligatoire lorsqu’au moins un prestataire a été briefé.');
        }
        if ($briefed === 0) {
            $presencePhoto = null;
        }
        $values = [
            'date_visite' => $visitDate,
            'points_forts' => requiredSemicolonListString($input, 'points_forts'),
            'points_saillants' => requiredSemicolonListString($input, 'points_saillants'),
            'points_a_ameliorer' => requiredSemicolonListString($input, 'points_a_ameliorer'),
            'recommandations' => requiredSemicolonListString($input, 'recommandations'),
            'action_correctrice' => requiredSemicolonListString($input, 'action_correctrice'),
            'scorecard_pourcentage' => $scorecard,
            'nombre_prestataires_briefes' => $briefed,
            'liste_presence_photo' => $presencePhoto,
            'statut_suivi' => $followUpStatus,
            'date_resolution' => $resolutionDate,
            'id' => $id,
            'superviseur_id' => $user['id'],
        ];
        $statement = $db->prepare(
            'UPDATE supervisions_pci SET date_visite = :date_visite, points_forts = :points_forts,
             points_saillants = :points_saillants, points_a_ameliorer = :points_a_ameliorer,
             recommandations = :recommandations, action_correctrice = :action_correctrice,
             scorecard_pourcentage = :scorecard_pourcentage, statut_suivi = :statut_suivi,
             nombre_prestataires_briefes = :nombre_prestataires_briefes,
             liste_presence_photo = :liste_presence_photo,
             date_resolution = :date_resolution, statut_visite = \'realisee\'
             WHERE id = :id AND superviseur_id = :superviseur_id'
        );
        $statement->execute($values);
        if ($statement->rowCount() !== 1) {
            apiError(409, 'Cette visite ne vous est plus assignée ou a été supprimée.');
        }
    }

    return records($db, $user, 'supervisions', $id, []);
}

function archiveResource(PDO $db, array $user, string $resource, int $id): void
{
    $simpleTables = [
        'zones' => ['zones_sante', ['super_admin'], null],
        'aires' => ['aires_sante', ['super_admin', 'admin_zone'], 'zone_id'],
        'ess' => ['etablissements_sante', ['super_admin', 'admin_zone'], 'aire_id'],
        'users' => ['users', ['super_admin', 'admin_zone', 'chef_pilier'], 'zone_id'],
    ];
    if (isset($simpleTables[$resource])) {
        [$table, $roles, $scopeColumn] = $simpleTables[$resource];
        requireRoles($user, $roles);
        $record = requireRecord($db, $table, $id);
        if ($resource === 'users') {
            $manageableRoles = $user['role'] === 'chef_pilier'
                ? ['superviseur_pci', 'responsable_eds']
                : ['chef_pilier', 'superviseur_pci', 'responsable_eds', 'surveillant_epidemiologique'];
            if (!in_array($record['role'], $manageableRoles, true)) {
                apiError(403, 'Les comptes d’administration sont protégés.');
            }
            if ($user['role'] !== 'super_admin' && (int) $record['zone_id'] !== $user['zone_id']) {
                apiError(404, 'Compte introuvable.');
            }
            $statement = $db->prepare('UPDATE users SET active = 0 WHERE id = ?');
        } elseif ($resource === 'zones') {
            $statement = $db->prepare('UPDATE zones_sante SET active = 0 WHERE id = ?');
        } else {
            if ($resource === 'aires') {
                assertAreaAccess($db, $user, $id);
            } else {
                assertFacilityAccess($db, $user, $id);
            }
            $statement = $db->prepare("UPDATE {$table} SET active = 0 WHERE id = ?");
        }
        $statement->execute([$id]);
        return;
    }

    $definitions = [
        'cases' => ['cas_mve', ['super_admin', 'admin_zone', 'surveillant_epidemiologique'], 'case'],
        'rings' => ['rings', ['super_admin', 'admin_zone', 'chef_pilier', 'superviseur_pci', 'surveillant_epidemiologique'], 'ring'],
        'deaths' => ['deces', ['super_admin', 'admin_zone', 'responsable_eds'], 'death'],
        'supervisions' => ['supervisions_pci', ['super_admin', 'admin_zone', 'chef_pilier'], 'supervision'],
        'activities' => ['activites_riposte', ['super_admin', 'admin_zone', 'chef_pilier', 'superviseur_pci', 'responsable_eds', 'surveillant_epidemiologique'], 'activity'],
    ];
    if (!isset($definitions[$resource])) {
        apiError(404, 'Ressource introuvable.');
    }
    [$table, $roles, $type] = $definitions[$resource];
    requireRoles($user, $roles);
    $record = requireRecord($db, $table, $id);

    if ($type === 'case') {
        assertCaseAccess($db, $user, $id);
    } elseif ($type === 'ring') {
        assertCaseAccess($db, $user, (int) $record['cas_mve_id']);
    } elseif ($type === 'death') {
        assertAreaAccess($db, $user, (int) $record['aire_id']);
    } elseif ($type === 'activity') {
        requireZoneAccess($user, (int) $record['zone_id']);
        $allowedPillars = allowedActivityPillars($user);
        if ($allowedPillars !== null && !in_array($record['pilier'], $allowedPillars, true)) {
            apiError(404, 'Activité introuvable.');
        }
    } else {
        assertFacilityAccess($db, $user, (int) $record['ess_id']);
        if (in_array($user['role'], ['chef_pilier'], true)
            && ((int) $record['planifie_par'] !== $user['id'] || $record['statut_visite'] !== 'planifiee')) {
            apiError(404, 'Seules vos planifications PCI non réalisées peuvent être archivées.');
        }
    }

    $statement = $db->prepare("UPDATE {$table} SET deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND deleted_at IS NULL");
    $statement->execute([$id]);
    if ($type === 'activity' && $record['pilier'] === 'pci' && $record['ring_id'] !== null) {
        syncRingDecontamination($db, (int) $record['ring_id']);
    }
}

function dispatch(PDO $db): void
{
    $segments = routeSegments();
    $resource = $segments[0] ?? '';
    if (count($segments) > 2) {
        apiError(404, 'Route introuvable.');
    }
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($resource === 'auth') {
        if (count($segments) !== 2) {
            apiError(404, 'Route d’authentification introuvable.');
        }
        $action = $segments[1] ?? '';
        if ($action === 'csrf' && $method === 'GET') {
            respond(200, ['csrf_token' => csrfToken()]);
        }
        if ($action === 'login' && $method === 'POST') {
            requireCsrfToken();
            $input = jsonBody();
            $email = requiredString($input, 'email', 190);
            $password = $input['password'] ?? null;
            if (!is_string($password)) {
                apiError(422, 'Mot de passe requis.');
            }
            $now = time();
            $attempts = array_values(array_filter(
                $_SESSION['login_attempts'] ?? [],
                static fn ($timestamp): bool => is_int($timestamp) && $timestamp > $now - 900
            ));
            if (count($attempts) >= 5) {
                apiError(429, 'Trop de tentatives. Réessayez dans 15 minutes.');
            }

            $statement = $db->prepare(
                'SELECT u.id, u.password, u.active, u.role, z.active AS zone_active
                 FROM users u LEFT JOIN zones_sante z ON z.id = u.zone_id
                 WHERE u.email = ? LIMIT 1'
            );
            $statement->execute([$email]);
            $account = $statement->fetch();
            if (!$account || !(bool) $account['active']
                || ($account['role'] !== 'super_admin' && !(bool) $account['zone_active'])
                || !password_verify($password, $account['password'])) {
                $attempts[] = $now;
                $_SESSION['login_attempts'] = $attempts;
                apiError(401, 'Identifiants invalides.');
            }

            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $account['id'];
            $_SESSION['login_attempts'] = [];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            respond(200, ['user' => currentUser($db), 'csrf_token' => $_SESSION['csrf_token']]);
        }
        if ($action === 'logout' && $method === 'POST') {
            requireCsrfToken();
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $cookie = session_get_cookie_params();
                setcookie(session_name(), '', [
                    'expires' => time() - 42000,
                    'path' => $cookie['path'],
                    'domain' => $cookie['domain'],
                    'secure' => $cookie['secure'],
                    'httponly' => $cookie['httponly'],
                    'samesite' => $cookie['samesite'] ?? 'Strict',
                ]);
            }
            session_destroy();
            respond(200, ['message' => 'Session terminée.']);
        }
        if ($action === 'me' && $method === 'GET') {
            respond(200, ['user' => currentUser($db), 'csrf_token' => csrfToken()]);
        }
        apiError(404, 'Route d’authentification introuvable.');
    }

    $id = idFromPath($segments);
    $user = currentUser($db);
    if ($method === 'GET') {
        if ($resource === 'dashboard' && $id === null) {
            respond(200, dashboard($db, $user, $_GET));
        }
        if ($resource === 'reports' && $id === null) {
            respond(200, reportData($db, $user, $_GET));
        }
        if ($resource === 'notifications' && $id === null) {
            respond(200, ['data' => notifications($db, $user)]);
        }
        respond(200, ['data' => records($db, $user, $resource, $id, $_GET)]);
    }

    if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        header('Allow: GET, POST, PUT, DELETE');
        apiError(405, 'Méthode non autorisée. Les suppressions physiques sont désactivées pour préserver la traçabilité.');
    }
    requireCsrfToken();
    if ($method === 'DELETE') {
        if ($id === null) {
            apiError(405, 'DELETE exige un identifiant dans l’URL.');
        }
        archiveResource($db, $user, $resource, $id);
        respond(200, ['message' => 'Enregistrement archivé ou désactivé.']);
    }
    $input = jsonBody();
    if ($method === 'POST' && $id !== null) {
        apiError(405, 'POST ne prend pas d’identifiant dans l’URL ; utilisez PUT pour modifier.');
    }
    if ($method === 'PUT' && $id === null) {
        apiError(405, 'PUT exige un identifiant dans l’URL.');
    }

    if (in_array($resource, ['zones', 'aires', 'ess'], true)) {
        $saved = saveGeography($db, $user, $resource, $id, $input);
    } elseif ($resource === 'users') {
        requireRoles($user, ['super_admin', 'admin_zone', 'chef_pilier']);
        $saved = saveUser($db, $user, $id, $input);
    } elseif ($resource === 'activities') {
        $saved = saveActivity($db, $user, $id, $input);
    } elseif ($resource === 'cases') {
        $saved = saveCase($db, $user, $id, $input);
    } elseif ($resource === 'rings') {
        $saved = saveRing($db, $user, $id, $input);
    } elseif ($resource === 'deaths') {
        $saved = saveDeath($db, $user, $id, $input);
    } elseif ($resource === 'supervisions') {
        $saved = saveSupervision($db, $user, $id, $input);
    } else {
        apiError(404, 'Ressource introuvable.');
    }

    respond($method === 'POST' ? 201 : 200, ['data' => $saved]);
}

try {
    dispatch(Database::connection());
} catch (ApiException $exception) {
    respond($exception->status, ['error' => $exception->getMessage()]);
} catch (PDOException $exception) {
    error_log(sprintf('MVE API database error [%s]: %s', $exception->getCode(), $exception->getMessage()));
    if ((string) $exception->getCode() === '23000') {
        respond(409, ['error' => 'Conflit de données : code déjà utilisé ou relation référencée.']);
    }
    respond(500, ['error' => 'Erreur interne lors de l’accès aux données.']);
} catch (Throwable $exception) {
    error_log(sprintf('MVE API unexpected error: %s in %s:%d', $exception->getMessage(), $exception->getFile(), $exception->getLine()));
    respond(500, ['error' => 'Erreur interne du serveur.']);
}
