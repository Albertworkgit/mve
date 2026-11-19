document.addEventListener('DOMContentLoaded', async () => {
    const api = 'backend/api.php';
    const sidebar = document.getElementById('sidebar');
    const moduleView = document.getElementById('view-module');
    const dashboardView = document.getElementById('view-dashboard');
    const zoneAdminPanel = document.getElementById('zoneAdminPanel');
    const userAdminPanel = document.getElementById('userAdminPanel');
    const genericModule = document.getElementById('genericModule');
    const moduleTitle = document.getElementById('moduleTitle');
    const moduleDescription = document.getElementById('moduleDescription');
    const breadcrumbCurrent = document.getElementById('breadcrumbCurrent');
    let csrfToken = '';
    let user = null;
    let casesChart = null;
    let ringChart = null;
    let zones = [];
    let areas = [];
    let resourceRows = [];
    let activeResource = null;
    let editingResourceId = null;
    let editingZoneId = null;
    let editingZoneActive = true;
    let editingUserId = null;
    let notificationsList = [];

    const modules = {
        cases: ['Cas confirmés', 'Registre et suivi des cas confirmés de maladie à virus Ebola.'],
        rings: ['Rings', 'Suivi des ceintures d’intervention associées aux cas confirmés.'],
        supervisions: ['Missions PCI et scorecard des ESS', 'Le Chef PCI/EDS planifie une activité dans un ESS et l’assigne à un Superviseur PCI, qui complète le compte rendu et le scorecard.'],
        deaths: ['Décès & EDS', 'Enregistrement des décès et suivi des enterrements dignes et sécurisés.'],
        activities: ['Activités de riposte', 'Planifiez et suivez les activités EDS, PCI, surveillance et autres piliers.'],
        geography: ['Zones de Santé', 'Créez les Zones de Santé et gérez leur état d’activation.'],
        aires: ['Aires de Santé', 'Gérez les Aires de Santé rattachées à chaque zone.'],
        ess: ['Établissements de Santé', 'Gérez les établissements rattachés à une Aire de Santé.'],
        users: ['Utilisateurs', 'Gérez les comptes opérationnels autorisés de votre Zone de Santé.']
    };
    const resourceDefinitions = {
        aires: {
            title: 'Aires de Santé',
            route: 'aires',
            columns: [['nom', 'Nom'], ['code', 'Code'], ['zone_nom', 'Zone'], ['active', 'Statut']],
            fields: [['zone_id', 'Zone de Santé', 'select:zones', true], ['nom', 'Nom', 'text', true], ['code', 'Code', 'text', true], ['active', 'Active', 'checkbox', false]]
        },
        ess: {
            title: 'Établissements de Santé',
            route: 'ess',
            columns: [['nom', 'Établissement'], ['aire_nom', 'Aire'], ['zone_nom', 'Zone'], ['type', 'Type'], ['active', 'Statut']],
            fields: [['aire_id', 'Aire de Santé', 'select:aires', true], ['nom', 'Nom', 'text', true], ['code', 'Code', 'text', false], ['type', 'Type', 'text', false], ['adresse', 'Adresse', 'text', false], ['telephone', 'Téléphone', 'text', false], ['active', 'Actif', 'checkbox', false]]
        },
        cases: {
            title: 'Cas confirmés',
            route: 'cases',
            columns: [['code_epidemiologique', 'Code épidémiologique'], ['zone_nom', 'Zone'], ['aire_nom', 'Aire'], ['origine', 'Provenance'], ['ppl_infecte', 'PPL infecté'], ['date_confirmation', 'Confirmation'], ['statut', 'Statut']],
            fields: [['zone_id', 'Zone de Santé', 'select:zones', true], ['aire_id', 'Aire de Santé', 'select:aires', true], ['origine', 'Provenance du cas', 'enum:ess|Établissement de Santé,communaute|Communauté', true], ['ess_origine_id', 'Établissement de Santé', 'select:ess', false, 'origine:ess'], ['adresse_communaute', 'Adresse du cas dans la communauté', 'text', false, 'origine:communaute'], ['code_epidemiologique', 'Code épidémiologique', 'text', true], ['nom_initiales', 'Nom / initiales', 'text', false], ['age', 'Âge', 'number', false], ['sexe', 'Sexe', 'enum:F|Femme,M|Homme,inconnu|Inconnu', true], ['ppl_infecte', 'Le cas est-il un Prestataire de Première Ligne (PPL) infecté ?', 'radio:0|Non,1|Oui', false], ['date_confirmation', 'Date de confirmation', 'date', true], ['statut', 'Statut', 'enum:en_traitement_cte|En traitement au CTE,gueri|Guéri,decede|Décédé', true]]
        },
        rings: {
            title: 'Rings d’intervention',
            route: 'rings',
            columns: [['code', 'Code'], ['code_epidemiologique', 'Cas lié'], ['ouvert_le', 'Ouvert le'], ['nombre_menages', 'Ménages ciblés'], ['menages_decontamines', 'Ménages décontaminés'], ['nombre_ess', 'ESS ciblées'], ['ess_decontamines', 'ESS décontaminées'], ['nombre_eglises', 'Églises ciblées'], ['eglises_decontaminees', 'Églises décontaminées'], ['noms_eglises', 'Noms des églises'], ['nombre_ecoles', 'Écoles ciblées'], ['ecoles_decontaminees', 'Écoles décontaminées'], ['noms_ecoles', 'Noms des écoles'], ['nombre_marches', 'Marchés ciblés'], ['marches_decontamines', 'Marchés décontaminés'], ['noms_marches', 'Noms des marchés'], ['nombre_parking', 'Parkings ciblés'], ['parking_decontamines', 'Parkings décontaminés'], ['noms_parking', 'Noms des parkings'], ['nombre_autres_lieux', 'Autres lieux ciblés'], ['autres_lieux_decontamines', 'Autres lieux décontaminés'], ['noms_autres_lieux', 'Noms des autres lieux'], ['statut', 'Statut']],
            fields: [
                ['cas_mve_id', 'Cas confirmé associé', 'select:cases', true],
                ['code', 'Code du Ring', 'text', true],
                ['date_debut', 'Date d’ouverture', 'date', true],
                ['nombre_menages', 'Nombre de ménages ciblés', 'number', false],
                ['menages_decontamines', 'Nombre de ménages décontaminés', 'number', false],
                ['nombre_ess', 'Nombre d’ESS ciblées', 'number', false],
                ['ess_decontamines', 'Nombre d’ESS décontaminées', 'number', false],
                ['nombre_eglises', 'Nombre d’églises ciblées', 'number', false],
                ['eglises_decontaminees', 'Nombre d’églises décontaminées', 'number', false],
                ['noms_eglises', 'Noms des églises ciblées', 'textarea', false],
                ['nombre_ecoles', 'Nombre d’écoles ciblées', 'number', false],
                ['ecoles_decontaminees', 'Nombre d’écoles décontaminées', 'number', false],
                ['noms_ecoles', 'Noms des écoles ciblées', 'textarea', false],
                ['nombre_marches', 'Nombre de marchés ciblés', 'number', false],
                ['marches_decontamines', 'Nombre de marchés décontaminés', 'number', false],
                ['noms_marches', 'Noms des marchés ciblés', 'textarea', false],
                ['nombre_parking', 'Nombre de parkings ciblés', 'number', false],
                ['parking_decontamines', 'Nombre de parkings décontaminés', 'number', false],
                ['noms_parking', 'Noms des parkings ciblés', 'textarea', false],
                ['nombre_autres_lieux', 'Nombre d’autres lieux publics ciblés', 'number', false],
                ['autres_lieux_decontamines', 'Nombre d’autres lieux publics décontaminés', 'number', false],
                ['noms_autres_lieux', 'Noms des autres lieux publics ciblés', 'textarea', false],
                ['statut_decontamination', 'État de décontamination', 'enum:non_demarre|Non démarrée,en_cours|En cours,termine|Terminée', true],
                ['statut_vaccination', 'État de vaccination', 'enum:non_demarre|Non démarrée,en_cours|En cours,termine|Terminée', true],
                ['statut_sensibilisation', 'État de sensibilisation', 'enum:non_demarre|Non démarrée,en_cours|En cours,termine|Terminée', true]
            ]
        },
        deaths: {
            title: 'Décès & EDS',
            route: 'deaths',
            columns: [['date_deces', 'Date du décès'], ['aire_nom', 'Aire'], ['ess_nom', 'CTE / ESS'], ['lieu', 'Lieu'], ['type_deces', 'Classification'], ['statut_eds', 'Statut EDS'], ['date_eds', 'Date EDS']],
            fields: [['aire_id', 'Aire de Santé', 'select:aires', true], ['cas_mve_id', 'Cas confirmé (facultatif)', 'select:cases', false], ['date_deces', 'Date du décès', 'date', true], ['lieu', 'Lieu du décès', 'enum:communaute|Communauté,cte_ess|CTE / ESS', true], ['type_deces', 'Classification du décès', 'enum:cte|Décès en CTE,swab_positif|Décès avec swab positif', true], ['ess_id', 'Établissement (si décès au CTE / ESS)', 'select:ess', false, 'lieu:cte_ess'], ['statut_eds', 'Statut EDS', 'enum:a_realiser|À réaliser,realise|Réalisé', true], ['date_eds', 'Date EDS (si réalisé)', 'date', false, 'statut_eds:realise'], ['commentaires', 'Commentaires', 'textarea', false]]
        },
        supervisions: {
            title: 'Missions PCI et scorecard des ESS',
            route: 'supervisions',
            columns: [['activite_planifiee', 'Activité PCI à réaliser'], ['description_activite', 'Consignes'], ['ess_nom', 'ESS'], ['date_planifiee', 'Date prévue'], ['date_visite', 'Date de visite'], ['superviseur_nom', 'Superviseur PCI'], ['nombre_prestataires_briefes', 'Prestataires briefés'], ['liste_presence_photo', 'Liste de présence'], ['points_forts', 'Points forts'], ['points_saillants', 'Points saillants'], ['points_a_ameliorer', 'Points faibles / à améliorer'], ['recommandations', 'Recommandations'], ['action_correctrice', 'Actions correctrices'], ['scorecard_pourcentage', 'Scorecard'], ['statut_visite', 'État de la visite'], ['statut_suivi', 'État du suivi']],
            fields: []
        },
        activities: {
            title: 'Activités de riposte',
            route: 'activities',
            columns: [['date_activite', 'Date'], ['pilier', 'Pilier'], ['titre', 'Activité'], ['aire_nom', 'Aire'], ['ess_nom', 'ESS'], ['realise', 'Réalisé'], ['statut', 'Statut']],
            fields: [
                ['zone_id', 'Zone de Santé', 'select:zones', true],
                ['aire_id', 'Aire de Santé', 'select:aires', false],
                ['ess_id', 'Établissement de Santé', 'select:ess', false],
                ['pilier', 'Pilier', 'enum:eds|EDS – Enterrements dignes et sécurisés,pci|PCI – Prévention et contrôle,surveillance|Surveillance épidémiologique,vaccination|Vaccination,sensibilisation|Sensibilisation,logistique|Logistique,coordination|Coordination,autre|Autre', true],
                ['cas_mve_id', 'Cas enregistré par la Surveillance', 'select:cases', true, 'pilier:pci'],
                ['ring_id', 'Ring ouvert associé', 'select:rings', true, 'pilier:pci'],
                ['titre', 'Intitulé de l’activité', 'text', true],
                ['date_activite', 'Date de l’activité', 'date', true],
                ['description', 'Description', 'textarea', false],
                ['indicateur', 'Indicateur suivi', 'text', false],
                ['cible', 'Cible', 'number', false],
                ['realise', 'Réalisé', 'number', false],
                ['menages_decontamines', 'Nombre de ménages décontaminés', 'number', false, 'pilier:pci'],
                ['ess_decontamines', 'Nombre d’ESS décontaminés', 'number', false, 'pilier:pci'],
                ['actions_sur_ring', 'Actions réalisées sur le Ring', 'textarea', false, 'pilier:pci'],
                ['statut', 'État', 'enum:planifiee|Planifiée,en_cours|En cours,terminee|Terminée,bloquee|Bloquée', true],
                ['difficultes', 'Difficultés rencontrées', 'textarea', false],
                ['actions_correctives', 'Actions correctives / prochaines étapes', 'textarea', false]
            ]
        }
    };

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    })[character]);

    const request = async (path, options = {}) => {
        const [route, queryString = ''] = path.split('?');
        const headers = { Accept: 'application/json', ...(options.headers || {}) };
        if (options.body !== undefined) {
            headers['Content-Type'] = 'application/json';
            headers['X-CSRF-Token'] = csrfToken;
        }
        const response = await fetch(`${api}?path=${encodeURIComponent(route)}${queryString ? `&${queryString}` : ''}`, {
            credentials: 'same-origin',
            ...options,
            headers
        });
        const result = await response.json();
        if (!response.ok) {
            if (response.status === 401) window.location.assign('login.php');
            throw new Error(result.error || 'Erreur de communication avec le serveur.');
        }
        return result;
    };

    const showFeedback = (id, message, isError = false) => {
        const element = document.getElementById(id);
        if (!element) return;
        element.textContent = message;
        element.classList.toggle('is-error', isError);
        element.hidden = false;
    };

    const loadNotifications = async () => {
        const list = document.getElementById('notificationList');
        const badge = document.getElementById('notificationCount');
        if (!list || !badge || user?.role !== 'superviseur_pci') return;
        try {
            const result = await request('notifications');
            notificationsList = result.data;
            badge.textContent = notificationsList.length > 99 ? '99+' : String(notificationsList.length);
            badge.hidden = notificationsList.length === 0;
            list.innerHTML = notificationsList.map((item) => {
                const title = `Nouveau cas ${escapeHtml(item.code_epidemiologique)}`;
                const detail = item.ring_id
                    ? `Ring ${escapeHtml(item.ring_code)} · ${escapeHtml(item.aire_nom)} — enregistrer les actions PCI`
                    : `${escapeHtml(item.aire_nom)} — en attente de l’ouverture du Ring par la Surveillance`;
                return `<button class="notification-item" type="button" data-notification-case="${item.cas_mve_id}">
                    <strong>${title}</strong><span>${detail}</span>
                </button>`;
            }).join('') || '<p class="notification-empty">Aucun nouveau cas à prendre en charge.</p>';
        } catch (error) {
            list.innerHTML = `<p class="notification-empty">${escapeHtml(error.message)}</p>`;
        }
    };

    const setZoneFormMode = (editing) => {
        const form = document.getElementById('zoneForm');
        form.querySelectorAll('[data-zone-admin-field]').forEach((field) => {
            field.hidden = editing;
            const input = field.querySelector('input');
            input.required = !editing;
            if (editing) input.value = '';
        });
        form.querySelector('button[type="submit"]').innerHTML = editing
            ? '<i class="bi bi-check-lg"></i> Enregistrer les modifications'
            : '<i class="bi bi-plus-lg"></i> Créer la zone et son admin';
        document.querySelector('#zoneAdminPanel h2').textContent = editing ? 'Modifier une Zone de Santé' : 'Créer une Zone de Santé';
    };

    const setActiveView = (view) => {
        document.querySelectorAll('.side-nav .nav-link').forEach((item) => {
            item.classList.toggle('active', item.dataset.view === view);
        });
        const isDashboard = view === 'dashboard';
        dashboardView.hidden = !isDashboard;
        dashboardView.classList.toggle('active', isDashboard);
        moduleView.hidden = isDashboard;
        moduleView.classList.toggle('active', !isDashboard);
        if (!isDashboard && modules[view]) {
            moduleTitle.textContent = modules[view][0];
            moduleDescription.textContent = modules[view][1];
            breadcrumbCurrent.textContent = modules[view][0];
        } else {
            breadcrumbCurrent.textContent = 'Tableau de bord';
        }
        if (genericModule) genericModule.hidden = view === 'geography' || view === 'users' || Boolean(resourceDefinitions[view]);
        const resourcePanel = document.getElementById('resourceCrudPanel');
        if (resourcePanel) resourcePanel.hidden = !resourceDefinitions[view];
        if (zoneAdminPanel) zoneAdminPanel.hidden = view !== 'geography';
        if (userAdminPanel) userAdminPanel.hidden = view !== 'users' || !['super_admin', 'admin_zone', 'chef_pilier'].includes(user?.role);
        sidebar.classList.remove('open');
    };

    document.addEventListener('click', async (event) => {
        const link = event.target.closest('[data-view]');
        if (!link) return;
        const view = link.dataset.view;
        if (!view) return;
        event.preventDefault();
        const createResource = link.dataset.createResource;
        if (createResource && createResource === view && resourceDefinitions[createResource]) {
            const quickActionModal = document.getElementById('quickActionModal');
            quickActionModal.addEventListener('hidden.bs.modal', async () => {
                setActiveView(createResource);
                await loadResource(createResource);
                if (canCreateResource(createResource)) await openResourceForm();
            }, { once: true });
            bootstrap.Modal.getOrCreateInstance(quickActionModal).hide();
            return;
        }
        setActiveView(view);
        if (view === 'geography') loadZones();
        if (view === 'users') loadUsers();
        if (resourceDefinitions[view]) loadResource(view);
    });

    document.getElementById('menuToggle').addEventListener('click', () => sidebar.classList.toggle('open'));
    document.getElementById('notificationButton')?.addEventListener('click', async () => {
        const panel = document.getElementById('notificationPanel');
        panel.hidden = !panel.hidden;
        document.getElementById('notificationButton').setAttribute('aria-expanded', String(!panel.hidden));
        if (!panel.hidden) await loadNotifications();
    });

    const fillZones = (allZones) => {
        const activeZones = allZones.filter((zone) => Number(zone.active) === 1 || zone.active === true);
        const userSelect = document.getElementById('userZoneSelect');
        if (userSelect) {
            userSelect.innerHTML = activeZones.map((zone) => `<option value="${zone.id}">${escapeHtml(zone.nom)}</option>`).join('');
        }
        const dashboardFilter = document.getElementById('zoneFilter');
        if (dashboardFilter) {
            const selected = dashboardFilter.value;
            dashboardFilter.innerHTML = `<option value="all">Toutes les zones</option>${allZones.map((zone) => {
                const active = Number(zone.active) === 1 || zone.active === true;
                return `<option value="${zone.id}">${escapeHtml(zone.nom)}${active ? '' : ' (désactivée)'}</option>`;
            }).join('')}`;
            if ([...dashboardFilter.options].some((option) => option.value === selected)) dashboardFilter.value = selected;
        }
    };

    const loadZones = async () => {
        try {
            const result = await request('zones?limit=200');
            zones = result.data;
            const [areasResult, facilitiesResult] = await Promise.all([
                request('aires?limit=200'),
                request('ess?limit=200')
            ]);
            areas = areasResult.data;
            fillZones(zones);
            const rows = zones.map((zone) => {
                const areaCount = areasResult.data.filter((area) => Number(area.zone_id) === Number(zone.id)).length;
                const facilityCount = facilitiesResult.data.filter((facility) => Number(facility.zone_id) === Number(zone.id)).length;
                const active = Number(zone.active) === 1 || zone.active === true;
                return `<tr>
                    <td><strong class="case-code">${escapeHtml(zone.nom)}</strong></td>
                    <td>${escapeHtml(zone.code)}</td>
                    <td>${escapeHtml(zone.province || '—')}</td>
                    <td>${areaCount}</td>
                    <td>${facilityCount}</td>
                    <td><span class="status-badge ${active ? 'status-recovered' : 'status-deceased'}"><i></i>${active ? 'Active' : 'Désactivée'}</span></td>
                    <td><div class="crud-actions"><button class="btn btn-sm btn-outline-primary" type="button" data-edit-zone="${zone.id}">Modifier</button><button class="btn btn-sm ${active ? 'btn-outline-danger' : 'btn-outline-success'} toggle-button" type="button" data-toggle-zone="${zone.id}" data-active="${active ? '1' : '0'}">${active ? 'Désactiver' : 'Activer'}</button><button class="btn btn-sm btn-outline-danger" type="button" data-delete-zone="${zone.id}">Supprimer</button></div></td>
                </tr>`;
            });
            document.getElementById('zonesTable').innerHTML = rows.join('') || '<tr><td colspan="7" class="empty-row">Aucune Zone de Santé enregistrée.</td></tr>';
            document.getElementById('zoneCount').textContent = `${zones.length} zone${zones.length > 1 ? 's' : ''} enregistrée${zones.length > 1 ? 's' : ''}`;
        } catch (error) {
            showFeedback('zoneFeedback', error.message, true);
        }
    };

    const loadUsers = async () => {
        try {
            const result = await request('users?limit=200');
            const rows = result.data.map((account) => {
                const active = Number(account.active) === 1 || account.active === true;
                const role = {
                    admin_zone: 'Admin de Zone',
                    chef_pilier: 'Chef PCI/EDS',
                    superviseur_pci: 'Superviseur PCI',
                    responsable_eds: 'Responsable EDS',
                    surveillant_epidemiologique: 'Surveillant épidémiologique',
                    super_admin: 'Super Admin'
                }[account.role] || account.role;
                const action = ['super_admin', 'admin_zone'].includes(account.role)
                    ? '<span class="muted-action">Compte protégé</span>'
                    : `<div class="crud-actions"><button class="btn btn-sm btn-outline-primary" type="button" data-edit-user="${account.id}">Modifier</button><button class="btn btn-sm ${active ? 'btn-outline-danger' : 'btn-outline-success'} toggle-button" type="button" data-toggle-user="${account.id}" data-active="${active ? '1' : '0'}">${active ? 'Désactiver' : 'Activer'}</button><button class="btn btn-sm btn-outline-danger" type="button" data-delete-user="${account.id}">Supprimer</button></div>`;
                return `<tr>
                    <td><strong class="case-code">${escapeHtml(account.nom)}</strong></td>
                    <td>${escapeHtml(account.email)}</td><td>${role}</td><td>${escapeHtml(account.zone_nom || '—')}</td>
                    <td><span class="status-badge ${active ? 'status-recovered' : 'status-deceased'}"><i></i>${active ? 'Actif' : 'Désactivé'}</span></td>
                    <td>${action}</td>
                </tr>`;
            });
            document.getElementById('usersTable').innerHTML = rows.join('') || '<tr><td colspan="6" class="empty-row">Aucun compte enregistré.</td></tr>';
            document.getElementById('userCount').textContent = `${result.data.length} compte${result.data.length > 1 ? 's' : ''}`;
        } catch (error) {
            showFeedback('userFeedback', error.message, true);
        }
    };

    const canManageResource = (resource) => {
        const allowed = {
            aires: ['super_admin', 'admin_zone'],
            ess: ['super_admin', 'admin_zone'],
            cases: ['super_admin', 'admin_zone', 'surveillant_epidemiologique'],
            rings: ['super_admin', 'admin_zone', 'chef_pilier', 'superviseur_pci', 'surveillant_epidemiologique'],
            deaths: ['super_admin', 'admin_zone', 'responsable_eds'],
            supervisions: ['super_admin', 'admin_zone', 'chef_pilier', 'superviseur_pci'],
            activities: ['super_admin', 'admin_zone', 'chef_pilier', 'superviseur_pci', 'responsable_eds', 'surveillant_epidemiologique']
        };
        if (resource === 'activities' && ['admin_zone', 'chef_pilier', 'super_admin'].includes(user?.role)) return true;
        if (resource === 'activities') return ['superviseur_pci', 'responsable_eds', 'surveillant_epidemiologique'].includes(user?.role);
        return (allowed[resource] || []).includes(user?.role);
    };

    const canCreateResource = (resource) => (
        canManageResource(resource)
        && !(resource === 'supervisions' && user?.role === 'superviseur_pci')
    );

    const fieldsForResource = (resource) => {
        if (resource !== 'supervisions') return resourceDefinitions[resource].fields;
        if (['super_admin', 'admin_zone', 'chef_pilier'].includes(user?.role)) {
            return [
                ['ess_id', 'Établissement de Santé', 'select:ess', true],
                ['superviseur_id', 'Superviseur PCI assigné', 'select:pci-supervisors', true],
                ['activite_planifiee', 'Activité PCI à réaliser', 'text', true],
                ['description_activite', 'Consignes de la planification', 'textarea', false],
                ['date_planifiee', 'Date prévue de visite', 'date', true]
            ];
        }
        return [
            ['date_visite', 'Date effective de visite', 'date', true],
            ['nombre_prestataires_briefes', 'Nombre de prestataires briefés', 'number', false],
            ['liste_presence_photo', 'Photo de la liste de présence écrite', 'file:image', false],
            ['points_forts', 'Points forts', 'textarea', true],
            ['points_saillants', 'Points saillants', 'textarea', true],
            ['points_a_ameliorer', 'Points faibles / à améliorer', 'textarea', true],
            ['recommandations', 'Recommandations', 'textarea', true],
            ['action_correctrice', 'Actions correctrices', 'textarea', true],
            ['scorecard_pourcentage', 'Scorecard de l’ESS (%) — facultatif', 'number', false],
            ['statut_suivi', 'Statut de suivi', 'enum:en_cours|En cours,resolu|Résolu', true],
            ['date_resolution', 'Date de résolution du suivi', 'date', false, 'statut_suivi:resolu']
        ];
    };

    const canManageActivityPillar = (pillar) => {
        if (user?.role === 'super_admin') return true;
        if (user?.role === 'admin_zone') return true;
        if (user?.role === 'chef_pilier') return pillar !== 'pci';
        return {
            superviseur_pci: ['pci', 'sensibilisation'],
            responsable_eds: ['eds'],
            surveillant_epidemiologique: ['surveillance']
        }[user?.role]?.includes(pillar) || false;
    };

    const loadResourceChoices = async (resource) => {
        const endpoints = new Set(fieldsForResource(resource)
            .filter((field) => !(field[4] === 'pilier:pci' && !['super_admin', 'admin_zone', 'superviseur_pci'].includes(user.role)))
            .map((field) => field[2])
            .filter((type) => type.startsWith('select:'))
            .map((type) => type.slice(7)));
        if (resource === 'rings') endpoints.add('rings');
        const results = await Promise.all([...endpoints].map(async (endpoint) => {
            const result = await request(`${endpoint}?limit=200`);
            return [endpoint, result.data];
        }));
        return Object.fromEntries(results);
    };

    const formatResourceValue = (key, value) => {
        const labels = {
            active: Number(value) === 1 || value === true ? 'Actif' : 'Désactivé',
            statut: {
                en_traitement_cte: 'En traitement', gueri: 'Guéri', decede: 'Décédé',
                actif: 'Actif', termine: 'Terminé',
                planifiee: 'Planifiée', en_cours: 'En cours', terminee: 'Terminée', bloquee: 'Bloquée'
            },
            statut_suivi: { en_cours: 'En cours', resolu: 'Résolu' },
            statut_visite: { planifiee: 'Planifiée', realisee: 'Réalisée' },
            statut_eds: { a_realiser: 'À réaliser', realise: 'Réalisé' },
            pilier: { eds: 'EDS', pci: 'PCI', surveillance: 'Surveillance', vaccination: 'Vaccination', sensibilisation: 'Sensibilisation', logistique: 'Logistique', coordination: 'Coordination', autre: 'Autre' },
            lieu: { communaute: 'Communauté', cte_ess: 'CTE / ESS' },
            type_deces: { cte: 'Décès en CTE', swab_positif: 'Swab positif' },
            origine: { ess: 'ESS', communaute: 'Communauté' }
        };
        if (key === 'ppl_infecte') {
            return Number(value) === 1 || value === true ? 'Oui' : 'Non';
        }
        if (key === 'liste_presence_photo' && value) {
            return `<a class="text-link" href="${escapeHtml(value)}" target="_blank" rel="noopener">Voir la photo</a>`;
        }
        if (key === 'scorecard_pourcentage' && value !== null && value !== undefined && value !== '') {
            return `${Number(value).toLocaleString('fr-FR')} %`;
        }
        return labels[key]?.[value] || (key === 'active' ? labels.active : (value ?? '—'));
    };

    const renderResourceTable = (resource, rows) => {
        const definition = resourceDefinitions[resource];
        const canWrite = canManageResource(resource);
        document.getElementById('resourceTableTitle').textContent = definition.title;
        document.getElementById('resourceTableSubtitle').textContent = resource === 'supervisions' && user.role === 'superviseur_pci'
            ? 'Vos missions PCI assignées par le Chef PCI/EDS sont listées ici. Complétez le compte rendu et le scorecard de l’ESS après chaque visite.'
            : canWrite
            ? 'Créer, consulter, modifier et supprimer les enregistrements. Les suppressions sont archivées.'
            : 'Rapport en lecture seule pour le périmètre autorisé.';
        document.getElementById('createResourceButton').hidden = !canCreateResource(resource);
        document.getElementById('resourceTableHead').innerHTML = `<tr>${definition.columns.map((column) => `<th>${escapeHtml(column[1].toUpperCase())}</th>`).join('')}${canWrite ? '<th>ACTIONS</th>' : ''}</tr>`;
        document.getElementById('resourceTableBody').innerHTML = rows.map((row) => {
            const cells = definition.columns.map(([key]) => {
                if (resource === 'supervisions' && key === 'liste_presence_photo' && row[key]) {
                    return `<td><a class="text-link" href="${escapeHtml(row[key])}" target="_blank" rel="noopener">Voir la photo</a></td>`;
                }
                if (resource === 'rings' && ['noms_eglises', 'noms_ecoles', 'noms_marches', 'noms_parking', 'noms_autres_lieux'].includes(key)) {
                    const names = String(row[key] || '').split(';').map((item) => item.trim()).filter(Boolean);
                    return `<td>${names.length ? names.map(escapeHtml).join('<br>') : '—'}</td>`;
                }
                if (resource === 'supervisions' && ['points_forts', 'points_saillants', 'points_a_ameliorer', 'recommandations', 'action_correctrice'].includes(key)) {
                    const rawValue = key === 'points_a_ameliorer' ? (row[key] || row.points_faibles) : row[key];
                    const values = String(rawValue || '').split(';').map((item) => item.trim()).filter(Boolean);
                    return `<td>${values.length ? values.map(escapeHtml).join('<br>') : '—'}</td>`;
                }
                return `<td>${escapeHtml(formatResourceValue(key, row[key]))}</td>`;
            }).join('');
            const rowCanWrite = canWrite && (resource !== 'activities' || canManageActivityPillar(row.pilier))
                && (resource !== 'supervisions' || (['super_admin', 'admin_zone', 'chef_pilier'].includes(user.role)
                    ? row.statut_visite === 'planifiee'
                    : user.role === 'superviseur_pci' && Number(row.superviseur_id) === Number(user.id)));
            const closeRingAction = resource === 'rings' && row.statut === 'actif'
                && ['super_admin', 'admin_zone', 'chef_pilier', 'superviseur_pci'].includes(user.role);
            const actions = closeRingAction
                ? `<td><div class="crud-actions">${user.role === 'superviseur_pci' ? '' : `<button class="btn btn-sm btn-outline-primary" type="button" data-edit-resource="${row.id}">Modifier</button><button class="btn btn-sm btn-outline-danger" type="button" data-delete-resource="${row.id}">Supprimer</button>`}<button class="btn btn-sm btn-outline-success" type="button" data-close-ring="${row.id}" ${new Date(String(row.ouvert_le).replace(' ', 'T')).getTime() + 48 * 60 * 60 * 1000 > Date.now() ? 'disabled title="Fermeture autorisée après 48 heures"' : ''}>Fermer le Ring</button></div></td>`
                : rowCanWrite
                ? `<td><div class="crud-actions"><button class="btn btn-sm btn-outline-primary" type="button" data-edit-resource="${row.id}">${resource === 'supervisions' && user.role === 'superviseur_pci' ? (row.statut_visite === 'planifiee' ? 'Renseigner le suivi' : 'Modifier le suivi') : 'Modifier'}</button>${['super_admin', 'admin_zone', 'chef_pilier'].includes(user.role) && resource === 'supervisions' ? `<button class="btn btn-sm btn-outline-danger" type="button" data-delete-resource="${row.id}">Annuler</button>` : resource === 'supervisions' ? '' : `<button class="btn btn-sm btn-outline-danger" type="button" data-delete-resource="${row.id}">Supprimer</button>`}</div></td>`
                : (canWrite ? '<td><span class="muted-action">Lecture seule</span></td>' : '');
            return `<tr>${cells}${actions}</tr>`;
        }).join('') || `<tr><td class="empty-row" colspan="${definition.columns.length + (canWrite ? 1 : 0)}">Aucun enregistrement.</td></tr>`;
    };

    const resourceFieldHtml = (field, value, choices, row, resource) => {
        const [name, label, type, required, condition] = field;
        const requiredAttribute = required ? 'required' : '';
        const valueAttribute = value === null || value === undefined ? '' : ` value="${escapeHtml(value)}"`;
        if (name === 'zone_id' && user?.role === 'admin_zone') {
            const zone = zones.find((item) => Number(item.id) === Number(user.zone_id));
            return `<label class="resource-field">${escapeHtml(label)}<input class="form-control" value="${escapeHtml(zone?.nom || 'Ma Zone de Santé')}" readonly><input type="hidden" name="zone_id" value="${user.zone_id}"></label>`;
        }
        if (type === 'file:image') {
            const existingLink = value
                ? `<a class="text-link" href="${escapeHtml(value)}" target="_blank" rel="noopener">Voir la liste enregistrée</a>`
                : '';
            return `<label class="resource-field">${escapeHtml(label)}<input class="form-control" name="${name}" type="file" accept="image/jpeg,image/png,image/webp" capture="environment">${existingLink}</label>`;
        }
        if (type === 'checkbox') {
            const checked = value === undefined ? true : Number(value) === 1 || value === true;
            return `<label class="resource-field resource-checkbox"><input type="checkbox" name="${name}" ${checked ? 'checked' : ''}><span>${escapeHtml(label)}</span></label>`;
        }
        if (type.startsWith('enum:')) {
            const options = type.slice(5).split(',').map((part) => part.split('|'));
            const control = `<label class="resource-field">${escapeHtml(label)}<select class="form-select" name="${name}" ${requiredAttribute}>${options.map(([option, optionLabel]) => `<option value="${escapeHtml(option)}" ${String(value ?? options[0][0]) === option ? 'selected' : ''}>${escapeHtml(optionLabel)}</option>`).join('')}</select></label>`;
            return condition ? `<div data-condition="${condition}">${control}</div>` : control;
        }
        if (type.startsWith('radio:')) {
            const options = type.slice(6).split(',').map((part) => part.split('|'));
            const selectedValue = String(value ?? options[0][0]);
            const control = `<fieldset class="resource-field resource-radio"><legend>${escapeHtml(label)}</legend><div class="resource-radio-options">${options.map(([option, optionLabel]) => `<label><input type="radio" name="${name}" value="${escapeHtml(option)}" ${selectedValue === option ? 'checked' : ''}><span>${escapeHtml(optionLabel)}</span></label>`).join('')}</div></fieldset>`;
            return condition ? `<div data-condition="${condition}">${control}</div>` : control;
        }
        if (type.startsWith('select:')) {
            const endpoint = type.slice(7);
            let items = choices[endpoint] || [];
            const hasCases = endpoint === 'cases' && items.length > 0;
            if (resource === 'rings' && endpoint === 'cases') {
                const activeRingCounts = (choices.rings || [])
                    .filter((ring) => ring.statut === 'actif')
                    .reduce((counts, ring) => {
                        const caseId = Number(ring.cas_mve_id);
                        counts.set(caseId, (counts.get(caseId) || 0) + 1);
                        return counts;
                    }, new Map());
                items = items.filter((item) => (
                    Number(row?.cas_mve_id) === Number(item.id)
                    || (activeRingCounts.get(Number(item.id)) || 0) < 5
                ));
            }
            if (endpoint === 'pci-supervisors') {
                items = items.filter((item) => user.role === 'super_admin' || Number(item.zone_id) === Number(user.zone_id));
            }
            const optionValue = (item) => item.id;
            const optionLabel = (item) => ['cases', 'rings'].includes(endpoint)
                ? (item.code_epidemiologique || item.code)
                : endpoint === 'ess' && resource === 'cases'
                    ? `${item.nom} — ${item.aire_nom}`
                    : item.nom;
            const isOptional = !required;
            const emptyLabel = resource === 'rings' && endpoint === 'cases' && items.length === 0
                ? (hasCases ? '— Aucun cas disponible : limite de cinq Rings actifs atteinte —' : '— Aucun cas : enregistrez-en d’abord un —')
                : isOptional ? '— Aucun —' : '— Sélectionner —';
            const emptyOption = `<option value="">${emptyLabel}</option>`;
            const selectedValue = value === null || value === undefined ? '' : String(value);
            const control = `<label class="resource-field">${escapeHtml(label)}<select class="form-select" name="${name}" ${requiredAttribute}>${emptyOption}${items.map((item) => `<option value="${optionValue(item)}" ${String(optionValue(item)) === selectedValue ? 'selected' : ''}>${escapeHtml(optionLabel(item))}</option>`).join('')}</select></label>`;
            return condition ? `<div data-condition="${condition}">${control}</div>` : control;
        }
        if (type === 'textarea') {
            const ringNameHint = {
                noms_eglises: 'Exemple : Église Saint-Paul ; Église La Vie',
                noms_ecoles: 'Exemple : EP Tumaini ; EP Tujenge',
                noms_marches: 'Exemple : Marché Central ; Marché Umoja',
                noms_parking: 'Exemple : Parking Central ; Parking Route',
                noms_autres_lieux: 'Exemple : Stade ; Réservoir ; Gare'
            }[name];
            const separatorHint = ringNameHint
                ? `<small class="form-text text-muted">${escapeHtml(ringNameHint)}. Séparez chaque nom avec un point-virgule (;).</small>`
                : ['points_forts', 'points_saillants', 'points_a_ameliorer', 'recommandations', 'action_correctrice'].includes(name)
                ? '<small class="form-text text-muted">Séparez chaque information par un point-virgule (;).</small>'
                : '';
            return `<label class="resource-field resource-field-wide">${escapeHtml(label)}<textarea class="form-control" name="${name}" rows="3" ${requiredAttribute}>${escapeHtml(value || '')}</textarea>${separatorHint}</label>`;
        }
        const maxLength = name.includes('comment') || name.startsWith('points') || name === 'recommandations' ? 10000 : 255;
        const numberLimits = name === 'scorecard_pourcentage' ? 'min="0" max="100" step="0.01"' : (type === 'number' ? 'min="0"' : '');
        return `<label class="resource-field">${escapeHtml(label)}<input class="form-control" name="${name}" type="${type}"${valueAttribute} ${requiredAttribute} ${numberLimits} maxlength="${maxLength}"></label>`;
    };

    const openResourceForm = async (row = null, defaults = {}) => {
        const resource = activeResource;
        const definition = resourceDefinitions[resource];
        if (!definition || !canManageResource(resource)) return;
        try {
            const fields = fieldsForResource(resource);
            const choices = await loadResourceChoices(resource);
            editingResourceId = row?.id || null;
            document.getElementById('resourceModalTitle').textContent = row ? `Modifier : ${definition.title}` : `Nouvel enregistrement : ${definition.title}`;
            document.getElementById('resourceFormFields').innerHTML = fields
                .map((field) => resourceFieldHtml(field, row?.[field[0]] ?? defaults[field[0]], choices, row, resource))
                .join('');
            if (resource === 'rings') {
                const ringNamePairs = [
                    ['nombre_eglises', 'noms_eglises'],
                    ['nombre_ecoles', 'noms_ecoles'],
                    ['nombre_marches', 'noms_marches'],
                    ['nombre_parking', 'noms_parking'],
                    ['nombre_autres_lieux', 'noms_autres_lieux']
                ];
                ringNamePairs.forEach(([countName, namesName]) => {
                    const countInput = document.querySelector(`#resourceForm [name="${countName}"]`);
                    const namesInput = document.querySelector(`#resourceForm [name="${namesName}"]`);
                    if (!countInput || !namesInput) return;
                    const syncNamesRequirement = () => {
                        namesInput.required = Number(countInput.value || 0) > 0;
                        namesInput.placeholder = namesInput.placeholder || 'Nom 1 ; Nom 2 ; Nom 3';
                    };
                    countInput.addEventListener('input', syncNamesRequirement);
                    syncNamesRequirement();
                });
            }
            if (resource === 'supervisions' && user.role === 'superviseur_pci') {
                document.querySelector('#resourceForm [name="date_visite"]').value ||= new Date().toISOString().slice(0, 10);
            }
            if (resource === 'supervisions' && user.role === 'superviseur_pci') {
                const briefedInput = document.querySelector('#resourceForm [name="nombre_prestataires_briefes"]');
                const presenceInput = document.querySelector('#resourceForm [name="liste_presence_photo"]');
                const syncPresenceRequirement = () => {
                    if (!briefedInput || !presenceInput) return;
                    const hasExistingPhoto = Boolean(row?.liste_presence_photo);
                    presenceInput.required = Number(briefedInput.value || 0) > 0 && !hasExistingPhoto;
                };
                briefedInput?.addEventListener('input', syncPresenceRequirement);
                syncPresenceRequirement();
            }
            if (resource === 'activities' && user.role !== 'super_admin') {
                const pillarSelect = document.querySelector('#resourceForm [name="pilier"]');
                [...pillarSelect.options].forEach((option) => {
                    if (!canManageActivityPillar(option.value)) option.remove();
                });
            }
            if (resource === 'activities' && row?.pilier === 'pci' && !['super_admin', 'admin_zone', 'superviseur_pci'].includes(user.role)) {
                throw new Error('Les actions PCI sur un Ring sont en lecture seule pour votre rôle.');
            }
            const areaSelect = document.querySelector('#resourceForm select[name="aire_id"]');
            const zoneSelect = document.querySelector('#resourceForm select[name="zone_id"]');
            const plannedEssSelect = resource === 'supervisions'
                ? document.querySelector('#resourceForm select[name="ess_id"]')
                : null;
            const assignedSupervisorSelect = resource === 'supervisions'
                ? document.querySelector('#resourceForm select[name="superviseur_id"]')
                : null;
            if (plannedEssSelect && assignedSupervisorSelect) {
                const filterSupervisorsByFacility = () => {
                    const facility = (choices.ess || []).find((item) => Number(item.id) === Number(plannedEssSelect.value));
                    [...assignedSupervisorSelect.options].forEach((option) => {
                        if (!option.value) return;
                        const supervisor = (choices['pci-supervisors'] || []).find((item) => Number(item.id) === Number(option.value));
                        option.hidden = Boolean(facility && supervisor && Number(supervisor.zone_id) !== Number(facility.zone_id));
                    });
                    if (assignedSupervisorSelect.selectedOptions[0]?.hidden) assignedSupervisorSelect.value = '';
                };
                plannedEssSelect.addEventListener('change', filterSupervisorsByFacility);
                filterSupervisorsByFacility();
            }
            if (areaSelect && zoneSelect) {
                const initialArea = row?.aire_id ?? defaults.aire_id;
                const filterAreas = () => {
                    const selectedZoneId = zoneSelect.value;
                    [...areaSelect.options].forEach((option) => {
                        if (!option.value) return;
                        const area = areas.find((item) => Number(item.id) === Number(option.value));
                        option.hidden = Boolean(selectedZoneId && area && Number(area.zone_id) !== Number(selectedZoneId));
                    });
                    if (areaSelect.selectedOptions[0]?.hidden) areaSelect.value = '';
                };
                zoneSelect.addEventListener('change', filterAreas);
                filterAreas();
                if (initialArea) areaSelect.value = String(initialArea);
            }
            if (resource === 'activities' || resource === 'cases') {
                const facilitySelect = document.querySelector(`#resourceForm select[name="${resource === 'cases' ? 'ess_origine_id' : 'ess_id'}"]`);
                const selectedFacility = resource === 'cases'
                    ? row?.ess_origine_id ?? defaults.ess_origine_id
                    : row?.ess_id ?? defaults.ess_id;
                const filterFacilities = () => {
                    if (!facilitySelect) return;
                    const selectedAreaId = areaSelect?.value || '';
                    const selectedZoneId = zoneSelect?.value || '';
                    const eligibleAreas = areas
                        .filter((area) => !selectedZoneId || Number(area.zone_id) === Number(selectedZoneId))
                        .map((area) => Number(area.id));
                    const eligibleFacilities = (choices.ess || []).filter((facility) => (
                        selectedAreaId
                            ? Number(facility.aire_id) === Number(selectedAreaId)
                            : (!selectedZoneId || eligibleAreas.includes(Number(facility.aire_id)))
                    ));
                    facilitySelect.innerHTML = `<option value="">— Aucun —</option>${eligibleFacilities.map((facility) => `<option value="${facility.id}">${escapeHtml(facility.nom)}</option>`).join('')}`;
                    const currentFacility = facilitySelect.value || selectedFacility;
                    if (currentFacility && eligibleFacilities.some((facility) => Number(facility.id) === Number(currentFacility))) {
                        facilitySelect.value = String(currentFacility);
                    }
                };
                areaSelect?.addEventListener('change', filterFacilities);
                zoneSelect?.addEventListener('change', filterFacilities);
                filterFacilities();
                const caseSelect = document.querySelector('#resourceForm select[name="cas_mve_id"]');
                const ringSelect = document.querySelector('#resourceForm select[name="ring_id"]');
                const initialRing = row?.ring_id ?? defaults.ring_id;
                const filterRings = () => {
                    if (!caseSelect || !ringSelect) return;
                    const selectedCase = caseSelect.value;
                    const eligibleRings = (choices.rings || []).filter((ring) => (
                        ring.statut === 'actif'
                        && (!selectedCase || Number(ring.cas_mve_id) === Number(selectedCase))
                    ));
                    ringSelect.innerHTML = `<option value="">— Aucun —</option>${eligibleRings.map((ring) => `<option value="${ring.id}">${escapeHtml(ring.code)}</option>`).join('')}`;
                    if (initialRing && eligibleRings.some((ring) => Number(ring.id) === Number(initialRing))) {
                        ringSelect.value = String(initialRing);
                    }
                };
                caseSelect?.addEventListener('change', filterRings);
                filterRings();
            }
            const refreshConditionalFields = () => {
                document.querySelectorAll('#resourceForm [data-condition]').forEach((wrapper) => {
                    const dependent = wrapper.querySelector('[name]');
                    const [source, expected] = wrapper.dataset.condition.split(':');
                    const visible = document.querySelector(`#resourceForm [name="${source}"]`)?.value === expected;
                    wrapper.hidden = !visible;
                    dependent.required = visible;
                    if (!visible) dependent.value = '';
                });
            };
            const conditionalSources = resource === 'cases'
                ? ['origine']
                : resource === 'deaths' ? ['lieu', 'statut_eds']
                    : resource === 'activities' ? ['pilier']
                        : resource === 'supervisions' && user.role === 'superviseur_pci' ? ['statut_suivi'] : [];
            conditionalSources.forEach((source) => {
                document.querySelector(`#resourceForm [name="${source}"]`)?.addEventListener('change', refreshConditionalFields);
            });
            refreshConditionalFields();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('resourceModal')).show();
        } catch (error) {
            showFeedback('resourceFeedback', error.message, true);
        }
    };

    const loadResource = async (resource) => {
        if (!resourceDefinitions[resource]) return;
        activeResource = resource;
        const panel = document.getElementById('resourceCrudPanel');
        panel.hidden = false;
        try {
            if (resource === 'aires' || resource === 'ess') {
                const [zonesResult, areasResult] = await Promise.all([request('zones?limit=200'), request('aires?limit=200')]);
                zones = zonesResult.data;
                areas = areasResult.data;
            } else if (['cases', 'rings', 'deaths', 'supervisions', 'activities'].includes(resource)) {
                const [zonesResult, areasResult, essResult] = await Promise.all([
                    request('zones?limit=200'),
                    request('aires?limit=200'),
                    request('ess?limit=200')
                ]);
                zones = zonesResult.data;
                areas = areasResult.data;
                window.mveChoices = { ...(window.mveChoices || {}), zones: zonesResult.data, aires: areasResult.data, ess: essResult.data };
            }
            const result = await request(`${resourceDefinitions[resource].route}?limit=200`);
            resourceRows = result.data;
            window.mveChoices = { ...(window.mveChoices || {}), zones, aires: areas };
            renderResourceTable(resource, resourceRows);
        } catch (error) {
            showFeedback('resourceFeedback', error.message, true);
        }
    };

    const renderCases = (rows) => {
        const body = document.getElementById('recentCases');
        if (!body) return;
        const count = document.getElementById('recentCasesCount');
        if (count) count.innerHTML = `Affichage de <strong>${rows.length}</strong> cas récent${rows.length > 1 ? 's' : ''}`;
        body.innerHTML = rows.slice(0, 6).map((item) => {
            const status = {
                en_traitement_cte: ['En traitement', 'status-treatment'],
                gueri: ['Guéri', 'status-recovered'],
                decede: ['Décédé', 'status-deceased']
            }[item.statut] || ['Inconnu', 'status-treatment'];
            const date = new Date(`${item.date_confirmation}T00:00:00`);
            return `<tr data-zone="${item.zone_id}">
                <td><span class="case-code">${escapeHtml(item.code_epidemiologique)}</span></td>
                <td>${escapeHtml(item.zone_nom)}</td><td>${escapeHtml(item.aire_nom)}</td>
                <td>${new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' }).format(date)}</td>
                <td><span class="status-badge ${status[1]}"><i></i>${status[0]}</span></td>
                <td></td>
            </tr>`;
        }).join('') || '<tr><td colspan="6" class="empty-row">Aucun cas enregistré.</td></tr>';
    };

    const loadDashboard = async (zoneId = 'all') => {
        const areaId = document.getElementById('areaFilter')?.value || 'all';
        const filters = [];
        if (zoneId !== 'all') filters.push(`zone_id=${encodeURIComponent(zoneId)}`);
        if (areaId !== 'all') filters.push(`aire_id=${encodeURIComponent(areaId)}`);
        const suffix = filters.length ? `&${filters.join('&')}` : '';
        const caseFilters = [];
        if (zoneId !== 'all') caseFilters.push(`zone_id=${encodeURIComponent(zoneId)}`);
        if (areaId !== 'all') caseFilters.push(`aire_id=${encodeURIComponent(areaId)}`);
        const caseReadableRoles = ['super_admin', 'admin_zone', 'chef_pilier', 'superviseur_pci', 'surveillant_epidemiologique'];
        const [dashboard, cases] = await Promise.all([
            request(`dashboard${suffix}`),
            caseReadableRoles.includes(user.role)
                ? request(`cases?limit=100${caseFilters.length ? `&${caseFilters.join('&')}` : ''}`)
                : Promise.resolve({ data: [] })
        ]);
        const kpis = dashboard.kpis;
        document.querySelector('[data-kpi="cases"]').textContent = kpis.total_cas;
        document.querySelector('[data-kpi="pplCases"]').textContent = kpis.total_ppl_infectes;
        document.querySelector('[data-kpi="deaths"]').textContent = kpis.total_deces;
        document.querySelector('[data-kpi="rings"]').textContent = kpis.rings_actifs;
        document.querySelector('[data-kpi="facilities"]').textContent = kpis.ess_supervises;
        document.querySelector('.kpi-denominator').textContent = `/ ${kpis.ess_total}`;
        document.querySelector('.kpi-progress').setAttribute('aria-valuenow', kpis.ess_total ? Math.round(kpis.ess_supervises * 100 / kpis.ess_total) : 0);
        document.querySelector('.kpi-progress .progress-bar').style.width = `${kpis.ess_total ? Math.min(100, kpis.ess_supervises * 100 / kpis.ess_total) : 0}%`;
        renderCases(cases.data);

        const areaRows = dashboard.cas_par_aire;
        if (casesChart) {
            casesChart.data.labels = areaRows.map((area) => area.aire_nom);
            casesChart.data.datasets[0].data = areaRows.map((area) => area.total);
            casesChart.data.datasets[0].label = 'Cas confirmés';
            casesChart.options.plugins.tooltip.callbacks.label = (context) => `${context.parsed.y} cas confirmés`;
            casesChart.update();
        }
        if (ringChart) {
            const counts = dashboard.structures_rings;
            ringChart.data.datasets[0].data = [counts.menages, counts.eglises, counts.ecoles, counts.marches, counts.parkings, counts.autres_lieux];
            ringChart.data.labels = ['Ménages', 'Églises', 'Écoles', 'Marchés', 'Parkings', 'Autres lieux'];
            ringChart.update();
            document.querySelector('.chart-center strong').textContent = Object.values(counts).reduce((sum, count) => sum + count, 0).toLocaleString('fr-FR');
        }
    };

    const reportQuery = () => {
        const params = new URLSearchParams();
        const zoneId = document.getElementById('zoneFilter')?.value || 'all';
        const areaId = document.getElementById('areaFilter')?.value || 'all';
        const from = document.getElementById('reportDateFrom').value;
        const to = document.getElementById('reportDateTo').value;
        if (zoneId !== 'all') params.set('zone_id', zoneId);
        if (areaId !== 'all') params.set('aire_id', areaId);
        if (from) params.set('date_from', from);
        if (to) params.set('date_to', to);
        return params;
    };

    const reportTables = (report) => {
        const labels = {
            activites: 'Activités de riposte',
            cas: 'Cas confirmés',
            deces: 'Décès / EDS',
            rings: 'Rings',
            supervisions: 'Supervisions PCI'
        };
        return Object.entries(labels)
            .filter(([key]) => Array.isArray(report[key]))
            .map(([key, title]) => ({ title, rows: report[key] }));
    };

    const exportReport = async (format) => {
        const feedback = document.getElementById('reportFeedback');
        try {
            const params = reportQuery();
            const report = await request(`reports?${params.toString()}`);
            const tables = reportTables(report);
            const metrics = report.indicateurs;
            const dateStamp = new Date().toISOString().slice(0, 10);
            if (format === 'xlsx') {
                if (!window.XLSX) throw new Error('Le module Excel ne s’est pas chargé. Vérifiez la connexion puis rechargez la page.');
                const workbook = window.XLSX.utils.book_new();
                window.XLSX.utils.book_append_sheet(workbook, window.XLSX.utils.json_to_sheet([{
                    'Cas confirmés': metrics.cas_confirmes,
                    'PPL infectés': metrics.ppl_infectes,
                    'Décès': metrics.deces,
                    'EDS réalisés': metrics.eds_realises,
                    'Rings actifs': metrics.rings_actifs,
                    'Supervisions PCI': metrics.supervisions,
                    'Activités': metrics.activites,
                    'Activités terminées': metrics.activites_terminees
                }]), 'Indicateurs');
                tables.forEach(({ title, rows }) => {
                    window.XLSX.utils.book_append_sheet(workbook, window.XLSX.utils.json_to_sheet(rows), title.slice(0, 31));
                });
                window.XLSX.writeFile(workbook, `rapport-riposte-mve-${dateStamp}.xlsx`);
            } else if (format === 'pdf') {
                if (!window.jspdf?.jsPDF) throw new Error('Le module PDF ne s’est pas chargé. Vérifiez la connexion puis rechargez la page.');
                const pdf = new window.jspdf.jsPDF({ orientation: 'landscape' });
                pdf.setFontSize(18);
                pdf.text('Rapport EPI DATA', 14, 16);
                pdf.setFontSize(10);
                pdf.text(`Généré le ${new Date(report.genere_le).toLocaleString('fr-FR')} — rôle : ${report.role}`, 14, 23);
                pdf.autoTable({
                    startY: 30,
                    head: [['Cas confirmés', 'PPL infectés', 'Décès', 'EDS réalisés', 'Rings actifs', 'Supervisions PCI', 'Activités', 'Terminées']],
                    body: [[metrics.cas_confirmes, metrics.ppl_infectes, metrics.deces, metrics.eds_realises, metrics.rings_actifs, metrics.supervisions, metrics.activites, metrics.activites_terminees]]
                });
                tables.forEach(({ title, rows }) => {
                    if (!rows.length) return;
                    pdf.addPage();
                    pdf.setFontSize(14);
                    pdf.text(title, 14, 16);
                    const columns = Object.keys(rows[0]).filter((key) => !['id', 'created_by', 'deleted_at', 'created_at', 'updated_at'].includes(key)).slice(0, 10);
                    pdf.autoTable({
                        startY: 22,
                        head: [columns.map((key) => key.replaceAll('_', ' '))],
                        body: rows.slice(0, 300).map((row) => columns.map((key) => String(row[key] ?? '')))
                    });
                });
                pdf.save(`rapport-riposte-mve-${dateStamp}.pdf`);
            } else if (format === 'pptx') {
                const Pptx = window.pptxgenjs || window.pptxgen;
                if (!Pptx) throw new Error('Le module PowerPoint ne s’est pas chargé. Vérifiez la connexion puis rechargez la page.');
                const deck = new Pptx();
                deck.layout = 'LAYOUT_WIDE';
                deck.author = 'EPI DATA';
                deck.subject = 'Rapport des activités de riposte';
                deck.title = 'Rapport EPI DATA';
                deck.theme = { headFontFace: 'Aptos Display', bodyFontFace: 'Aptos', lang: 'fr-FR' };
                const slide = deck.addSlide();
                slide.background = { color: '10233F' };
                slide.addText('RAPPORT EPI DATA', { x: 0.7, y: 0.8, w: 11.8, h: 0.7, fontSize: 28, bold: true, color: 'FFFFFF' });
                slide.addText(`Données générées le ${new Date(report.genere_le).toLocaleString('fr-FR')}`, { x: 0.7, y: 1.65, w: 11, h: 0.4, fontSize: 14, color: 'DCE7F5' });
                slide.addText(`Périmètre du rapport — rôle : ${report.role}`, { x: 0.7, y: 2.1, w: 11, h: 0.4, fontSize: 13, color: 'DCE7F5' });
                slide.addText([
                    { text: `Cas confirmés : ${metrics.cas_confirmes}\n`, options: { breakLine: true } },
                    { text: `Prestataires de première ligne infectés : ${metrics.ppl_infectes}\n`, options: { breakLine: true } },
                    { text: `Décès : ${metrics.deces} (EDS réalisés : ${metrics.eds_realises})\n`, options: { breakLine: true } },
                    { text: `Rings actifs : ${metrics.rings_actifs}\n`, options: { breakLine: true } },
                    { text: `Supervisions PCI : ${metrics.supervisions}\n`, options: { breakLine: true } },
                    { text: `Activités : ${metrics.activites} (terminées : ${metrics.activites_terminees})` }
                ], { x: 0.9, y: 3, w: 10.5, h: 2.3, fontSize: 20, color: 'FFFFFF', breakLine: false, paraSpaceAfterPt: 12 });
                const activityCounts = Object.entries(report.activites.reduce((counts, activity) => {
                    counts[formatResourceValue('pilier', activity.pilier)] = (counts[formatResourceValue('pilier', activity.pilier)] || 0) + 1;
                    return counts;
                }, {}));
                const activitySlide = deck.addSlide();
                activitySlide.addText('Activités par pilier', { x: 0.6, y: 0.4, w: 12, h: 0.5, fontSize: 24, bold: true, color: '10233F' });
                if (activityCounts.length) {
                    activitySlide.addChart(deck.ChartType.bar, [{
                        name: 'Activités',
                        labels: activityCounts.map(([label]) => label),
                        values: activityCounts.map(([, count]) => count)
                    }], { x: 0.7, y: 1.2, w: 11.8, h: 5.3, catAxisLabelFontFace: 'Aptos', valAxisMinVal: 0, valAxisLabelFormatCode: '0', showLegend: false, chartColors: ['3978D6'] });
                } else {
                    activitySlide.addText('Aucune activité dans le périmètre et la période sélectionnés.', { x: 0.9, y: 1.4, w: 10.5, h: 0.5, fontSize: 16, color: '78879B' });
                }
                if (report.activites.length) {
                    const activitiesSlide = deck.addSlide();
                    activitiesSlide.addText('Suivi des activités', { x: 0.6, y: 0.4, w: 12, h: 0.5, fontSize: 24, bold: true, color: '10233F' });
                    activitiesSlide.addTable([
                        ['Date', 'Pilier', 'Activité', 'Aire', 'État', 'Cible', 'Réalisé'],
                        ...report.activites.slice(0, 15).map((activity) => [
                            activity.date_activite,
                            formatResourceValue('pilier', activity.pilier),
                            activity.titre,
                            activity.aire_nom || '—',
                            formatResourceValue('statut', activity.statut),
                            activity.cible ?? '—',
                            activity.realise ?? '—'
                        ])
                    ], { x: 0.5, y: 1.1, w: 12.3, h: 5.8, border: { type: 'solid', color: 'DCE3EB', pt: 0.5 }, fontSize: 10, color: '26384F', fill: 'FFFFFF', rowH: 0.32, autoFit: true, margin: 0.06 });
                }
                await deck.writeFile({ fileName: `rapport-riposte-mve-${dateStamp}.pptx` });
            }
            showFeedback('reportFeedback', `Rapport ${format.toUpperCase()} généré selon vos droits et les filtres sélectionnés.`);
        } catch (error) {
            showFeedback('reportFeedback', error.message, true);
        }
    };

    const chartFont = { family: 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif' };
    if (window.Chart) {
        casesChart = new Chart(document.getElementById('casesChart'), {
            type: 'bar',
            data: { labels: [], datasets: [{ label: 'Cas confirmés', data: [], backgroundColor: '#5d8fdb', borderRadius: 4, maxBarThickness: 26 }] },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#10233f', padding: 9, titleFont: { ...chartFont, size: 10 }, bodyFont: { ...chartFont, size: 10 } } },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { color: '#9aa5b2', font: { ...chartFont, size: 9 }, maxRotation: 0 } },
                    y: { beginAtZero: true, border: { display: false, dash: [3, 4] }, grid: { color: '#edf0f4', drawTicks: false }, ticks: { precision: 0, color: '#9aa5b2', padding: 9, font: { ...chartFont, size: 9 } } }
                }
            }
        });
        ringChart = new Chart(document.getElementById('ringChart'), {
            type: 'doughnut',
            data: { labels: ['Ménages', 'Églises', 'Écoles', 'Marchés', 'Parkings', 'Autres lieux'], datasets: [{ data: [0, 0, 0, 0, 0, 0], backgroundColor: ['#3978d6', '#51b89b', '#f0ad55', '#a3b6d2', '#7caed8', '#8f79c7'], borderWidth: 0, hoverOffset: 3 }] },
            options: { cutout: '77%', maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { backgroundColor: '#10233f', padding: 8, titleFont: { ...chartFont, size: 10 }, bodyFont: { ...chartFont, size: 10 } } } }
        });
    }

    document.getElementById('zoneFilter').addEventListener('change', (event) => {
        const selectedZoneId = event.currentTarget.value;
        const areaFilter = document.getElementById('areaFilter');
        if (areaFilter) {
            areaFilter.value = 'all';
            const areaLabel = document.querySelector('.area-filter-label');
            areaLabel.hidden = selectedZoneId === 'all';
            areaFilter.hidden = selectedZoneId === 'all';
            if (selectedZoneId !== 'all') {
                const zoneAreas = areas.filter((area) => Number(area.zone_id) === Number(selectedZoneId));
                areaFilter.innerHTML = `<option value="all">Toutes les aires</option>${zoneAreas.map((area) => `<option value="${area.id}">${escapeHtml(area.nom)}</option>`).join('')}`;
            }
        }
        loadDashboard(selectedZoneId).catch((error) => console.error(error));
    });

    document.getElementById('areaFilter')?.addEventListener('change', () => {
        loadDashboard(document.getElementById('zoneFilter').value).catch((error) => console.error(error));
    });

    document.addEventListener('click', (event) => {
        const exportButton = event.target.closest('[data-report-export]');
        if (exportButton) exportReport(exportButton.dataset.reportExport);
    });

    document.getElementById('createResourceButton').addEventListener('click', () => openResourceForm());
    document.getElementById('refreshResourceButton').addEventListener('click', () => loadResource(activeResource));
    document.getElementById('resourceForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const definition = resourceDefinitions[activeResource];
        const formData = new FormData(event.currentTarget);
        const payload = {};
        const presenceFile = event.currentTarget.querySelector('[name="liste_presence_photo"]')?.files?.[0];
        if (presenceFile) {
            if (presenceFile.size > 8 * 1024 * 1024) {
                showFeedback('resourceFeedback', 'La photo de la liste de présence ne doit pas dépasser 8 Mo.', true);
                return;
            }
            payload.liste_presence_photo = await new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result);
                reader.onerror = () => reject(new Error('Impossible de lire la photo sélectionnée.'));
                reader.readAsDataURL(presenceFile);
            });
        }
        for (const [name, , type] of fieldsForResource(activeResource)) {
            if (type.startsWith('radio:')) {
                payload[name] = formData.get(name) === '1';
            } else if (type === 'checkbox') {
                payload[name] = formData.has(name);
            } else if (type === 'file:image') {
                continue;
            } else if (type === 'number') {
                const value = formData.get(name);
                payload[name] = value === '' ? (activeResource === 'rings' ? 0 : null) : Number(value);
            } else {
                const value = formData.get(name);
                payload[name] = value === '' ? null : value;
            }
        }
        if (activeResource === 'cases' && user.role !== 'super_admin') {
            delete payload.zone_id;
        }
        try {
            await request(editingResourceId ? `${definition.route}/${editingResourceId}` : definition.route, {
                method: editingResourceId ? 'PUT' : 'POST',
                body: JSON.stringify(payload)
            });
            bootstrap.Modal.getOrCreateInstance(document.getElementById('resourceModal')).hide();
            showFeedback('resourceFeedback', `${definition.title} ${editingResourceId ? 'modifié' : 'créé'}.`);
            await loadResource(activeResource);
            await loadDashboard(document.getElementById('zoneFilter').value);
            await loadNotifications();
        } catch (error) {
            showFeedback('resourceFeedback', error.message, true);
        }
    });

    document.getElementById('zoneForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const fields = new FormData(form);
        try {
            const payload = {
                nom: fields.get('nom'),
                code: fields.get('code'),
                province: fields.get('province') || null,
                active: editingZoneId ? editingZoneActive : true
            };
            if (!editingZoneId) {
                payload.admin = {
                    nom: fields.get('admin_nom'),
                    email: fields.get('admin_email'),
                    password: fields.get('admin_password')
                };
            }
            await request(editingZoneId ? `zones/${editingZoneId}` : 'zones', {
                method: editingZoneId ? 'PUT' : 'POST',
                body: JSON.stringify(payload)
            });
            const wasEditing = Boolean(editingZoneId);
            editingZoneId = null;
            form.reset();
            setZoneFormMode(false);
            showFeedback('zoneFeedback', wasEditing ? 'Zone de Santé modifiée.' : 'Zone et compte Admin de Zone créés. Transmettez les identifiants par un canal sécurisé.');
            await loadZones();
        } catch (error) {
            showFeedback('zoneFeedback', error.message, true);
        }
    });

    document.getElementById('userForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const fields = new FormData(form);
        try {
            const wasEditing = Boolean(editingUserId);
            const userRoute = editingUserId ? `users/${editingUserId}` : 'users';
            await request(userRoute, { method: editingUserId ? 'PUT' : 'POST', body: JSON.stringify({
                nom: fields.get('nom'),
                email: fields.get('email'),
                ...(fields.get('password') ? { password: fields.get('password') } : {}),
                role: fields.get('role'),
                zone_id: Number(fields.get('zone_id') || user.zone_id)
            }) });
            editingUserId = null;
            form.reset();
            form.querySelector('[name="password"]').required = true;
            form.querySelector('[name="password"]').value = '';
            form.querySelector('button[type="submit"]').innerHTML = '<i class="bi bi-person-plus"></i> Créer le compte';
            showFeedback('userFeedback', wasEditing ? 'Compte modifié.' : 'Compte créé. Communiquez les identifiants au membre de l’équipe par un canal sécurisé.');
            await loadUsers();
        } catch (error) {
            showFeedback('userFeedback', error.message, true);
        }
    });

    document.addEventListener('click', async (event) => {
        const zoneButton = event.target.closest('[data-toggle-zone]');
        const userButton = event.target.closest('[data-toggle-user]');
        const refreshButton = event.target.closest('[data-refresh]');
        const editResourceButton = event.target.closest('[data-edit-resource]');
        const deleteResourceButton = event.target.closest('[data-delete-resource]');
        const editZoneButton = event.target.closest('[data-edit-zone]');
        const editUserButton = event.target.closest('[data-edit-user]');
        const deleteZoneButton = event.target.closest('[data-delete-zone]');
        const deleteUserButton = event.target.closest('[data-delete-user]');
        const closeRingButton = event.target.closest('[data-close-ring]');
        const caseNotificationButton = event.target.closest('[data-notification-case]');
        try {
            if (editZoneButton) {
                const zone = zones.find((item) => Number(item.id) === Number(editZoneButton.dataset.editZone));
                if (!zone) throw new Error('Zone de Santé introuvable.');
                const form = document.getElementById('zoneForm');
                editingZoneId = zone.id;
                editingZoneActive = Number(zone.active) === 1 || zone.active === true;
                form.nom.value = zone.nom;
                form.code.value = zone.code;
                form.province.value = zone.province || '';
                setZoneFormMode(true);
                document.querySelector('#zoneForm button[type="submit"]').innerHTML = '<i class="bi bi-check-lg"></i> Enregistrer les modifications';
                return;
            }
            if (editUserButton) {
                const result = await request('users?limit=200');
                const account = result.data.find((item) => Number(item.id) === Number(editUserButton.dataset.editUser));
                if (!account) throw new Error('Compte introuvable.');
                editingUserId = account.id;
                const form = document.getElementById('userForm');
                form.nom.value = account.nom;
                form.email.value = account.email;
                form.password.value = '';
                form.password.required = false;
                form.role.value = account.role;
                if (form.zone_id) form.zone_id.value = account.zone_id;
                form.querySelector('button[type="submit"]').innerHTML = '<i class="bi bi-check-lg"></i> Enregistrer les modifications';
                setActiveView('users');
                form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            if (editResourceButton) {
                const row = resourceRows.find((item) => Number(item.id) === Number(editResourceButton.dataset.editResource));
                if (!row) throw new Error('Enregistrement introuvable.');
                await openResourceForm(row);
                return;
            }
            if (deleteResourceButton) {
                const row = resourceRows.find((item) => Number(item.id) === Number(deleteResourceButton.dataset.deleteResource));
                if (!row) throw new Error('Enregistrement introuvable.');
                const definition = resourceDefinitions[activeResource];
                if (!window.confirm(`Supprimer cet enregistrement de « ${definition.title} » ? Il sera archivé et restera conservé dans l’historique.`)) return;
                await request(`${definition.route}/${row.id}`, { method: 'DELETE', body: '{}' });
                showFeedback('resourceFeedback', 'Enregistrement supprimé et archivé dans l’historique.');
                await loadResource(activeResource);
                await loadDashboard(document.getElementById('zoneFilter').value);
                return;
            }
            if (closeRingButton) {
                const ring = resourceRows.find((item) => Number(item.id) === Number(closeRingButton.dataset.closeRing));
                if (!ring) throw new Error('Ring introuvable.');
                if (!window.confirm(`Fermer le Ring « ${ring.code} » ? Sa fermeture sera vérifiée par le serveur après 48 heures d’ouverture.`)) return;
                await request(`rings/${ring.id}`, { method: 'PUT', body: JSON.stringify({ statut: 'termine' }) });
                showFeedback('resourceFeedback', 'Ring fermé.');
                await loadResource('rings');
                await loadDashboard(document.getElementById('zoneFilter').value);
                return;
            }
            if (caseNotificationButton) {
                const notification = notificationsList.find((item) => Number(item.cas_mve_id) === Number(caseNotificationButton.dataset.notificationCase));
                if (!notification) throw new Error('Notification introuvable.');
                document.getElementById('notificationPanel').hidden = true;
                if (!notification.ring_id) {
                    setActiveView('rings');
                    await loadResource('rings');
                    const activeRing = resourceRows.find((ring) => (
                        Number(ring.cas_mve_id) === Number(notification.cas_mve_id) && ring.statut === 'actif'
                    ));
                    if (!activeRing) {
                        await openResourceForm(null, { cas_mve_id: notification.cas_mve_id });
                        return;
                    }
                    notification.ring_id = activeRing.id;
                    notification.ring_code = activeRing.code;
                }
                setActiveView('activities');
                await loadResource('activities');
                await openResourceForm(null, {
                    zone_id: user.zone_id,
                    aire_id: notification.aire_id,
                    cas_mve_id: notification.cas_mve_id,
                    ring_id: notification.ring_id,
                    pilier: 'pci'
                });
                return;
            }
            if (deleteZoneButton) {
                const zone = zones.find((item) => Number(item.id) === Number(deleteZoneButton.dataset.deleteZone));
                if (!zone) throw new Error('Zone de Santé introuvable.');
                if (!window.confirm(`Supprimer la Zone de Santé « ${zone.nom} » ? Elle sera désactivée sans effacer son historique, et ses comptes ne pourront plus se connecter.`)) return;
                await request(`zones/${zone.id}`, { method: 'DELETE', body: '{}' });
                showFeedback('zoneFeedback', 'Zone supprimée : elle a été désactivée et son historique est conservé.');
                await loadZones();
                await loadDashboard(document.getElementById('zoneFilter').value);
                return;
            }
            if (deleteUserButton) {
                const account = (await request('users?limit=200')).data.find((item) => Number(item.id) === Number(deleteUserButton.dataset.deleteUser));
                if (!account) throw new Error('Compte introuvable.');
                if (!window.confirm(`Supprimer le compte « ${account.nom} » ? Il sera désactivé et conservé dans l’historique.`)) return;
                await request(`users/${account.id}`, { method: 'DELETE', body: '{}' });
                showFeedback('userFeedback', 'Compte supprimé et désactivé. Son historique est conservé.');
                await loadUsers();
                return;
            }
            if (zoneButton) {
                const zone = zones.find((item) => Number(item.id) === Number(zoneButton.dataset.toggleZone));
                if (!zone) throw new Error('Zone de Santé introuvable.');
                const active = zoneButton.dataset.active !== '1';
                const action = active ? 'activer' : 'désactiver';
                if (!window.confirm(`Voulez-vous ${action} la Zone de Santé « ${zone.nom} » ?`)) return;
                await request(`zones/${zone.id}`, {
                    method: 'PUT',
                    body: JSON.stringify({ nom: zone.nom, code: zone.code, province: zone.province, active })
                });
                showFeedback('zoneFeedback', `Zone de Santé ${active ? 'activée' : 'désactivée'}.`);
                await loadZones();
                await loadDashboard(document.getElementById('zoneFilter').value);
            }
            if (userButton) {
                const account = (await request('users?limit=200')).data.find((item) => Number(item.id) === Number(userButton.dataset.toggleUser));
                if (!account) throw new Error('Compte introuvable.');
                const active = userButton.dataset.active !== '1';
                if (!window.confirm(`Voulez-vous ${active ? 'activer' : 'désactiver'} le compte « ${account.nom} » ?`)) return;
                await request(`users/${account.id}`, {
                    method: 'PUT',
                    body: JSON.stringify({ nom: account.nom, email: account.email, role: account.role, zone_id: Number(account.zone_id), active })
                });
                showFeedback('userFeedback', `Compte ${active ? 'activé' : 'désactivé'}.`);
                await loadUsers();
            }
            if (refreshButton) {
                if (refreshButton.dataset.refresh === 'zones') await loadZones();
                if (refreshButton.dataset.refresh === 'users') await loadUsers();
                if (resourceDefinitions[refreshButton.dataset.refresh]) await loadResource(refreshButton.dataset.refresh);
            }
        } catch (error) {
            showFeedback(zoneButton || deleteZoneButton ? 'zoneFeedback' : (userButton || deleteUserButton ? 'userFeedback' : 'resourceFeedback'), error.message, true);
        }
    });

    document.getElementById('logoutButton').addEventListener('click', async () => {
        try {
            await request('auth/logout', { method: 'POST', body: '{}' });
            window.location.assign('login.php');
        } catch (error) {
            window.alert(error.message);
        }
    });

    try {
        const session = await request('auth/me');
        csrfToken = session.csrf_token;
        user = session.user;
        const zoneScopeBadge = document.getElementById('zoneScopeBadge');
        if (zoneScopeBadge && user.role !== 'super_admin' && user.zone_nom) {
            zoneScopeBadge.textContent = `Zone : ${user.zone_nom}`;
            zoneScopeBadge.hidden = false;
        }
        document.getElementById('todayDate').textContent = new Intl.DateTimeFormat('fr-FR', {
            day: 'numeric', month: 'long', year: 'numeric'
        }).format(new Date());
        await loadDashboard();
        await loadNotifications();
        if (user.role === 'superviseur_pci') window.setInterval(loadNotifications, 60000);
        if (user.role === 'super_admin') {
            await loadZones();
            await loadUsers();
        } else if (user.role === 'admin_zone') {
            const areasResult = await request('aires?limit=200');
            areas = areasResult.data;
            const areaFilter = document.getElementById('areaFilter');
            areaFilter.innerHTML = `<option value="all">Toutes les aires</option>${areas.map((area) => `<option value="${area.id}">${escapeHtml(area.nom)}</option>`).join('')}`;
            document.querySelector('.area-filter-label').hidden = false;
            areaFilter.hidden = false;
            document.getElementById('zoneFilter').hidden = true;
            document.querySelector('.filter-label').hidden = true;
            document.querySelector('.heading-actions .action-button').hidden = true;
            setActiveView('users');
            await loadUsers();
        } else if (user.role === 'chef_pilier') {
            document.querySelector('.filter-label').hidden = true;
            document.getElementById('zoneFilter').hidden = true;
            setActiveView('users');
            await loadUsers();
        } else if (user.role === 'superviseur_pci') {
            document.querySelector('.filter-label').hidden = true;
            document.getElementById('zoneFilter').hidden = true;
            document.getElementById('view-dashboard').querySelector('.alert-strip').hidden = true;
            setActiveView('supervisions');
            await loadResource('supervisions');
        } else {
            document.querySelector('.filter-label').hidden = true;
            document.getElementById('zoneFilter').hidden = true;
        }
    } catch (error) {
        console.error(error);
    }
});
