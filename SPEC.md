# Hori Agri : spécification de construction

Document unique de référence pour la construction de l'application. Tout ce qui est nécessaire
pour développer la solution s'y trouve. Ce qui n'y est pas est hors périmètre.

**Projet** : Hori Agri, solution SaaS d'aide à la décision pour les coopératives agricoles.
**Contexte** : mémoire de Licence 3 Systèmes d'Information, option Génie Logiciel, Lomé Business
School. Auteur : Jean KODIO. Terrain : coopérative agricole Hori, au Togo.
**Cultures suivies** : maïs, soja, manioc.

---

## 0. Comment lire ce document

Les sections 1 à 4 posent le cadre et doivent être lues avant d'écrire une ligne de code.
Les sections 5 à 12 sont la spécification proprement dite. La section 13 donne l'ordre de
construction et la définition de « terminé » pour chaque module. La section 14 liste les tests
attendus. La section 15 rappelle ce qui est hors périmètre.

Les noms de classes, de tables, de colonnes et de méthodes sont **en anglais**. Tout ce que
l'utilisateur voit à l'écran est **en français**. Cette règle n'a aucune exception.

---

## 1. Règles absolues

Ces règles priment sur toute autre considération, y compris sur l'élégance du code.

1. **Ne jamais inventer une valeur agronomique.** Aucun seuil, aucune dose de référence, aucun
   rendement de référence, aucune durée de cycle ne doit être écrit en dur dans le code ni
   deviné. Ces valeurs viennent exclusivement des fichiers de données décrits en section 10.
   Si une valeur manque, laisser le champ vide et le signaler, jamais le remplir au jugé.
2. **Aucune donnée sur la coopérative Hori.** Les jeux de données livrés avec le code sont des
   fixtures de test et doivent être nommées et documentées comme telles.
3. **L'isolation entre coopératives n'est pas optionnelle.** Toute requête sur une table métier
   passe par le filtre décrit en section 7. Un développement qui contourne ce filtre est un
   défaut bloquant, même s'il fonctionne.
4. **Une règle agronomique est une ligne de base de données, pas une ligne de code.** Aucune
   condition agronomique ne doit apparaître dans un fichier PHP. Le moteur évalue des arbres de
   conditions chargés depuis la base.
5. **Interface utilisable sur un téléphone Android d'entrée de gamme.** Contraste élevé, cibles
   tactiles d'au moins 48 pixels, aucun tableau dense sur petit écran, formulaires courts.
6. **Ordre de construction impératif.** Les huit modules de la section 13 se construisent dans
   l'ordre. Aucun module ne commence avant que le précédent ne soit terminé et testé.

---

## 2. Point de départ

Un projet Laravel existe déjà, avec le kit de démarrage React et Inertia, TypeScript, Tailwind,
et Laravel Boost installé. Rien n'a encore été modifié.

**Première chose à faire, avant tout le reste.** Établir l'état exact du projet plutôt que de le
supposer :

- interroger Laravel Boost pour obtenir la version de Laravel, la version de PHP, les paquets
  installés et la configuration de la base de données ;
- lire `composer.json`, `package.json` et `config/database.php` ;
- vérifier que la connexion par défaut est PostgreSQL, et la basculer si ce n'est pas le cas ;
- lancer la suite de tests existante pour partir d'une base verte.

**Si un squelette Vue est présent en plus de React**, ne pas s'en servir et ne pas le supprimer
sans demander. La spécification est écrite pour React et Inertia, qui est la pile documentée dans
le mémoire.

**Ne pas mettre à jour Laravel, PHP ou les dépendances** sans nécessité démontrée. Le projet doit
rester constructible tel qu'il est.

---

## 3. Pile technique

| Élément | Choix | Rôle |
|---|---|---|
| Langage et cadre applicatif | PHP et Laravel, versions du projet existant | Contrôleurs, services métier, couche de correspondance objets et tables |
| Interface | React et Inertia, avec TypeScript | Écrans, sans séparation stricte client et serveur |
| Mise en forme | Tailwind CSS | Interface adaptative aux petits écrans |
| Base de données | PostgreSQL | Données métier et conditions des règles au format JSON |
| Graphiques | Chart.js | Tableau de bord de la coopérative |
| Tests | Pest | Tests unitaires et tests fonctionnels |
| Outillage | Laravel Boost | Inspection de l'application, requêtes, documentation |

**Rien d'autre ne doit être ajouté** sans nécessité démontrée. Pas de paquet de gestion de rôles,
pas de paquet multi-locataire, pas de bibliothèque de composants. Le filtre d'isolation et les
autorisations se écrivent à la main, ce qui est justifié dans le mémoire et doit rester vrai dans
le code.

---

## 4. Architecture et conventions

### 4.1. Organisation

```
app/
  Enums/                      énumérations PHP adossées à des valeurs
  Models/                     modèles Eloquent
  Models/Concerns/            trait BelongsToCooperative
  Models/Scopes/              CooperativeScope
  Services/
    Import/ImportService.php
    Indicators/IndicatorService.php
    Rules/RuleEvaluator.php
    Rules/RecommendationService.php
    Rules/EvaluationContext.php
  Http/Controllers/           un contrôleur par ressource
  Http/Requests/              une requête de validation par action d'écriture
  Http/Middleware/            EnsureRole
  Policies/                   une policy par modèle métier
database/
  migrations/
  seeders/
  seeders/data/               crops.json, varieties.json, rules.json
resources/js/
  pages/                      pages Inertia
  components/                 composants partagés
  lib/                        utilitaires
tests/
  Unit/
  Feature/
```

### 4.2. Conventions

- Les contrôleurs ne contiennent aucune logique métier. Ils valident, appellent un service,
  rendent une page Inertia.
- Les services ne connaissent ni la requête HTTP ni la session. La coopérative courante leur est
  passée, ou lue depuis le filtre global, jamais depuis `request()`.
- Toute action d'écriture a une classe de requête de validation dédiée.
- Les montants sont en francs CFA, stockés en `decimal`, jamais en flottant.
- Les superficies sont en hectares, avec trois décimales.
- Les dates sont des `date`, sans heure, sauf `computed_at` et `generated_at` qui sont des
  `datetime`.
- Les identifiants sont des entiers auto-incrémentés.
- Les noms de tables sont au pluriel et en anglais, en minuscules avec tirets bas.

### 4.3. Langue

Le code est en anglais. L'interface est en français. Les libellés d'énumération affichés à
l'écran passent par une méthode `label()` sur l'énumération, qui rend le texte français. Un
tableau de correspondance figure en annexe A.

---

## 5. Les acteurs et leurs droits

Quatre rôles, portés par l'énumération `Role`.

| Rôle | Rattaché à une coopérative | Ce qu'il peut faire |
|---|---|---|
| `super_admin` | Non | Créer et suspendre des coopératives, créer le compte de l'administrateur de coopérative, tenir le référentiel des cultures et des variétés, gérer les règles communes |
| `coop_admin` | Oui | Gérer les comptes de sa coopérative, les producteurs, les campagnes, surcharger les seuils des règles pour sa coopérative, consulter le tableau de bord |
| `technician` | Oui | Enregistrer parcelles et producteurs, ouvrir des cycles culturaux, saisir opérations et récoltes, importer un fichier, consulter les recommandations des producteurs suivis |
| `farmer` | Oui | Consulter ses parcelles, les indicateurs de ses cycles, ses recommandations, et donner un retour |

Un compte porte **un seul rôle**. L'attribut `cooperative_id` de `users` est nul si et seulement
si le rôle est `super_admin`. Une contrainte de base de données ne peut pas exprimer cette règle
simplement ; elle est donc vérifiée dans le modèle et couverte par un test.

Les autorisations passent par des policies Laravel, une par modèle métier, plus un middleware
`EnsureRole` pour les routes réservées à un rôle. Aucun contrôle d'autorisation ne doit reposer
uniquement sur le fait de masquer un lien dans l'interface.

---

## 6. Modèle de données

Quatorze tables. La classe pivot est `crop_cycles` : elle réunit une parcelle, une culture, une
variété facultative et une campagne.

### 6.1. Vue d'ensemble des relations

```
cooperatives 1 --- 0..* users
cooperatives 1 --- 0..* farmers
cooperatives 1 --- 0..* campaigns
cooperatives 0..1 --- 0..* rules          (nul = règle commune)
users 0..1 --- 0..1 farmers
farmers 1 --- 0..* plots
plots 1 --- 0..* crop_cycles
crops 1 --- 0..* varieties
crops 1 --- 0..* crop_cycles
crops 0..1 --- 0..* rules                 (nul = règle applicable à toute culture)
varieties 0..1 --- 0..* crop_cycles
campaigns 1 --- 0..* crop_cycles
crop_cycles 1 --- 0..* operations
crop_cycles 1 --- 0..* harvests
crop_cycles 1 --- 0..1 indicators
crop_cycles 1 --- 0..* recommendations
rules 1 --- 0..* recommendations
recommendations 1 --- 0..1 feedbacks
farmers 1 --- 0..* feedbacks
```

### 6.2. Les tables

Toutes les tables portent `id` (entier auto-incrémenté, clé primaire) et les horodatages
`created_at` et `updated_at`. Ces colonnes ne sont pas répétées ci-dessous.

**`cooperatives`** : le locataire.

| Colonne | Type | Nul | Notes |
|---|---|---|---|
| `name` | string(120) | non | unique |
| `region` | string(60) | non | sert de zone de repli pour les comparaisons |
| `prefecture` | string(60) | non | |
| `contact` | string(120) | oui | téléphone ou courriel du responsable |
| `suspended_at` | datetime | oui | non nul si la coopérative est suspendue |

**`users`** : les comptes.

| Colonne | Type | Nul | Notes |
|---|---|---|---|
| `cooperative_id` | foreignId | oui | nul uniquement pour `super_admin` |
| `name` | string(120) | non | |
| `phone` | string(20) | non | unique, sert d'identifiant de connexion |
| `email` | string(120) | oui | unique si renseigné |
| `password` | string | non | empreinte |
| `role` | string(20) | non | valeur de l'énumération `Role` |

Le kit de démarrage authentifie par courriel. **Il faut basculer l'authentification sur le
numéro de téléphone**, car beaucoup de techniciens n'ont pas d'adresse électronique. Le champ
courriel reste facultatif.

**`farmers`** : la fiche producteur.

| Colonne | Type | Nul | Notes |
|---|---|---|---|
| `cooperative_id` | foreignId | non | |
| `user_id` | foreignId | oui | renseigné si le producteur consulte lui-même l'application |
| `first_name` | string(80) | non | |
| `last_name` | string(80) | non | |
| `gender` | string(1) | oui | valeur de l'énumération `Gender` |
| `birth_year` | integer | oui | préféré à l'âge |
| `experience_years` | integer | oui | |
| `village` | string(80) | non | sert de zone de comparaison |
| `phone` | string(20) | oui | |

**`plots`** : la parcelle.

| Colonne | Type | Nul | Notes |
|---|---|---|---|
| `cooperative_id` | foreignId | non | |
| `farmer_id` | foreignId | non | |
| `name` | string(80) | non | nom d'usage donné par le producteur |
| `area_ha` | decimal(6,3) | non | sans elle, aucun indicateur à l'hectare |
| `soil_type` | string(20) | non | valeur de l'énumération `SoilType` |
| `latitude` | decimal(10,7) | oui | |
| `longitude` | decimal(10,7) | oui | |
| `previous_crop` | string(60) | oui | utile aux règles de rotation |

**`crops`** : le référentiel des cultures. **Pas de colonne de coopérative**, le référentiel est
commun.

| Colonne | Type | Nul | Notes |
|---|---|---|---|
| `name` | string(60) | non | unique |
| `family` | string(60) | oui | famille botanique |
| `cycle_days` | integer | non | durée de référence du cycle |
| `reference_density` | integer | non | plants par hectare |
| `reference_yield_kg_ha` | decimal(8,2) | non | rendement attendu au référentiel |
| `npk_needs` | json | oui | besoins en azote, phosphore, potassium |

**`varieties`** : le référentiel des variétés. Pas de colonne de coopérative.

| Colonne | Type | Nul | Notes |
|---|---|---|---|
| `crop_id` | foreignId | non | |
| `name` | string(80) | non | unique par culture |
| `cycle_days` | integer | oui | |
| `potential_yield_kg_ha` | decimal(8,2) | oui | |

**`campaigns`** : la campagne agricole.

| Colonne | Type | Nul | Notes |
|---|---|---|---|
| `cooperative_id` | foreignId | non | |
| `label` | string(40) | non | par exemple « 2026 grande saison » |
| `start_date` | date | non | borne basse des dates acceptées dans un cycle |
| `end_date` | date | non | borne haute |
| `season_type` | string(20) | non | valeur de l'énumération `SeasonType` |

**`crop_cycles`** : la classe pivot.

| Colonne | Type | Nul | Notes |
|---|---|---|---|
| `cooperative_id` | foreignId | non | |
| `plot_id` | foreignId | non | |
| `crop_id` | foreignId | non | |
| `variety_id` | foreignId | oui | souvent inconnue du producteur |
| `campaign_id` | foreignId | non | |
| `sowing_date` | date | non | point de départ de toutes les règles de calendrier |
| `density` | integer | oui | plants par hectare réellement semés |
| `status` | string(20) | non | valeur de l'énumération `CycleStatus` |

Contrainte d'unicité sur le triplet (`plot_id`, `crop_id`, `campaign_id`).

**`operations`** : l'itinéraire technique.

| Colonne | Type | Nul | Notes |
|---|---|---|---|
| `cooperative_id` | foreignId | non | |
| `crop_cycle_id` | foreignId | non | |
| `type` | string(20) | non | valeur de l'énumération `OperationType` |
| `input_type` | string(20) | oui | obligatoire dès qu'un intrant est apporté, voir 6.3 |
| `date` | date | non | doit tomber dans les bornes de la campagne du cycle |
| `product` | string(80) | oui | nom commercial, jamais lu par le moteur |
| `quantity` | decimal(8,2) | oui | pour la parcelle entière, telle que le technicien la compte |
| `unit` | string(20) | oui | valeur de l'énumération `QuantityUnit` |
| `cost` | decimal(10,2) | oui | en francs CFA |
| `notes` | text | oui | |

**`harvests`** : la récolte. Plusieurs récoltes possibles pour un même cycle.

| Colonne | Type | Nul | Notes |
|---|---|---|---|
| `cooperative_id` | foreignId | non | |
| `crop_cycle_id` | foreignId | non | |
| `date` | date | non | |
| `quantity_kg` | decimal(10,2) | non | |
| `unit_price` | decimal(10,2) | oui | prix de vente au kilogramme, en francs CFA |
| `quality` | string(20) | oui | valeur de l'énumération `Quality` |
| `post_harvest_losses_kg` | decimal(10,2) | oui | |

**`indicators`** : les valeurs calculées. Un seul jeu par cycle, unicité sur `crop_cycle_id`.
Aucune de ces valeurs n'est saisie.

| Colonne | Type | Nul | Notes |
|---|---|---|---|
| `cooperative_id` | foreignId | non | |
| `crop_cycle_id` | foreignId | non | unique |
| `yield_kg_ha` | decimal(10,2) | oui | |
| `production_cost_ha` | decimal(10,2) | oui | |
| `gross_margin_ha` | decimal(12,2) | oui | |
| `input_cost_share` | decimal(5,3) | oui | |
| `zone_median_yield_kg_ha` | decimal(10,2) | oui | |
| `zone_median_cost_ha` | decimal(10,2) | oui | |
| `zone_plot_count` | integer | non | par défaut 0 |
| `yield_vs_zone_median` | decimal(6,3) | oui | nul si `zone_plot_count` est inférieur à 5 |
| `cost_vs_zone_median` | decimal(6,3) | oui | nul si `zone_plot_count` est inférieur à 5 |
| `computed_at` | datetime | non | |

**`rules`** : la base de connaissances.

| Colonne | Type | Nul | Notes |
|---|---|---|---|
| `cooperative_id` | foreignId | oui | nul pour une règle commune à toutes les coopératives |
| `code` | string(20) | non | par exemple `FERT-MAIS-01` |
| `crop_id` | foreignId | oui | nul pour une règle applicable à toute culture |
| `family` | string(1) | non | valeur de l'énumération `RuleFamily` |
| `condition` | json | non | arbre de conditions, voir section 9 |
| `severity` | string(10) | non | valeur de l'énumération `Severity` |
| `message` | string(255) | non | le constat, en français |
| `action` | text | non | ce que le producteur doit faire |
| `estimated_gain` | string(120) | oui | sert au classement à sévérité égale |
| `source` | string(255) | non | référence agronomique affichée à l'agriculteur |
| `threshold_status` | string(20) | non | valeur de l'énumération `ThresholdStatus` |
| `enabled` | boolean | non | faux par défaut si le seuil est une convention |

Contrainte d'unicité sur le couple (`cooperative_id`, `code`), la valeur nulle de
`cooperative_id` comptant comme une valeur distincte. En PostgreSQL, cela suppose deux index
uniques partiels : un sur (`code`) où `cooperative_id` est nul, un sur
(`cooperative_id`, `code`) où il ne l'est pas.

**`recommendations`** : la sortie du moteur.

| Colonne | Type | Nul | Notes |
|---|---|---|---|
| `cooperative_id` | foreignId | non | |
| `crop_cycle_id` | foreignId | non | |
| `rule_id` | foreignId | non | |
| `priority` | integer | non | rang après tri, 1 étant le plus prioritaire |
| `generated_message` | text | non | message de la règle complété par les valeurs observées |
| `status` | string(20) | non | valeur de l'énumération `RecoStatus` |
| `generated_at` | datetime | non | |

Contrainte d'unicité sur le couple (`crop_cycle_id`, `rule_id`).

**`feedbacks`** : le retour de l'agriculteur.

| Colonne | Type | Nul | Notes |
|---|---|---|---|
| `cooperative_id` | foreignId | non | |
| `recommendation_id` | foreignId | non | unique |
| `farmer_id` | foreignId | non | doit être le propriétaire de la parcelle concernée |
| `applied` | boolean | non | |
| `comment` | text | oui | |
| `observed_result` | text | oui | |

### 6.3. Quand `input_type` est obligatoire

`operations.input_type` est obligatoire lorsque le type d'opération apporte un produit, c'est à
dire pour les types `sowing`, `fertilization` et `treatment`. Il doit être nul pour les types
`tillage`, `weeding`, `irrigation` et `harvest`. Cette règle est vérifiée dans la classe de
requête de validation et couverte par un test.

Sans cet attribut, sept règles sur dix-sept ne peuvent pas s'évaluer. C'est le point de
conception le plus important du modèle et il ne doit pas être assoupli.

### 6.4. Deux écarts assumés à la normalisation

`rules.condition` n'est pas atomique et `cooperative_id` est répété sur chaque table métier. Ces
deux écarts sont volontaires et justifiés dans le mémoire. Ne pas les corriger.

---

## 7. Isolation multi-locataire

Patron du schéma partagé. Une seule base, une colonne discriminante par table métier.

### 7.1. Les trois pièces

1. **La colonne `cooperative_id`** sur chaque table métier : `farmers`, `plots`, `campaigns`,
   `crop_cycles`, `operations`, `harvests`, `indicators`, `recommendations`, `feedbacks`, et
   `rules` où elle est nullable. Elle est absente de `crops` et `varieties`, qui forment un
   référentiel commun, et nullable sur `users`.
2. **Le scope global `CooperativeScope`**, appliqué automatiquement à toute requête sur ces
   modèles. Il ajoute une condition sur la coopérative de l'utilisateur connecté.
3. **Le trait `BelongsToCooperative`**, qui applique le scope et renseigne `cooperative_id` à la
   création, sans intervention du code appelant.

### 7.2. Comportement attendu

- Un utilisateur dont le rôle est `super_admin` n'a pas de coopérative : le scope ne s'applique
  pas à lui. Cette exception doit être explicite dans le code et couverte par un test.
- Hors contexte HTTP, par exemple dans une commande ou un test, le scope ne s'applique pas s'il
  n'y a pas d'utilisateur connecté. Une méthode explicite doit permettre de fixer la coopérative
  courante pour les traitements en arrière-plan.
- Pour la table `rules`, le scope est particulier : une coopérative voit les règles dont
  `cooperative_id` est nul **ou** égal au sien. Écrire un scope dédié pour ce cas.
- Une demande portant sur une ressource d'une autre coopérative doit produire une réponse
  « ressource introuvable » et non un refus d'accès. Une réponse de refus révélerait l'existence
  de la ressource.

### 7.3. Ce qui est interdit

- Filtrer par coopérative dans un contrôleur ou dans une vue plutôt que dans le scope.
- Utiliser `withoutGlobalScope` ailleurs que dans un traitement d'administration explicitement
  réservé au `super_admin`.
- Renseigner `cooperative_id` à la main dans un contrôleur.

---

## 8. Les énumérations

Douze énumérations PHP adossées à des chaînes de caractères. Chacune expose une méthode
`label(): string` qui rend le libellé français affiché à l'écran.

| Énumération | Valeurs |
|---|---|
| `Role` | `super_admin`, `coop_admin`, `technician`, `farmer` |
| `Gender` | `M`, `F` |
| `SoilType` | `ferrugineux`, `sableux`, `argileux`, `limoneux`, `hydromorphe` |
| `SeasonType` | `grande_saison`, `petite_saison` |
| `CycleStatus` | `planned`, `in_progress`, `harvested`, `abandoned` |
| `OperationType` | `tillage`, `sowing`, `fertilization`, `treatment`, `weeding`, `irrigation`, `harvest` |
| `InputType` | `npk`, `urea`, `potassium`, `phosphate`, `organic`, `seed`, `inoculant`, `herbicide`, `insecticide`, `fungicide`, `other` |
| `QuantityUnit` | `kg`, `l`, `sac_50kg`, `sac_25kg`, `unite` |
| `Quality` | `good`, `average`, `poor` |
| `RuleFamily` | `A`, `B`, `C`, `D` |
| `Severity` | `high`, `medium`, `low` |
| `ThresholdStatus` | `sourced`, `imported`, `convention` |
| `RecoStatus` | `new`, `read`, `applied`, `dismissed` |

Signification des familles de règles : `A` écart au référentiel technique, `B` écart au
calendrier cultural, `C` comparaison entre parcelles semblables, `D` économie et durabilité.

Signification de l'origine du seuil : `sourced` source togolaise publiée, `imported` source
régionale reprise faute de mieux, `convention` convention de travail à confirmer par la
coopérative.

`QuantityUnit` porte une méthode `toKilograms(): ?float` qui rend le facteur de conversion :
`kg` vaut 1, `sac_50kg` vaut 50, `sac_25kg` vaut 25, et `l` comme `unite` rendent `null` car
elles ne se convertissent pas en masse.

---

## 9. Le moteur de règles

### 9.1. Le cycle de vie d'une recommandation

Le moteur se déclenche à la suite de l'enregistrement d'une opération ou d'une récolte, jamais
sur action volontaire de l'utilisateur. L'enchaînement est le suivant.

1. Le cycle est chargé. S'il ne comporte aucune récolte, le traitement s'arrête.
2. `IndicatorService` calcule les indicateurs et les enregistre.
3. `RecommendationService` construit le contexte d'évaluation à quinze champs.
4. Il sélectionne les règles applicables et applique la surcharge de la coopérative.
5. Pour chaque règle, `RuleEvaluator` évalue l'arbre de conditions.
6. Chaque condition vraie produit ou met à jour une recommandation.
7. Les recommandations du cycle sont triées et reçoivent leur rang de priorité.

### 9.2. Le calcul des indicateurs

`IndicatorService::computeFor(CropCycle $cycle): Indicator`

| Indicateur | Calcul |
|---|---|
| `yield_kg_ha` | somme des `harvests.quantity_kg` divisée par `plots.area_ha` |
| `production_cost_ha` | somme des `operations.cost` du cycle divisée par `plots.area_ha` |
| `gross_margin_ha` | (somme de `quantity_kg` multipliée par `unit_price`, moins somme des coûts) divisée par `area_ha` |
| `input_cost_share` | somme des `cost` des opérations dont `input_type` n'est pas nul, divisée par la valeur totale de la récolte |
| `zone_median_yield_kg_ha` | médiane des `yield_kg_ha` des cycles comparables, voir 9.3 |
| `zone_median_cost_ha` | médiane des `production_cost_ha` des cycles comparables |
| `zone_plot_count` | nombre de cycles comparables retenus |
| `yield_vs_zone_median` | `yield_kg_ha` divisé par la médiane, **nul si `zone_plot_count` est inférieur à 5** |
| `cost_vs_zone_median` | `production_cost_ha` divisé par la médiane, **nul si `zone_plot_count` est inférieur à 5** |
| `computed_at` | horodatage du calcul |

Une opération dont le coût est nul compte pour zéro. Un `unit_price` nul rend la valeur de la
récolte incalculable : dans ce cas `gross_margin_ha` et `input_cost_share` restent nuls.

### 9.3. La zone de comparaison

L'algorithme est le suivant, et il doit être implémenté exactement ainsi.

1. Constituer l'ensemble des cycles de la même coopérative, de la même culture, de la même
   campagne, dont le statut n'est pas `abandoned`, qui comportent au moins une récolte, et dont
   le producteur habite **le même village** que celui du cycle évalué. Le cycle évalué en fait
   partie.
2. Si cet ensemble compte moins de cinq cycles, le reconstituer sans la condition de village,
   c'est à dire à l'échelle de la coopérative, qui n'a qu'une région.
3. `zone_plot_count` est le nombre de cycles de l'ensemble finalement retenu.
4. Les médianes se calculent sur cet ensemble. La médiane d'un nombre pair de valeurs est la
   moyenne des deux valeurs centrales.
5. Si `zone_plot_count` reste inférieur à 5, les deux rapports restent nuls et les règles de la
   famille `C` sont suspendues pour ce cycle.

Ce seuil de cinq n'est pas négociable. Il vient d'une limite constatée : la médiane de deux
valeurs étant leur moyenne, l'une est toujours au-dessus et l'autre en dessous, ce qui fait
déclencher une règle de comparaison sur une parcelle correctement conduite.

### 9.4. Le contexte d'évaluation

`EvaluationContext` est un objet immuable qui expose exactement quinze champs. Aucun autre champ
n'est interrogeable par une règle. Ajouter un champ suppose de modifier cette classe et de le
documenter ici.

| Champ | Type | Dérivation |
|---|---|---|
| `crop_name` | string | `crops.name` de la culture du cycle |
| `region` | string | `cooperatives.region` |
| `sowing_month` | int | mois de `crop_cycles.sowing_date`, de 1 à 12 |
| `sowing_density_plants_ha` | ?int | `crop_cycles.density` |
| `seed_rate_kg_ha` | ?float | somme des quantités converties en kilogrammes des opérations de type `sowing` et d'`input_type` `seed`, divisée par `area_ha` |
| `npk_dose_kg_ha` | ?float | idem pour `input_type` `npk`, tous types d'opération confondus |
| `urea_dose_kg_ha` | ?float | idem pour `input_type` `urea` |
| `potassium_dose_kg_ha` | ?float | idem pour `input_type` `potassium` |
| `inoculant_used` | bool | vrai s'il existe une opération d'`input_type` `inoculant` |
| `first_weeding_days_after_sowing` | ?int | nombre de jours entre `sowing_date` et la date de la première opération de type `weeding` |
| `urea_days_after_sowing` | ?int | nombre de jours entre `sowing_date` et la date de la première opération de type `fertilization` et d'`input_type` `urea` |
| `consecutive_seasons_same_crop` | int | nombre de campagnes consécutives, campagnes ordonnées par `start_date`, où la même parcelle a porté la même culture, cycle courant inclus |
| `yield_vs_zone_median_ratio` | ?float | `indicators.yield_vs_zone_median` |
| `cost_vs_zone_median_ratio` | ?float | `indicators.cost_vs_zone_median` |
| `input_cost_share_of_harvest_value` | ?float | `indicators.input_cost_share` |

**Conversion des unités.** Une quantité ne compte dans une dose en kilogrammes par hectare que si
son unité se convertit en masse. Les opérations exprimées en litres ou en unités sont ignorées
pour ces champs. Une opération dont la quantité ou l'unité est nulle est ignorée.

**Valeurs nulles.** Un champ nul n'est pas zéro. Une condition portant sur un champ nul rend
faux, sauf pour les opérateurs `!=` et `not_in` où elle rend vrai. Ce comportement doit être
couvert par des tests, car il est la source d'erreur la plus probable du moteur.

### 9.5. La grammaire des conditions

Une condition est un arbre JSON. Trois formes seulement.

```json
{"field": "npk_dose_kg_ha", "op": "<", "value": 100}
{"and": [ {...}, {...} ]}
{"or":  [ {...}, {...} ]}
```

Opérateurs reconnus : `=`, `!=`, `<`, `<=`, `>`, `>=`, `between`, `in`, `not_in`.
Pour `between`, `value` est un tableau de deux nombres, bornes incluses.
Pour `in` et `not_in`, `value` est un tableau de valeurs.

`RuleEvaluator::evaluate(array $condition, EvaluationContext $context): bool`

L'évaluateur ne connaît ni l'agronomie ni les coopératives. Il ne fait pas d'appel à la base de
données. Il lève une exception si le champ n'est pas l'un des quinze, si l'opérateur n'est pas
reconnu, ou si la forme de l'arbre est invalide. Aucune fonction, aucun calcul et aucun appel
externe ne sont acceptés dans une condition : la grammaire est volontairement pauvre pour rester
lisible par un responsable technique de coopérative.

### 9.6. La sélection des règles et la surcharge

Sont retenues les règles telles que :

- `enabled` est vrai ;
- `crop_id` est nul ou égal à la culture du cycle ;
- `cooperative_id` est nul ou égal à la coopérative du cycle.

Puis la surcharge s'applique : lorsque deux règles retenues portent le même `code`, celle dont
`cooperative_id` n'est pas nul remplace l'autre.

Enfin, si `zone_plot_count` est inférieur à 5, les règles de famille `C` sont écartées.

### 9.7. La production et le tri

Chaque condition vraie produit une recommandation par `updateOrCreate` sur le couple
(`crop_cycle_id`, `rule_id`). Le `generated_message` est composé du `message` de la règle suivi
des valeurs observées qui ont fait déclencher la condition, entre parenthèses. La règle conserve
son `action`, sa `source` et son `threshold_status`, qui sont affichés depuis la relation et non
recopiés.

Le tri se fait par sévérité décroissante (`high`, puis `medium`, puis `low`), puis par gain
estimé décroissant, puis par `code` alphabétique pour rester déterministe. `estimated_gain`
étant un libellé, le gain se compare sur le premier nombre qu'il contient ; un libellé sans
nombre compte pour zéro. Le rang ainsi obtenu est écrit dans `priority`, en commençant à 1.

Les recommandations d'un cycle qui ne se déclenchent plus lors d'un nouveau calcul sont
supprimées, sauf celles dont le `status` est `applied` ou `dismissed`, qui sont conservées.

---

## 10. Les données de référence

Trois fichiers JSON alimentent les seeders. **Ils sont fournis par l'auteur et ne doivent pas
être écrits par déduction.** Si un fichier est absent, le seeder doit échouer avec un message
clair plutôt que de créer des données de remplacement.

### 10.1. `database/seeders/data/crops.json`

```json
[
  {
    "name": "Maïs",
    "family": "Poaceae",
    "cycle_days": 0,
    "reference_density": 0,
    "reference_yield_kg_ha": 0,
    "npk_needs": {"n": 0, "p": 0, "k": 0}
  }
]
```

Trois entrées attendues : maïs, soja, manioc. Les valeurs numériques viennent des recommandations
publiées par le Ministère de l'Agriculture du Togo et par l'Institut Togolais de Recherche
Agronomique.

### 10.2. `database/seeders/data/varieties.json`

```json
[
  {"crop": "Maïs", "name": "", "cycle_days": null, "potential_yield_kg_ha": null}
]
```

Le champ `crop` référence le nom d'une culture de `crops.json`.

### 10.3. `database/seeders/data/rules.json`

Dix-sept entrées. C'est le fichier le plus important du projet.

```json
[
  {
    "code": "FERT-MAIS-01",
    "crop": "Maïs",
    "family": "A",
    "severity": "high",
    "condition": {"and": [
      {"field": "crop_name", "op": "=", "value": "Maïs"},
      {"field": "npk_dose_kg_ha", "op": "<", "value": 0}
    ]},
    "message": "Fertilisation de fond insuffisante par rapport au référentiel national",
    "action": "",
    "estimated_gain": "",
    "source": "",
    "threshold_status": "sourced",
    "enabled": true
  }
]
```

Les dix-sept codes attendus, avec leur famille et leur culture, sont fixés :

| Code | Famille | Culture |
|---|---|---|
| `FERT-MAIS-01` | A | Maïs |
| `FERT-MAIS-02` | A | Maïs |
| `FERT-MAIS-03` | A | Maïs |
| `DENS-MAIS-01` | A | Maïs |
| `CAL-MAIS-01` | B | Maïs |
| `CAL-MAIS-02` | B | Maïs |
| `CAL-MAIS-03` | B | Maïs |
| `ROT-MAIS-01` | D | Maïs |
| `DENS-SOJA-01` | A | Soja |
| `INOC-SOJA-01` | A | Soja |
| `CAL-SOJA-01` | B | Soja |
| `FERT-MANI-01` | A | Manioc |
| `CAL-MANI-01` | B | Manioc |
| `ROT-MANI-01` | D | Manioc |
| `PERF-PAIR-01` | C | toute culture |
| `COUT-PAIR-01` | C | toute culture |
| `ECO-INTR-01` | D | toute culture |

Répartition attendue, à vérifier par le seeder : 7 règles de famille A, 5 de famille B, 2 de
famille C, 3 de famille D ; 8 pour le maïs, 3 pour le soja, 3 pour le manioc, 3 génériques ;
11 seuils `sourced`, 1 `imported`, 5 `convention` ; 7 de sévérité `high`, 10 de sévérité
`medium`. Les cinq règles dont `threshold_status` vaut `convention` doivent avoir `enabled` à
faux.

### 10.4. Validation à la lecture

Le seeder de règles doit, avant d'écrire quoi que ce soit :

1. valider chaque entrée contre le schéma ci-dessus ;
2. vérifier que chaque `condition` est acceptée par `RuleEvaluator` sur un contexte factice ;
3. vérifier les comptages de la section 10.3 et échouer si l'un ne tombe pas juste ;
4. vérifier qu'aucune règle de `threshold_status` `convention` n'a `enabled` à vrai.

Une commande `php artisan rules:validate` doit exposer ces contrôles sans écrire en base.

---

## 11. Les écrans

Interface en français. Pages Inertia, une par écran. Le tableau ci-dessous donne les routes et
les rôles autorisés.

| Route | Écran | Rôles |
|---|---|---|
| `GET /login` | Connexion par numéro de téléphone | tous |
| `GET /cooperatives` | Liste et création des coopératives | `super_admin` |
| `GET /crops` | Référentiel des cultures et variétés | `super_admin` |
| `GET /rules` | Base de règles, avec l'origine de chaque seuil | `super_admin`, `coop_admin` |
| `PATCH /rules/{rule}` | Surcharge d'un seuil pour la coopérative | `coop_admin` |
| `GET /users` | Comptes de la coopérative | `super_admin`, `coop_admin` |
| `GET /farmers` | Liste et fiche des producteurs | `coop_admin`, `technician` |
| `GET /plots` | Liste et fiche des parcelles | `coop_admin`, `technician` |
| `GET /campaigns` | Campagnes de la coopérative | `coop_admin` |
| `GET /cycles` | Cycles culturaux, filtrables par campagne et par culture | `coop_admin`, `technician` |
| `GET /cycles/{cycle}` | Itinéraire technique du cycle, opérations et récoltes | `coop_admin`, `technician` |
| `POST /cycles/{cycle}/operations` | Saisie d'une opération | `technician` |
| `POST /cycles/{cycle}/harvests` | Saisie d'une récolte | `technician` |
| `GET /imports` | Dépôt d'un fichier et rapport d'import | `coop_admin`, `technician` |
| `GET /recommandations` | Recommandations, filtrées selon le rôle | `technician`, `farmer` |
| `PATCH /recommandations/{reco}` | Changement d'état d'une recommandation | `farmer` |
| `POST /recommandations/{reco}/retour` | Retour de l'agriculteur | `farmer` |
| `GET /tableau-de-bord` | Tableau de bord de la coopérative | `coop_admin` |
| `GET /tableau-de-bord/export` | Export de la synthèse de campagne au format CSV | `coop_admin` |

### 11.1. L'écran de saisie d'une opération

C'est l'écran le plus important du projet. Il porte l'hypothèse selon laquelle la saisie est
possible sur un téléphone d'entrée de gamme.

- Le formulaire s'adapte au type d'opération choisi : la nature de l'intrant, la quantité,
  l'unité et le coût n'apparaissent que pour les types qui apportent un produit.
- Le formulaire **ne demande jamais une dose à l'hectare**. Il demande une quantité et une unité
  telles que le technicien les compte, le sac de 50 kg étant l'unité de terrain. La dose est
  calculée.
- La date est contrôlée contre les bornes de la campagne. Une date hors bornes produit une
  erreur sur le champ, et la saisie déjà faite est conservée.
- Les opérations déjà saisies s'affichent en ordre chronologique au-dessus du formulaire.

### 11.2. L'écran des recommandations

Chaque recommandation affiche quatre choses, dans cet ordre : le constat, l'action à mener, la
source agronomique et le gain estimé. La source n'est jamais masquée derrière un repli. C'est
l'exigence d'explicabilité, et elle est vérifiée par un test fonctionnel.

L'agriculteur peut marquer une recommandation comme appliquée, avec un commentaire et le résultat
observé, ou l'écarter. Le système vérifie qu'il est bien le propriétaire de la parcelle concernée.

### 11.3. L'import

Le traitement ne s'interrompt pas à la première ligne fautive. Il parcourt le fichier entier,
enregistre les lignes conformes et dresse la liste des rejets avec leur motif, ligne par ligne.
Un second dépôt du même fichier corrigé ne doit pas dupliquer les lignes déjà enregistrées.

### 11.4. Contraintes d'interface

- Contraste élevé, lisible en plein soleil.
- Cibles tactiles d'au moins 48 pixels.
- Aucun tableau dense sur petit écran : sous 640 pixels de large, les listes deviennent des
  cartes empilées.
- Les listes longues sont paginées côté serveur.

---

## 12. Le tableau de bord

Agrégats calculés côté base de données, jamais en mémoire PHP sur des collections complètes.

- Rendement moyen, coût moyen et marge moyenne, par culture, par village et par campagne.
- Nombre de recommandations par sévérité.
- Part des recommandations dont le statut est `applied` sur celles dont le statut n'est plus
  `new`.
- Nombre de parcelles dont le rendement est inférieur à la médiane de leur zone, en excluant les
  cycles dont `zone_plot_count` est inférieur à 5.

Graphiques par Chart.js. Export CSV de la synthèse de campagne.

---

## 13. Les huit modules

Ordre impératif. Chaque module se termine par ses tests, qui doivent passer avant que le suivant
ne commence.

### Module 1 : comptes et accès
Migrations `cooperatives` et `users`, énumération `Role`, authentification par numéro de
téléphone, trait `BelongsToCooperative`, scope `CooperativeScope`, middleware `EnsureRole`,
policies de base.
**Terminé quand** : les quatre rôles se connectent, le `super_admin` voit toutes les
coopératives, un technicien d'une coopérative reçoit une réponse « ressource introuvable » sur
une ressource d'une autre, et le scope renseigne `cooperative_id` à la création sans intervention
du contrôleur.

### Module 2 : référentiel agriculteurs
Migration et modèle `farmers`, écrans de liste et de fiche, rattachement facultatif à un compte.
**Terminé quand** : un technicien crée, modifie et consulte un producteur de sa coopérative, et
ne voit aucun producteur d'une autre.

### Module 3 : parcelles et campagnes
Migrations `plots`, `crops`, `varieties`, `campaigns`, `crop_cycles`. Seeders de cultures et de
variétés. Contrainte d'unicité du triplet du cycle. Diagramme d'états du cycle appliqué :
`planned` vers `in_progress` à la première opération, vers `harvested` à la clôture, `abandoned`
à tout moment.
**Terminé quand** : un cycle s'ouvre sur une parcelle pour une culture et une campagne, et un
second cycle sur le même triplet est refusé.

### Module 4 : saisie des données
Opérations, récoltes, import CSV. Validation conditionnelle de `input_type`. Contrôle des dates
contre les bornes de la campagne. Écran de saisie adapté au petit écran.
**Terminé quand** : un itinéraire complet se saisit sur un écran de 360 pixels de large, une
date hors campagne est refusée sans perte de saisie, et un import de plusieurs centaines de
lignes produit un rapport ligne à ligne.

### Module 5 : couche analytique
`IndicatorService`, migration `indicators`, calcul déclenché par l'enregistrement d'une récolte
ou d'une opération, zone de comparaison et médianes, suspension en dessous de cinq parcelles.
**Terminé quand** : les dix indicateurs se calculent, les rapports restent nuls en dessous de
cinq parcelles comparables, et le recalcul est déclenché sans action de l'utilisateur.

### Module 6 : moteur de recommandations
`EvaluationContext`, `RuleEvaluator`, `RecommendationService`, migrations `rules` et
`recommendations`, seeder de règles avec ses contrôles, commande `rules:validate`.
**Terminé quand** : les dix-sept règles se chargent et passent la validation, l'évaluateur est
couvert opérateur par opérateur, un même couple cycle et règle ne produit jamais deux
recommandations, et les priorités sont déterministes.

### Module 7 : restitution agriculteur
Écran des recommandations, changement d'état, migration `feedbacks` et enregistrement du retour,
contrôle du propriétaire de la parcelle.
**Terminé quand** : le constat, l'action et la source apparaissent pour chaque recommandation,
et un producteur ne peut ni voir ni commenter la recommandation d'un autre.

### Module 8 : tableau de bord coopérative
Agrégats, graphiques, filtres, export CSV.
**Terminé quand** : les agrégats sont calculés en base, les filtres fonctionnent, et l'export
reprend exactement ce que l'écran affiche.

---

## 14. Les tests attendus

Suite Pest. Les tests sont rattachés aux quatre hypothèses du mémoire ; leur résultat sera
reporté dans le chapitre 3. Nommer les fichiers de façon que ce rattachement reste lisible.

### 14.1. Tests unitaires

**`RuleEvaluatorTest`**, le plus important.
- Une assertion par opérateur, sur un contexte connu, dans le cas vrai et dans le cas faux.
- Combinaisons `and` et `or`, y compris imbriquées.
- Champ nul : rend faux pour `=`, `<`, `<=`, `>`, `>=`, `between`, `in` ; rend vrai pour `!=` et
  `not_in`.
- Champ inconnu, opérateur inconnu et arbre malformé lèvent une exception.
- Aucune requête de base de données n'est émise pendant ces tests.

**`EvaluationContextTest`**
- Les quinze champs se dérivent correctement d'un cycle complet.
- Les unités se convertissent : un sac de 50 kg compte pour 50 kilogrammes.
- Une opération en litres n'entre pas dans une dose en kilogrammes par hectare.
- `consecutive_seasons_same_crop` compte bien les campagnes consécutives et s'interrompt à la
  première campagne portant une autre culture.

**`IndicatorServiceTest`**
- Les dix indicateurs sur un cycle à une récolte, puis sur un cycle à plusieurs récoltes.
- La médiane d'un nombre pair de valeurs est la moyenne des deux valeurs centrales.
- En dessous de cinq parcelles comparables, `zone_plot_count` est renseigné mais les deux
  rapports restent nuls.
- Le repli du village vers la coopérative s'active bien en dessous de cinq.
- Un cycle `abandoned` n'entre pas dans le calcul des médianes.

**`CooperativeScopeTest`**
- La colonne est renseignée à la création sans intervention du code appelant.
- Le `super_admin` n'est pas filtré.
- Le scope des règles rend les règles communes et celles de la coopérative, et aucune autre.

**`RecommendationSortTest`**
- Le tri par sévérité, puis par gain estimé, puis par code est déterministe.
- Deux exécutions successives du moteur produisent le même ordre.

### 14.2. Tests fonctionnels

**Hypothèse H1**
- Un technicien saisit une opération complète et l'enregistrement réussit.
- Le formulaire ne demande aucune dose à l'hectare.
- Une date hors des bornes de la campagne est refusée et la saisie est conservée.
- `input_type` est exigé pour les types qui apportent un produit et refusé pour les autres.

**Hypothèse H2**
- Le moteur produit des recommandations sur un cycle conduit en dessous du référentiel.
- Le moteur reste silencieux sur un cycle conforme, à l'exception des règles de comparaison.
- Le constat, l'action et la source apparaissent sur l'écran des recommandations.
- Une seconde exécution du moteur ne crée pas de doublon.
- Une règle propre à la coopérative remplace la règle commune de même code.
- Une règle de `threshold_status` `convention` n'est pas évaluée tant qu'elle est désactivée.

**Hypothèse H3**
- Avec moins de cinq parcelles comparables, aucune règle de famille `C` ne se déclenche.
- Avec cinq parcelles ou plus, les rapports sont calculés et les règles s'évaluent.

**Hypothèse H4**
- Un technicien de la coopérative B demandant une parcelle de la coopérative A reçoit une
  réponse « ressource introuvable », et non un refus d'accès.
- Le même contrôle sur un cycle, une opération, une récolte, une recommandation et un retour.
- Un producteur ne peut pas enregistrer un retour sur la recommandation d'un autre producteur.
- Un `coop_admin` ne voit aucune règle propre à une autre coopérative.

### 14.3. Le jeu de données d'épreuve

Le mémoire présente une vérification conduite sur un dossier de producteur construit. Il faut la
reproduire sous forme de fixture de test, sous le nom `VerificationDatasetSeeder`, avec la
mention explicite qu'il s'agit d'une construction et non d'un relevé.

Structure attendue : une coopérative, trois producteurs du même village, quatre parcelles, deux
campagnes, vingt et une opérations et cinq récoltes.

Valeurs d'indicateurs à retrouver exactement :

| Cycle | Surface | Culture | Rendement | Coût à l'hectare | Marge brute à l'hectare |
|---|---|---|---|---|---|
| 1 | 0,75 ha | Maïs | 900 kg/ha | 97 333,3 FCFA | 82 666,7 FCFA |
| 2 | 0,50 ha | Soja | 800 kg/ha | 74 000 FCFA | 206 000 FCFA |
| 3 | 1,20 ha | Manioc | 9 000 kg/ha | 80 000 FCFA | 730 000 FCFA |
| 4 | 1,00 ha | Maïs | 2 600 kg/ha | 185 500 FCFA | 334 500 FCFA |

Le cycle 3 porte deux récoltes échelonnées, ce qui éprouve la multiplicité des récoltes.

**Si l'auteur dispose du script Python de vérification et de son jeu de données**, les porter tels
quels plutôt que de reconstruire des opérations : le nombre de recommandations attendu, treize sur
les quatre cycles, dépend des opérations exactes et des seuils réels. Sans ce script, poser comme
critère la reproduction des indicateurs ci-dessus, et ajuster le nombre de recommandations
attendu une fois `rules.json` chargé.

---

## 15. Hors périmètre

Ces fonctions ont été écartées et ne doivent pas être développées, même si elles paraissent
faciles.

- Fonctionnement hors connexion.
- Envoi réel de messages courts.
- Données météorologiques.
- Modèles prédictifs ou apprentissage automatique.
- Facturation des abonnements.
- Gestion des stocks d'intrants.
- Interface multilingue.
- Application mobile native.

---

## Annexe A : libellés français des énumérations

| Valeur | Libellé affiché |
|---|---|
| `super_admin` | Administrateur de la solution |
| `coop_admin` | Administrateur de coopérative |
| `technician` | Technicien |
| `farmer` | Agriculteur |
| `tillage` | Labour |
| `sowing` | Semis |
| `fertilization` | Fertilisation |
| `treatment` | Traitement |
| `weeding` | Sarclage |
| `irrigation` | Irrigation |
| `harvest` | Récolte |
| `npk` | NPK |
| `urea` | Urée |
| `potassium` | Potassium |
| `phosphate` | Phosphate |
| `organic` | Fumure organique |
| `seed` | Semence |
| `inoculant` | Inoculant |
| `herbicide` | Herbicide |
| `insecticide` | Insecticide |
| `fungicide` | Fongicide |
| `other` | Autre |
| `kg` | Kilogramme |
| `l` | Litre |
| `sac_50kg` | Sac de 50 kg |
| `sac_25kg` | Sac de 25 kg |
| `unite` | Unité |
| `planned` | Planifié |
| `in_progress` | En cours |
| `harvested` | Récolté |
| `abandoned` | Abandonné |
| `high` | Élevée |
| `medium` | Modérée |
| `low` | Faible |
| `new` | Nouvelle |
| `read` | Lue |
| `applied` | Appliquée |
| `dismissed` | Écartée |
| `sourced` | Source togolaise publiée |
| `imported` | Source régionale |
| `convention` | Convention à confirmer |
| `grande_saison` | Grande saison |
| `petite_saison` | Petite saison |
| `ferrugineux` | Ferrugineux |
| `sableux` | Sableux |
| `argileux` | Argileux |
| `limoneux` | Limoneux |
| `hydromorphe` | Hydromorphe |
| `good` | Bonne |
| `average` | Moyenne |
| `poor` | Mauvaise |
| `M` | Homme |
| `F` | Femme |
| `A` | Écart au référentiel technique |
| `B` | Écart au calendrier cultural |
| `C` | Comparaison entre parcelles semblables |
| `D` | Économie et durabilité |

---

## Annexe B : questions à poser avant de commencer

Ces points ne sont pas tranchés et ne doivent pas être devinés.

1. Les fichiers `crops.json`, `varieties.json` et `rules.json` sont-ils disponibles ? Sans eux,
   les modules 3 et 6 ne peuvent pas être terminés.
2. Le script Python de vérification et son jeu de données sont-ils disponibles pour être portés
   en fixture Pest ?
3. Le découpage des zones de comparaison est-il bien le village, ou faut-il prévoir le canton ou
   le secteur du technicien ? La spécification retient le village avec repli sur la région.
4. La liste réelle des intrants utilisés par la coopérative est-elle arrêtée ? Elle fixe le
   contenu définitif de l'énumération `InputType`.
5. Le producteur connaît-il sa densité de semis en plants par hectare, ou seulement la quantité
   de semence employée ? Le champ `density` est facultatif pour cette raison.
