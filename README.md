# Mais où vont mes impôts ? — API

[![Version de PHP](https://img.shields.io/badge/PHP-%5E8.3-777bb4.svg)](composer.json)
[![Version de Laravel](https://img.shields.io/badge/Laravel-%5E13.0-ff2d20.svg)](composer.json)

L’API Laravel de **« Mais où vont mes impôts ? »**, un projet pédagogique
open source qui vise à rendre les recettes et les dépenses publiques françaises
plus compréhensibles à partir de données officielles, neutres et traçables.

Ce dépôt contient uniquement le backend. Le frontend est maintenu séparément.

> **État du développement :** ce dépôt est actuellement un squelette Laravel.
> Le modèle de données financières, les imports et les endpoints REST décrits
> comme prévus ci-dessous ne sont pas encore implémentés. La seule route
> applicative est la page d’accueil Laravel sur `GET /` ; Laravel expose
> également une route de santé sur `GET /up`.

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

Les fonctionnalités suivantes sont prévues et en cours de conception :

- une API REST publique en lecture seule, sans compte ni authentification pour
  les visiteurs ;
- des imports depuis des fichiers CSV, Excel, des données extraites de PDF et
  des portails open data officiels ;
- la normalisation de sources hétérogènes dans un modèle relationnel cohérent ;
- des séries historiques et des ventilations des recettes et dépenses ;
- une traçabilité par source, institution, période et import.

Aucune de ces fonctionnalités métier n’est actuellement exposée.

## Vue d’ensemble de l’architecture

Le projet est une application Laravel 13 utilisant PostgreSQL en développement.
Docker Compose définit trois services :

- `app` : PHP-FPM 8.4 avec les extensions PostgreSQL, Redis, Xdebug et autres
  extensions nécessaires à l’image de développement ;
- `web` : Nginx, publié par défaut sur le port `8080` ;
- `pgsql` : PostgreSQL 16 avec un volume Docker persistant.

Laravel utilise par défaut la base de données pour les files d’attente, le cache
et les sessions. Il s’agit à ce stade de réglages du framework, et non de la
preuve qu’un pipeline d’import asynchrone ou des sessions visiteurs existent.

Le dépôt contient encore le modèle `User` et les migrations utilisateur fournis
par Laravel. Aucune route d’authentification ou fonctionnalité de compte
visiteur n’est enregistrée.

## Modèle de données et normalisation

Le modèle métier n’est pas encore implémenté. L’approche prévue sépare deux
couches :

1. **Données brutes importées** : elles conservent aussi fidèlement que
   possible les enregistrements sources ou les données extraites, avec les
   métadonnées d’acquisition et les diagnostics d’import.
2. **Données applicatives normalisées** : elles représentent sous forme
   relationnelle les institutions, jeux de données, périodes, périmètres
   comptables, classifications, unités et observations financières.

PostgreSQL stockera les données relationnelles normalisées. JSONB pourra être
utilisé pour des métadonnées propres à une source ou des données brutes dont la
structure varie, mais ne devra pas remplacer les champs relationnels utilisés
pour le filtrage, les relations ou la validation.

Les transformations devront être déterministes, documentées et testées. Les
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

Ce modèle de provenance est prévu mais pas encore implémenté. Les migrations
actuelles ne contiennent que les tables Laravel par défaut pour les
utilisateurs, réinitialisations de mot de passe, sessions, caches et files
d’attente.

## Technologies

- PHP `^8.3`
- Laravel `^13.0`
- PostgreSQL 16 avec Docker Compose
- Nginx 1.27 et PHP-FPM 8.4 dans les conteneurs de développement
- PHPUnit `^12.5`
- Laravel Pint `^1.27`
- Vite 8 et Tailwind CSS 4 pour les ressources de la page d’accueil actuelle

## Prérequis

Pour l’installation recommandée avec conteneurs :

- Docker avec Docker Compose ;
- Git.

Pour une installation native :

- PHP 8.3 ou ultérieur avec les extensions requises par Laravel et PostgreSQL ;
- Composer 2 ;
- PostgreSQL ;
- Node.js et npm uniquement pour compiler les ressources Vite actuelles.

## Installation locale

Clonez le dépôt puis préparez l’environnement :

```bash
git clone [TODO: ajouter l’URL du dépôt]
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

## Initialisation de la base de données

```bash
docker compose -f docker-compose-dev.yml up -d
docker compose -f docker-compose-dev.yml exec app php artisan migrate
```

Les migrations actuelles créent uniquement les tables Laravel par défaut.
Aucune table de finances publiques ni donnée d’exemple métier n’est
implémentée. `DatabaseSeeder` crée un utilisateur fictif lorsqu’il est lancé
explicitement ; il n’est pas nécessaire au démarrage.

## Exécution des imports

**En cours de développement.** Il n’existe actuellement aucune commande ou
service d’import personnalisé. La présence de fichiers dans `data/` ne signifie
pas qu’ils ont été importés ou validés.

Lorsque des commandes d’import seront ajoutées, leur syntaxe exacte, les
fichiers attendus, leur idempotence, leurs validations et les enregistrements de
provenance produits devront être documentés ici.

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

L’API REST n’est **pas encore implémentée** et aucun fichier `routes/api.php`
n’existe. Les routes actuelles sont :

| Méthode | Chemin | Rôle |
| --- | --- | --- |
| `GET` | `/` | Page d’accueil Laravel par défaut |
| `GET` | `/up` | Contrôle de santé Laravel |

`/up` relève de l’infrastructure du framework et non des finances publiques.

La future documentation devra préciser les schémas de réponse, filtres,
pagination, unités, périmètres comptables, périodes, liens de provenance,
erreurs et règles de versionnage.

`[TODO: ajouter l’URL de production de l’API lorsqu’elle sera disponible]`

## Exécution des tests

La configuration PHPUnit utilise SQLite en mémoire :

```bash
docker compose -f docker-compose-dev.yml exec app composer test
```

Pour une installation native :

```bash
composer test
```

La suite actuelle ne contient que les exemples unitaires et fonctionnels de
Laravel. Les tests métier, d’import, de provenance et de contrat d’API restent
à écrire.

## Qualité du code

Laravel Pint est installé pour le formatage PHP :

```bash
docker compose -f docker-compose-dev.yml exec app ./vendor/bin/pint --test
docker compose -f docker-compose-dev.yml exec app ./vendor/bin/pint
```

La première commande vérifie le formatage ; la seconde l’applique. Aucun outil
d’analyse statique, service de couverture ou linter Markdown n’est configuré.

## Structure du projet

```text
app/                 Code Laravel (actuellement un squelette)
bootstrap/           Initialisation de Laravel
config/              Configuration de l’application et des services
data/                Sources candidates, pas encore importées ni validées
database/            Migrations, fabriques et seeders
docker/              Image PHP-FPM et configuration Nginx
public/              Point d’entrée HTTP et ressources publiques
resources/           CSS, JavaScript et vue Blade de la page actuelle
routes/              Routes web et console ; aucune route API
tests/               Tests unitaires et fonctionnels PHPUnit
docker-compose-dev.yml
```

## Sources de données

Le dossier `data/` contient actuellement des fichiers candidats aux formats
CSV, XLS, XLSX et PDF. Le dépôt ne fournit pas encore de catalogue des sources,
de provenance exploitable par machine, d’import ni assez de documentation pour
vérifier l’éditeur et l’URL d’acquisition de chaque fichier.

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

- Définir le modèle métier et les contraintes de provenance.
- Cataloguer les sources candidates et vérifier leurs licences.
- Implémenter des imports reproductibles et validés.
- Ajouter des tests métier, d’import et de rapprochement.
- Concevoir et implémenter l’API REST versionnée en lecture seule.
- Publier une spécification d’API et connecter le frontend séparé.
- Ajouter l’intégration continue et des contrôles de qualité automatisés.

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
