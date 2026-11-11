# Plan d'implémentation — Module Leitner

- **Statut** : MVP en cours — étapes 0 à 4 livrées (vérification à largeur téléphone à faire)
- **Date** : 2026-11-11
- **Auteur** : Frédéric Peignot (assisté d'un agent IA)
- **PRD associé** : [leitner_prd.md](../prds/leitner_prd.md), §9.2 « Découpage en lots »
- **Documents liés** : [PRD Mentor](../prds/mentor_prd.md) §1.4 (architecture modulaire),
  [PRD QCM](../prds/qcm_prd.md), [plan QCM](qcm_plan.md) (conventions reprises ici)

## Vue d'ensemble des lots

| Lot | Contenu (PRD §9.2) | Étapes | Statut |
|---|---|---|---|
| MVP | Banques, questions de quatre types, suites, séances selon les règles §7, état d'une suite | 0 à 5 (§4) | En cours |
| Lot B1 (module Banques) | Banques communes aux modules QCM et Leitner : voir le [plan Banques](banques_plan.md) | — | À faire |
| Lot 2 — Séances enrichies et suivi | Nouveaux types en séance (lot B2 du module Banques) ; temps de réponse ; sources par niveau ; actions sur les questions ; contestation ; historique ; suite acquise, contrôle d'entretien, test de placement | 6 à 10 (§5) | À faire |
| Lot 3 — Questions ouvertes | Questions ouvertes corrigées par l'IA en séance (lot B3 du module Banques) | 11 et 12 (§6) | À faire |
| v1, v2 | Comptes et rôles, suites proposées, signalements ; taille d'étude, autres types, etc. | À détailler (§7) | — |

Les étapes s'appuient sur des choix provisoires pour des questions ouvertes du PRD ; ils sont
signalés « (Qn, choix provisoire) » et à revoir si la question est tranchée autrement.

---

## 1. Objectif du MVP

Un utilisateur unique, **sans connexion**, peut :

1. créer des banques de questions de quatre types : QCM, vrai / faux, auto-évaluation, valeur
   numérique ;
2. créer une suite sur une ou plusieurs banques, avec ses paramètres ;
3. faire des séances qui appliquent les règles du PRD §7 : boîtes posées selon le numéro de
   séance, introduction des nouvelles questions par difficulté croissante, recul d'une boîte puis
   retour en boîte 1, reprise des erreurs en fin de séance, ordre de présentation ;
4. voir à tout moment l'état de la suite et l'avertissement de régularité.

Critère de sortie (PRD §9.2) : l'utilisateur crée une banque de questions des quatre types, une
suite de 5 boîtes, enchaîne au moins 8 séances, et l'état affiché (boîtes, banque, prochaine
séance) correspond aux règles §7.

## 2. Périmètre

### Inclus

| PRD | Contenu retenu pour le MVP |
|---|---|
| UC-01 Gérer les banques | Créer, renommer, supprimer (confirmation avec le nombre de questions et de suites concernées) ; liste avec le nombre de questions. |
| UC-02 Gérer les questions | Types QCM, vrai / faux, auto-évaluation, valeur numérique ; énoncé, image facultative avec texte alternatif obligatoire, explication, difficulté (Q13, choix provisoire : 3 niveaux), thème ; modification mineure ou de fond (1a) ; suppression ; filtres par type, difficulté, thème. |
| UC-03 Ajouter ses questions à une suite | « Nouvelle question » depuis une suite : banque personnelle de la suite créée au besoin ; placement en boîte 1 ou en banque. |
| UC-05 Créer une suite | Nom, description, banques sources (sans restriction par thème ni difficulté), nombre de boîtes (5), minimum en boîte 1 (10, Q4b), ordre de présentation, reprise des erreurs (oui). Modification des paramètres et des sources (3a). |
| UC-08 Faire une séance | Scénario nominal ; variantes 2a, 3a, 4a (passer), 4b (annuler la dernière réponse), 4c (reprise des erreurs), 6a (reprise d'une séance interrompue). |
| UC-11 État d'une suite | Questions par boîte, en banque (par difficulté), maîtrisées, suspendues ; prochaine séance ; dernière séance ; régularité et avertissement (§7.5). |

### Reporté (hors MVP)

| Élément | PRD | Lot cible |
|---|---|---|
| Duplication d'une banque ; décompte par type, difficulté et thème dans la liste | PRD Banques UC-01 | Lot B2 (module Banques) |
| Prévisualisation d'une question ; recherche plein texte ; doublons probables | PRD Banques UC-02 | Lot B2 (module Banques) |
| Types énumération, correspondance, réponse courte ; images dans les propositions | PRD Banques §6 | Lot B2 (module Banques), puis lot 2 en séance |
| Restriction d'une source par thème ou jusqu'à un niveau de difficulté ; état par banque | UC-05 (2), UC-11 | Lot 2 |
| Temps de réponse limité | UC-08 (4e), §7.7 | Lot 2 |
| Création d'une question pendant une séance | UC-03 (variante) | Lot 2 |
| Import / export des banques | PRD Banques UC-04, UC-05 | Lot B1 (module Banques) |
| Actions sur les questions d'une suite ; réinitialisation | UC-06, UC-07 | Lot 2 |
| Contestation d'un verdict | UC-09 | Lot 2 (IA lot 3) |
| Historique et statistiques | UC-12 | Lot 2 |
| Suite acquise, contrôle d'entretien, test de placement | UC-14, UC-15, §7.6 | Lot 2 |
| Questions ouvertes corrigées par l'IA | PRD Banques §6 | Lot B3 (module Banques), puis lot 3 en séance |
| Comptes, suites proposées, signalements, accès (banques prédéfinies et personnelles : module Banques) | UC-05 (1a), UC-10, UC-13 | v1 |
| Taille d'étude, texte à trous, classement, désignation sur image, questions inversées, etc. | — | v2 |

## 3. Décisions techniques

| Sujet | Décision | Alternatives écartées |
|---|---|---|
| Interface | Un **panel Filament `leitner`** (`/leitner`), sans connexion, comme le panel `qcm` ; marque « Mentor — Leitner ». Le panel `qcm` reste le panel par défaut et `/` redirige toujours vers `/qcm` ; un lien de navigation relie les deux panels. | Ressources Leitner dans le panel `qcm` : mélange deux modules que le PRD veut indépendants. Page d'accueil commune aux modules : relève de l'assemblage Mentor, pas de ce module. |
| Emplacement du code | `App\Leitner\` (modèles, ressources Filament, services), tables préfixées `leitner_`, `config/leitner.php`. Les banques et les questions, d'abord dans ce module, passent au module Banques de questions au lot B1 ([plan Banques](banques_plan.md)). | Package dès maintenant : prématuré tant que la Q28 du PRD Mentor n'est pas tranchée (même choix que le QCM). |
| Types de questions | Une seule table `leitner_questions` avec une colonne `type` (énumération PHP `QuestionType`) et une colonne `payload` JSON qui porte la réponse attendue propre au type. Chaque type a une classe qui implémente `QuestionTypeHandler` : schéma du formulaire, validation du `payload`, vue de saisie de la réponse, correction (`evaluate()` → `Evaluation` : verdict et détail), texte de la réponse attendue. Ajouter un type = ajouter une classe et une vue. | Une table par type : multiplie migrations, relations et requêtes pour l'état d'une suite. Table de réponses comme le QCM : ne convient ni à l'énumération, ni à la correspondance, ni à la valeur numérique. |
| Progression | Une **progression** par suite (`leitner_progressions`), avec `user_id` nul jusqu'en v1. Les questions *en banque* ne sont pas enregistrées : ce sont les questions des sources sans ligne dans `leitner_progress_items`. Une ligne est créée à l'introduction (ou au placement) et porte l'état (`box`, `mastered`, `suspended`), la boîte et le nombre d'échecs consécutifs. Les banques ne sont jamais modifiées par l'entraînement (PRD §3). | Une ligne par question des sources dès la création de la suite : à resynchroniser à chaque ajout de question dans une banque partagée. État porté par la question : interdit le partage des banques entre suites et étudiants. |
| Séance | Tables `leitner_sessions` et `leitner_session_entries`. Les entrées de la séance (question, position, boîte de départ, reprise ou non) sont **créées au lancement** ; chaque réponse est écrite dès qu'elle est donnée (réponse, verdict, boîte d'arrivée). La reprise d'une séance interrompue repart de la première entrée sans réponse. | État dans le composant Livewire, comme l'étude QCM : contraire à l'exigence de fiabilité du PRD et à la reprise (UC-08, 6a). |
| Règles §7 | Logique pure, sans Eloquent, dans `App\Leitner\Engine\` : `BoxSchedule` (boîtes posées à la séance *n*), `BoxMover` (mouvement selon le verdict et les échecs consécutifs), `Introducer` (choix des nouvelles questions : difficulté croissante, hasard à graine injectable), `SessionComposer` (entrées d'une séance dans l'ordre de présentation), `Regularity` (séances des 7 derniers jours, avertissement). Testées unitairement de façon exhaustive. | Règles dans les modèles ou les pages : difficiles à tester et à faire évoluer (Q2, Q5). |
| Numéro de séance | Le numéro est attribué à la **première réponse** de la séance (PRD UC-08, règles) ; une séance abandonnée sans réponse est supprimée. La composition utilise le numéro suivant le dernier attribué. | Numéro attribué au lancement : une séance ouverte puis fermée décalerait le rythme. |
| Difficulté | Entier 1 (facile), 2 (moyen), 3 (difficile), nullable ; nul compte comme 2 (Q13, choix provisoire). | — |
| Comparaison des réponses saisies | `AnswerNormalizer` : minuscules, accents retirés, espaces réduits ; nombres acceptés avec virgule ou point (Q10, choix provisoire). | Distance d'édition : reportée à la Q10. |
| Image | Comme le QCM : fichier sur le disque `public`, chemin et texte alternatif dans la question ; fichier supprimé au remplacement et à la suppression. Taille maximale `config/leitner.php` (`LEITNER_IMAGE_MAX_KB`, 2 Mo). | Classe d'image du QCM : stockage interne d'un autre module. Médiathèque : pas encore extraite dans Laraskel ; à reprendre quand elle le sera. |
| Page de séance | Page Filament `App\Leitner\Filament\Pages\Seance` (Livewire) qui délègue la saisie à une vue Blade par type (`resources/views/leitner/answers/<type>.blade.php`) ; raccourcis clavier en Alpine.js. | Composant Livewire par type : plus de code pour un gain nul tant que les saisies restent simples. |

### Modèle de données

```
leitner_banks            id, name (unique), description?, auditColumns
leitner_questions        id, bank_id → leitner_banks (cascade), type, statement, payload (json),
                         explanation?, difficulty? (1-3), theme?, image_path?, image_alt?, auditColumns
leitner_suites           id, name, description?, box_count (5), min_box_one (10),
                         presentation_order, retry_errors (bool), personal_bank_id? → leitner_banks (nullOnDelete),
                         auditColumns
leitner_suite_sources    id, suite_id (cascade), bank_id → leitner_banks (cascade), auditColumns ; unique(suite_id, bank_id)
leitner_progressions     id, suite_id (cascade), user_id? → users (cascade), last_session_number (0),
                         auditColumns
leitner_progress_items   id, progression_id (cascade), question_id → leitner_questions (cascade),
                         box? , mastered_at?, suspended_at?, consecutive_failures (0),
                         introduced_at, auditColumns ; unique(progression_id, question_id)
leitner_sessions         id, progression_id (cascade), kind (seance), number?, status (en_cours,
                         terminee, abandonnee), presentation_order, undo_available (bool),
                         started_at, finished_at?, auditColumns
leitner_session_entries  id, session_id (cascade), question_id? → leitner_questions (nullOnDelete),
                         retry_of_id? → leitner_session_entries (cascade), position, is_retry, is_new,
                         box_before?, failures_before?, response (json)?, verdict?, detail?,
                         box_after?, mastered (bool), answered_at?, skipped_at?, duration_ms?,
                         auditColumns
```

`created_by` / `updated_by` restent nuls tant qu'il n'y a pas de connexion. `kind` prendra les
valeurs `controle` et `placement` au lot 2.

---

## 4. Étapes du MVP

### Étape 0 — Panel Leitner sans connexion

- [x] Créer le panel : `php artisan make:filament-panel leitner` ; dans `LeitnerPanelProvider`, `->path('leitner')`, sans `->login()` ni `->authMiddleware(...)`, `->databaseTransactions()`, découverte dans `app/Leitner/Filament`, marque « Mentor — Leitner », couleur distincte du QCM. Bloc PHPDoc qui rappelle que le panel n'est pas fait pour être exposé sur Internet.
- [x] Liens de navigation croisés : « Leitner » dans le panel `qcm`, « QCM » dans le panel `leitner` (`NavigationItem` vers l'URL de l'autre panel).
- [x] `config/leitner.php` : `image_max_kb`, `defaults` (`box_count` 5, `min_box_one` 10, `presentation_order` mélangé, `retry_errors` vrai), `regularity` (`window_days` 7, `min_sessions` 3).
- [x] Tests (`tests/Feature/Leitner/PanelTest.php`) : `/leitner` répond 200 sans connexion ; le panel `qcm` reste le panel par défaut et `/` redirige toujours vers `/qcm` ; les liens croisés sont présents ; l'interface est en français.
- [x] `composer qa` vert ; commit.

### Étape 1 — Banques et questions des types du MVP (UC-01, UC-02)

**Base de données et modèles**

- [x] Migrations `create_leitner_banks_table`, `create_leitner_questions_table` (§3), `auditColumns()` ; index sur `(bank_id, type)`, `(bank_id, difficulty)`, `(bank_id, theme)`.
- [x] Énumération `App\Leitner\Questions\QuestionType` : `Qcm`, `VraiFaux`, `AutoEvaluation`, `ValeurNumerique` (libellés français, méthode `handler()`).
- [x] Énumération `Difficulty` (`Facile` = 1, `Moyen` = 2, `Difficile` = 3) avec `effective(?int)` qui ramène nul à `Moyen`.
- [x] Modèles `Bank`, `Question` (cast de `type`, `payload` en tableau, `difficulty`), trait `TracksAuthor` ; `Bank::themeNames()` pour l'autocomplétion ; suppression du fichier image au remplacement et à la suppression, y compris en cascade depuis une banque.
- [x] Interface `QuestionTypeHandler` et objet `Evaluation` (verdict `Verdict::Juste|Fausse`, détail affichable). Énumération `Verdict`.
- [x] Handlers du MVP, chacun avec `formSchema()`, `rules()`, `evaluate()`, `expectedAnswer()`, `answerView()` :
  - [x] `QcmHandler` : `payload` = bonne réponse, mauvaises réponses (au moins une, trois proposées) avec explication facultative, `shuffle` ; verdict automatique ; explication de la mauvaise réponse choisie dans le détail ;
  - [x] `VraiFauxHandler` : `payload` = valeur ;
  - [x] `AutoEvaluationHandler` : `payload` = réponse de référence ; verdict donné par l'étudiant (« Je savais » / « Je ne savais pas ») ;
  - [x] `ValeurNumeriqueHandler` : `payload` = valeur, tolérance (aucune, absolue, en %), unité facultative ; saisie avec virgule ou point ; message clair si la saisie n'est pas un nombre.
- [x] `AnswerNormalizer` (§3).
- [x] Fabriques `BankFactory`, `QuestionFactory` avec un état par type (`qcm()`, `vraiFaux()`, `autoEvaluation()`, `valeurNumerique()`) et `difficulty(int)`.

**Interface**

- [x] `BankResource` : liste (nom, description, nombre de questions), création, modification, suppression avec confirmation qui annonce le nombre de questions et de suites concernées.
- [x] `QuestionsRelationManager` : liste (énoncé tronqué, type, difficulté, thème, image), filtres par type, difficulté et thème ; création, modification, suppression avec confirmation qui annonce le nombre de suites où la question est en boîte.
- [x] Formulaire commun `QuestionFormSchema` : type (sélection réactive, non modifiable après création), énoncé, image et texte alternatif (obligatoire si une image est fournie), explication, difficulté, thème avec suggestions ; puis les champs du handler du type choisi.
- Reporté à l'étape 2, qui crée les progressions : modification mineure ou de fond d'une question déjà introduite (UC-02, 1a) ; nombre de suites concernées dans les confirmations de suppression d'une banque et d'une question.
- [x] Messages de succès et d'erreur explicites pour chaque action.

**Tests**

- [x] Modèles : relations, thème vide ramené à nul, `themeNames()`, cascade banque → questions → images.
- [x] Chaque handler : `payload` valide et invalide (message qui nomme le champ), verdicts juste et faux, cas limites (tolérance exacte en absolu et en %, tolérance nulle, virgule décimale, saisie non numérique, QCM sans mauvaise réponse refusé).
- [x] `AnswerNormalizer` : majuscules, accents, espaces.
- [x] Ressource et gestionnaire de questions avec Livewire : créer une question de chaque type, refus des formulaires incomplets, modifier, supprimer ; image stockée et effacée (`Storage::fake`), texte alternatif exigé.

**Documentation utilisateur**

- [x] `doc/users/leitner.md` : « Créer une banque », « Saisir une question » (un paragraphe par type), « Choisir la difficulté ».
- [x] `composer qa` vert ; vérification manuelle dans le navigateur ; commit.

### Étape 2 — Suites et progression (UC-05, UC-03)

**Base de données et modèles**

- [x] Migrations `leitner_suites`, `leitner_suite_sources`, `leitner_progressions`, `leitner_progress_items` (§3).
- [x] Énumération `PresentationOrder` (`Melange`, `BoiteUnDAbord`, `BoitesHautesDAbord`).
- [x] Modèles `Suite`, `SuiteSource`, `Progression`, `ProgressItem` ; une progression créée avec la suite (MVP : une seule, `user_id` nul).
- [x] Requêtes de la progression : `suiteQuestions()` (questions des sources ; une banque ne figure qu'une fois dans les sources d'une suite, contrainte d'unicité), `inBank()` (questions des sources sans ligne de progression), `inBox(k)`, `mastered()`, `suspended()`, compteurs groupés en une requête par catégorie.
- [x] Contrainte : `box` entre 1 et `box_count` quand la question n'est ni maîtrisée ni suspendue ; contrôlée dans le modèle (exception en français) et par les tests.

**Logique**

- [x] `SuiteSettingsUpdater` (UC-05, 3a) :
  - [x] réduire le nombre de boîtes : questions des boîtes supprimées → nouvelle dernière boîte ;
  - [x] augmenter : rien ne bouge ;
  - [x] ajouter une source : ses questions sont *en banque* (rien à écrire) ;
  - [x] retirer une source : lignes de progression de ses questions supprimées ; nombre annoncé dans la confirmation (`SettingsChange`, demandée seulement si la modification fait perdre ou déplacer des questions).
- [x] `PersonalQuestionCreator` (UC-03) : crée au besoin la banque personnelle de la suite (« <nom de la suite> — mes questions »), l'ajoute aux sources, enregistre la question et, si demandé, l'introduit en boîte 1.

**Interface**

- [x] `SuiteResource` : liste (nom, banques, répartition résumée ; la dernière séance s'ajoute à l'étape 4) ; formulaire (nom, description, sources, paramètres pré-remplis depuis `config/leitner.php`, nombre de boîtes de 3 à 10 avec la **durée estimée** affichée à côté, PRD §7.2) ; suppression avec confirmation.
- [x] Action « Nouvelle question » sur la suite (UC-03), avec le choix « en boîte 1 » ou « en banque ».
- [x] Modification d'une question déjà introduite dans une progression (UC-02, 1a, reporté de l'étape 1) : choix « mineure » (garde sa boîte) ou « de fond » (retour en boîte 1 dans toutes les progressions, échecs consécutifs remis à zéro), avec notification qui donne le nombre de progressions touchées.
- [x] Confirmations de suppression d'une banque et d'une question : nombre de suites concernées (reporté de l'étape 1).

**Tests**

- [x] Création d'une suite : toutes les questions *en banque*, aucune séance, valeurs par défaut.
- [x] Une même banque dans deux suites : progressions indépendantes, la banque n'est pas modifiée.
- [x] Paramètres : réduction et augmentation du nombre de boîtes, ajout et retrait de source, confirmation des seules modifications destructives.
- [x] Question personnelle : banque créée une seule fois, question en boîte 1 ou en banque.
- [x] Ajout d'une question dans une banque source : elle apparaît *en banque* sans autre action.
- [x] Modification mineure ou de fond d'une question introduite ; confirmations de suppression avec le nombre de suites.

**Documentation utilisateur**

- [x] `doc/users/leitner.md` : « Créer une suite », « Choisir le nombre de boîtes », « Ajouter mes propres questions ».
- [x] `composer qa` vert ; commit.

### Étape 3 — Moteur des séances (PRD §7)

**Logique pure (`App\Leitner\Engine\`)**

- [x] `BoxSchedule::boxesDue(int $sessionNumber, int $boxCount): list<int>` : la boîte 1 et une seule boîte haute, 2 + *v* où *v* est le nombre de fois que *n* est divisible par 2 (la boîte *k* revient une séance sur 2<sup>k−1</sup>) ; boîte 1 seule quand ce numéro dépasse le nombre de boîtes (PRD v0.9, §7.1).
- [x] `BoxMover::move(box, consecutiveFailures, verdict, boxCount): Move` : juste → boîte suivante, ou maîtrisée depuis la dernière boîte, échecs remis à zéro ; fausse → recul d'une boîte (boîte 1 reste en boîte 1), ou boîte 1 si l'échec précédent était déjà un échec ; échecs incrémentés.
- [x] `Introducer::pick(candidates, count, ?seed)` : difficulté effective croissante, hasard au sein d'un niveau (Q8, choix provisoire).
- [x] `SessionComposer::compose(progression, number, order, ?seed)` : questions de la boîte 1 et des boîtes dues, hors suspendues ; ordre de présentation (mélangé, boîte 1 d'abord, boîtes hautes d'abord, mélange à l'intérieur d'une boîte).
- [x] `Regularity::evaluate(sessionDates, firstSessionDate, now)` : nombre de séances sur 7 jours glissants ; avertissement si moins de 3 et première séance vieille d'au moins 7 jours.

**Services (persistance)**

- [x] Migrations `leitner_sessions`, `leitner_session_entries` ; modèles `Session` (énumération `SessionStatus`), `SessionEntry`.
- [x] `SessionStarter` : refuse si une séance est en cours (renvoie celle-ci) ; complète la boîte 1 jusqu'au minimum avec l'`Introducer` et annonce le nombre introduit ; signale « plus de questions à introduire » (2a) et « rien à poser » (3a) ; crée la séance et ses entrées avec `box_before`.
- [x] `AnswerRecorder` :
  - [x] enregistre la réponse, le verdict (handler), le détail et le temps mis à répondre (`duration_ms`, mesuré par la page, PRD §7.7) ;
  - [x] à la première réponse de la séance, attribue le numéro (`last_session_number + 1`) ;
  - [x] pour une entrée qui n'est pas une reprise : applique le `BoxMover` à la ligne de progression et écrit `box_after` ;
  - [x] si la réponse est fausse et que la reprise des erreurs est active : ajoute une entrée de reprise en fin de séance ; une reprise fausse en ajoute une autre ; une reprise juste n'en ajoute pas ;
  - [x] refus avec message : entrée déjà répondue, séance terminée, entrée d'une autre séance.
- [x] `AnswerUndoer` (4b) : annule la dernière réponse de la séance (restaure boîte et échecs consécutifs, retire la reprise ajoutée) ; une seule annulation, et seulement tant que la question suivante n'a pas reçu de réponse.
- [x] `EntrySkipper` (4a) : entrée marquée passée, question inchangée.
- [x] `SessionFinisher` : termine la séance quand toutes les entrées sont traitées, ou sur demande ; abandon d'une séance sans réponse → suppression ; produit le bilan (`SessionSummary` : posées, réussies, échouées, mouvements entre boîtes, introduites, maîtrisées, questions échouées, composition de la prochaine séance).
- [x] Question supprimée en cours de séance : l'entrée est sautée avec un message.
- [x] Réalisé en plus : `NextSession` (composition prévue de la prochaine séance, reprise par l'état de la suite à l'étape 4) ; `Shuffler` (mélange à graine facultative, `Collection::shuffle()` n'acceptant plus de graine) ; colonnes de séance nécessaires à l'annulation et au bilan (`undo_available`, `retry_of_id`, `failures_before`, `is_new`, `mastered`, `skipped_at`).

**Tests**

- [x] `BoxSchedule` : tableau du PRD §7.1 pour les séances 1 à 16, avec 5 et 7 boîtes.
- [x] `BoxMover` : toutes les combinaisons boîte / échecs / verdict, dernière boîte, boîte 1.
- [x] `Introducer` : ordre des difficultés, nul compté moyen, graine reproductible, moins de candidats que demandé.
- [x] `SessionComposer` : boîtes dues, suspendues exclues, trois ordres de présentation.
- [x] `Regularity` : fenêtre glissante, seuil exact, première semaine sans avertissement.
- [x] Scénario sur 8 séances (horloge et graine contrôlées) : répartition dans les boîtes conforme au calcul attendu, écrit à la main dans le test.
- [x] Reprise des erreurs : seule la première réponse change la boîte ; reprises successives ; reprise désactivée.
- [x] Numérotation : séance abandonnée sans réponse non comptée ; séance interrompue après une réponse comptée.
- [x] Annulation de la dernière réponse ; question passée ; refus des réponses invalides.
- [x] `composer qa` vert ; commit.

### Étape 4 — Écran de séance et état de la suite (UC-08, UC-11)

**Page de séance (`App\Leitner\Filament\Pages\Seance`)**

- [x] Ouverture depuis une suite : si une séance est en cours, proposition de la **reprendre** ou de l'**abandonner** (6a) ; sinon, composition annoncée (numéro, boîtes posées, nombre de questions, nouvelles questions) et choix de l'ordre de présentation pour cette séance.
- [x] Écran question : énoncé, image (zoomable, texte alternatif), boîte d'origine discrète, saisie par la vue du type ; position (7 / 23) et taux de réussite de la séance en permanence.
- [x] Après réponse : verdict, réponse attendue, explication, détail du handler ; mouvement de boîte (« boîte 3 → boîte 4 ») ; boutons « Question suivante », « Annuler ma réponse », « Passer » avant réponse.
- [x] Vues de saisie : QCM (propositions mélangées sauf `shuffle` faux), vrai / faux, auto-évaluation (« Voir la réponse » puis « Je savais » / « Je ne savais pas »), valeur numérique (champ et unité).
- [x] Raccourcis clavier : 1-9 pour les propositions, V / F, Espace pour « Voir la réponse », Entrée pour valider et passer à la suite ; aide affichée.
- [x] Bilan de fin de séance (`SessionSummary`) ; bouton « Terminer la séance » à tout moment.
- [ ] Mise en page utilisable à largeur téléphone (gros boutons, image adaptée) : à vérifier, la fenêtre du navigateur piloté n'a pas pu être réduite.

**État de la suite (UC-11)**

- [x] Page de la suite (`ViewSuite`, lecture via `SuiteState`) : barres des boîtes (nombre par boîte), questions en banque par difficulté, maîtrisées, suspendues, avec le total qui vérifie l'égalité du PRD ; prochaine séance (numéro, boîtes, nombre de questions, introductions prévues) ; date de la dernière séance ; régularité et avertissement ; bouton « Commencer la séance » / « Reprendre la séance ».
- [x] Liste des suites : même résumé en une ligne, avertissement de régularité visible.

**Tests**

- [x] Séance complète avec Livewire sur les quatre types : réponses justes et fausses, mouvements affichés, bilan juste.
- [x] Reprise d'une séance interrompue (nouvelle instance de la page) : elle repart à la première entrée sans réponse.
- [x] Une question ne peut pas être validée sans réponse ; saisie numérique invalide → message, pas de verdict.
- [x] État de la suite : compteurs et prochaine séance conformes au moteur après plusieurs séances ; avertissement affiché ou non selon les dates (horloge simulée).

**Documentation utilisateur**

- [x] `doc/users/leitner.md` : « Faire une séance », « Comprendre les boîtes et le rythme », « Lire l'état d'une suite », « Reprendre une séance interrompue », raccourcis clavier.
- [x] `composer qa` vert ; vérification manuelle dans le navigateur sur ordinateur (séance complète des quatre types, raccourcis clavier, annulation, reprise d'une erreur, bilan) ; commit.
- [ ] Vérification manuelle à largeur téléphone.

### Étape 5 — Clôture du MVP

- [ ] Dérouler le critère de sortie du §1 dans le navigateur (8 séances, en avançant l'horloge par `php artisan tinker` ou un jeu de données de démonstration `LeitnerDemoSeeder`), sur ordinateur et à largeur téléphone.
- [ ] README : accès au module Leitner.
- [ ] Mettre à jour le statut de ce plan et du PRD Leitner (§9.2).
- [ ] `composer qa` vert ; `php artisan laraskel:outillage --check` conforme ; PR.

---

## 5. Lot 2 — Séances enrichies et suivi

**Prérequis** : lot B1 du [plan Banques](banques_plan.md) (banques communes, import et export) ; lot B2 pour les nouveaux types de questions.

### 5.1 Objectif et périmètre

Toujours sans connexion, l'utilisateur fait des séances avec les types du lot B2 du module Banques
(énumération, correspondance, réponse courte), avec un temps de réponse limité s'il le souhaite et
des sources restreintes jusqu'à un niveau de difficulté ; il agit sur les questions d'une suite,
conteste un verdict, consulte son historique, voit ses suites acquises et les vérifie par des
contrôles d'entretien, et passe un test de placement. La gestion, l'import et l'export des banques
relèvent du module Banques.

Critère de sortie (PRD §9.2) : l'utilisateur s'entraîne sur une banque de capitales importée, en
énumération et en correspondance, conteste un verdict, et retrouve ses séances dans l'historique.

### 5.2 Décisions techniques

| Sujet | Décision | Alternatives écartées |
|---|---|---|
| Contrôle d'entretien et test de placement | Ce sont des séances de `kind` `controle` et `placement` : mêmes tables et même page, sans numéro de séance ; le `SessionStarter` et l'`AnswerRecorder` appliquent des règles propres au type de séance. | Tables séparées : dupliquent la saisie, la reprise et l'historique. |
| Suite acquise | Colonne `acquired_at` sur la progression, posée ou effacée par un service `AcquisitionTracker` appelé après chaque réponse et chaque changement de sources ; colonnes `last_check_at`, `last_check_passed`, `next_check_at`. | Calcul à la volée : l'historique de l'acquisition (date) serait perdu. |


### 5.3 Modèle de données (ajouts)

```
leitner_suite_sources    + themes (json)?, max_difficulty?
leitner_suites           + placement_box (3), response_time_seconds?
leitner_progressions     + acquired_at?, last_check_at?, last_check_passed?, next_check_at?
leitner_sessions         kind : + controle, placement ; + check_rate?, check_passed?
leitner_session_entries  + contested_at?, contest_comment?, verdict_overridden (bool), timed_out (bool)
```

### Étape 6 — Nouveaux types en séance, sources par niveau, temps de réponse

- [ ] Vues de saisie des types du lot B2 : liste de champs pour l'énumération (ajout de ligne au clavier), sélection par élément pour la correspondance (utilisable au clavier et sur téléphone), champ texte pour la réponse courte ; tirage des paires présentées enregistré dans l'entrée pour la reprise ; détail élément par élément ou paire par paire dans la correction.
- [ ] QCM de plus de 6 propositions ([PRD Banques](../prds/banques_prd.md) §6) : vue de saisie en liste déroulante avec recherche (seuil dans `config/banques.php`, partagé avec le module QCM) ; raccourcis numériques réservés aux QCM de 6 propositions au plus ; Entrée valide le choix ; tests de la saisie et de la correction (réponse choisie et bonne réponse seulement).
- [ ] Restriction d'une source par thèmes et jusqu'à un niveau de difficulté maximal (UC-05, 2) ; une question sans niveau compte comme moyenne pour cette restriction.
- [ ] Temps de réponse (UC-08 4e, PRD §7.7) : paramètre `response_time_seconds` de la suite (nul = désactivé, 10 proposé) ; compte à rebours sur la page de séance (Alpine.js) ; à zéro, l'`AnswerRecorder` enregistre une réponse fausse `timed_out` sans réponse saisie ; durée réglable ou chronomètre désactivable en cours de séance, enregistré sur la suite, effectif à la question suivante ; question en cours remise à temps complet à la reprise ; le serveur refuse une réponse arrivée nettement après le délai (marge de 2 s pour la latence) et la compte comme temps écoulé.
- [ ] Tests du temps de réponse : expiration → fausse et reprise en fin de séance ; changement de durée en cours de séance ; désactivation ; réponse tardive refusée par le serveur (horloge simulée) ; auto-évaluation chronométrée jusqu'à « Voir la réponse » ; restriction par niveau maximal.
- [ ] Tests : séance avec Livewire sur chaque nouveau type ; reprise d'une séance avec une correspondance tirée.
- [ ] Documentation utilisateur : répondre aux nouveaux types en séance ; temps de réponse ; sources limitées par niveau.
- [ ] `composer qa` vert ; commit.

### Étape 7 — Actions sur les questions d'une suite, réinitialisation (UC-06, UC-07, UC-03)

- [ ] Liste des questions d'une suite, filtrable par état (en banque, boîte *k*, maîtrisée, suspendue), thème et difficulté.
- [ ] Actions individuelles et groupées : introduire, suspendre, réactiver, déclarer connue (boîte choisie ou maîtrisée), remettre en boîte 1, réintroduire une question maîtrisée ; notification avec le nombre de questions touchées.
- [ ] Réinitialisation (UC-07) : confirmation qui annonce ce qui sera perdu ; lignes de progression supprimées, numéro de séance remis à 0 ; séances passées conservées et marquées antérieures (`reset_at` sur la progression, séances antérieures filtrées dans les compteurs).
- [ ] Création d'une question personnelle pendant une séance (UC-03, variante), sans quitter la séance.
- [ ] Tests de chaque action, de la réinitialisation (historique conservé, compteurs remis à zéro) et de la création en séance.
- [ ] Documentation utilisateur : « Suspendre ou déclarer connue une question », « Recommencer une suite ».
- [ ] `composer qa` vert ; commit.

### Étape 8 — Contestation, historique et statistiques (UC-09, UC-12)

- [ ] Contestation (UC-09) après un verdict automatique d'une réponse saisie (réponse courte, énumération, valeur numérique, correspondance) : commentaire facultatif ; verdict retourné (Q12, choix provisoire : dernier mot à l'étudiant) ; mouvement de boîte recalculé (même mécanisme que l'annulation) ; contestation journalisée. QCM et vrai / faux : pas de contestation (renvoi au signalement en v1).
- [ ] Liste des contestations dans la banque, par question, pour améliorer la réponse attendue.
- [ ] État par banque (UC-11) : pour une suite à plusieurs sources, répartition boîtes / en banque / maîtrisées par banque.
- [ ] Historique (UC-12) : temps moyen de réponse par thème ; séances (date, durée, type, posées, taux, mouvements), évolution de la répartition dans les boîtes (graphique Filament), taux par thème et par type, questions difficiles (les plus souvent échouées) ; depuis celles-ci, remise en boîte 1 ou entraînement libre sans effet sur les boîtes.
- [ ] Tests : contestation et mouvement de boîte ; journal ; historique et taux calculés sur un jeu de séances connu ; entraînement libre sans effet sur la progression.
- [ ] Documentation utilisateur : « Contester un verdict », « Suivre sa progression ».
- [ ] `composer qa` vert ; commit.

### Étape 9 — Suite acquise, contrôle d'entretien, test de placement (UC-14, UC-15, §7.6)

- [ ] `AcquisitionTracker` : suite acquise quand toutes les questions sont maîtrisées ou suspendues et qu'aucune ne reste en banque ; acquisition perdue à l'ajout de questions ; date conservée.
- [ ] Contrôle d'entretien (UC-14) : séance `controle` sur un échantillon aléatoire de questions maîtrisées (20 par défaut, toutes s'il y en a moins), restriction facultative par thème ; seuil 90 % ; question échouée → dernière boîte, échec consécutif compté (boîte 1 au prochain échec) ; conclusion « acquis confirmé » ou « à consolider » ; contrôle interrompu sans conclusion ; planification : 1 mois après l'acquisition, 3 mois après un contrôle réussi, puis 6 mois (Q22, choix provisoires dans `config/leitner.php`).
- [ ] Test de placement (UC-15) : séance `placement` sur les questions en banque (toutes, par thème ou nombre maximal) ; sans reprise des erreurs ni numéro ; réussie → boîte d'arrivée de la suite (3), une boîte plus bas pour QCM et vrai / faux (Q23, choix provisoire) ; échouée ou passée → reste en banque ; interruption et reprise ; proposé à la création de la suite.
- [ ] État de la suite (UC-11) : état acquise et date, dernier contrôle et résultat, prochain contrôle proposé ; avertissement de régularité masqué pour une suite acquise.
- [ ] Tests : acquisition et perte d'acquisition ; échantillon (taille, absence de doublon, restriction) ; seuil exact ; planification des contrôles (horloge simulée) ; placement (boîtes d'arrivée par type, échecs en banque, reprise).
- [ ] Documentation utilisateur : « Savoir qu'une suite est acquise », « Faire un contrôle d'entretien », « Passer un test de placement ».
- [ ] `composer qa` vert ; commit.

### Étape 10 — Clôture du lot 2

- [ ] Dérouler le critère de sortie du §5.1 dans le navigateur, sur ordinateur et à largeur téléphone.
- [ ] Mettre à jour le statut de ce plan et du PRD Leitner (§9.2).
- [ ] `composer qa` vert ; PR.

---

## 6. Lot 3 — Questions ouvertes corrigées par l'IA

### 6.1 Objectif et périmètre

Une suite contient des questions ouvertes corrigées par l'IA (type du lot B3 du module Banques),
avec bascule en auto-évaluation quand l'IA n'est pas disponible, et contestation du verdict.

Critère de sortie (PRD §9.2) : une suite mêle des questions QCM et des questions ouvertes ; l'IA
rend verdict et justification ; IA coupée, la séance continue en auto-évaluation.

**Prérequis** : lot B3 du [plan Banques](banques_plan.md), qui dépend lui-même de l'interface LLM
du socle ([PRD Laraskel](../../../laraskel/doc/prds/interface-llm.md)), pas encore livrée.

### 6.2 Décisions techniques

| Sujet | Décision | Alternatives écartées |
|---|---|---|
| Correction par l'IA | Port `AnswerJudge` dans Leitner (`judge(question, référence, critères, réponse): Evaluation` avec justification) ; implémentation `LlmAnswerJudge` sur l'interface LLM du socle ; implémentation `UnavailableJudge` quand aucune interface n'est configurée. Appel synchrone avec délai maximal de 10 s et indicateur d'attente ; au-delà, ou en cas d'erreur, bascule en auto-évaluation signalée (UC-08, 4d). Seuls la question, la référence, les critères et la réponse sont envoyés, sans donnée d'identité. | Appel par file d'attente : l'étudiant attend de toute façon le verdict pour continuer ; complexité sans gain. |

### Étape 11 — Questions ouvertes en séance

- [ ] Page de séance : saisie en texte libre, indicateur d'attente, justification affichée, bascule en auto-évaluation signalée.
- [ ] Contestation (UC-09) étendue aux verdicts IA.
- [ ] Tests avec un juge factice : verdicts juste et faux, justification affichée, délai dépassé et erreur → auto-évaluation, contestation ; contenu envoyé au juge sans donnée d'identité.
- [ ] Documentation utilisateur : « Répondre à une question ouverte », « Que faire si l'IA se trompe ».
- [ ] `composer qa` vert ; commit.

### Étape 12 — Clôture du lot 3

- [ ] Dérouler le critère de sortie du §6.1, avec l'IA active puis coupée.
- [ ] Mettre à jour le statut de ce plan et du PRD Leitner (§9.2).
- [ ] `composer qa` vert ; PR.

---

## 7. Lots suivants

v1 (comptes et rôles du socle, banques prédéfinies et personnelles, suites proposées et
adoptées, signalements, vue formateur, accès) et v2 (taille d'étude, autres types, questions
inversées, génération par l'IA, import d'autres formats, hors ligne, rappels) seront détaillés ici
avant leur démarrage ; leur contenu est décrit au PRD §9.2. Le modèle de données les prépare déjà :
`user_id` sur la progression, une progression par étudiant et par suite.
