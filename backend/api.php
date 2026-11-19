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
    $limit = isset($query['limit']) ? filter_var($query['limit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 200]]) : 100;
    if ($limit === false) {
        apiError(422, 'Le paramètre limit doit être compris entre 1 et 200.');
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
                             c.ess_origine_id, e.nom AS ess_origine_nom, c.code_epidemiologique,
                             c.nom_initiales, c.age, c.sexe, c.date_confirmation, c.statut,
                             c.created_by, c.created_at, c.updated_at
                      FROM cas_mve c
                      JOIN zones_sante z ON z.id = c.zone_id
                      JOIN aires_sante a ON a.id = c.aire_id
                      LEFT JOIN etablissements_sante e ON e.id = c.ess_origine_id
                      WHERE 1 = 1',
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
                      WHERE 1 = 1',
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
                      WHERE 1 = 1',
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
                      WHERE 1 = 1',
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
    ];

    if (!isset($queries[$resource])) {
        apiError(404, 'Ressource introuvable.');
    }
    if ($resource === 'users') {
        requireRoles($user, ['super_admin']);
    }
    if ($resource === 'deaths') {
        requireRoles($user, ['super_admin', 'chef_pilier']);
    }
    if ($resource === 'supervisions') {
        requireRoles($user, ['super_admin', 'superviseur_pci']);
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
    if (isset($query['aire_id']) && in_array($resource, ['ess', 'cases', 'deaths'], true)) {
        $areaId = filter_var($query['aire_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($areaId === false) {
            apiError(422, 'Filtre Aire de Santé invalide.');
        }
        assertAreaAccess($db, $user, (int) $areaId);
        $sql .= ' AND ' . ($resource === 'ess' ? 'e.aire_id' : ($resource === 'deaths' ? 'd.aire_id' : 'c.aire_id')) . ' = :aire_id';
        $bindings['aire_id'] = (int) $areaId;
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
    $deathFilter = $buildFilter($zoneId, $areaId, 'a.zone_id', 'a.id', 'deaths');
    $ringFilter = $buildFilter($zoneId, $areaId, 'c.zone_id', 'c.aire_id', 'rings');
    $supervisionFilter = $buildFilter($zoneId, $areaId, 'a.zone_id', 'a.id', 'supervisions');
    $facilityFilter = $buildFilter($zoneId, $areaId, 'a.zone_id', 'a.id', 'facilities');
    $areaFilter = $buildFilter($zoneId, $areaId, 'a.zone_id', 'a.id', 'areas');

    $statement = $db->prepare(
        "SELECT
            (SELECT COUNT(*) FROM cas_mve c WHERE {$caseFilter['sql']}) AS total_cas,
            (SELECT COUNT(*) FROM deces d JOIN aires_sante a ON a.id = d.aire_id WHERE {$deathFilter['sql']}) AS total_deces,
            (SELECT COUNT(*) FROM rings r JOIN cas_mve c ON c.id = r.cas_mve_id WHERE r.statut = 'actif' AND {$ringFilter['sql']}) AS rings_actifs,
            (SELECT COUNT(DISTINCT s.ess_id)
                FROM supervisions_pci s
                JOIN etablissements_sante e ON e.id = s.ess_id
                JOIN aires_sante a ON a.id = e.aire_id WHERE {$supervisionFilter['sql']}) AS ess_supervises,
            (SELECT COUNT(*) FROM etablissements_sante e JOIN aires_sante a ON a.id = e.aire_id WHERE {$facilityFilter['sql']} AND e.active = 1) AS ess_total"
    );
    $dashboardBindings = array_merge(
        $caseFilter['bindings'],
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
         LEFT JOIN cas_mve c ON c.aire_id = a.id
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
                COALESCE(SUM(r.nombre_autres_lieux), 0) AS autres_lieux
         FROM rings r JOIN cas_mve c ON c.id = r.cas_mve_id
         WHERE ' . $ringFilter['sql']
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

function requireRecord(PDO $db, string $table, int $id): array
{
    $allowed = [
        'zones_sante' => 'id, nom, code, province, active',
        'aires_sante' => 'id, zone_id, nom, code, active',
        'etablissements_sante' => 'id, aire_id, nom, code, type, adresse, telephone, active',
        'cas_mve' => 'id, zone_id, aire_id, ess_origine_id, code_epidemiologique, nom_initiales, age, sexe, date_confirmation, statut',
        'rings' => 'id, cas_mve_id, code, date_debut, date_fin, nombre_menages, nombre_eglises, nombre_ecoles, nombre_marches, nombre_autres_lieux, statut_decontamination, statut_vaccination, statut_sensibilisation, statut',
        'deces' => 'id, cas_mve_id, aire_id, date_deces, lieu, ess_id, statut_eds, date_eds, commentaires',
        'supervisions_pci' => 'id, ess_id, superviseur_id, date_visite, points_forts, points_faibles, recommandations, statut_suivi, date_resolution',
        'users' => 'id, zone_id, nom, email, role, active',
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
    requireRoles($user, ['super_admin']);
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
    } elseif ($resource === 'aires') {
        $zoneId = requiredId($input, 'zone_id');
        $statement = $db->prepare('SELECT id FROM zones_sante WHERE id = ? AND active = 1');
        $statement->execute([$zoneId]);
        if (!$statement->fetchColumn()) {
            apiError(422, 'Zone de Santé inconnue ou désactivée.');
        }
        $values = ['zone_id' => $zoneId, 'nom' => requiredString($input, 'nom', 120), 'code' => requiredString($input, 'code', 30), 'active' => booleanValue($input, 'active')];
        $columns = ['zone_id', 'nom', 'code', 'active'];
    } else {
        $areaId = requiredId($input, 'aire_id');
        $statement = $db->prepare('SELECT id FROM aires_sante WHERE id = ?');
        $statement->execute([$areaId]);
        if (!$statement->fetchColumn()) {
            apiError(422, 'Aire de Santé inconnue.');
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

function saveUser(PDO $db, ?int $id, array $input): array
{
    $name = requiredString($input, 'nom', 120);
    $email = requiredString($input, 'email', 190);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        apiError(422, 'Adresse e-mail invalide.');
    }
    $role = enumValue($input, 'role', ['chef_pilier', 'superviseur_pci']);
    $zoneId = optionalId($input, 'zone_id');
    if ($zoneId === null) {
        apiError(422, 'Un compte de zone doit être rattaché à une Zone de Santé.');
    }
    if ($zoneId !== null) {
        $statement = $db->prepare('SELECT id FROM zones_sante WHERE id = ? AND active = 1');
        $statement->execute([$zoneId]);
        if (!$statement->fetchColumn()) {
            apiError(422, 'Zone de Santé inconnue ou désactivée.');
        }
    }
    $active = isset($input['active']) ? filter_var($input['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : true;
    if ($active === null) {
        apiError(422, 'Le champ active doit être booléen.');
    }

    if ($id === null) {
        $password = $input['password'] ?? null;
        if (!is_string($password) || strlen($password) < 12) {
            apiError(422, 'Le mot de passe doit comporter au moins 12 caractères.');
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
    if ($password !== null && (!is_string($password) || strlen($password) < 12)) {
        apiError(422, 'Le mot de passe doit comporter au moins 12 caractères.');
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

function saveCase(PDO $db, array $user, ?int $id, array $input): array
{
    requireRoles($user, ['super_admin', 'chef_pilier', 'superviseur_pci']);
    $zoneId = $user['role'] === 'super_admin' ? requiredId($input, 'zone_id') : (int) $user['zone_id'];
    requireZoneAccess($user, $zoneId);
    $areaId = requiredId($input, 'aire_id');
    if (assertAreaAccess($db, $user, $areaId) !== $zoneId) {
        apiError(422, 'L’Aire de Santé ne dépend pas de la Zone de Santé indiquée.');
    }
    $facilityId = optionalId($input, 'ess_origine_id');
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
        'code_epidemiologique' => requiredString($input, 'code_epidemiologique', 60),
        'nom_initiales' => optionalString($input, 'nom_initiales', 160),
        'age' => $age,
        'sexe' => enumValue($input, 'sexe', ['F', 'M', 'inconnu'], 'inconnu'),
        'date_confirmation' => dateValue($input, 'date_confirmation'),
        'statut' => enumValue($input, 'statut', ['en_traitement_cte', 'gueri', 'decede'], 'en_traitement_cte'),
    ];
    if ($id === null) {
        $values['created_by'] = $user['id'];
        $statement = $db->prepare(
            'INSERT INTO cas_mve (zone_id, aire_id, ess_origine_id, code_epidemiologique, nom_initiales, age, sexe, date_confirmation, statut, created_by)
             VALUES (:zone_id, :aire_id, :ess_origine_id, :code_epidemiologique, :nom_initiales, :age, :sexe, :date_confirmation, :statut, :created_by)'
        );
        $statement->execute($values);
        $id = (int) $db->lastInsertId();
    } else {
        assertCaseAccess($db, $user, $id);
        $statement = $db->prepare(
            'UPDATE cas_mve SET zone_id = :zone_id, aire_id = :aire_id, ess_origine_id = :ess_origine_id,
             code_epidemiologique = :code_epidemiologique, nom_initiales = :nom_initiales, age = :age,
             sexe = :sexe, date_confirmation = :date_confirmation, statut = :statut WHERE id = :id'
        );
        $values['id'] = $id;
        $statement->execute($values);
    }

    return records($db, $user, 'cases', $id, []);
}

function saveRing(PDO $db, array $user, ?int $id, array $input): array
{
    requireRoles($user, ['super_admin', 'chef_pilier', 'superviseur_pci']);
    $caseId = requiredId($input, 'cas_mve_id');
    assertCaseAccess($db, $user, $caseId);
    $values = [
        'cas_mve_id' => $caseId,
        'code' => requiredString($input, 'code', 60),
        'date_debut' => dateValue($input, 'date_debut'),
        'date_fin' => dateValue($input, 'date_fin', false),
        'nombre_menages' => unsignedNumber($input, 'nombre_menages'),
        'nombre_eglises' => unsignedNumber($input, 'nombre_eglises'),
        'nombre_ecoles' => unsignedNumber($input, 'nombre_ecoles'),
        'nombre_marches' => unsignedNumber($input, 'nombre_marches'),
        'nombre_autres_lieux' => unsignedNumber($input, 'nombre_autres_lieux'),
        'statut_decontamination' => enumValue($input, 'statut_decontamination', ['non_demarre', 'en_cours', 'termine'], 'non_demarre'),
        'statut_vaccination' => enumValue($input, 'statut_vaccination', ['non_demarre', 'en_cours', 'termine'], 'non_demarre'),
        'statut_sensibilisation' => enumValue($input, 'statut_sensibilisation', ['non_demarre', 'en_cours', 'termine'], 'non_demarre'),
        'statut' => enumValue($input, 'statut', ['actif', 'termine'], 'actif'),
    ];
    if ($values['date_fin'] !== null && $values['date_fin'] < $values['date_debut']) {
        apiError(422, 'La date de fin doit être postérieure ou égale à la date de début.');
    }
    if ($id === null) {
        $values['created_by'] = $user['id'];
        $statement = $db->prepare(
            'INSERT INTO rings (cas_mve_id, code, date_debut, date_fin, nombre_menages, nombre_eglises, nombre_ecoles,
             nombre_marches, nombre_autres_lieux, statut_decontamination, statut_vaccination, statut_sensibilisation, statut, created_by)
             VALUES (:cas_mve_id, :code, :date_debut, :date_fin, :nombre_menages, :nombre_eglises, :nombre_ecoles,
             :nombre_marches, :nombre_autres_lieux, :statut_decontamination, :statut_vaccination, :statut_sensibilisation, :statut, :created_by)'
        );
        $statement->execute($values);
        $id = (int) $db->lastInsertId();
    } else {
        $existing = requireRecord($db, 'rings', $id);
        assertCaseAccess($db, $user, (int) $existing['cas_mve_id']);
        $statement = $db->prepare(
            'UPDATE rings SET cas_mve_id = :cas_mve_id, code = :code, date_debut = :date_debut, date_fin = :date_fin,
             nombre_menages = :nombre_menages, nombre_eglises = :nombre_eglises, nombre_ecoles = :nombre_ecoles,
             nombre_marches = :nombre_marches, nombre_autres_lieux = :nombre_autres_lieux,
             statut_decontamination = :statut_decontamination, statut_vaccination = :statut_vaccination,
             statut_sensibilisation = :statut_sensibilisation, statut = :statut WHERE id = :id'
        );
        $values['id'] = $id;
        $statement->execute($values);
    }

    return records($db, $user, 'rings', $id, []);
}

function saveDeath(PDO $db, array $user, ?int $id, array $input): array
{
    requireRoles($user, ['super_admin', 'chef_pilier']);
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
        'ess_id' => $facilityId,
        'statut_eds' => $edsStatus,
        'date_eds' => $edsDate,
        'commentaires' => optionalString($input, 'commentaires', 10000),
    ];
    if ($id === null) {
        $values['created_by'] = $user['id'];
        $statement = $db->prepare(
            'INSERT INTO deces (cas_mve_id, aire_id, date_deces, lieu, ess_id, statut_eds, date_eds, commentaires, created_by)
             VALUES (:cas_mve_id, :aire_id, :date_deces, :lieu, :ess_id, :statut_eds, :date_eds, :commentaires, :created_by)'
        );
        $statement->execute($values);
        $id = (int) $db->lastInsertId();
    } else {
        $existing = requireRecord($db, 'deces', $id);
        assertAreaAccess($db, $user, (int) $existing['aire_id']);
        $statement = $db->prepare(
            'UPDATE deces SET cas_mve_id = :cas_mve_id, aire_id = :aire_id, date_deces = :date_deces,
             lieu = :lieu, ess_id = :ess_id, statut_eds = :statut_eds, date_eds = :date_eds, commentaires = :commentaires
             WHERE id = :id'
        );
        $values['id'] = $id;
        $statement->execute($values);
    }

    return records($db, $user, 'deaths', $id, []);
}

function saveSupervision(PDO $db, array $user, ?int $id, array $input): array
{
    requireRoles($user, ['superviseur_pci']);
    $facilityId = requiredId($input, 'ess_id');
    assertFacilityAccess($db, $user, $facilityId);
    $visitDate = dateValue($input, 'date_visite');
    $followUpStatus = enumValue($input, 'statut_suivi', ['en_cours', 'resolu'], 'en_cours');
    $resolutionDate = dateValue($input, 'date_resolution', false);
    if (($followUpStatus === 'resolu' && $resolutionDate === null) || ($followUpStatus === 'en_cours' && $resolutionDate !== null)) {
        apiError(422, 'La date de résolution est obligatoire uniquement pour un suivi résolu.');
    }
    if ($resolutionDate !== null && $resolutionDate < $visitDate) {
        apiError(422, 'La date de résolution ne peut pas précéder la visite.');
    }
    $values = [
        'ess_id' => $facilityId,
        'superviseur_id' => $user['id'],
        'date_visite' => $visitDate,
        'points_forts' => optionalString($input, 'points_forts', 10000),
        'points_faibles' => optionalString($input, 'points_faibles', 10000),
        'recommandations' => optionalString($input, 'recommandations', 10000),
        'statut_suivi' => $followUpStatus,
        'date_resolution' => $resolutionDate,
    ];
    if ($id === null) {
        $statement = $db->prepare(
            'INSERT INTO supervisions_pci (ess_id, superviseur_id, date_visite, points_forts, points_faibles, recommandations, statut_suivi, date_resolution)
             VALUES (:ess_id, :superviseur_id, :date_visite, :points_forts, :points_faibles, :recommandations, :statut_suivi, :date_resolution)'
        );
        $statement->execute($values);
        $id = (int) $db->lastInsertId();
    } else {
        $existing = requireRecord($db, 'supervisions_pci', $id);
        assertFacilityAccess($db, $user, (int) $existing['ess_id']);
        $statement = $db->prepare(
            'UPDATE supervisions_pci SET ess_id = :ess_id, date_visite = :date_visite, points_forts = :points_forts,
             points_faibles = :points_faibles, recommandations = :recommandations, statut_suivi = :statut_suivi,
             date_resolution = :date_resolution WHERE id = :id AND superviseur_id = :superviseur_id'
        );
        $values['id'] = $id;
        $statement->execute($values);
        if ($statement->rowCount() === 0) {
            $check = $db->prepare('SELECT id FROM supervisions_pci WHERE id = ? AND superviseur_id = ?');
            $check->execute([$id, $user['id']]);
            if (!$check->fetchColumn()) {
                apiError(404, 'Supervision introuvable ou non modifiable par cet utilisateur.');
            }
        }
    }

    return records($db, $user, 'supervisions', $id, []);
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
        respond(200, ['data' => records($db, $user, $resource, $id, $_GET)]);
    }

    if (!in_array($method, ['POST', 'PUT'], true)) {
        header('Allow: GET, POST, PUT');
        apiError(405, 'Méthode non autorisée. Les suppressions physiques sont désactivées pour préserver la traçabilité.');
    }
    requireCsrfToken();
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
        requireRoles($user, ['super_admin']);
        $saved = saveUser($db, $id, $input);
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
