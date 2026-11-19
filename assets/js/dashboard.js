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

    const modules = {
        cases: ['Cas confirmés', 'Registre et suivi des cas confirmés de maladie à virus Ebola.'],
        rings: ['Rings', 'Suivi des ceintures d’intervention associées aux cas confirmés.'],
        supervisions: ['Supervisions PCI', 'Visites de suivi et d’accompagnement des établissements de soins.'],
        deaths: ['Décès & EDS', 'Enregistrement des décès et suivi des enterrements dignes et sécurisés.'],
        geography: ['Zones & structures', 'Créez les Zones de Santé et gérez leur état d’activation.'],
        users: ['Utilisateurs', 'Créez les comptes des Chefs de Pilier et Superviseurs PCI de chaque zone.']
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
        if (genericModule) genericModule.hidden = view === 'geography' || view === 'users';
        if (zoneAdminPanel) zoneAdminPanel.hidden = view !== 'geography';
        if (userAdminPanel) userAdminPanel.hidden = view !== 'users';
        sidebar.classList.remove('open');
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest('[data-view]');
        if (!link) return;
        const view = link.dataset.view;
        if (!view) return;
        event.preventDefault();
        setActiveView(view);
        if (view === 'geography') loadZones();
        if (view === 'users') loadUsers();
    });

    document.getElementById('menuToggle').addEventListener('click', () => sidebar.classList.toggle('open'));

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
                    <td><button class="btn btn-sm ${active ? 'btn-outline-danger' : 'btn-outline-success'} toggle-button" type="button" data-toggle-zone="${zone.id}" data-active="${active ? '1' : '0'}">${active ? 'Désactiver' : 'Activer'}</button></td>
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
                const role = account.role === 'chef_pilier' ? 'Chef de Pilier' : account.role === 'superviseur_pci' ? 'Superviseur PCI' : 'Super Admin';
                const action = account.role === 'super_admin'
                    ? '<span class="muted-action">Compte principal</span>'
                    : `<button class="btn btn-sm ${active ? 'btn-outline-danger' : 'btn-outline-success'} toggle-button" type="button" data-toggle-user="${account.id}" data-active="${active ? '1' : '0'}">${active ? 'Désactiver' : 'Activer'}</button>`;
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

    const renderCases = (rows) => {
        const body = document.getElementById('recentCases');
        if (!body) return;
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
        const [dashboard, cases] = await Promise.all([
            request(`dashboard${suffix}`),
            request(`cases?limit=100${caseFilters.length ? `&${caseFilters.join('&')}` : ''}`)
        ]);
        const kpis = dashboard.kpis;
        document.querySelector('[data-kpi="cases"]').textContent = kpis.total_cas;
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
            ringChart.data.datasets[0].data = [counts.menages, counts.eglises, counts.ecoles, counts.marches, counts.autres_lieux];
            ringChart.data.labels = ['Ménages', 'Églises', 'Écoles', 'Marchés', 'Autres lieux'];
            ringChart.update();
            document.querySelector('.chart-center strong').textContent = Object.values(counts).reduce((sum, count) => sum + count, 0).toLocaleString('fr-FR');
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
            data: { labels: ['Ménages', 'Églises', 'Écoles', 'Marchés', 'Autres lieux'], datasets: [{ data: [0, 0, 0, 0, 0], backgroundColor: ['#3978d6', '#51b89b', '#f0ad55', '#a3b6d2', '#8f79c7'], borderWidth: 0, hoverOffset: 3 }] },
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

    document.getElementById('zoneForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const fields = new FormData(form);
        try {
            await request('zones', { method: 'POST', body: JSON.stringify({ nom: fields.get('nom'), code: fields.get('code'), province: fields.get('province') || null, active: true }) });
            form.reset();
            showFeedback('zoneFeedback', 'Zone de Santé créée.');
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
            await request('users', {
                method: 'POST',
                body: JSON.stringify({
                    nom: fields.get('nom'),
                    email: fields.get('email'),
                    password: fields.get('password'),
                    role: fields.get('role'),
                    zone_id: Number(fields.get('zone_id'))
                })
            });
            form.reset();
            showFeedback('userFeedback', 'Compte créé. Communiquez les identifiants au membre de l’équipe par un canal sécurisé.');
            await loadUsers();
        } catch (error) {
            showFeedback('userFeedback', error.message, true);
        }
    });

    document.addEventListener('click', async (event) => {
        const zoneButton = event.target.closest('[data-toggle-zone]');
        const userButton = event.target.closest('[data-toggle-user]');
        const refreshButton = event.target.closest('[data-refresh]');
        try {
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
            }
        } catch (error) {
            showFeedback(zoneButton ? 'zoneFeedback' : 'userFeedback', error.message, true);
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
        document.getElementById('todayDate').textContent = new Intl.DateTimeFormat('fr-FR', {
            day: 'numeric', month: 'long', year: 'numeric'
        }).format(new Date());
        await loadDashboard();
        if (user.role === 'super_admin') {
            await loadZones();
            await loadUsers();
        } else if (user.role === 'superviseur_pci') {
            document.querySelector('.filter-label').hidden = true;
            document.getElementById('zoneFilter').hidden = true;
            document.getElementById('view-dashboard').querySelector('.alert-strip').hidden = true;
        } else {
            document.querySelector('.filter-label').hidden = true;
            document.getElementById('zoneFilter').hidden = true;
        }
    } catch (error) {
        console.error(error);
    }
});
