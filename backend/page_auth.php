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

$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) || $userId < 1) {
    header('Location: login.php');
    exit;
}

try {
    $statement = Database::connection()->prepare(
        'SELECT u.id, u.zone_id, u.nom, u.email, u.role, u.active, z.active AS zone_active
         FROM users u LEFT JOIN zones_sante z ON z.id = u.zone_id
         WHERE u.id = ?'
    );
    $statement->execute([$userId]);
    $pageUser = $statement->fetch();
} catch (PDOException $exception) {
    error_log(sprintf('MVE page authentication failed [%s]: %s', $exception->getCode(), $exception->getMessage()));
    http_response_code(503);
    exit('Service temporairement indisponible. Vérifiez la configuration de la base de données.');
}

if (!$pageUser || !(bool) $pageUser['active']
    || ($pageUser['role'] !== 'super_admin' && !(bool) $pageUser['zone_active'])) {
    $_SESSION = [];
    session_regenerate_id(true);
    header('Location: login.php?disabled=1');
    exit;
}

$pageUser['id'] = (int) $pageUser['id'];
$pageUser['zone_id'] = $pageUser['zone_id'] === null ? null : (int) $pageUser['zone_id'];
$pageUser['is_super_admin'] = $pageUser['role'] === 'super_admin';
$pageUser['initials'] = mb_strtoupper(mb_substr($pageUser['nom'], 0, 2, 'UTF-8'), 'UTF-8');
