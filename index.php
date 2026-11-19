<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/page_auth.php';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#10233f">
    <title>Riposte MVE | Tableau de bord</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="index.php" aria-label="Accueil Riposte MVE">
            <span class="brand-mark"><i class="bi bi-heart-pulse-fill"></i></span>
            <span><strong>Riposte MVE</strong><small>Surveillance & action</small></span>
        </a>
        <div class="nav-caption">ESPACE DE TRAVAIL</div>
        <nav class="side-nav" aria-label="Navigation principale">
            <a class="nav-link active" href="#dashboard" data-view="dashboard"><i class="bi bi-grid-1x2"></i><span>Tableau de bord</span></a>
            <?php if ($pageUser['role'] !== 'superviseur_pci'): ?>
            <a class="nav-link" href="#cases" data-view="cases"><i class="bi bi-clipboard2-pulse"></i><span>Cas confirmés</span></a>
            <a class="nav-link" href="#rings" data-view="rings"><i class="bi bi-bullseye"></i><span>Rings</span></a>
            <?php endif; ?>
            <a class="nav-link" href="#supervisions" data-view="supervisions"><i class="bi bi-hospital"></i><span>Supervisions PCI</span></a>
            <?php if ($pageUser['role'] !== 'superviseur_pci'): ?>
            <a class="nav-link" href="#deaths" data-view="deaths"><i class="bi bi-heartbreak"></i><span>Décès & EDS</span></a>
            <?php endif; ?>
        </nav>
        <?php if ($pageUser['is_super_admin']): ?>
        <div class="nav-caption nav-caption-spaced">ADMINISTRATION</div>
        <nav class="side-nav" aria-label="Administration">
            <a class="nav-link" href="#geography" data-view="geography"><i class="bi bi-geo-alt"></i><span>Zones & structures</span></a>
            <a class="nav-link" href="#users" data-view="users"><i class="bi bi-people"></i><span>Utilisateurs</span></a>
        </nav>
        <?php endif; ?>
        <div class="sidebar-footer">
            <div class="system-status"><span class="status-dot"></span><span>Système opérationnel</span></div>
            <div class="user-profile">
                <div class="avatar"><?= htmlspecialchars($pageUser['initials'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="user-info"><strong><?= htmlspecialchars($pageUser['nom'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($pageUser['role'], ENT_QUOTES, 'UTF-8') ?></small></div>
                <button class="icon-button" type="button" id="logoutButton" aria-label="Se déconnecter"><i class="bi bi-box-arrow-right"></i></button>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <button class="icon-button mobile-menu" type="button" id="menuToggle" aria-label="Ouvrir le menu"><i class="bi bi-list"></i></button>
            <div class="breadcrumb-line"><span>Riposte MVE</span><i class="bi bi-chevron-right"></i><strong id="breadcrumbCurrent">Tableau de bord</strong></div>
            <div class="topbar-actions">
                <span class="today-label"><i class="bi bi-calendar3"></i> <span id="todayDate"></span></span>
                <button class="icon-button notification-button" type="button" aria-label="Notifications"><i class="bi bi-bell"></i><span></span></button>
                <div class="topbar-divider"></div>
                <div class="avatar avatar-small"><?= htmlspecialchars($pageUser['initials'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </header>

        <div class="page-container">
            <section class="view-panel active" id="view-dashboard">
                <div class="page-heading">
                    <div>
                        <div class="eyebrow">SURVEILLANCE ÉPIDÉMIOLOGIQUE</div>
                        <h1>Vue d’ensemble</h1>
                        <p>Suivez les indicateurs clés et coordonnez la riposte.</p>
                    </div>
                    <div class="heading-actions">
                        <label class="filter-label" for="zoneFilter">Zone de Santé</label>
                        <select class="form-select zone-filter" id="zoneFilter">
                            <option value="all">Toutes les zones</option>
                        </select>
                        <?php if ($pageUser['is_super_admin']): ?>
                        <label class="filter-label area-filter-label" for="areaFilter" hidden>Aire de Santé</label>
                        <select class="form-select zone-filter area-filter" id="areaFilter" hidden>
                            <option value="all">Toutes les aires</option>
                        </select>
                        <?php endif; ?>
                        <button class="btn btn-primary action-button" type="button" data-bs-toggle="modal" data-bs-target="#quickActionModal"><i class="bi bi-plus-lg"></i> Nouvelle saisie</button>
                    </div>
                </div>

                <div class="alert-strip">
                    <span class="alert-icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
                    <div><strong>Suivi de la riposte</strong><span>Les indicateurs affichés sont calculés à partir des données enregistrées dans le système.</span></div>
                    <a href="#cases" data-view="cases">Registre des cas <i class="bi bi-arrow-right"></i></a>
                </div>

                <div class="row g-3 kpi-grid">
                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="kpi-card">
                            <div class="kpi-top"><span class="kpi-icon kpi-red"><i class="bi bi-clipboard2-pulse"></i></span><span class="kpi-period">Depuis le début</span></div>
                            <div class="kpi-value" data-kpi="cases">128</div><div class="kpi-label">Cas confirmés</div>
                            <div class="kpi-foot"><span class="trend-neutral"><i class="bi bi-dash"></i> 0</span><span>sur les 7 derniers jours</span></div>
                        </article>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="kpi-card">
                            <div class="kpi-top"><span class="kpi-icon kpi-orange"><i class="bi bi-heartbreak"></i></span><span class="kpi-period">Depuis le début</span></div>
                            <div class="kpi-value" data-kpi="deaths">34</div><div class="kpi-label">Décès enregistrés</div>
                            <div class="kpi-foot"><span class="trend-down"><i class="bi bi-arrow-down-right"></i> 12%</span><span>sur les 7 derniers jours</span></div>
                        </article>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="kpi-card">
                            <div class="kpi-top"><span class="kpi-icon kpi-blue"><i class="bi bi-bullseye"></i></span><span class="live-pill"><span></span> En cours</span></div>
                            <div class="kpi-value" data-kpi="rings">18</div><div class="kpi-label">Rings actifs</div>
                            <div class="kpi-foot"><span class="trend-up"><i class="bi bi-arrow-up-right"></i> 4</span><span>créés cette semaine</span></div>
                        </article>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="kpi-card">
                            <div class="kpi-top"><span class="kpi-icon kpi-green"><i class="bi bi-hospital"></i></span><span class="kpi-period">Sur 42 structures</span></div>
                            <div class="kpi-value"><span data-kpi="facilities">31</span><small class="kpi-denominator">/ 42</small></div><div class="kpi-label">ESS supervisés</div>
                            <div class="progress kpi-progress" role="progressbar" aria-label="ESS supervisés" aria-valuenow="74" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:74%"></div></div>
                        </article>
                    </div>
                </div>

                <div class="row g-3 dashboard-charts">
                    <div class="col-12 col-xl-8">
                        <article class="content-card chart-card">
                            <div class="card-heading"><div><h2>Cas par Aire de Santé</h2><p>Répartition des cas dans le périmètre sélectionné</p></div></div>
                            <div class="chart-wrap"><canvas id="casesChart" aria-label="Graphique de l'évolution hebdomadaire des cas" role="img"></canvas></div>
                        </article>
                    </div>
                    <div class="col-12 col-xl-4">
                        <article class="content-card chart-card">
                            <div class="card-heading"><div><h2>Structures ciblées</h2><p>Répartition des lieux dans les rings</p></div><button class="icon-button subtle-button" type="button" aria-label="Plus d'options"><i class="bi bi-three-dots"></i></button></div>
                            <div class="ring-chart-wrap"><canvas id="ringChart" aria-label="Répartition des structures dans les rings" role="img"></canvas><div class="chart-center"><strong>1 284</strong><span>lieux ciblés</span></div></div>
                            <div class="chart-legend">
                                <span><i class="legend-dot dot-house"></i>Ménages</span><span><i class="legend-dot dot-public"></i>Églises</span>
                                <span><i class="legend-dot dot-school"></i>Écoles</span><span><i class="legend-dot dot-market"></i>Marchés</span>
                                <span><i class="legend-dot dot-other"></i>Autres lieux</span>
                            </div>
                        </article>
                    </div>
                </div>

                <article class="content-card table-card">
                    <div class="card-heading table-heading"><div><h2>Derniers cas enregistrés</h2><p>Suivi récent des cas confirmés dans les zones de santé</p></div><a class="text-link" href="#cases" data-view="cases">Voir tous les cas <i class="bi bi-arrow-right"></i></a></div>
                    <div class="table-responsive">
                        <table class="table align-middle data-table">
                            <thead><tr><th>CODE ÉPIDÉMIOLOGIQUE</th><th>ZONE DE SANTÉ</th><th>AIRE DE SANTÉ</th><th>DATE DE CONFIRMATION</th><th>STATUT</th><th></th></tr></thead>
                            <tbody id="recentCases">
                                <tr data-zone="Beni"><td><span class="case-code">MVE-2025-0128</span></td><td>Beni</td><td>Boikene</td><td>14 nov. 2025</td><td><span class="status-badge status-treatment"><i></i> En traitement</span></td><td><button class="icon-button row-action" aria-label="Voir le cas"><i class="bi bi-arrow-up-right"></i></button></td></tr>
                                <tr data-zone="Butembo"><td><span class="case-code">MVE-2025-0127</span></td><td>Butembo</td><td>Kitatumba</td><td>12 nov. 2025</td><td><span class="status-badge status-recovered"><i></i> Guéri</span></td><td><button class="icon-button row-action" aria-label="Voir le cas"><i class="bi bi-arrow-up-right"></i></button></td></tr>
                                <tr data-zone="Mabalako"><td><span class="case-code">MVE-2025-0126</span></td><td>Mabalako</td><td>Mangina</td><td>11 nov. 2025</td><td><span class="status-badge status-treatment"><i></i> En traitement</span></td><td><button class="icon-button row-action" aria-label="Voir le cas"><i class="bi bi-arrow-up-right"></i></button></td></tr>
                                <tr data-zone="Beni"><td><span class="case-code">MVE-2025-0125</span></td><td>Beni</td><td>Paida</td><td>9 nov. 2025</td><td><span class="status-badge status-deceased"><i></i> Décédé</span></td><td><button class="icon-button row-action" aria-label="Voir le cas"><i class="bi bi-arrow-up-right"></i></button></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-footer"><span>Affichage de <strong>4</strong> cas récents</span><a href="#cases" data-view="cases">Consulter le registre complet</a></div>
                </article>
                <footer class="page-footer"><span>Riposte MVE <span class="footer-dot">·</span> Outil de coordination sanitaire</span><span>Données confidentielles <i class="bi bi-lock-fill"></i></span></footer>
            </section>

            <section class="view-panel module-placeholder" id="view-module" hidden>
                <div class="page-heading"><div><div class="eyebrow">GESTION OPÉRATIONNELLE</div><h1 id="moduleTitle">Module</h1><p id="moduleDescription"></p></div><button class="btn btn-primary action-button" type="button" data-bs-toggle="modal" data-bs-target="#quickActionModal"><i class="bi bi-plus-lg"></i> Nouvelle saisie</button></div>
                <article class="content-card module-card" id="genericModule"><span class="module-icon"><i class="bi bi-cone-striped"></i></span><h2>Module prêt à connecter</h2><p>Cette vue sera reliée aux données et aux contrôles d’accès lorsque le backend sera mis en place.</p><a href="#dashboard" data-view="dashboard" class="btn btn-outline-primary">Retour au tableau de bord</a></article>
                <?php if ($pageUser['is_super_admin']): ?>
                <section id="zoneAdminPanel" class="admin-panel" hidden>
                    <article class="content-card admin-form-card">
                        <div class="card-heading"><div><h2>Créer une Zone de Santé</h2><p>Une zone désactivée bloque la connexion des comptes qui lui sont rattachés.</p></div></div>
                        <form id="zoneForm" class="admin-inline-form">
                            <label>Nom de la zone<input class="form-control" name="nom" maxlength="120" required placeholder="Ex. Beni"></label>
                            <label>Code<input class="form-control" name="code" maxlength="30" required placeholder="Ex. BENI"></label>
                            <label>Province<input class="form-control" name="province" maxlength="120" placeholder="Province"></label>
                            <button class="btn btn-primary action-button" type="submit"><i class="bi bi-plus-lg"></i> Créer la zone</button>
                        </form>
                        <div class="admin-feedback" id="zoneFeedback" role="status" hidden></div>
                    </article>
                    <article class="content-card admin-table-card">
                        <div class="card-heading"><div><h2>Zones de Santé</h2><p id="zoneCount">Chargement…</p></div><button class="btn btn-outline-secondary refresh-button" type="button" data-refresh="zones"><i class="bi bi-arrow-clockwise"></i> Actualiser</button></div>
                        <div class="table-responsive"><table class="table align-middle data-table"><thead><tr><th>ZONE</th><th>CODE</th><th>PROVINCE</th><th>AIRES</th><th>ESS</th><th>STATUT</th><th>ACTION</th></tr></thead><tbody id="zonesTable"></tbody></table></div>
                    </article>
                </section>
                <section id="userAdminPanel" class="admin-panel" hidden>
                    <article class="content-card admin-form-card">
                        <div class="card-heading"><div><h2>Créer un compte de zone</h2><p>Le mot de passe provisoire doit comporter au moins 12 caractères.</p></div></div>
                        <form id="userForm" class="admin-inline-form user-inline-form">
                            <label>Nom complet<input class="form-control" name="nom" maxlength="120" required></label>
                            <label>Adresse e-mail<input class="form-control" name="email" type="email" maxlength="190" required></label>
                            <label>Mot de passe provisoire<input class="form-control" name="password" type="password" minlength="12" required autocomplete="new-password"></label>
                            <label>Rôle<select class="form-select" name="role" required><option value="chef_pilier">Chef de Pilier</option><option value="superviseur_pci">Superviseur PCI</option></select></label>
                            <label>Zone de Santé<select class="form-select" name="zone_id" required id="userZoneSelect"></select></label>
                            <button class="btn btn-primary action-button" type="submit"><i class="bi bi-person-plus"></i> Créer le compte</button>
                        </form>
                        <div class="admin-feedback" id="userFeedback" role="status" hidden></div>
                    </article>
                    <article class="content-card admin-table-card">
                        <div class="card-heading"><div><h2>Comptes des zones</h2><p id="userCount">Chargement…</p></div><button class="btn btn-outline-secondary refresh-button" type="button" data-refresh="users"><i class="bi bi-arrow-clockwise"></i> Actualiser</button></div>
                        <div class="table-responsive"><table class="table align-middle data-table"><thead><tr><th>NOM</th><th>E-MAIL</th><th>RÔLE</th><th>ZONE</th><th>STATUT</th><th>ACTION</th></tr></thead><tbody id="usersTable"></tbody></table></div>
                    </article>
                </section>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>

<div class="modal fade" id="quickActionModal" tabindex="-1" aria-labelledby="quickActionTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content quick-modal">
        <div class="modal-header"><div><div class="eyebrow">ACTION RAPIDE</div><h2 class="modal-title" id="quickActionTitle">Nouvelle saisie</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
        <div class="modal-body"><p class="modal-intro">Choisissez le type d’information à enregistrer.</p>
            <div class="quick-actions">
                <a href="#cases" data-view="cases" data-bs-dismiss="modal"><span class="quick-icon quick-case"><i class="bi bi-clipboard2-pulse"></i></span><span><strong>Cas confirmé</strong><small>Enregistrer un nouveau cas MVE</small></span><i class="bi bi-chevron-right"></i></a>
                <a href="#rings" data-view="rings" data-bs-dismiss="modal"><span class="quick-icon quick-ring"><i class="bi bi-bullseye"></i></span><span><strong>Ring</strong><small>Créer un ring lié à un cas</small></span><i class="bi bi-chevron-right"></i></a>
                <a href="#supervisions" data-view="supervisions" data-bs-dismiss="modal"><span class="quick-icon quick-supervision"><i class="bi bi-hospital"></i></span><span><strong>Visite de supervision</strong><small>Documenter une visite PCI</small></span><i class="bi bi-chevron-right"></i></a>
                <a href="#deaths" data-view="deaths" data-bs-dismiss="modal"><span class="quick-icon quick-death"><i class="bi bi-heartbreak"></i></span><span><strong>Décès / EDS</strong><small>Enregistrer un décès et son enterrement</small></span><i class="bi bi-chevron-right"></i></a>
            </div>
        </div>
    </div></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>
