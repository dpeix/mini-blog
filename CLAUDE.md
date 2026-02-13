# Mini-Blog — Project Context

## Overview

Blog personnel avec dashboard analytics intégré. Chaque technologie utilisée répond à un besoin réel — rien n'est artificiel.

**Stack :** Symfony 8 · FrankenPHP/Caddy · PostgreSQL 16 · Redis · Matomo · Twig · Stimulus · CSS fait main

## TDD — Règle absolue

Tout développement suit le cycle **Rouge → Vert → Refactor**. Aucun code de production ne doit être écrit sans test préalable.

### Structure des tests

```
tests/
├── Unit/           # Services isolés (MatomoApiService, logique cache)
├── Functional/     # Controllers HTTP (requêtes, réponses, redirections)
└── Integration/    # Services réels (Redis, base de données)
```

### Commandes

```bash
# Lancer les tests dans le conteneur
docker compose exec php bin/phpunit

# Tests unitaires uniquement
docker compose exec php bin/phpunit tests/Unit

# Tests fonctionnels uniquement
docker compose exec php bin/phpunit tests/Functional

# Tests d'intégration uniquement
docker compose exec php bin/phpunit tests/Integration
```

### Principes

- PHPUnit 13 avec `dama/doctrine-test-bundle` pour l'isolation DB
- Chaque feature commence par un test qui échoue
- Les tests fonctionnels vérifient les codes HTTP, redirections, persistance en base
- Les tests unitaires mockent les dépendances externes (HttpClient pour Matomo)
- Les tests d'intégration tournent contre les vrais services Docker (Redis, PostgreSQL)

## Architecture Docker

### Services existants (symfony-docker / Dunglas)

- **php** — FrankenPHP + Caddy (ports 80, 443)
- **database** — PostgreSQL 16

### Services à ajouter dans compose.yaml

- **redis** — `redis:7-alpine` — Cache applicatif
- **matomo** — `matomo:latest` — Analytics web (port 8080)
- **matomo-db** — `mariadb` — Base dédiée Matomo (indépendante de PostgreSQL)

## Entité

### Article

| Champ     | Type     | Notes                          |
|-----------|----------|--------------------------------|
| id        | int      | Auto-generated                 |
| title     | string   | Required                       |
| slug      | string   | Unique, généré depuis le titre |
| content   | text     | Corps de l'article             |
| likes     | int      | Défaut: 0                      |
| createdAt | datetime | Auto-set à la création         |

## Fonctionnalités

### Partie publique

- Liste des articles avec recherche/filtre en temps réel (Stimulus)
- Page de lecture d'un article
- Système de "like" en AJAX (sans rechargement)
- Mode sombre (dark mode) avec persistance localStorage

### Partie admin (protégée par Symfony Security)

- CRUD complet des articles
- Dashboard analytics avec données Matomo (visiteurs, pages vues, articles populaires)

## Services PHP

### MatomoApiService

- Utilise `HttpClient` pour interroger l'API Reporting de Matomo
- Récupère : visiteurs uniques, pages vues, pages les plus consultées
- Développé en TDD (mock du HttpClient dans les tests unitaires)

### Cache Redis

- Pool Symfony Cache avec adapter Redis
- Cache les articles populaires (top 5 par likes)
- Invalidation automatique sur : création, modification, like d'un article
- Configuration dans `config/packages/cache.yaml`

## Stimulus Controllers

| Controller            | Rôle                                                       |
|-----------------------|------------------------------------------------------------|
| `like_controller`     | POST AJAX pour liker, met à jour le compteur sans reload   |
| `search_controller`   | Filtre la liste d'articles en temps réel (keyup)           |
| `darkmode_controller` | Toggle classe CSS sur body, persiste dans localStorage     |
| `stats_controller`    | Récupère les données Matomo API et affiche dans le dashboard |

## Frontend

- **CSS fait main** — Pas de Bootstrap ni Tailwind. Layout responsive, design propre
- **AssetMapper** — Pas de Webpack ni Vite. Import via `importmap.php`
- **Twig** — Héritage de templates (`base.html.twig`), blocs, partials
- Le tag de tracking Matomo est intégré dans `base.html.twig`

## Ordre de développement

1. Ajouter Redis, Matomo, MariaDB dans `compose.yaml`
2. Créer l'entité Article + migrations
3. Tests fonctionnels du CRUD → puis controllers et templates Twig
4. CSS responsive fait main
5. Stimulus controllers (like, search, darkmode, stats)
6. Tests unitaires du cache → puis configuration Redis et logique d'invalidation
7. Tests du service Matomo → puis intégration tracking + API + dashboard admin

## Commandes utiles

```bash
# Démarrer la stack
docker compose up -d

# Console Symfony
docker compose exec php bin/console

# Migrations
docker compose exec php bin/console doctrine:migrations:migrate

# Créer une migration
docker compose exec php bin/console make:migration

# Vider le cache Symfony
docker compose exec php bin/console cache:clear

# Accès Matomo
# http://localhost:8080 (une fois le service ajouté)
```

## Conventions

- Code en anglais, contexte projet en français
- Pas de sur-ingénierie : chaque fichier a un but, chaque technologie une justification
- Security : accès admin protégé, pas de secrets dans le code
- Pas d'API Platform ni EasyAdmin pour ce projet (on utilise des controllers et templates Twig classiques)
