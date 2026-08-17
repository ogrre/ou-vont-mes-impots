# API HTTP v1

L’API est publique, sans authentification et strictement en lecture seule. Son préfixe est `/api/v1`. Les montants monétaires sont des chaînes décimales en EUR : le frontend ne doit pas les convertir implicitement en nombres JavaScript avant d’avoir choisi une stratégie de précision.

Les réponses peuvent indiquer `publication_ready: false` tant que certaines métadonnées officielles restent manquantes. Cela permet l’intégration du frontend en développement sans présenter le jeu comme prêt pour la production.

## Version de l’API

```http
GET /api/v1/version
```

```json
{
  "name": "Mais où vont mes impôts ? API",
  "version": "0.1.0",
  "api_version": "v1"
}
```

## Dépenses exécutées de l’État

```http
GET /api/v1/state-expenditure
```

Filtres :

| Paramètre | Défaut | Valeurs |
| --- | --- | --- |
| `year` | `2025` | entier entre 2000 et 2100 |
| `classification` | `mission` | `mission`, `ministry`, `nature` |
| `measure` | `cp` | `ae`, `cp`, `commitment_authorization`, `payment_credit` |

Exemple :

```http
GET /api/v1/state-expenditure?year=2025&classification=mission&measure=cp
```

Chaque élément contient son montant total, son pourcentage et sa ventilation par composante budgétaire. Le dénominateur du pourcentage partage exactement l’année, le périmètre, le statut, la mesure, la classification et l’ensemble de composantes de la requête.

```json
{
  "period": 2025,
  "scope": {
    "code": "french_state_budget",
    "label": "Budget de l’État français"
  },
  "status": "executed",
  "flow_type": "expenditure",
  "measure": {
    "code": "payment_credit",
    "official_label": "CP"
  },
  "classification": "mission",
  "currency": "EUR",
  "total": "797915296662.68",
  "percentage_denominator": {
    "amount": "797915296662.68",
    "description": "Total du même exercice, périmètre, statut, mesure, classification et ensemble de composantes budgétaires."
  },
  "items": [],
  "source": {}
}
```

## Recettes du budget général

```http
GET /api/v1/state-revenue
```

Filtres :

| Paramètre | Défaut | Valeurs |
| --- | --- | --- |
| `year` | `2025` | entier entre 2000 et 2100 |
| `status` | `revised_estimate` | `initial_estimate`, `revised_estimate`, `budget_bill` |

Exemples :

```http
GET /api/v1/state-revenue?year=2025&status=revised_estimate
GET /api/v1/state-revenue?year=2026&status=budget_bill
```

La réponse n’expose volontairement aucun total calculé : le classeur contient simultanément des lignes détaillées, des déductions, des sous-totaux et des totaux officiels. Chaque élément précise `is_aggregate` et `is_deduction`.

## Erreurs

- `422 Unprocessable Content` : filtre absent ou invalide après application des valeurs par défaut ;
- `404 Not Found` : aucun import terminé ne correspond aux filtres ;
- `405 Method Not Allowed` : tentative d’écriture sur ces routes en lecture seule.

Les requêtes CORS en lecture depuis un frontend séparé sont acceptées par la configuration Laravel actuelle.
