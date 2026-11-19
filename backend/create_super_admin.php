<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

require_once __DIR__ . '/../data_base/Database.php';

$name = getenv('MVE_ADMIN_NAME') ?: 'Administrateur';
$email = 'admin@gmail.com';
$password = getenv('MVE_ADMIN_PASSWORD');

if (!is_string($name) || trim($name) === ''
    || !is_string($password) || strlen($password) < 12) {
    fwrite(STDERR, "Définissez MVE_ADMIN_PASSWORD (12 caractères minimum), puis relancez ce script en CLI. L'adresse du Super Admin est admin@gmail.com.\n");
    exit(1);
}

try {
    $db = Database::connection();
    $db->beginTransaction();
    $statement = $db->prepare('SELECT id, role FROM users WHERE email = ? FOR UPDATE');
    $statement->execute([$email]);
    $existing = $statement->fetch();
    if ($existing && $existing['role'] !== 'super_admin') {
        $db->rollBack();
        fwrite(STDERR, "L'adresse admin@gmail.com est déjà utilisée par un autre rôle.\n");
        exit(1);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($existing) {
        $statement = $db->prepare(
            "UPDATE users SET zone_id = NULL, nom = :nom, password = :password, active = 1, role = 'super_admin'
             WHERE id = :id"
        );
        $statement->execute(['nom' => trim($name), 'password' => $hash, 'id' => (int) $existing['id']]);
        $message = 'Compte Super Admin admin@gmail.com mis à jour.';
    } else {
        $statement = $db->prepare(
            "INSERT INTO users (zone_id, nom, email, password, role, active)
             VALUES (NULL, :nom, :email, :password, 'super_admin', 1)"
        );
        $statement->execute(['nom' => trim($name), 'email' => $email, 'password' => $hash]);
        $message = 'Compte Super Admin admin@gmail.com créé.';
    }
    $db->commit();
    fwrite(STDOUT, $message . "\n");
} catch (PDOException $exception) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log(sprintf('MVE Super Admin setup failed [%s]: %s', $exception->getCode(), $exception->getMessage()));
    fwrite(STDERR, (string) $exception->getCode() === '23000'
        ? "Un compte utilise déjà cette adresse e-mail.\n"
        : "Échec de création du compte. Vérifiez la base et sa configuration.\n");
    exit(1);
}
