# Mini-Blog

Blog personnel avec dashboard analytics intégré, construit avec Symfony 8. Chaque technologie utilisée répond à un besoin concret — rien n'est artificiel.

## Stack technique

| Technologie | Rôle | Pourquoi |
|---|---|---|
| **Symfony 8** | Framework PHP | Routing, Security, Doctrine ORM, formulaires, validation — tout le socle applicatif |
| **FrankenPHP / Caddy** | Serveur web | Serveur PHP moderne avec HTTPS automatique, HTTP/3 et worker mode pour des performances élevées |
| **PostgreSQL 16** | Base de données | Stockage des utilisateurs et articles, requêtes relationnelles (likes, tri, recherche) |
| **Redis 7** | Cache applicatif | Cache des articles populaires (top 5) et des derniers articles, compteur de likes en temps réel |
| **Matomo** | Analytics web | Alternative self-hosted à Google Analytics — visiteurs uniques, pages vues, articles populaires |
| **MariaDB 11** | Base Matomo | Base de données dédiée à Matomo, indépendante de PostgreSQL |
| **Twig** | Moteur de templates | Héritage de templates, blocs, partials — rendu HTML côté serveur |
| **Stimulus** | JavaScript | Interactions dynamiques sans SPA — likes AJAX, recherche temps réel, dark mode, stats |
| **AssetMapper** | Gestion des assets | Import JS natif via `importmap.php`, sans Webpack ni Vite |
| **CSS fait main** | Styles | Design responsive custom avec variables CSS, dark mode, animations — sans framework CSS |

## Fonctionnalites

### Partie publique

- **Liste des articles** avec recherche/filtre en temps réel (Stimulus `search_controller`)
- **Lecture d'un article** avec slug URL-friendly
- **Likes en AJAX** — le compteur se met à jour sans rechargement (Stimulus `like_controller`, cache Redis)
- **Dark mode** avec persistance localStorage (Stimulus `darkmode_controller`)
- **Animations au scroll** — les éléments apparaissent progressivement via IntersectionObserver (`reveal_controller`)

### Partie admin (protégée par Symfony Security)

- CRUD complet des articles
- Dashboard analytics avec données Matomo (visiteurs, pages vues, articles populaires)
- Inscription et connexion utilisateur

## Architecture Docker

Le projet tourne entièrement sous Docker avec 5 services :

```
php            FrankenPHP + Caddy       → ports 80/443 (HTTPS auto)
database       PostgreSQL 16            → données applicatives
redis          Redis 7 Alpine           → cache et compteurs de likes
matomo         Matomo                   → analytics (port 8080)
matomo-db      MariaDB 11              → base dédiée Matomo
```

## Ou chaque technologie intervient

### Symfony 8

Le framework structure toute l'application :

- **Controllers** — `HomeController` (accueil), `ArticleController` (CRUD, likes, recherche), `SecurityController` (auth)
- **Entités Doctrine** — `Users` et `Article` avec relation OneToMany
- **Security** — authentification par formulaire, protection des routes admin par `ROLE_USER`
- **Validation** — contraintes sur les entités (`NotBlank`, `Email`, `PositiveOrZero`)

### Redis

Utilisé pour le cache applicatif et les compteurs via `ArticleCacheService` :

- **Compteur de likes** — incrémenté directement dans Redis (rapide), synchronisé vers PostgreSQL
- **Top 5 articles** — cache des articles les plus likés (TTL 1h)
- **Derniers articles** — cache de la homepage (TTL 1h)
- **Invalidation** — le cache se vide automatiquement à la création, modification ou like d'un article
- **Fallback** — si Redis est indisponible, le controller interroge directement la base

### Stimulus

4 controllers JavaScript légers, chacun avec une responsabilité unique :

| Controller | Fichier | Ce qu'il fait |
|---|---|---|
| `like` | `like_controller.js` | POST AJAX vers `/article/{id}/like`, met à jour le compteur sans reload |
| `search` | `search_controller.js` | Filtre les cartes d'articles en temps réel sur `keyup` |
| `darkmode` | `darkmode_controller.js` | Toggle une classe CSS sur `<body>`, persiste le choix dans localStorage |
| `reveal` | `reveal_controller.js` | Anime l'apparition des éléments au scroll via IntersectionObserver |

### CSS fait main

Un seul fichier `app.css` (+ `login.css` pour l'authentification) :

- Variables CSS pour le theming light/dark (`--bg`, `--text`, `--accent`...)
- Grille responsive (3 → 2 → 1 colonnes)
- Navigation fixe avec backdrop blur
- Animations (`fadeUp`, `pulse` sur le bouton like)
- Design glassmorphism pour les pages login/register

### Matomo

Analytics self-hosted intégré de deux façons :

- **Tracking** — tag JavaScript dans `base.html.twig` pour collecter les visites
- **API Reporting** — `MatomoApiService` interroge l'API Matomo pour afficher les stats dans le dashboard admin

## Tests (TDD)

Le développement suit le cycle **Rouge → Vert → Refactor**. Structure des tests :

```
tests/
├── Unit/           # Services isolés (mock HttpClient pour Matomo, logique cache)
├── Functional/     # Controllers HTTP (codes de réponse, redirections, persistance)
└── Integration/    # Services réels contre Redis et PostgreSQL
```

```bash
# Tous les tests
docker compose exec php bin/phpunit

# Par catégorie
docker compose exec php bin/phpunit tests/Unit
docker compose exec php bin/phpunit tests/Functional
docker compose exec php bin/phpunit tests/Integration
```

## Demarrage rapide

```bash
# Cloner et lancer
git clone <repo-url> mini-blog
cd mini-blog
docker compose up -d --wait

# Migrations
docker compose exec php bin/console doctrine:migrations:migrate

# Accès
# App       → https://localhost
# Matomo    → http://localhost:8080
```

## Credits

Basé sur [symfony-docker](https://github.com/dunglas/symfony-docker) par Kévin Dunglas.
