# Organisation des données par millésime

## Données 2024

- `data/2024/budget-etat/` : six vues de dépenses de l’État 2024, en AE/CP,
  par mission, ministère et nature.
- `data/2024/insee/` : `T_3301` à `T_3307`, dépenses publiques par fonction
  COFOG, avec des données jusqu’à 2024.
- `data/2024/protection-sociale/` : comptes de la protection sociale DREES,
  jusqu’à l’exercice 2024.
- `data/2024/sante/` : comptes nationaux de la santé `CNS2025`, dont les
  tableaux portent sur l’exercice 2024 malgré une publication en 2025.

## Données 2025

`data/2025/budget-etat/` contient l’exécution 2025 disponible, le RAP 2025,
la NEB fiscale 2025 et les prévisions de recettes 2025/PLF 2026.

## Séries pluriannuelles

`data/series-historiques/insee/` contient les tables INSEE `T_3101–T_3108`,
`T_3201–T_3217` et `T_7301–T_7306`. Elles contiennent des observations 2024 et
2025, ainsi que des années antérieures : elles ne doivent pas être présentées
comme des fichiers annuels d’un seul millésime.

`data/series-historiques/ofgl/` contient la base CCAS/CIAS OFGL couvrant les
exercices 2018 à 2025. Elle nécessite un import spécialisé et un filtrage par
exercice avant publication.

## Manques identifiés pour une couverture complète de 2024

- exécution détaillée des recettes de l’État en 2024, avec les encaissements
  réellement constatés par impôt ;
- RAP 2024, notamment les résultats et indicateurs par programme et action ;
- NEB 2024 sur les recettes fiscales, remboursements et dégrèvements ;
- données budgétaires 2024 détaillées par programme/action ;
- éventuels Documents de politique transversale 2024 ;
- import normalisé et contrôlé des données OFGL 2024 ;
- import normalisé des comptes DREES et CNS 2024.

Les séries INSEE `T_310x`, `T_320x`, `T_330x` et `T_730x` fournissent déjà une
base solide pour les comptes nationaux, le déficit, la dette, les impôts, les
fonctions COFOG et les sous-secteurs publics en 2024.
