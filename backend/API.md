# API PHP native

L'API est servie par `backend/api.php`. Elle utilise les sessions PHP, PDO et des réponses JSON. Les requêtes d'écriture doivent envoyer `Content-Type: application/json` ainsi que l'en-tête `X-CSRF-Token`.

## Authentification

1. `GET /backend/api.php/auth/csrf` ouvre une session et fournit le jeton CSRF.
2. `POST /backend/api.php/auth/login` avec `{ "email": "...", "password": "..." }` ouvre la session authentifiée. La réponse fournit le nouveau jeton CSRF.
3. `GET /backend/api.php/auth/me` retourne l'utilisateur et le jeton CSRF de sa session.
4. `POST /backend/api.php/auth/logout` termine la session.

Le navigateur doit conserver le cookie de session et envoyer `X-CSRF-Token` sur les appels POST et PUT.

## Ressources

Les collections acceptent `GET`; la création utilise `POST /ressource`, la modification `PUT /ressource/{id}`. Les suppressions physiques ne sont pas exposées : les entités géographiques et utilisateurs peuvent être désactivés avec `"active": false`.

| Ressource | Route | Accès d’écriture |
|---|---|---|
| Tableau de bord | `GET /dashboard?zone_id=ID&aire_id=ID` | Lecture authentifiée |
| Zones de Santé | `/zones` | Super Admin |
| Aires de Santé | `/aires` | Super Admin |
| Établissements de Santé | `/ess` | Super Admin |
| Utilisateurs | `/users` | Super Admin |
| Cas confirmés | `/cases` | Chef de Pilier, Superviseur PCI |
| Rings | `/rings` | Chef de Pilier, Superviseur PCI |
| Décès / EDS | `/deaths` | Chef de Pilier |
| Supervisions PCI | `/supervisions` | Superviseur PCI |

Les utilisateurs non Super Admin sont automatiquement limités à leur Zone de Santé. Le Super Admin peut filtrer le tableau de bord par Zone puis par Aire de Santé ; indicateurs, graphique des cas, répartition des rings et registre des cas suivent le même périmètre. Une Aire choisie hors de la Zone filtrée est rejetée. Les collections acceptent `zone_id`, `limit` (1 à 200), et selon le module `aire_id` ou `statut`.

## Champs métier importants

- Cas : `zone_id` (Super Admin seulement), `aire_id`, `code_epidemiologique`, `date_confirmation`; `ess_origine_id` est facultatif.
- Ring : `cas_mve_id` est obligatoire; compteurs et états de décontamination, vaccination et sensibilisation sont validés.
- Décès : `aire_id`, `date_deces`, `lieu` (`communaute` ou `cte_ess`); un décès au CTE/ESS exige `ess_id`. Le cas confirmé est facultatif pour un décès communautaire suspect.
- Supervision : `ess_id`, `date_visite`; la visite est attribuée au superviseur connecté.

Les champs requis manquants, valeurs enum invalides, dates incohérentes et relations entre zones/aires/ESS incohérentes renvoient une réponse 422. Un conflit d'unicité ou de clé étrangère renvoie 409.

## Compte Super Admin initial

Après sauvegarde de la base, installer les tables avec `php backend/install_schema.php --confirm`. L'installateur s'arrête si le schéma est déjà partiellement présent et conserve les tables historiques étrangères au schéma MVE.

Ensuite, exécuter `backend/create_super_admin.php` en CLI avec `MVE_ADMIN_PASSWORD` (12 caractères minimum). L'adresse de connexion est `admin@gmail.com`; le mot de passe est lu depuis l'environnement, haché avec `password_hash`, et n'est jamais enregistré dans le code. L'exécution du script met à jour le mot de passe du Super Admin existant portant cette adresse.

Le Super Admin peut créer des Zones de Santé, les activer ou les désactiver, et créer/désactiver les comptes Chef de Pilier et Superviseur PCI rattachés à une zone. Une zone désactivée empêche immédiatement ses comptes de se connecter et d'utiliser l'API; sa réactivation restaure leur accès.
