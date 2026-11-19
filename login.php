<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#10233f">
    <title>Connexion | EPI DATA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body class="login-page">
    <main class="login-card">
        <a class="brand login-brand" href="login.php">
            <span class="brand-mark"><i class="bi bi-heart-pulse-fill"></i></span>
            <span><strong>EPI DATA</strong><small>Données épidémiologiques</small></span>
        </a>
        <div class="eyebrow">ESPACE SÉCURISÉ</div>
        <h1>Connexion</h1>
        <p class="login-intro">Connectez-vous pour accéder à votre espace de travail.</p>
        <div class="alert alert-warning login-notice" id="loginNotice" role="alert" hidden>Votre compte ou votre Zone de Santé est désactivé. Contactez l’administrateur.</div>
        <div class="alert alert-danger login-error" id="loginError" role="alert" hidden></div>
        <form id="loginForm">
            <label class="form-label" for="email">Adresse e-mail</label>
            <div class="login-input-wrap"><i class="bi bi-envelope"></i><input class="form-control" id="email" name="email" type="email" autocomplete="username" required maxlength="190" placeholder="nom@organisation.org"></div>
            <label class="form-label password-label" for="password">Mot de passe</label>
            <div class="login-input-wrap"><i class="bi bi-lock"></i><input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required><button class="password-toggle" type="button" id="togglePassword" aria-label="Afficher le mot de passe"><i class="bi bi-eye"></i></button></div>
            <button class="btn btn-primary login-submit" type="submit" id="loginButton"><span>Se connecter</span><i class="bi bi-arrow-right"></i></button>
        </form>
        <div class="login-footer"><i class="bi bi-shield-lock"></i> Connexion sécurisée · Données de santé protégées</div>
    </main>
    <script src="assets/js/login.js"></script>
</body>
</html>
