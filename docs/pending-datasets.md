# Jeux de données en attente

## `donnée.csv`

**Statut : bloqué — provenance et sémantiques comptables non résolues.**

L’année, l’unité, le statut comptable, la source et la publication exacte ne sont pas établis. Ce fichier ne doit pas alimenter les données publiées.

## `RAP_synthese_2025.xls`

**Statut : analyse future requise.**

La structure du fichier XLS n’a pas été validée de façon suffisamment fiable. Aucun schéma n’est déduit de son nom et aucun importeur n’est implémenté.

## PDF sur les recettes fiscales, remboursements et dégrèvements

**Statut : source de soutien / futur jeu contrôlé.**

Aucune extraction automatique de tableau PDF n’est implémentée. Tout chiffre extrait devra être rapproché d’une source structurée faisant autorité avant publication.

## Base OFGL CCAS/CIAS

**Statut : domaine différé.**

Les CCAS/CIAS relèvent des organismes sociaux locaux et non du budget de l’État. Leur futur modèle et leurs endpoints devront rester séparés. Vu le volume de plusieurs millions de lignes, la stratégie recommandée est un `COPY` PostgreSQL vers une table de staging suivi d’une normalisation SQL, sans charger le fichier complet en mémoire PHP.
