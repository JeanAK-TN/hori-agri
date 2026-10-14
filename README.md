# AgriRepère

Solution SaaS d'aide à la décision pour les exploitations agricoles. Les techniciens saisissent
l'itinéraire technique des parcelles ; la plateforme calcule des indicateurs, confronte les
pratiques à une base de règles agronomiques sourcées et restitue des recommandations priorisées,
justifiées et traçables.

Ce dépôt est le support technique d'un mémoire de Licence 3 Systèmes d'Information, option
Génie Logiciel (Lomé Business School). Terrain d'étude : l'entreprise maraîchère INSECTGO, au
Togo. La culture couverte par la base de règles est la tomate, à partir de la fiche
« Techniques culturales de la tomate » du Ministère de l'Agriculture du Togo.

## La chaîne fonctionnelle

```
saisie des données -> calcul d'indicateurs -> évaluation des règles -> recommandations
```

1. **Saisie** : producteurs, parcelles, campagnes, cycles culturaux, opérations de l'itinéraire
   technique (labour, repiquage, fertilisation, traitements, récoltes), import de fichier.
2. **Indicateurs** : rendement en kg/ha, coût de production à l'hectare, marge brute, médiane de
   zone, calculés par `IndicatorService`.
3. **Moteur de règles** : chaque règle est une ligne en base de données, jamais une ligne de
   code. Les conditions sont des arbres JSON (neuf opérateurs : `=`, `!=`, `<`, `<=`, `>`, `>=`,
   `between`, `in`, `not_in` ; deux combinateurs : `and`, `or`) évalués en PHP par
   `RuleEvaluator` sur un contexte de sept champs (`EvaluationContext`).
4. **Restitution** : recommandations priorisées par sévérité, avec la pratique observée, la
   pratique de référence, l'action conseillée et la source agronomique en toutes lettres.
   Le producteur indique s'il a appliqué le conseil (retour d'expérience).

### La base de règles

- Cinq règles actives, toutes sur la tomate, toutes issues de la même source togolaise publiée.
  Le fichier `database/seeders/data/rules.json` est l'unique source ; aucun seuil agronomique
  n'apparaît dans le code.
- Quatre familles : A (écart au référentiel technique), B (écart au calendrier cultural),
  C (comparaison entre pairs), D (économie et durabilité, sans règle active à ce jour).
- Garde-fou de la famille C : la comparaison entre pairs est suspendue tant que la zone compte
  moins de cinq parcelles comparables. Le moteur préfère ne pas conclure plutôt que conclure mal ;
  de même, une condition portant sur un champ non renseigné ne se déclenche pas.
- Chaque règle affiche l'origine de son seuil (source publiée, note éventuelle) et une
  exploitation peut surcharger un seuil pour elle seule, la valeur commune restant visible.
- La commande `php artisan rules:validate` contrôle le fichier des règles (répartitions
  attendues, unicité de la source, aucun seuil simulé, champs connus du contexte) et fait
  partie de l'intégration continue.

## Multi-tenant et rôles

Le cloisonnement entre exploitations suit le patron du schéma partagé : chaque table métier
porte une colonne `exploitation_id`, un global scope Eloquent filtre toutes les requêtes et un
accès croisé répond « ressource introuvable », jamais « accès refusé ». Des tests d'isolation
dédiés (dossier `tests/Feature/H4`) le prouvent.

| Rôle | Portée |
|---|---|
| `super_admin` | Éditeur de la solution : exploitations, référentiel commun des cultures |
| `exploitation_admin` | Administre son exploitation : comptes, campagnes, règles, tableau de bord |
| `technician` | Saisit producteurs, parcelles, cycles, opérations et récoltes |
| `farmer` | Consulte ses recommandations et y répond |

La connexion se fait par numéro de téléphone et mot de passe (Laravel Fortify). L'interface est
intégralement en français, mobile d'abord, avec des cibles tactiles d'au moins 48 pixels.

## Pile technique

- PHP 8.3+ (8.5 en CI), Laravel 13, Laravel Fortify, Laravel Wayfinder
- Inertia 3, React 19, TypeScript, Tailwind CSS 4, composants Radix UI, Chart.js
- PostgreSQL (les conditions des règles sont stockées en JSON et évaluées en PHP, pas en SQL)
- Pest 5 (tests unitaires, fonctionnels et navigateur via Playwright), Larastan, Pint,
  ESLint, Prettier
- Laravel Boost (MCP et guidelines pour l'assistance au développement)

## Démarrage

Prérequis : PHP 8.3 ou plus, Composer, Node 22, PostgreSQL.

```bash
# Bases de données attendues
createdb agrirepere        # développement (.env)
createdb agrirepere_test   # tests (phpunit.xml)

composer setup             # install, .env, clé, migrations, npm install, build
php artisan migrate:fresh --seed
composer dev               # serveur, file d'attente et Vite en parallèle
```

Renseigner `DB_USERNAME` et `DB_PASSWORD` dans `.env` avant les migrations. Le seeder crée le
référentiel (tomate, variétés, cinq règles) et quatre comptes de démonstration, un par rôle,
aux numéros `00000000` à `00000003`, mot de passe `password`. Ce sont des fixtures : aucune
donnée de la ferme réelle n'est versionnée.

## Tests et qualité

```bash
php artisan test --compact                        # toute la suite
php artisan test --compact --testsuite=Unit,Feature   # sans les tests navigateur
php artisan rules:validate                        # contrôle du fichier des règles
vendor/bin/pint --format agent                    # formatage PHP
vendor/bin/phpstan analyse                        # analyse statique
npm run lint && npm run types:check               # ESLint et TypeScript
composer ci:check                                 # tout ce que la CI exécute
```

Les tests s'exécutent sur la base PostgreSQL `agrirepere_test`. Les tests navigateur
(`tests/Browser`) demandent Playwright (`npx playwright install chromium`) et un front construit
(`npm run build`).

La suite est organisée par hypothèse du mémoire :

| Dossier | Hypothèse éprouvée |
|---|---|
| `tests/Feature/H1` | La saisie : opérations, récoltes, import |
| `tests/Feature/H2` | Le moteur : déclenchement et affichage des recommandations |
| `tests/Feature/H3` | La comparaison entre pairs et son seuil de suspension |
| `tests/Feature/H4` | L'isolation entre exploitations |

`VerificationDatasetSeeder` rejoue le jeu d'épreuve décrit dans le mémoire (deux parcelles de
tomate, l'une en écart, l'autre conforme) et `VerificationDatasetTest` vérifie que le moteur
produit exactement les recommandations attendues.

L'intégration continue (GitHub Actions, `.github/workflows/tests.yml`) exécute `composer
ci:check` : lint PHP et front, types TypeScript, analyse statique, validation du fichier des
règles et suite de tests.

## Organisation du code

```
app/
  Enums/                   rôles, types d'opération, sévérités, familles de règles...
  Models/                  Eloquent ; Scopes/ porte le filtre d'exploitation
  Services/
    Indicators/            IndicatorService : rendement, coûts, marges, médianes
    Rules/                 RuleEvaluator, EvaluationContext, RecommendationService,
                           RulesFileValidator
    Dashboard/             agrégats et export du tableau de bord
    Import/                ImportService : dépôt de fichier et rapport d'import
database/seeders/data/     crops.json, varieties.json, rules.json (sources des référentiels)
resources/js/pages/        écrans Inertia + React (auth, farmers, plots, cycles, regles,
                           recommandations, tableau-de-bord, exploitations, users...)
lang/fr/                   messages de validation et d'authentification en français
tests/                     Unit, Feature (H1 à H4), Browser (Playwright)
```

Conventions : le code (classes, tables, colonnes, variables) est en anglais ; tout ce que
l'utilisateur voit est en français. La logique métier vit dans `app/Services`, pas dans les
contrôleurs. Aucune valeur agronomique n'est écrite en dur : ce qui ne vient pas d'une source
publiée reste nul.

## Documents de référence

- `SPEC.md` : spécification de construction initiale.
- `SPEC-CORRECTION-AGRIREPERE.md` : mise en conformité du code avec la version finale du
  mémoire (marque AgriRepère, entité exploitation, base réduite à cinq règles sur la tomate).
- `stitch_hori_agri_decision_support/` : maquettes d'interface exportées de Google Stitch.
