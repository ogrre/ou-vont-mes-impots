# Architecture des données financières

## Périmètre du MVP

Le MVP représente l’**exécution 2025 du budget de l’État français**. Il ne représente ni les collectivités territoriales, ni les administrations de sécurité sociale, ni l’ensemble des administrations publiques.

Les six CSV PLRG décrivent le même périmètre sous trois classifications officielles (`mission`, `ministry`, `nature`) et deux mesures :

- `commitment_authorization` (`AE`) : autorisations d’engagement ;
- `payment_credit` (`CP`) : crédits de paiement.

AE et CP sont persistées séparément et ne doivent jamais être additionnées. PLRG est enregistré avec `status = executed`, jamais comme prévision, PLF ou estimation initiale.

## Modèle normalisé

`financial_observations` est la table de faits. Chaque observation relie un montant à un jeu de données, un fichier, un lot d’import, une année, un périmètre comptable, une composante budgétaire et un élément de classification.

Les quatre composantes correspondent exactement aux colonnes sources : budget général, budgets annexes, comptes d’affectation spéciale et comptes de concours financiers. Les classifications sont plates pour ce MVP ; `parent_id` n’est pas renseigné sans preuve d’une hiérarchie source.

Le classeur de recettes utilise la classification séparée `state_budget_revenue`. Ses lignes de détail et agrégats officiels restent à plat, avec un indicateur empêchant de les sommer naïvement.

| Colonne source | Année | Statut |
| --- | ---: | --- |
| Évaluations 2025 initiales | 2025 | `initial_estimate` |
| Évaluations 2025 révisées | 2025 | `revised_estimate` |
| Projet de loi de finances 2026 | 2026 | `budget_bill` |

Une estimation révisée n’est jamais assimilée à une exécution.

## Montants

Les sources comprises expriment les montants en milliards d’euros. Les valeurs sont stockées en EUR dans un `numeric(22, 2)`. Le CSV est converti par calcul décimal sur chaîne, sans `float`. La valeur et l’unité sources sont conservées en JSON.

Les pourcentages ne sont pas persistés. Ils devront être calculés avec un dénominateur partageant exactement année, périmètre, statut, mesure et composantes avec le numérateur.

## Provenance et cycle d’import

```text
financial_observation
  → import_batch
  → dataset_file
  → dataset
  → source
```

Le fichier original reste la donnée brute. Une table permanente copiant toutes les lignes brutes n’apporte pas assez de valeur pour ces petits fichiers. Le checksum SHA-256, le nom du fichier, la ligne source, la valeur/unité initiales et les métadonnées de transformation permettent l’audit. Les erreurs annulent transactionnellement les observations et restent enregistrées sur le lot.

L’unicité `(dataset_file_id, checksum)` rend l’import d’un contenu idempotent. Une contrainte sémantique protège aussi les observations, complétée par un identifiant source déterministe.

## Descripteurs fiables

Les sémantiques comptables ne sont jamais déduites du nom reçu en ligne de commande. Les six descripteurs PLRG encodent année, périmètre, statut, classification, mesure, flux et unité. Le nom attendu sert uniquement de documentation.

## Réconciliation

`data:validate state-expenditure-2025` compare, pour chaque mesure et chaque composante, les sommes obtenues par mission, ministère et nature. Un groupe absent ou un écart produit un échec visible. Aucun écart n’est ignoré et aucune tolérance n’est actuellement appliquée.

## Publication

`Dataset::isPublishable()` exige une source officielle, un producteur, une URL source, un titre, une date de téléchargement, une licence, une période, un périmètre, une unité et un statut. Les données ne doivent pas être publiées tant que cette vérification échoue.

## Frontières futures

- Les catégories pédagogiques formeront une couche de correspondance transparente distincte des classifications officielles.
- Les CCAS/CIAS sont des organismes sociaux locaux : ils ne rejoindront pas les observations du budget de l’État.
- Les fichiers de plusieurs millions de lignes devront passer par `COPY` vers une table de staging PostgreSQL, puis une normalisation SQL.
- Les tableaux PDF ne seront intégrés qu’après rapprochement avec une source structurée faisant autorité.
