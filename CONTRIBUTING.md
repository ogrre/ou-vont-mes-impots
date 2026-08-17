# Contribuer

Merci de contribuer à rendre les finances publiques françaises plus
compréhensibles et vérifiables. Les contributions doivent rester factuelles,
neutres, reproductibles et traçables jusqu’à des sources faisant autorité.

La couche d’import et les premiers endpoints REST du MVP sont disponibles.

## Signaler un problème de qualité des données

Ouvrez une issue GitHub en indiquant :

- le chiffre, endpoint, jeu de données ou fichier concerné ;
- la valeur attendue et la valeur observée ;
- l’URL de la source officielle et, si possible, la page ou le tableau précis ;
- la période, l’unité, le périmètre comptable et l’institution ;
- si le problème semble venir de l’extraction, de la transformation, de la
  classification, de l’arrondi, des métadonnées ou d’une révision ;
- les étapes ou preuves permettant de reproduire l’écart.

Un chiffre simplement incorrect n’est pas une vulnérabilité. Utilisez la
procédure de sécurité uniquement si le problème résulte d’une faille ou en
révèle une.

## Proposer une nouvelle source officielle

Ouvrez une issue avant d’implémenter un import conséquent. Précisez :

- l’éditeur et l’institution publique responsable ;
- les URL canoniques de présentation et de téléchargement ;
- le titre du jeu de données ou de la publication ;
- les dates de publication et de récupération ;
- la licence ou les conditions de réutilisation ;
- les périodes couvertes ;
- le périmètre comptable et institutionnel ;
- les unités, classifications et la fréquence de mise à jour ;
- les formats disponibles et limites connues ;
- la complémentarité ou les différences avec les sources existantes.

Expliquez également comment automatiser l’import et détecter les révisions ou
les fichiers remplacés.

## Signaler un bug

Recherchez d’abord les issues existantes. Indiquez le comportement attendu, le
comportement observé, les étapes de reproduction, la route ou commande
concernée, l’environnement et un extrait minimal de l’erreur sans secret.

Ne publiez jamais d’identifiants, de fichier d’environnement privé, de jeton
d’accès ou de journal sensible non expurgé.

## Environnement de développement

L’installation recommandée utilise Docker Compose :

```bash
cp .env.example .env
docker compose -f docker-compose-dev.yml up -d --build
docker compose -f docker-compose-dev.yml exec app composer install
docker compose -f docker-compose-dev.yml exec app php artisan key:generate
docker compose -f docker-compose-dev.yml exec app php artisan migrate
docker compose -f docker-compose-dev.yml exec app php artisan db:seed
```

L’application est ensuite disponible sur `http://localhost:8080` :

```bash
curl http://localhost:8080/up
```

Le conteneur PHP n’inclut pas Node.js. Pour modifier les ressources Vite,
utilisez une machine hôte équipée de Node.js et npm :

```bash
npm install
npm run build
```

## Conventions de code

- Respectez les conventions Laravel et l’organisation PSR-4 existante.
- Utilisez Laravel Pint pour formater le code PHP.
- Gardez les contrôleurs ciblés et placez l’analyse des sources et la
  normalisation dans les services d’import dédiés.
- Préférez des types explicites, des noms clairs et de petites transformations
  déterministes.
- Restez neutre et distinguez les faits sources des transformations du projet.
- Mettez la documentation à jour lorsque les routes, commandes, variables,
  schémas ou procédures changent.

Vérifiez le formatage :

```bash
docker compose -f docker-compose-dev.yml exec app ./vendor/bin/pint --test
```

Appliquez-le :

```bash
docker compose -f docker-compose-dev.yml exec app ./vendor/bin/pint
```

Aucun outil d’analyse statique ou de lint Markdown n’est configuré.

## Exigences relatives aux imports

Chaque nouveau jeu de données importé doit documenter :

- son origine et l’institution responsable ;
- l’URL de présentation et l’URL de téléchargement direct, si disponible ;
- les dates de publication et de récupération ;
- le périmètre comptable et institutionnel ;
- la période de référence ;
- l’unité et l’échelle ;
- le format et la feuille, le tableau ou la page concernés ;
- toutes les règles d’extraction, correspondance, filtrage, conversion,
  agrégation, signe, arrondi et normalisation ;
- les règles de révision et les limites connues.

Conservez les données brutes ou assez d’éléments d’acquisition pour auditer les
données normalisées. Chaque valeur publiée doit rester reliée à sa source, son
jeu de données, son import, sa période et l’institution concernée.

N’effectuez aucune correction manuelle non documentée. Toute correction doit
être reproductible dans le code ou dans une table de correspondance versionnée
et relue, avec sa justification et sa source.

Ne comparez ou ne combinez jamais des chiffres relevant du budget de l’État,
des collectivités territoriales, des administrations de sécurité sociale ou de
l’ensemble des administrations publiques sans vérifier explicitement la
compatibilité de leurs périmètres.

## Exigences de test

Exécutez la suite actuelle :

```bash
docker compose -f docker-compose-dev.yml exec app composer test
```

Tout nouveau comportement doit être testé. Les imports doivent couvrir
l’analyse, les unités, transformations, erreurs de validation, l’idempotence et
la provenance. L’API doit tester ses contrats de réponse, filtres, erreurs et la
protection contre les écritures accidentelles. Utilisez les fixtures les plus
petites possible et vérifiez leurs droits de redistribution.

La réussite des tests ne remplace pas la vérification comptable et documentaire
des sources réelles.

Avant une pull request modifiant la logique métier, mesurez aussi la couverture
et exécutez Infection :

```bash
docker compose -f docker-compose-dev.yml exec app composer test:coverage
docker compose -f docker-compose-dev.yml exec app composer test:mutation
```

Le score de mutation ne doit pas descendre sous les seuils définis dans
`infection.json5`. Une mutation survivante significative doit conduire à un
test plus précis ; une mutation équivalente doit être documentée plutôt que
masquée arbitrairement.

## Attentes pour les pull requests

- Limitez chaque pull request à un objectif clair et expliquez sa nécessité.
- Liez les issues associées et signalez tout impact de migration,
  configuration ou compatibilité.
- Résumez les tests et contrôles de formatage effectués.
- Mettez à jour la documentation pour tout changement visible ou opérationnel.
- Pour les données, joignez les métadonnées, règles de transformation et
  preuves de validation ou de rapprochement.
- Ne versionnez pas `.env`, des secrets, dépendances générées ou fichiers
  sources sans rapport avec le changement.
- Identifiez clairement le travail incomplet et les suites nécessaires.

Les mainteneurs peuvent demander des fixtures plus petites ou le retrait de
fichiers dont la licence ou la provenance n’est pas claire.

## Sécurité

Signalez les vulnérabilités en privé selon
[`.github/SECURITY.md`](.github/SECURITY.md), jamais dans une issue publique.

## Licence

En contribuant, vous acceptez que votre contribution soit distribuée sous la
[licence MIT](LICENSE) du projet. Les données restent soumises aux licences et
conditions de leurs sources.
