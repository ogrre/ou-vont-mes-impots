# Modèle canonique des finances publiques

## Principe

L’API normalise les sources dans une table d’observations commune. Elle ne crée
pas une table par fichier et ne fabrique aucune relation directe entre une
recette et une dépense : les recettes et les usages sont deux vues séparées de
finances publiques largement fongibles.

La chaîne de traçabilité est :

```text
Source → Dataset → DatasetFile → ImportBatch → FinancialObservation
```

Chaque observation conserve son année, son périmètre, sa classification, son
unité, son statut, sa base comptable, son étape budgétaire et son fichier/numéro
de ligne source.

## Référentiels

- `sources` décrit le producteur officiel ou institutionnel.
- `datasets` décrit un jeu logique, sa période, son unité et sa méthode.
- `accounting_scopes` décrit les périmètres institutionnels et leur hiérarchie.
- `classifications` et `classification_items` portent les nomenclatures
  officielles ou éditoriales : mission, ministère, nature, INSEE, COFOG à venir.
- `budget_components` sépare budget général, budgets annexes, CAS et comptes de
  concours financiers.

Les colonnes `measurement_type`, `accounting_basis`, `budget_stage`, `ae_cp` et
`is_consolidated` sont extensibles et permettent de distinguer notamment :

- dépense, recette, impôt, cotisation, dette et déficit ;
- comptabilité budgétaire et comptabilité nationale ;
- prévision, budget initial, budget rectifié et exécution ;
- autorisation d’engagement et crédit de paiement.

## Règles comptables

Le budget de l’État, l’administration centrale, les collectivités, la Sécurité
sociale et l’ensemble des administrations publiques ne sont pas interchangeables.
Les données INSEE `T_3201–T_3217` sont en comptabilité nationale, généralement en
milliards d’euros ; les fichiers PLRG sont des données budgétaires d’exécution
2025 en AE ou CP. Les montants sont normalisés en EUR, mais leur unité originale
reste dans les métadonnées.

AE et CP ne sont jamais additionnées. Les tableaux par sous-secteur peuvent
contenir des transferts internes ; les dépenses consolidées ne doivent donc pas
être reconstituées par addition naïve des sous-secteurs.

Les catégories agrégées et les lignes détaillées sont conservées avec leur
provenance. Une somme n’est validée que si le dataset documente explicitement le
dénominateur et le périmètre commun.

## Sources actuellement prises en charge

- Budget de l’État : six vues PLRG 2025 par mission, ministère et nature, en AE
  et CP.
- Recettes nettes du budget général : estimations 2025 et PLF 2026.
- INSEE `T_3201–T_3217` : recettes, dépenses, impôts, prélèvements et sous-
  secteurs des administrations publiques.

Les dictionnaires INSEE `T_3101` et `T_7301–T_7306` servent à interpréter les
codes et ne sont pas des séries de montants importées par défaut.

## Sources à intégrer ensuite

Les tables INSEE `T_3301–T_3307` pourront utiliser le même importeur avec une
classification `COFOG`. Les comptes DREES, les RAP détaillés par
programme/action et les Documents de politique transversale devront recevoir
des importeurs dédiés, sans changer la table d’observations.

Les CCAS/CIAS OFGL restent un périmètre local/social distinct. Leur fichier
volumineux doit passer par une table de staging PostgreSQL et une normalisation
par lots ; il ne doit pas être chargé en mémoire dans Laravel.

## RAP 2024

`php artisan dataset:import-rap 2024` utilise exclusivement la page officielle
PLRG 2024, son filtre RAP et ses liens PDF réels. Il conserve le catalogue dans
`data/processed/rap/2024/catalog.json`, les PDF dans `data/raw/rap/2024/` et un
JSON par programme. L’extraction est faite par `pdftotext -layout`, sans OCR,
avec détection par titres textuels. Les écarts entre somme des actions et total
du programme sont conservés avec une tolérance de 1 000 € et marqués
`review_required`, sans correction automatique.

## API pédagogique

`/api/v1/overview/{year}` regroupe les informations disponibles, mais expose
les années et les sources séparément lorsqu’elles diffèrent. Les ventilations
retournent `amount`, `percentage`, `per_100_euros` et `per_1000_euros`. Les
endpoints de recettes et de dépenses n’établissent aucune correspondance
TVA→santé ou IR→éducation sans affectation juridique documentée.
