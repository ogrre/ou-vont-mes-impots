# Catalogue des jeux de données compris

## Exécution des dépenses du budget de l’État 2025

| Métadonnée | Valeur |
| --- | --- |
| Identifiant interne | `state-expenditure-execution-2025` |
| Titre officiel | TODO |
| Producteur | TODO |
| URL source officielle | <https://www.budget.gouv.fr/budget-etat> |
| URL de téléchargement | TODO |
| Date de téléchargement | TODO |
| Date de publication | TODO |
| Licence / réutilisation | TODO |
| Période | 2025 |
| Périmètre | Budget de l’État français |
| Statut | Exécuté (`executed`) |
| Mesures | AE et CP, séparées |
| Unité source | Milliards d’euros |
| Classifications | Mission, ministère, nature |
| Importeur | `StateExpenditurePlrgImporter` |

Les six fichiers sont les vues `mission-ae`, `mission-cp`, `ministry-ae`, `ministry-cp`, `nature-ae` et `nature-cp` d’un jeu logique unique.

Limites : la provenance éditoriale et la licence manquent encore. Les colonnes incluent plusieurs composantes budgétaires ; le total n’est pas l’ensemble de la dépense publique française.

## Recettes nettes du budget général — PLF 2026

| Métadonnée | Valeur |
| --- | --- |
| Identifiant interne | `state-general-budget-revenue-plf-2026` |
| Titre dans le fichier | Recettes nettes du budget général |
| Publication | Projet de loi de finances 2026 |
| Producteur | Ministère de l’Économie, des Finances et de la Souveraineté industrielle et numérique |
| URL source officielle | TODO |
| URL de téléchargement | TODO |
| Date de téléchargement | TODO |
| Date de publication | TODO |
| Licence / réutilisation | TODO |
| Périodes | 2025 et 2026 |
| Périmètre | Budget général de l’État, champ indiqué « France » |
| Statuts | Estimation initiale 2025, estimation révisée 2025, PLF 2026 |
| Mesure | Sans objet ; il ne s’agit pas d’AE/CP |
| Unité source | Milliards d’euros |
| Classification | Lignes officielles de recettes, agrégats signalés |
| Importeur | `StateBudgetRevenueXlsxImporter` (OpenSpout) |

Limites : aucune colonne n’est une exécution. Les totaux, sous-totaux, lignes de recette et prélèvements ne doivent pas être additionnés sans appliquer les identités comptables indiquées par leurs libellés.
