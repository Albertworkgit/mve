# API PHP native

L'API est servie par `backend/api.php`. Elle utilise les sessions PHP, PDO et des réponses JSON. Les requêtes d'écriture doivent envoyer `Content-Type: application/json` ainsi que l'en-tête `X-CSRF-Token`.

## Authentification

1. `GET /backend/api.php/auth/csrf` ouvre une session et fournit le jeton CSRF.
2. `POST /backend/api.php/auth/login` avec `{ "email": "...", "password": "..." }` ouvre la session authentifiée. La réponse fournit le nouveau jeton CSRF.
3. `GET /backend/api.php/auth/me` retourne l'utilisateur et le jeton CSRF de sa session.
4. `POST /backend/api.php/auth/logout` termine la session.

Le navigateur doit conserver le cookie de session et envoyer `X-CSRF-Token` sur les appels POST, PUT et DELETE.

## Ressources

Les collections acceptent `GET`; la création utilise `POST /ressource`, la modification `PUT /ressource/{id}` et la suppression `DELETE /ressource/{id}`. Le bouton « Supprimer » est disponible sur les registres gérables. Les zones, aires, ESS et comptes opérationnels sont désactivés (`active = 0`); cas, rings, décès et supervisions sont archivés (`deleted_at`). L’interface confirme l’action et précise son effet. Il s’agit d’une suppression logique : les données et leur historique sont préservés, plutôt que d’être effacés définitivement. Les comptes Super Admin et Admin de Zone restent protégés.

| Ressource | Route | Accès d’écriture |
|---|---|---|
| Tableau de bord | `GET /dashboard?zone_id=ID&aire_id=ID` | Lecture authentifiée |
| Zones de Santé | `/zones` | Super Admin |
| Aires de Santé | `/aires` | Super Admin ; Admin de Zone limité à sa zone |
| Établissements de Santé | `/ess` | Super Admin ; Admin de Zone limité aux aires de sa zone |
| Utilisateurs | `/users` | Super Admin ; Admin de Zone (comptes opérationnels de sa zone) ; Chef PCI/EDS (comptes Superviseur PCI et Responsable EDS de sa zone) |
| Cas confirmés | `/cases` | Lecture : tous les rôles opérationnels concernés ; écriture : Super Admin, Admin de Zone et Surveillant épidémiologique |
| Rings | `/rings` | Lecture : Super Admin, Admin de Zone, Chef PCI/EDS, Superviseur PCI, Surveillant épidémiologique ; ouverture : Super Admin, Chef PCI/EDS, PCI, Surveillance ; fermeture après 48 h : Super Admin, Chef PCI/EDS, PCI |
| Décès / EDS | `/deaths` | Lecture : Super Admin, Admin de Zone, Chef PCI/EDS, Responsable EDS ; écriture : Super Admin et Responsable EDS |
| Planification et supervision PCI | `/supervisions` | Lecture : Admin de Zone, Chef PCI/EDS, Superviseur PCI ; planification : Chef PCI/EDS ; compte rendu : Superviseur PCI assigné |
| Superviseurs PCI actifs | `GET /pci-supervisors` | Super Admin, Admin de Zone et Chef PCI/EDS (sélection pour planifier une visite) |
| Activités de riposte | `/activities` | Admin de Zone, Chef PCI/EDS, Superviseur PCI, Responsable EDS, Surveillant épidémiologique |
| Notifications PCI | `GET /notifications` | Superviseur PCI de la zone ; nouveaux cas déclarés par la Surveillance qui n’ont pas encore d’action PCI |
| Rapport filtré | `GET /reports?zone_id=ID&aire_id=ID&date_from=AAAA-MM-JJ&date_to=AAAA-MM-JJ` | Lecture selon le rôle et la zone |

Les utilisateurs non Super Admin sont automatiquement limités à leur Zone de Santé. L’Admin de Zone peut créer, modifier et désactiver les Aires de Santé de sa zone ainsi que les ESS rattachés à ces aires ; l’API refuse les identifiants d’une autre zone, même s’ils sont envoyés directement. Il crée également les comptes opérationnels de sa zone, qui est imposée par le serveur. Le Super Admin peut gérer les structures de toutes les zones.

Le Superviseur PCI peut ouvrir un Ring pour un cas confirmé, compléter une activité PCI sur son Ring actif et fermer un Ring après 48 heures complètes. Lorsqu’un cas saisi par la Surveillance n’a pas encore de Ring, sa notification PCI permet d’ouvrir directement le formulaire de création du Ring.

Le Super Admin peut filtrer le tableau de bord par Zone puis par Aire de Santé ; indicateurs, graphique des cas, répartition des rings et registre des cas suivent le même périmètre. Une Aire choisie hors de la Zone filtrée est rejetée. Les collections acceptent `zone_id`, `limit` (1 à 5000), et selon le module `aire_id`, `statut` ou `pilier`.

## Champs métier importants

- Cas : `zone_id` (Super Admin seulement), `aire_id`, `code_epidemiologique`, `date_confirmation`; `ess_origine_id` est facultatif. `ppl_infecte` indique si le cas est un Prestataire de Première Ligne infecté et alimente l’indicateur PPL du tableau de bord et des rapports.
- Ring : `cas_mve_id` est obligatoire et un cas ne peut pas avoir plus de cinq Rings actifs simultanément. Le Superviseur PCI ou la Surveillance peut ouvrir un Ring autour d’un cas confirmé. Le Superviseur PCI ne peut pas modifier un Ring existant, mais peut le fermer après 48 heures complètes. Pour chaque lieu, le Ring enregistre le nombre ciblé, le nombre décontaminé et les noms des églises, écoles, marchés, parkings ou autres lieux publics. Les valeurs décontaminées ne peuvent pas dépasser les valeurs ciblées.
- Décès : `aire_id`, `date_deces`, `lieu` (`communaute` ou `cte_ess`); un décès au CTE/ESS exige `ess_id`. Le cas confirmé est facultatif pour un décès communautaire suspect.
- Supervision : la planification relie un ESS, une activité PCI, une date prévue et un superviseur PCI assigné. Le compte rendu est signé par cet utilisateur connecté.
- Planification PCI : le Chef PCI/EDS choisit un ESS de sa zone, un superviseur PCI actif de la même zone, l’activité prévue et la date. La planification apparaît dans le registre du superviseur assigné ; celui-ci complète le suivi après la visite et peut corriger son compte rendu.
- Compte rendu de supervision : points forts, points saillants, points à améliorer, recommandations et actions correctrices sont obligatoires. Dans ces champs, chaque point-virgule sépare une information et les éléments sont enregistrés puis affichés séparément. Le scorecard est facultatif et limité à 0–100 %.
- Activité de riposte : pilier (EDS, PCI, surveillance, vaccination, sensibilisation, logistique, coordination ou autre), date, titre, statut, indicateur, cible/réalisé, difficultés et actions correctives. Une activité peut être localisée à une Aire et/ou un ESS.
- Action PCI sur un Ring : l’activité doit référencer un cas et son Ring actif. Elle enregistre les ménages et ESS décontaminés ainsi que les actions menées sur le Ring. Les compteurs du Ring reflètent la dernière activité PCI non archivée ; l’archivage d’une activité recalcule ces valeurs.

## Utilisateurs et rapports

Les rôles sont fixes : Super Admin, Admin de Zone, Chef PCI/EDS (`chef_pilier`), Superviseur PCI, Responsable EDS et Surveillant épidémiologique. Le Super Admin attribue les rôles opérationnels aux comptes des zones. L'Admin de Zone crée et gère les comptes opérationnels de sa zone. Le Chef PCI/EDS peut créer, modifier, activer, désactiver et supprimer logiquement uniquement les comptes Superviseur PCI et Responsable EDS de sa propre zone ; l’API limite aussi la liste des comptes qu’il peut consulter. Pour tout compte non Super Admin, la zone est imposée par l’utilisateur connecté.

Chaque compte de zone ne peut consulter ou modifier que les données autorisées dans sa Zone de Santé. Le Superviseur PCI gère les activités PCI et de sensibilisation et les Rings ; le Responsable EDS gère les activités EDS et enregistre tous les décès, communautaires ou au CTE/ESS ; le Surveillant épidémiologique enregistre les cas et gère les activités de surveillance. Le Chef PCI/EDS coordonne les piliers, gère les comptes PCI/EDS et planifie les visites PCI, mais ne saisit pas les cas ni les décès. Toutes ces permissions sont vérifiées par l'API, y compris pour les suppressions logiques.

Les nouveaux cas saisis par un Surveillant épidémiologique apparaissent dans le panneau de notifications du Superviseur PCI de la même zone. Le panneau se rafraîchit toutes les 60 secondes ; le clic ouvre le formulaire PCI prérempli dès qu’un Ring actif lié au cas existe. Sans Ring actif, la notification indique que la Surveillance doit d’abord l’ouvrir.

Depuis le tableau de bord, les exports Excel (.xlsx), PDF et PowerPoint (.pptx) reprennent les filtres zone/aire et période sélectionnés. Les fichiers ne contiennent que les modules accessibles au rôle authentifié. Les bibliothèques d'export sont chargées côté navigateur ; aucune donnée de rapport n'est envoyée à leur fournisseur.

Les champs requis manquants, valeurs enum invalides, dates incohérentes et relations entre zones/aires/ESS incohérentes renvoient une réponse 422. Un conflit d'unicité ou de clé étrangère renvoie 409.

## Compte Super Admin initial

Après sauvegarde de la base, installer les tables avec `php backend/install_schema.php --confirm`. L'installateur s'arrête si le schéma est déjà partiellement présent et conserve les tables historiques étrangères au schéma MVE.

Ensuite, exécuter `backend/create_super_admin.php` en CLI avec `MVE_ADMIN_PASSWORD` (4 caractères minimum). L'adresse de connexion est `admin@gmail.com`; le mot de passe est lu depuis l'environnement, haché avec `password_hash`, et n'est jamais enregistré dans le code. L'exécution du script met à jour le mot de passe du Super Admin existant portant cette adresse.

Pour une base existante, exécuter `php backend/migrate_case_origin.php` pour ajouter la provenance des cas, puis `php backend/migrate_case_ppl.php` pour ajouter l’indicateur PPL (les cas existants sont considérés non-PPL), `php backend/migrate_ring_details.php` pour ajouter les cibles et décontaminations détaillées des Rings, et `php backend/migrate_soft_delete.php` pour ajouter les champs d’archivage requis par les registres des cas et des Rings. Exécuter ensuite `php backend/migrate_response_activities.php` afin d'ajouter les rôles EDS et Surveillance, la table des activités de riposte et les champs PCI/horodatages des Rings, puis `php backend/migrate_pci_supervision.php` pour ajouter la planification, les prestataires briefés, la photo de présence et les champs de compte rendu PCI. Ces migrations sont idempotentes et ne suppriment pas de données. Les fichiers SQL dans `data_base/migrations` décrivent les migrations manuelles.

Le Super Admin doit fournir dans le champ `admin` de `POST /zones` le nom, l'e-mail et le mot de passe (4 caractères minimum) du nouvel administrateur. La création de la zone et du compte `admin_zone` est atomique : si l'adresse e-mail est déjà prise, rien n'est créé. L'Admin de Zone peut créer et gérer les comptes opérationnels de sa zone, mais ne peut pas modifier un autre Admin de Zone. Une zone désactivée empêche immédiatement ses comptes de se connecter et d'utiliser l'API.
