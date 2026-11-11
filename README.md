# mentor
Support d'apprentissage assisté par IA

## Socle technique

Mentor est une application dérivée de [Laraskel](../laraskel) : un projet Laravel qui consomme les briques du socle (`laraskel-core`, `laraskel-users`…) en packages Composer et les étend par composition. Pendant la phase de co-évolution, les deux dépôts sont côte à côte et les briques sont référencées en *path repository* (`../laraskel/packages/*`). Voir le [guide de l'application dérivée](../laraskel/doc/development/guide-application-derivee.md).

## Installation locale

`laraskel` doit être cloné à côté de `mentor` (`../laraskel`).

```bash
composer install            # publie aussi les assets Filament
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan storage:link    # images des questions
php artisan serve
```

L'application s'ouvre sur le module QCM (`/qcm`). Le MVP n'a pas de connexion : ne pas l'exposer sur Internet.

### Mettre à jour une base existante

Sauvegardez la base avant `php artisan migrate`. C'est indispensable pour la migration qui bascule le module QCM sur le module Banques de questions (`switch_qcm_to_banques_questions`) : elle reprend les banques et questions QCM et ne s'annule pas. Elle refuse de s'exécuter tant qu'un passage d'examen est en cours.

```bash
cp database/database.sqlite database/database.sqlite.sauvegarde   # base SQLite par défaut
php artisan migrate
```

## Architecture modulaire

Mentor s'architecture sur des modules indépendants qui s'utilisent les uns les autres. Chaque module peut être utilisé seul ou regroupé dans Mentor ; un module est utilisable dès qu'il est disponible (le module QCM en premier).

| Module | Rôle |
|---|---|
| **QCM** | Gère des banques de questions ; crée et gère des examens à partir de ces banques. |
| **Leitner** | Étude par répétition espacée avec des questions / réponses ; utilise les questions QCM et des questions textuelles. |
| **Présentation** | Équivalent PowerPoint (ou bibliothèque existante) ; pour commencer, les pages sont des pages Markdown. |
| **Déroulement de support** | Déroule des présentations, des PDF, des vidéos, en enregistrant les temps de lecture. |
| **Coach IA** | Connaît les autres modules ; définit et adapte un programme adapté à l'étudiant. |

## Documentation

- [PRD d'ensemble](doc/prds/mentor_prd.md) — parcours intégré et architecture (§1.4).
- [Plan du module QCM](doc/plans/qcm_plan.md) — lots et étapes avec cases à cocher.
- Chaque module a son PRD indépendant dans `doc/prds/` :
  - [QCM](doc/prds/qcm_prd.md) ;
  - à rédiger : `leitner_prd.md`, `presentation_prd.md`, `deroulement_prd.md`, `coach_prd.md`.
