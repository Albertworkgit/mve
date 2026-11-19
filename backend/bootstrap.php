<?php
declare(strict_types=1);

require_once __DIR__ . '/../data_base/Database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

final class ApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message
    ) {
        parent::__construct($message);
    }
}

function respond(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function apiError(int $status, string $message): never
{
    throw new ApiException($status, $message);
}

function jsonBody(): array
{
    $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($contentType !== 'application/json') {
        apiError(415, 'Le corps de la requête doit être au format application/json.');
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw === false ? '' : $raw, true);
    if (!is_array($data) || array_is_list($data)) {
        apiError(400, 'Le corps JSON doit être un objet valide.');
    }

    return $data;
}

function requireCsrfToken(): void
{
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';
    if (!is_string($provided) || !is_string($expected) || $expected === '' || !hash_equals($expected, $provided)) {
        apiError(419, 'Jeton CSRF manquant ou invalide.');
    }
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function requiredString(array $input, string $key, int $maxLength): string
{
    $value = $input[$key] ?? null;
    if (!is_string($value) || trim($value) === '' || mb_strlen(trim($value), 'UTF-8') > $maxLength) {
        apiError(422, "Le champ « {$key} » est obligatoire et ne peut pas dépasser {$maxLength} caractères.");
    }

    return trim($value);
}

function optionalString(array $input, string $key, int $maxLength): ?string
{
    $value = $input[$key] ?? null;
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_string($value) || mb_strlen(trim($value), 'UTF-8') > $maxLength) {
        apiError(422, "Le champ « {$key} » ne peut pas dépasser {$maxLength} caractères.");
    }

    return trim($value);
}

function separatedNamesValue(array $input, string $key, int $targetCount, int $maxLength = 10000): ?string
{
    $value = optionalString($input, $key, $maxLength);
    if ($targetCount === 0) {
        return null;
    }
    if ($value === null) {
        apiError(422, 'Les noms des lieux ciblés sont obligatoires lorsque le nombre ciblé est supérieur à zéro.');
    }
    $names = array_values(array_filter(
        array_map('trim', explode(';', $value)),
        static fn (string $name): bool => $name !== ''
    ));
    if (count($names) !== $targetCount) {
        apiError(422, 'Le nombre de noms saisis doit correspondre au nombre ciblé. Utilisez un point-virgule (;) entre chaque nom.');
    }
    return implode('; ', $names);
}

function requiredId(array $input, string $key): int
{
    $value = filter_var($input[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($value === false) {
        apiError(422, "Le champ « {$key} » doit être un identifiant valide.");
    }

    return (int) $value;
}

function optionalId(array $input, string $key): ?int
{
    if (!array_key_exists($key, $input) || $input[$key] === null || $input[$key] === '') {
        return null;
    }

    return requiredId($input, $key);
}

function unsignedNumber(array $input, string $key, int $default = 0): int
{
    $value = $input[$key] ?? $default;
    if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 0) {
        apiError(422, "Le champ « {$key} » doit être un entier positif ou nul.");
    }

    return (int) $value;
}

function booleanValue(array $input, string $key, bool $default = true): int
{
    if (!array_key_exists($key, $input)) {
        return (int) $default;
    }

    $value = filter_var($input[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($value === null) {
        apiError(422, "Le champ « {$key} » doit être booléen.");
    }

    return (int) $value;
}

function enumValue(array $input, string $key, array $allowed, ?string $default = null): string
{
    $value = $input[$key] ?? $default;
    if (!is_string($value) || !in_array($value, $allowed, true)) {
        apiError(422, "La valeur du champ « {$key} » est invalide.");
    }

    return $value;
}

function dateValue(array $input, string $key, bool $required = true): ?string
{
    $value = $input[$key] ?? null;
    if (($value === null || $value === '') && !$required) {
        return null;
    }
    if (!is_string($value)) {
        apiError(422, "Le champ « {$key} » doit être une date au format AAAA-MM-JJ.");
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if ($date === false || $date->format('Y-m-d') !== $value) {
        apiError(422, "Le champ « {$key} » doit être une date valide au format AAAA-MM-JJ.");
    }

    return $value;
}

function currentUser(PDO $db): array
{
    $userId = $_SESSION['user_id'] ?? null;
    if (!is_int($userId) || $userId < 1) {
        apiError(401, 'Authentification requise.');
    }

    $statement = $db->prepare(
        'SELECT u.id, u.zone_id, z.nom AS zone_nom, u.nom, u.email, u.role, u.active, z.active AS zone_active
         FROM users u LEFT JOIN zones_sante z ON z.id = u.zone_id
         WHERE u.id = ?'
    );
    $statement->execute([$userId]);
    $user = $statement->fetch();
    if (!$user || !(bool) $user['active']
        || ($user['role'] !== 'super_admin' && !(bool) $user['zone_active'])) {
        $_SESSION = [];
        session_regenerate_id(true);
        apiError(401, 'Cette session n’est plus valide.');
    }

    $user['id'] = (int) $user['id'];
    $user['zone_id'] = $user['zone_id'] === null ? null : (int) $user['zone_id'];
    $user['active'] = (bool) $user['active'];
    unset($user['zone_active']);
    return $user;
}

function requireRoles(array $user, array $roles): void
{
    if (!in_array($user['role'], $roles, true)) {
        apiError(403, 'Vous n’avez pas les droits nécessaires pour cette opération.');
    }
}

function requireZoneAccess(array $user, int $zoneId): void
{
    if ($user['role'] !== 'super_admin' && $user['zone_id'] !== $zoneId) {
        apiError(404, 'Ressource introuvable.');
    }
}

function assertAreaAccess(PDO $db, array $user, int $areaId): int
{
    $statement = $db->prepare('SELECT zone_id FROM aires_sante WHERE id = ?');
    $statement->execute([$areaId]);
    $zoneId = $statement->fetchColumn();
    if ($zoneId === false) {
        apiError(422, 'Aire de Santé inconnue.');
    }

    requireZoneAccess($user, (int) $zoneId);
    return (int) $zoneId;
}

function assertFacilityAccess(PDO $db, array $user, int $facilityId, ?int $expectedAreaId = null): int
{
    $statement = $db->prepare(
        'SELECT a.id AS aire_id, a.zone_id
         FROM etablissements_sante e
         JOIN aires_sante a ON a.id = e.aire_id
         WHERE e.id = ?'
    );
    $statement->execute([$facilityId]);
    $row = $statement->fetch();
    if (!$row || ($expectedAreaId !== null && (int) $row['aire_id'] !== $expectedAreaId)) {
        apiError(422, 'Établissement de Santé inconnu ou incohérent avec l’Aire de Santé.');
    }

    requireZoneAccess($user, (int) $row['zone_id']);
    return (int) $row['aire_id'];
}

function assertCaseAccess(PDO $db, array $user, int $caseId): array
{
    $statement = $db->prepare('SELECT id, zone_id, aire_id FROM cas_mve WHERE id = ?');
    $statement->execute([$caseId]);
    $case = $statement->fetch();
    if (!$case) {
        apiError(422, 'Cas confirmé inconnu.');
    }

    requireZoneAccess($user, (int) $case['zone_id']);
    return ['id' => (int) $case['id'], 'zone_id' => (int) $case['zone_id'], 'aire_id' => (int) $case['aire_id']];
}

function idFromPath(array $segments): ?int
{
    if (!isset($segments[1])) {
        return null;
    }
    $id = filter_var($segments[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        apiError(404, 'Route introuvable.');
    }

    return (int) $id;
}
