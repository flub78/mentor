# Plan d'implémentation — Module QCM, MVP

- **Statut** : en cours — étape 0 livrée
- **Date** : 2026-11-08
- **Auteur** : Frédéric Peignot (assisté d'un agent IA)
- **PRD associé** : [qcm_prd.md](../prds/qcm_prd.md), §8 « Découpage en lots », lot MVP
- **Documents liés** : [PRD Mentor](../prds/mentor_prd.md) §1.4 (architecture modulaire),
  [guide de l'application dérivée](../../../laraskel/doc/development/guide-application-derivee.md)

---

## 1. Objectif du MVP

Un utilisateur unique, **sans connexion**, peut :

1. créer des banques de questions et saisir, modifier, supprimer leurs questions ;
2. s'entraîner en **mode étude** sur une ou plusieurs banques, avec correction immédiate.

Critère de sortie : l'utilisateur crée une banque, y saisit une dizaine de questions réparties
sur deux modules, dont une avec une image, puis fait une session d'étude dont le bilan par
module est juste.

## 2. Périmètre

### Inclus

| PRD | Contenu retenu pour le MVP |
|---|---|
| UC-03 Gérer les banques | Créer, renommer, supprimer (avec confirmation) une banque ; liste avec le nombre de questions. |
| UC-04 Gérer les questions | Créer, modifier, supprimer une question : énoncé, bonne réponse, mauvaises réponses (3 par défaut, au moins 1) avec explication facultative, explication générale facultative, une image facultative, module facultatif en texte libre avec proposition des noms déjà utilisés dans la banque. Filtre de la liste par module. |
| UC-11 Étudier en mode étude | Choix des banques ; nombre de questions par module ou « toutes » ; tirage au hasard ; réponses mélangées ; correction immédiate avec explications ; pourcentage de bonnes réponses depuis le début de la session ; bilan final global et par module. Modules regroupés sans tenir compte des majuscules. |

### Reporté (hors MVP)

| Élément | PRD | Lot cible (PRD §8) |
|---|---|---|
| Dupliquer une banque ; décompte par module dans la liste des banques | UC-03 | Lot 2 |
| Prévisualisation, recherche plein texte, actions groupées, détection des doublons, interdiction du mélange des réponses par question | UC-04 | Lot 2 |
| Archivage et versions des questions déjà utilisées | UC-04 | Lot 2 (avec les examens) |
| Copier / déplacer des questions | UC-05 | Lot 2 |
| Export / import CSV et ZIP | UC-06, UC-07 | Lot 2 |
| Examens, passage, correction, historique et progression | UC-08, UC-12 à UC-14 | Lot 2 |
| Passer une question sans répondre ; relancer sur les questions échouées ; enregistrement des réponses d'étude | UC-11 (4a, 7a, règle) | Lot 2 |
| Filtres de tirage (jamais vues, échouées) | UC-11 (2a) | v1 |
| Comptes, rôles, accès, résultats, statistiques, signalements | UC-01, 02, 09, 10, 15 | v1 |
| Statut brouillon / publié, appartenance des banques, groupes, reprise de session, autres médias | — | v2 |

## 3. Décisions techniques

| Sujet | Décision | Alternatives écartées |
|---|---|---|
| Interface | Un **panel Filament `qcm`** (`/qcm`) pour les banques, les questions et la page d'étude. Filament est déjà présent (brique `laraskel-users`) et c'est la brique CRUD retenue par Laraskel. | Pages Blade + Livewire écrites à la main : plus de code pour le même résultat. Panel `admin` de `laraskel-users` : il impose la connexion. |
| Sans connexion | Le panel `qcm` n'a ni page de connexion ni `authMiddleware`. Il devient le panel par défaut (`LARASKEL_USERS_ADMIN_PANEL_DEFAULT=false`) ; `/` redirige vers `/qcm`. Le panel `admin` reste en place, inutilisé. **Le MVP n'est pas fait pour être exposé sur Internet.** | Utilisateur fictif connecté automatiquement : complique le passage aux rôles en v1 sans rien apporter. |
| Emplacement du code | Dans l'application, sous l'espace de noms `App\Qcm\` (modèles, ressources Filament, services) ; tables préfixées `qcm_`. L'extraction en package reste possible (PRD Mentor, Q28). | Package `packages/qcm` dès maintenant : prématuré tant que Q28 n'est pas tranchée. |
| Réponses | Une table `qcm_answers` avec `is_correct` ; exactement une bonne réponse par question, contrôlée à l'enregistrement. | Bonne réponse en colonne de la question : complique le mélange et l'affichage uniforme des réponses. |
| Image | Fichier sur le disque `public`, chemin dans la question ; ancien fichier supprimé au remplacement et à la suppression. | `spatie/laravel-medialibrary` : la brique médias de Laraskel n'est pas encore extraite ; à reprendre quand elle le sera. |
| Session d'étude | État tenu dans le composant Livewire de la page (questions tirées, réponses, score) ; rien n'est écrit en base. Un rechargement de page perd la session (reprise reportée, Q10). | Table de sessions : utile seulement avec l'historique (lot 2 du PRD). |
| Regroupement des modules | Clé de regroupement = nom en minuscules (`mb_strtolower`) ; libellé affiché = la forme la plus fréquente. Questions sans module dans « Sans module ». | — |

### Modèle de données

```
qcm_banks      id, name (unique), description?, auditColumns
qcm_questions  id, bank_id → qcm_banks (cascade), statement, module?, explanation?, image_path?, auditColumns
qcm_answers    id, question_id → qcm_questions (cascade), text, is_correct, explanation?, position, auditColumns
```

`created_by` / `updated_by` restent nuls dans le MVP (pas d'utilisateur connecté).

---

## 4. Étapes du MVP

### Étape 0 — Panel sans connexion

- [x] Créer le panel : `php artisan make:filament-panel qcm` ; dans `QcmPanelProvider`, `->default()`, `->path('qcm')`, retirer `->login()`, `AuthenticateSession` et `->authMiddleware(...)`, ainsi que le widget de compte ; découverte des ressources, pages et widgets dans `app/Qcm/Filament`.
- [x] Ajouter `LARASKEL_USERS_ADMIN_PANEL_DEFAULT=false` à `.env` et `.env.example`.
- [x] Remplacer la route `/` de `routes/web.php` par une redirection vers `/qcm`.
- [x] Mettre le panel en français : `APP_LOCALE=fr`, `APP_FAKER_LOCALE=fr_FR`, nom de marque « Mentor — QCM ».
- [x] Publier les assets Filament à chaque `composer install` / `dump-autoload` (`@php artisan filament:upgrade` dans `post-autoload-dump`) ; `/public/css`, `/public/js` et `/public/fonts` ignorés par git.
- [x] `php artisan storage:link` ; le noter dans le README (installation).
- [x] Tests (`tests/Feature/Qcm/PanelTest.php`) :
  - [x] `/` redirige vers `/qcm` ;
  - [x] `/qcm` répond 200 sans être connecté ;
  - [x] le panel `qcm` est le panel par défaut ;
  - [x] `/admin` demande toujours la connexion ;
  - [x] l'application est en français.
- [x] Retirer le test d'exemple du squelette (`tests/Feature/ExampleTest.php`), qui attendait une page d'accueil en 200.
- [x] `composer qa` vert ; commit.

### Étape 1 — Banques et questions

**Base de données et modèles**

- [ ] Migration `create_qcm_banks_table` : colonnes du §3, `name` unique, `auditColumns()`.
- [ ] Migration `create_qcm_questions_table` : clé étrangère `bank_id` en cascade, index sur `(bank_id, module)`.
- [ ] Migration `create_qcm_answers_table` : clé étrangère `question_id` en cascade, `position` entier.
- [ ] Modèles `App\Qcm\Models\Bank`, `Question`, `Answer` avec relations (`Bank::questions`, `Question::answers`, `Question::correctAnswer`, `Question::wrongAnswers`) et trait `TracksAuthor`.
- [ ] `Bank::moduleNames()` : noms de module distincts des questions de la banque, triés, pour l'autocomplétion.
- [ ] Suppression du fichier image lors du remplacement de l'image et de la suppression d'une question (y compris en cascade depuis une banque).
- [ ] Fabriques `BankFactory`, `QuestionFactory` (avec un état `withAnswers(wrong: 3)`), `AnswerFactory`.

**Interface**

- [ ] Ressource Filament `BankResource` : liste (nom, description, nombre de questions), création, modification, suppression avec confirmation indiquant le nombre de questions supprimées.
- [ ] Gestionnaire de relation `QuestionsRelationManager` dans la banque : liste (énoncé tronqué, module, présence d'image), filtre par module, création, modification, suppression avec confirmation.
- [ ] Formulaire de question :
  - [ ] énoncé (texte multiligne, obligatoire) ;
  - [ ] bonne réponse (obligatoire) ;
  - [ ] mauvaises réponses : répéteur, 3 lignes par défaut, au moins 1, chaque ligne avec texte obligatoire et explication facultative ;
  - [ ] explication générale facultative ;
  - [ ] image facultative (`FileUpload`, images seulement, taille maximale à fixer dans la config, Q8) ;
  - [ ] module : champ texte avec suggestions `Bank::moduleNames()` de la banque courante (`TextInput::datalist`).
- [ ] Enregistrement de la question et de ses réponses dans une transaction ; positions des réponses conservées.
- [ ] Messages de succès et d'erreur explicites pour chaque action (règle commune UX).

**Tests**

- [ ] Modèles : relations ; `moduleNames()` ne renvoie que les modules de la banque, sans doublon ; suppression d'une banque → questions, réponses et images supprimées.
- [ ] Ressource banque : créer, renommer, supprimer ; nom obligatoire et unique.
- [ ] Questions : créer avec 3 mauvaises réponses ; refus sans énoncé, sans bonne réponse, sans mauvaise réponse ; modifier les réponses ; supprimer ; filtre par module.
- [ ] Image : envoi stocké sur le disque `public` (`Storage::fake`) ; remplacement et suppression effacent l'ancien fichier ; refus d'un fichier qui n'est pas une image.

**Documentation utilisateur**

- [ ] `doc/users/qcm.md` : « Créer une banque », « Saisir une question », « Réutiliser un nom de module ».
- [ ] `composer qa` vert ; vérification manuelle dans le navigateur (ordinateur et largeur téléphone) ; commit.

### Étape 2 — Étude libre

**Logique**

- [ ] Service `App\Qcm\Study\ModuleCatalog` : pour une liste de banques, regroupe les questions par module sans tenir compte des majuscules, avec libellé (forme la plus fréquente) et nombre de questions ; groupe « Sans module ».
- [ ] Service `App\Qcm\Study\StudyDraw` : à partir d'un nombre demandé par module (ou « toutes »), tire les questions au hasard, sans doublon ; un nombre supérieur au disponible est ramené au disponible, avec un message.
- [ ] Objet `StudySession` (sérialisable dans Livewire) : liste des identifiants de questions, ordre mélangé des réponses par question, réponses données, score courant, score par module.

**Page d'étude (`App\Qcm\Filament\Pages\Study`)**

- [ ] Écran 1, configuration : choix d'une ou plusieurs banques ; tableau des modules avec le nombre disponible, un champ nombre et une case « toutes » par module ; bouton « Commencer » désactivé tant que le total est nul.
- [ ] Écran 2, question : énoncé, image éventuelle, réponses mélangées ; l'utilisateur choisit et valide.
- [ ] Après validation : bonne ou mauvaise réponse signalée, bonne réponse mise en évidence, explication de la mauvaise réponse choisie et explication générale si elles existent ; bouton « Question suivante ».
- [ ] En permanence : pourcentage de bonnes réponses depuis le début de la session et nombre de questions restantes.
- [ ] Écran 3, bilan : taux global, taux par module, liste des questions échouées avec leur bonne réponse ; bouton « Nouvelle session ».
- [ ] Lien « Étudier » dans la navigation du panel et depuis la liste des banques (banque présélectionnée).
- [ ] Affichage utilisable à largeur téléphone.

**Tests**

- [ ] `ModuleCatalog` : « Météo » et « météo » forment un seul module ; libellé majoritaire ; groupe « Sans module » ; plusieurs banques.
- [ ] `StudyDraw` : nombre exact par module ; « toutes » ; demande supérieure au disponible ; pas de doublon.
- [ ] Page : session complète (configuration → réponses → bilan) avec Livewire ; bonne et mauvaise réponse mettent à jour le pourcentage ; le bilan par module est juste ; une question ne peut pas être validée sans réponse choisie.
- [ ] Mélange : sur une question, l'ordre des réponses n'est pas toujours le même (tirage répété avec graine contrôlée).

**Documentation utilisateur**

- [ ] `doc/users/qcm.md` : « Lancer une session d'étude », « Lire le bilan ».
- [ ] `composer qa` vert ; vérification manuelle dans le navigateur ; commit.

### Étape 3 — Clôture du MVP

- [ ] Dérouler le critère de sortie du §1 dans le navigateur, sur ordinateur et à largeur téléphone.
- [ ] README : comment lancer l'application et accéder au module QCM.
- [ ] Mettre à jour le statut de ce plan et du PRD QCM (§8).
- [ ] `composer qa` vert ; `php artisan laraskel:outillage --check` conforme ; PR.
