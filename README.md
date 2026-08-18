# Mais où vont mes impôts ? — API

[![Version de PHP](https://img.shields.io/badge/PHP-~8.4.0-777bb4.svg)](composer.json)
[![Version de Laravel](https://img.shields.io/badge/Laravel-%5E13.0-ff2d20.svg)](composer.json)

L’API Laravel de **« Mais où vont mes impôts ? »**, un projet pédagogique
open source qui vise à rendre les recettes et les dépenses publiques françaises
plus compréhensibles à partir de données officielles, neutres et traçables.

Ce dépôt contient uniquement le backend. Le frontend est maintenu séparément.

> **État du développement :** la couche de données du MVP « Budget de l’État
> français — exécution 2025 », ses imports et les premiers endpoints REST en
> lecture seule sont implémentés.

## Objectifs du projet

- Expliquer l’origine de l’argent public français et la collecte des recettes.
- Montrer la répartition des dépenses entre santé, éducation, défense,
  protection sociale, politiques liées à l’immigration, dette et autres
  services publics.
- Présenter les évolutions dans le temps sans masquer les changements de
  périmètre comptable, de définition ou de méthode.
- Relier chaque chiffre publié à suffisamment d’informations pour le vérifier
  dans sa source officielle.
- Présenter les finances publiques sans opinion ni interprétation politique.

## Fonctionnalités principales

Fonctionnalités disponibles :

- import CSV en streaming des six vues PLRG 2025 par mission, ministère et
  nature, en AE et CP ;
- import XLSX dédié des estimations de recettes du budget général ;
- normalisation en EUR, checksum SHA-256, ligne source et lot d’import ;
- rejet des doublons et réconciliation des totaux PLRG.

Fonctionnalités encore prévues :

- des séries historiques et des ventilations des recettes et dépenses ;
- des imports supplémentaires depuis les portails open data officiels.

## Vue d’ensemble de l’architecture

Le projet est une application Laravel 13 utilisant PostgreSQL en développement.
Docker Compose définit trois services de développement :

- `app` : PHP-FPM 8.4 avec les extensions PostgreSQL, Redis, Xdebug et autres
  extensions nécessaires à l’image de développement ;
- `web` : Nginx, publié par défaut sur le port `8080` ;
- `pgsql` : PostgreSQL 16 avec un volume Docker persistant.

En production, le [`Dockerfile`](Dockerfile) construit un conteneur autonome
PHP-FPM 8.4 + Nginx, destiné à Dokploy. PostgreSQL reste un service Dokploy
distinct sur le même réseau privé.

Laravel utilise par défaut la base de données pour les files d’attente, le cache
et les sessions. Il s’agit à ce stade de réglages du framework, et non de la
preuve qu’un pipeline d’import asynchrone ou des sessions visiteurs existent.

Le dépôt contient encore le modèle `User` et les migrations utilisateur fournis
par Laravel. Aucune route d’authentification ou fonctionnalité de compte
visiteur n’est enregistrée.

## Modèle de données et normalisation

Le modèle métier sépare deux couches :

1. **Données sources** : le fichier original reste la référence brute ; son
   checksum, son descripteur, le lot et la ligne source sont conservés.
2. **Données applicatives normalisées** : elles représentent sous forme
   relationnelle les institutions, jeux de données, périodes, périmètres
   comptables, classifications, unités et observations financières.

PostgreSQL stockera les données relationnelles normalisées. JSONB pourra être
utilisé pour des métadonnées propres à une source ou des données brutes dont la
structure varie, mais ne devra pas remplacer les champs relationnels utilisés
pour le filtrage, les relations ou la validation.

Les transformations sont déterministes, documentées et testées. Les
unités, conventions de signe, classifications, révisions, valeurs manquantes et
arrondis devront être traités explicitement. Les valeurs brutes devront rester
accessibles afin d’auditer un chiffre normalisé sans correction manuelle non
documentée.

## Provenance et traçabilité

Chaque chiffre publié devra rester relié :

- à sa source officielle et à son URL ;
- au jeu de données ou à la publication précise ;
- à l’exécution d’import qui l’a acquis ou produit ;
- à sa période de référence et à sa date de publication ;
- à l’institution publique concernée ;
- à son périmètre comptable, sa classification et son unité ;
- aux transformations appliquées entre valeur brute et valeur normalisée.

Les imports devront enregistrer la version de la source, la date de récupération
et, si possible, des informations d’intégrité. Lorsqu’un tableau PDF est
converti en données structurées, la méthode d’extraction et l’emplacement du
tableau d’origine devront également être documentés.

La chaîne de provenance est implémentée par `sources`, `datasets`,
`dataset_files`, `import_batches` et `financial_observations`. La publication
reste bloquée tant que les métadonnées critiques recensées dans
[`docs/datasets.md`](docs/datasets.md) ne sont pas complétées.

## Technologies

- PHP `~8.4.0`
- Laravel `^13.0`
- PostgreSQL 16 avec Docker Compose
- Nginx 1.27 et PHP-FPM 8.4 dans les conteneurs de développement
- PHPUnit `^12.5`
- Laravel Pint `^1.27`
- OpenSpout `^5.10` pour la lecture XLSX en streaming
- Docker et Dokploy pour l’image et l’orchestration de production

## Prérequis

Pour l’installation recommandée avec conteneurs :

- Docker avec Docker Compose ;
- Git.

Pour une installation native :

- PHP 8.4 avec les extensions requises par Laravel et PostgreSQL ;
- Composer 2 ;
- PostgreSQL ;
- Node.js et npm uniquement pour compiler les ressources Vite actuelles.

## Installation locale

Clonez le dépôt puis préparez l’environnement :

```bash
git clone https://github.com/ogrre/ou-vont-mes-impots
cd ou-vont-mes-impots
cp .env.example .env
docker compose -f docker-compose-dev.yml up -d --build
docker compose -f docker-compose-dev.yml exec app composer install
docker compose -f docker-compose-dev.yml exec app php artisan key:generate
```

L’image PHP inclut Composer, mais pas Node.js. Exécutez `npm install` et
`npm run build` sur la machine hôte si vous devez recompiler les ressources de
la page d’accueil.

## Configuration de l’environnement

`.env.example` contient les valeurs de développement :

| Variable | Rôle | Valeur par défaut |
| --- | --- | --- |
| `APP_URL` | URL de base de l’application | `http://localhost:8080` |
| `APP_DEBUG` | Affichage détaillé des erreurs locales | `true` |
| `DB_CONNECTION` | Pilote de base de données Laravel | `pgsql` |
| `DB_HOST` | Hôte PostgreSQL dans Compose | `pgsql` |
| `DB_PORT` | Port PostgreSQL dans Compose | `5432` |
| `DB_DATABASE` | Nom de la base | `ovmi` |
| `DB_USERNAME` | Utilisateur PostgreSQL | `ovmi` |
| `DB_PASSWORD` | Mot de passe PostgreSQL local | `ovmi` |
| `DOCKER_WEB_PORT` | Port hôte de Nginx | `8080` |
| `DOCKER_DB_PORT` | Port PostgreSQL exposé sur l’hôte | `5432` |

Ces identifiants sont réservés au développement local. Ne versionnez jamais un
fichier `.env` réel ni des secrets de production.

Les variables de production et leur gestion dans Dokploy sont documentées dans
[`docs/deployment-dokploy.md`](docs/deployment-dokploy.md).

## Initialisation de la base de données

```bash
docker compose -f docker-compose-dev.yml up -d
docker compose -f docker-compose-dev.yml exec app php artisan migrate --seed
```

Les seeders créent uniquement les référentiels et les descripteurs d’import.
Ils ne contiennent aucun montant officiel.

## Exécution des imports

Après `php artisan migrate --seed` :

```bash
php artisan dataset:import state-expenditure-2025-mission-ae data/depenses-par-mission-plrg-ae-2025.csv
php artisan dataset:import state-expenditure-2025-mission-cp data/depenses-par-mission-plrg-cp-2025.csv
php artisan dataset:import state-expenditure-2025-ministry-ae data/depenses-par-ministeres-plrg-ae-2025.csv
php artisan dataset:import state-expenditure-2025-ministry-cp data/depenses-par-ministeres-plrg-cp-2025.csv
php artisan dataset:import state-expenditure-2025-nature-ae data/depenses-par-nature-plrg-ae-2025.csv
php artisan dataset:import state-expenditure-2025-nature-cp data/depenses-par-nature-plrg-cp-2025.csv
php artisan data:validate state-expenditure-2025
php artisan dataset:import state-general-budget-revenue-2025-2026 data/econ-fin-pub-recettes-budget.xlsx
```

Dans Docker, préfixez chaque commande par
`docker compose -f docker-compose-dev.yml exec app`. Un même checksum est rejeté
pour un même descripteur.

## Lancement local de l’API

Avec Docker Compose :

```bash
docker compose -f docker-compose-dev.yml up -d
curl http://localhost:8080/up
```

Dans un environnement PHP natif configuré :

```bash
php artisan serve
```

Le serveur natif utilise par défaut `http://127.0.0.1:8000`.

## Documentation et endpoints de l’API

La documentation interactive OpenAPI est disponible à l’adresse
[`/docs/api`](http://localhost:8080/docs/api). Le document OpenAPI 3.1 au format
JSON, utilisable par des générateurs de clients et d’autres outils, est exposé
sur [`/docs/api.json`](http://localhost:8080/docs/api.json). Ces deux routes sont
publiques, comme l’API en lecture seule.

L’API v1 est publique, sans authentification et en lecture seule :

| Méthode | Chemin | Rôle |
| --- | --- | --- |
| `GET` | `/up` | Contrôle de santé Laravel |
| `GET` | `/api/v1/version` | Versions de l’application et de l’API |
| `GET` | `/api/v1/state-expenditure` | Dépenses exécutées du budget de l’État |
| `GET` | `/api/v1/state-revenue` | Estimations de recettes du budget général |

Exemples :

```bash
curl 'http://localhost:8080/api/v1/state-expenditure?year=2025&classification=mission&measure=cp'
curl 'http://localhost:8080/api/v1/state-revenue?year=2025&status=revised_estimate'
```

Les filtres, valeurs autorisées et contrats de réponse sont décrits dans
[`docs/api.md`](docs/api.md). Les montants sont sérialisés sous forme de chaînes
décimales en EUR afin de préserver leur précision côté JavaScript.

`[TODO: ajouter l’URL de production de l’API lorsqu’elle sera disponible]`

## Déploiement avec Dokploy

La branche `main` est la source de production. Dokploy construit le Dockerfile,
expose le port `8080`, contrôle `/up` et connecte l’application à un PostgreSQL
privé doté d’un volume persistant. Le déploiement automatique ne se déclenche
qu’après fusion d’une pull request validée par la CI.

Consultez [`docs/deployment-dokploy.md`](docs/deployment-dokploy.md) pour la
création des services, les variables, les migrations, les sauvegardes et les
tests après déploiement.

## Exécution des tests

La configuration PHPUnit utilise SQLite en mémoire :

```bash
docker compose -f docker-compose-dev.yml exec app composer test
```

Pour une installation native :

```bash
composer test
```

La suite couvre les imports, leurs erreurs, l’idempotence, la provenance, les
sémantiques comptables, la réconciliation et les contrats HTTP.

## Qualité du code

Laravel Pint est installé pour le formatage PHP :

```bash
docker compose -f docker-compose-dev.yml exec app ./vendor/bin/pint --test
docker compose -f docker-compose-dev.yml exec app ./vendor/bin/pint
```

La première commande vérifie le formatage ; la seconde l’applique. Larastan
exécute PHPStan au niveau 6 :

```bash
docker compose -f docker-compose-dev.yml exec app composer analyse
```

Aucun linter Markdown n’est configuré.

La couverture PHPUnit et les tests de mutation Infection utilisent Xdebug dans
le conteneur :

```bash
docker compose -f docker-compose-dev.yml exec app composer test:coverage
docker compose -f docker-compose-dev.yml exec app composer test:mutation
```

Infection impose un MSI global et couvert minimal de 70 %.

## Structure du projet

```text
app/Enums/           Vocabulaires financiers fermés
app/Models/          Modèles Eloquent et relations de provenance
app/Services/        Import et réconciliation
app/Console/Commands Commandes d’import et de validation
bootstrap/           Initialisation de Laravel
config/              Configuration de l’application et des services
data/                Fichiers sources locaux ; tous ne sont pas validés
database/            Migrations et seeders de référentiels/descripteurs
docs/                Architecture, catalogue et jeux en attente
docker/              Configurations des conteneurs de développement/production
public/              Point d’entrée HTTP et ressources publiques
routes/              Routes web, API et console
tests/               Tests unitaires et fonctionnels PHPUnit
Dockerfile           Image de production PHP 8.4 pour Dokploy
docker-compose-dev.yml Environnement local
```

## Sources de données

Le catalogue et les lacunes de provenance sont détaillés dans
[`docs/datasets.md`](docs/datasets.md) et
[`docs/pending-datasets.md`](docs/pending-datasets.md).

`[TODO: documenter chaque source officielle approuvée, son éditeur, son URL
canonique, sa date de publication, ses conditions de réutilisation, son
périmètre comptable, sa période de référence et sa date de récupération]`

Seules des données officielles ou clairement reconnues comme faisant autorité
devront étayer les chiffres publiés. Ajouter un fichier à `data/` ne suffit pas
à l’approuver comme source.

## Exactitude et limites des données

Les chiffres de finances publiques dépendent fortement des définitions et du
périmètre comptable. Des chiffres issus de périmètres différents ne doivent pas
être comparés sans vérifier la compatibilité de leur périmètre, période, unité,
classification et méthode.

La « dépense publique » peut désigner le budget de l’État, les collectivités
territoriales, les administrations de sécurité sociale ou l’ensemble des
administrations publiques. Ces périmètres ne sont pas interchangeables. Les
sources peuvent aussi présenter des engagements, paiements, crédits,
dépenses consolidées, mesures de comptabilité nationale ou estimations
révisées.

L’API devra exposer ces précisions avec les valeurs. Les données importées
peuvent comporter des erreurs sources, révisions, erreurs d’extraction, lacunes
ou écarts d’arrondi. Pour tout usage important, consultez la publication
d’origine citée.

## Contribution

Les contributions sont bienvenues, notamment sur la traçabilité, les règles de
normalisation, les tests et la documentation. Consultez
[`CONTRIBUTING.md`](CONTRIBUTING.md) avant d’ouvrir une issue ou une pull
request. Toute contribution de données doit identifier sa source officielle et
documenter chaque transformation ; les corrections manuelles non documentées
ne sont pas acceptées.

## Sécurité

Ne divulguez pas de vulnérabilité dans une issue publique. Suivez les
instructions de [`.github/SECURITY.md`](.github/SECURITY.md).

Un chiffre, une classification ou une métadonnée incorrecte relève normalement
de la qualité des données, sauf si le problème résulte d’une faille de sécurité
ou en révèle une.

## Feuille de route

- Compléter et vérifier la provenance et les licences des sources présentes.
- Étendre l’API REST avec les futures séries historiques validées.
- Ajouter les calculs de pourcentage avec un dénominateur explicite.
- Publier une spécification d’API et connecter le frontend séparé.
- Déployer le MVP sur Dokploy et compléter les sauvegardes PostgreSQL.

Ces éléments sont prévus, mais ne constituent ni des fonctionnalités terminées
ni des dates de livraison.

## Licence

Ce projet est distribué sous **licence MIT**. Consultez [`LICENSE`](LICENSE).
Les jeux de données et publications peuvent avoir leurs propres licences ou
conditions de réutilisation, que la licence MIT ne remplace pas.

## Avertissement

> **Ce projet indépendant n’est ni affilié, ni approuvé, ni exploité par le
> gouvernement français ou une administration publique.**

Il s’agit d’un outil pédagogique, et non d’une publication comptable
officielle, d’un conseil juridique ou financier, ni d’un substitut aux sources
d’origine. Le projet vise la neutralité et la traçabilité, mais ne peut garantir
que tous les chiffres sont complets, à jour et exempts d’erreur.
