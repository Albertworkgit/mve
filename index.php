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
    <title>EPI DATA | Tableau de bord</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="index.php" aria-label="Accueil EPI DATA">
            <span class="brand-mark"><i class="bi bi-heart-pulse-fill"></i></span>
            <span><strong>EPI DATA</strong><small>Données épidémiologiques</small></span>
        </a>
        <div class="nav-caption">ESPACE DE TRAVAIL</div>
        <nav class="side-nav" aria-label="Navigation principale">
            <a class="nav-link active" href="#dashboard" data-view="dashboard"><i class="bi bi-grid-1x2"></i><span>Tableau de bord</span></a>
            <?php if (in_array($pageUser['role'], ['super_admin', 'admin_zone', 'chef_pilier', 'superviseur_pci', 'surveillant_epidemiologique'], true)): ?>
            <a class="nav-link" href="#cases" data-view="cases"><i class="bi bi-clipboard2-pulse"></i><span>Cas confirmés</span></a>
            <a class="nav-link" href="#rings" data-view="rings"><i class="bi bi-bullseye"></i><span>Rings</span></a>
            <?php endif; ?>
            <?php if (in_array($pageUser['role'], ['super_admin', 'admin_zone', 'chef_pilier', 'superviseur_pci'], true)): ?>
            <a class="nav-link" href="#supervisions" data-view="supervisions"><i class="bi bi-calendar2-check"></i><span>Missions PCI / Scorecard ESS</span></a>
            <?php endif; ?>
            <?php if (in_array($pageUser['role'], ['super_admin', 'admin_zone', 'chef_pilier', 'responsable_eds'], true)): ?>
            <a class="nav-link" href="#deaths" data-view="deaths"><i class="bi bi-heartbreak"></i><span>Décès & EDS</span></a>
            <?php endif; ?>
            <a class="nav-link" href="#activities" data-view="activities"><i class="bi bi-activity"></i><span>Activités de riposte</span></a>
        </nav>
        <?php if ($pageUser['is_super_admin'] || in_array($pageUser['role'], ['admin_zone', 'chef_pilier'], true)): ?>
        <div class="nav-caption nav-caption-spaced">ADMINISTRATION</div>
        <nav class="side-nav" aria-label="Administration">
            <?php if ($pageUser['is_super_admin']): ?>
            <a class="nav-link" href="#geography" data-view="geography"><i class="bi bi-geo-alt"></i><span>Zones de Santé</span></a>
            <?php endif; ?>
            <?php if (in_array($pageUser['role'], ['super_admin', 'admin_zone'], true)): ?>
            <a class="nav-link" href="#aires" data-view="aires"><i class="bi bi-map"></i><span>Aires de Santé</span></a>
            <a class="nav-link" href="#ess" data-view="ess"><i class="bi bi-hospital"></i><span>Établissements</span></a>
            <?php endif; ?>
            <a class="nav-link" href="#users" data-view="users"><i class="bi bi-people"></i><span>Utilisateurs</span></a>
        </nav>
        <?php endif; ?>
        <div class="sidebar-footer">
            <div class="system-status"><span class="status-dot"></span><span>Système opérationnel</span></div>
            <div class="user-profile">
                <div class="avatar"><?= htmlspecialchars($pageUser['initials'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="user-info"><strong><?= htmlspecialchars($pageUser['nom'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(['super_admin' => 'Super Admin', 'admin_zone' => 'Admin de Zone', 'chef_pilier' => 'Chef PCI/EDS', 'superviseur_pci' => 'Superviseur PCI', 'responsable_eds' => 'Responsable EDS', 'surveillant_epidemiologique' => 'Surveillant épidémiologique'][$pageUser['role']] ?? $pageUser['role'], ENT_QUOTES, 'UTF-8') ?></small></div>
                <button class="icon-button" type="button" id="logoutButton" aria-label="Se déconnecter"><i class="bi bi-box-arrow-right"></i></button>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <button class="icon-button mobile-menu" type="button" id="menuToggle" aria-label="Ouvrir le menu"><i class="bi bi-list"></i></button>
            <div class="breadcrumb-line"><span>EPI DATA</span><i class="bi bi-chevron-right"></i><strong id="breadcrumbCurrent">Tableau de bord</strong></div>
            <div class="topbar-actions">
                <span class="today-label"><i class="bi bi-calendar3"></i> <span id="todayDate"></span></span>
                <?php if ($pageUser['role'] === 'superviseur_pci'): ?>
                <div class="notification-wrapper">
                    <button class="icon-button notification-button" type="button" id="notificationButton" aria-label="Notifications PCI" aria-expanded="false"><i class="bi bi-bell"></i><span id="notificationCount" hidden>0</span></button>
                    <div class="notification-panel" id="notificationPanel" hidden>
                        <strong>Nouveaux cas à prendre en charge</strong>
                        <div id="notificationList"><p class="notification-empty">Aucune notification.</p></div>
                    </div>
                </div>
                <?php endif; ?>
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
                        <span class="zone-scope-badge" id="zoneScopeBadge" hidden></span>
                        <?php if (in_array($pageUser['role'], ['super_admin', 'admin_zone'], true)): ?>
                        <label class="filter-label area-filter-label" for="areaFilter" hidden>Aire de Santé</label>
                        <select class="form-select zone-filter area-filter" id="areaFilter" hidden>
                            <option value="all">Toutes les aires</option>
                        </select>
                        <?php endif; ?>
                        <button class="btn btn-primary action-button" type="button" data-bs-toggle="modal" data-bs-target="#quickActionModal"><i class="bi bi-plus-lg"></i> Nouvelle saisie</button>
                        <div class="report-export-controls">
                            <label class="visually-hidden" for="reportDateFrom">Date de début du rapport</label><input class="form-control form-control-sm" type="date" id="reportDateFrom" title="Date de début du rapport">
                            <label class="visually-hidden" for="reportDateTo">Date de fin du rapport</label><input class="form-control form-control-sm" type="date" id="reportDateTo" title="Date de fin du rapport">
                            <button class="btn btn-sm btn-outline-success" type="button" data-report-export="xlsx">Excel</button>
                            <button class="btn btn-sm btn-outline-danger" type="button" data-report-export="pdf">PDF</button>
                            <button class="btn btn-sm btn-outline-primary" type="button" data-report-export="pptx">PowerPoint</button>
                        </div>
                    </div>
                </div>
                <div class="admin-feedback" id="reportFeedback" role="status" hidden></div>

                <div class="alert-strip">
                    <span class="alert-icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
                    <div><strong>Suivi de la riposte</strong><span>Les indicateurs affichés sont calculés à partir des données enregistrées dans le système.</span></div>
                    <?php if (in_array($pageUser['role'], ['super_admin', 'admin_zone', 'chef_pilier', 'superviseur_pci', 'surveillant_epidemiologique'], true)): ?>
                    <a href="#cases" data-view="cases">Registre des cas <i class="bi bi-arrow-right"></i></a>
                    <?php endif; ?>
                </div>

                <div class="row g-3 kpi-grid">
                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="kpi-card">
                            <div class="kpi-top"><span class="kpi-icon kpi-red"><i class="bi bi-clipboard2-pulse"></i></span><span class="kpi-period">Depuis le début</span></div>
                            <div class="kpi-value" data-kpi="cases">0</div><div class="kpi-label">Cas confirmés</div>
                            <div class="kpi-foot"><span class="trend-neutral" data-kpi="pplCases">0</span><span>PPL infectés</span></div>
                        </article>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="kpi-card">
                            <div class="kpi-top"><span class="kpi-icon kpi-orange"><i class="bi bi-heartbreak"></i></span><span class="kpi-period">Depuis le début</span></div>
                            <div class="kpi-value" data-kpi="deaths">0</div><div class="kpi-label">Décès enregistrés</div>
                            <div class="kpi-foot"><span>Depuis le début</span></div>
                        </article>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="kpi-card">
                            <div class="kpi-top"><span class="kpi-icon kpi-blue"><i class="bi bi-bullseye"></i></span><span class="live-pill"><span></span> En cours</span></div>
                            <div class="kpi-value" data-kpi="rings">0</div><div class="kpi-label">Rings actifs</div>
                            <div class="kpi-foot"><span>Depuis le début</span></div>
                        </article>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="kpi-card">
                            <div class="kpi-top"><span class="kpi-icon kpi-green"><i class="bi bi-hospital"></i></span><span class="kpi-period">Structures actives</span></div>
                            <div class="kpi-value"><span data-kpi="facilities">0</span><small class="kpi-denominator">/ 0</small></div><div class="kpi-label">ESS supervisés</div>
                            <div class="progress kpi-progress" role="progressbar" aria-label="ESS supervisés" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:0%"></div></div>
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
                            <div class="ring-chart-wrap"><canvas id="ringChart" aria-label="Répartition des structures dans les rings" role="img"></canvas><div class="chart-center"><strong>0</strong><span>lieux ciblés</span></div></div>
                            <div class="chart-legend">
                                <span><i class="legend-dot dot-house"></i>Ménages</span><span><i class="legend-dot dot-public"></i>Églises</span>
                                <span><i class="legend-dot dot-school"></i>Écoles</span><span><i class="legend-dot dot-market"></i>Marchés</span>
                                <span><i class="legend-dot" style="background:#7caed8"></i>Parkings</span>
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
                                <tr><td colspan="6" class="empty-row">Chargement des données réelles…</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-footer"><span id="recentCasesCount">Chargement des données…</span><a href="#cases" data-view="cases">Consulter le registre complet</a></div>
                </article>
                <footer class="page-footer"><span>EPI DATA <span class="footer-dot">·</span> Outil de coordination sanitaire</span><span>Données confidentielles <i class="bi bi-lock-fill"></i></span></footer>
            </section>

            <section class="view-panel module-placeholder" id="view-module" hidden>
                <div class="page-heading"><div><div class="eyebrow">GESTION OPÉRATIONNELLE</div><h1 id="moduleTitle">Module</h1><p id="moduleDescription"></p></div><?php if ($pageUser['role'] !== 'admin_zone'): ?><button class="btn btn-primary action-button" type="button" data-bs-toggle="modal" data-bs-target="#quickActionModal"><i class="bi bi-plus-lg"></i> Nouvelle saisie</button><?php endif; ?></div>
                <article class="content-card module-card" id="genericModule"><span class="module-icon"><i class="bi bi-cone-striped"></i></span><h2>Module prêt à connecter</h2><p>Cette vue sera reliée aux données et aux contrôles d’accès lorsque le backend sera mis en place.</p><a href="#dashboard" data-view="dashboard" class="btn btn-outline-primary">Retour au tableau de bord</a></article>
                <article class="content-card admin-table-card" id="resourceCrudPanel" hidden>
                    <div class="card-heading"><div><h2 id="resourceTableTitle">Registre</h2><p id="resourceTableSubtitle">Créer, consulter, modifier et supprimer les enregistrements.</p></div><div class="d-flex gap-2"><button class="btn btn-outline-secondary refresh-button" type="button" id="refreshResourceButton"><i class="bi bi-arrow-clockwise"></i> Actualiser</button><button class="btn btn-primary action-button" type="button" id="createResourceButton"><i class="bi bi-plus-lg"></i> Ajouter</button></div></div>
                    <div class="admin-feedback" id="resourceFeedback" role="status" hidden></div>
                    <div class="table-responsive"><table class="table align-middle data-table"><thead id="resourceTableHead"></thead><tbody id="resourceTableBody"></tbody></table></div>
                </article>
                <?php if ($pageUser['is_super_admin']): ?>
                <section id="zoneAdminPanel" class="admin-panel" hidden>
                    <article class="content-card admin-form-card">
                        <div class="card-heading"><div><h2>Créer une Zone de Santé</h2><p>Une zone désactivée bloque la connexion des comptes qui lui sont rattachés.</p></div></div>
                        <form id="zoneForm" class="admin-inline-form zone-inline-form">
                            <label>Nom de la zone<input class="form-control" name="nom" maxlength="120" required placeholder="Ex. Beni"></label>
                            <label>Code<input class="form-control" name="code" maxlength="30" required placeholder="Ex. BENI"></label>
                            <label>Province<input class="form-control" name="province" maxlength="120" placeholder="Province"></label>
                            <label data-zone-admin-field>Nom de l’Admin de Zone<input class="form-control" name="admin_nom" maxlength="120" required></label>
                            <label data-zone-admin-field>E-mail de l’Admin<input class="form-control" name="admin_email" type="email" maxlength="190" required></label>
                            <label data-zone-admin-field>Mot de passe initial<input class="form-control" name="admin_password" type="password" minlength="4" required autocomplete="new-password"></label>
                            <button class="btn btn-primary action-button" type="submit"><i class="bi bi-plus-lg"></i> Créer la zone et son admin</button>
                        </form>
                        <div class="admin-feedback" id="zoneFeedback" role="status" hidden></div>
                    </article>
                    <article class="content-card admin-table-card">
                        <div class="card-heading"><div><h2>Zones de Santé</h2><p id="zoneCount">Chargement…</p></div><button class="btn btn-outline-secondary refresh-button" type="button" data-refresh="zones"><i class="bi bi-arrow-clockwise"></i> Actualiser</button></div>
                        <div class="table-responsive"><table class="table align-middle data-table"><thead><tr><th>ZONE</th><th>CODE</th><th>PROVINCE</th><th>AIRES</th><th>ESS</th><th>STATUT</th><th>ACTION</th></tr></thead><tbody id="zonesTable"></tbody></table></div>
                    </article>
                </section>
                <?php endif; ?>
                <?php if ($pageUser['is_super_admin'] || in_array($pageUser['role'], ['admin_zone', 'chef_pilier'], true)): ?>
                <section id="userAdminPanel" class="admin-panel" hidden>
                    <article class="content-card admin-form-card">
                        <div class="card-heading"><div><h2>Créer un compte utilisateur de la zone</h2><p><?= $pageUser['role'] === 'chef_pilier' ? 'Créez les comptes des Superviseurs PCI et Responsables EDS de votre zone.' : 'Les comptes sont automatiquement rattachés à votre Zone de Santé.' ?></p></div></div>
                        <form id="userForm" class="admin-inline-form user-inline-form">
                            <label>Nom complet<input class="form-control" name="nom" maxlength="120" required></label>
                            <label>Adresse e-mail<input class="form-control" name="email" type="email" maxlength="190" required></label>
                            <label>Mot de passe provisoire (laisser vide pour le conserver)<input class="form-control" name="password" type="password" minlength="4" required autocomplete="new-password"></label>
                            <label>Rôle<select class="form-select" name="role" required>
                                <?php if ($pageUser['role'] !== 'chef_pilier'): ?><option value="chef_pilier">Chef PCI/EDS</option><?php endif; ?>
                                <option value="superviseur_pci">Superviseur PCI</option>
                                <option value="responsable_eds">Responsable EDS</option>
                                <?php if ($pageUser['role'] !== 'chef_pilier'): ?><option value="surveillant_epidemiologique">Surveillant épidémiologique</option><?php endif; ?>
                            </select></label>
                            <?php if ($pageUser['is_super_admin']): ?>
                            <label>Zone de Santé<select class="form-select" name="zone_id" required id="userZoneSelect"></select></label>
                            <?php else: ?>
                            <input type="hidden" name="zone_id" value="<?= (int) $pageUser['zone_id'] ?>">
                            <?php endif; ?>
                            <button class="btn btn-primary action-button" type="submit"><i class="bi bi-person-plus"></i> Créer le compte</button>
                        </form>
                        <div class="admin-feedback" id="userFeedback" role="status" hidden></div>
                    </article>
                    <article class="content-card admin-table-card">
                        <div class="card-heading"><div><h2><?= $pageUser['is_super_admin'] ? 'Comptes des zones' : ($pageUser['role'] === 'chef_pilier' ? 'Comptes PCI et EDS de ma Zone de Santé' : 'Utilisateurs de ma Zone de Santé') ?></h2><p id="userCount">Chargement…</p></div><button class="btn btn-outline-secondary refresh-button" type="button" data-refresh="users"><i class="bi bi-arrow-clockwise"></i> Actualiser</button></div>
                        <div class="table-responsive"><table class="table align-middle data-table"><thead><tr><th>NOM</th><th>E-MAIL</th><th>RÔLE</th><th>ZONE</th><th>STATUT</th><th>ACTION</th></tr></thead><tbody id="usersTable"></tbody></table></div>
                    </article>
                </section>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>

<div class="modal fade" id="resourceModal" tabindex="-1" aria-labelledby="resourceModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content quick-modal">
        <div class="modal-header"><div><div class="eyebrow">GESTION DES DONNÉES</div><h2 class="modal-title" id="resourceModalTitle">Nouvel enregistrement</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
        <div class="modal-body"><form id="resourceForm"><div class="resource-form-grid" id="resourceFormFields"></div><div class="d-flex justify-content-end gap-2 mt-4"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary action-button" type="submit">Enregistrer</button></div></form></div>
    </div></div>
</div>
<div class="modal fade" id="quickActionModal" tabindex="-1" aria-labelledby="quickActionTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content quick-modal">
        <div class="modal-header"><div><div class="eyebrow">ACTION RAPIDE</div><h2 class="modal-title" id="quickActionTitle">Nouvelle saisie</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
        <div class="modal-body"><p class="modal-intro">Choisissez le type d’information à enregistrer.</p>
            <div class="quick-actions">
                <?php if (in_array($pageUser['role'], ['super_admin', 'admin_zone', 'surveillant_epidemiologique'], true)): ?>
                <a href="#cases" data-view="cases" data-create-resource="cases"><span class="quick-icon quick-case"><i class="bi bi-clipboard2-pulse"></i></span><span><strong>Cas confirmé</strong><small>Enregistrer un nouveau cas MVE</small></span><i class="bi bi-chevron-right"></i></a>
                <?php endif; ?>
                <?php if (in_array($pageUser['role'], ['super_admin', 'admin_zone', 'chef_pilier', 'superviseur_pci', 'surveillant_epidemiologique'], true)): ?>
                <a href="#rings" data-view="rings" data-create-resource="rings"><span class="quick-icon quick-ring"><i class="bi bi-bullseye"></i></span><span><strong>Ring</strong><small>Créer un ring lié à un cas</small></span><i class="bi bi-chevron-right"></i></a>
                <?php endif; ?>
                <?php if (in_array($pageUser['role'], ['super_admin', 'admin_zone', 'chef_pilier'], true)): ?>
                <a href="#supervisions" data-view="supervisions" data-bs-dismiss="modal"><span class="quick-icon quick-supervision"><i class="bi bi-calendar2-check"></i></span><span><strong>Planifier une mission PCI</strong><small>Choisir l’ESS, l’activité, la date et le Superviseur PCI responsable</small></span><i class="bi bi-chevron-right"></i></a>
                <?php endif; ?>
                <?php if (in_array($pageUser['role'], ['super_admin', 'admin_zone', 'responsable_eds'], true)): ?>
                <a href="#deaths" data-view="deaths" data-bs-dismiss="modal"><span class="quick-icon quick-death"><i class="bi bi-heartbreak"></i></span><span><strong>Décès / EDS</strong><small>Enregistrer un décès et son enterrement</small></span><i class="bi bi-chevron-right"></i></a>
                <?php endif; ?>
                <?php if (in_array($pageUser['role'], ['super_admin', 'admin_zone', 'chef_pilier', 'superviseur_pci', 'responsable_eds', 'surveillant_epidemiologique'], true)): ?>
                <a href="#activities" data-view="activities" data-bs-dismiss="modal"><span class="quick-icon quick-supervision"><i class="bi bi-activity"></i></span><span><strong>Activité de riposte</strong><small>Planifier ou documenter une activité de pilier</small></span><i class="bi bi-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </div></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.4/dist/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pptxgenjs@3.12.0/dist/pptxgen.bundle.js"></script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>
