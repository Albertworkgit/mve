document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('loginForm');
    const errorBox = document.getElementById('loginError');
    const notice = document.getElementById('loginNotice');
    const button = document.getElementById('loginButton');
    const password = document.getElementById('password');
    const api = 'backend/api.php';

    if (new URLSearchParams(window.location.search).has('disabled')) {
        notice.hidden = false;
    }

    document.getElementById('togglePassword').addEventListener('click', (event) => {
        const toggle = event.currentTarget;
        const visible = password.type === 'password';
        password.type = visible ? 'text' : 'password';
        toggle.setAttribute('aria-label', visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        toggle.innerHTML = `<i class="bi bi-eye${visible ? '-slash' : ''}"></i>`;
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        errorBox.hidden = true;
        notice.hidden = true;
        button.disabled = true;
        button.querySelector('span').textContent = 'Connexion...';

        try {
            const csrfResponse = await fetch(`${api}?path=auth/csrf`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const csrfData = await csrfResponse.json();
            if (!csrfResponse.ok || !csrfData.csrf_token) {
                throw new Error(csrfData.error || 'Impossible d’initialiser la session.');
            }

            const response = await fetch(`${api}?path=auth/login`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfData.csrf_token, Accept: 'application/json' },
                body: JSON.stringify({ email: form.email.value.trim(), password: password.value })
            });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error || 'Échec de connexion.');
            }
            window.location.assign('index.php');
        } catch (error) {
            errorBox.textContent = error.message || 'Erreur de connexion au serveur.';
            errorBox.hidden = false;
        } finally {
            button.disabled = false;
            button.querySelector('span').textContent = 'Se connecter';
        }
    });
});
