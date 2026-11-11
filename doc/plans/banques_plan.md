# Plan d'implémentation — Module Banques de questions

- **Statut** : lot B1 en cours — étapes B1 à B3 livrées
- **Date** : 2026-11-11
- **Auteur** : Frédéric Peignot (assisté d'un agent IA)
- **PRD associé** : [banques_prd.md](../prds/banques_prd.md), §9.2 « Découpage en lots »
- **Documents liés** : [plan QCM](qcm_plan.md), [plan Leitner](leitner_plan.md), [PRD Mentor](../prds/mentor_prd.md) §1.4

## Vue d'ensemble des lots

| Lot | Contenu (PRD §9.2) | Étapes | Statut |
|---|---|---|---|
| B1 — Banques communes | Module Banques, banque par défaut, reprise des banques QCM et Leitner, import / export, modules QCM et Leitner branchés dessus, déplacement | B1 à B8 (§4) | En cours |
| B2 — Types complémentaires | Réponse courte, énumération, correspondance ; compléments d'édition | À détailler (§5) | — |
| B3 — Questions ouvertes | Question ouverte corrigée par l'IA | À détailler (§5) | — |
| v1, v2 | Banques prédéfinies et personnelles, signalements, accès ; autres types | À détailler | — |

**Place dans le calendrier** : le lot B1 se fait **après la clôture du MVP Leitner (étape 5) et du lot 3 QCM**, et **avant le lot 2 Leitner et le lot 4 QCM**. Plus il est fait tard, plus il y a de code à reprendre dans les deux modules.

---

## 1. Objectif du lot B1

Une seule gestion des banques pour toute l'application, sans connexion :

1. toute question vit dans une banque ; une banque par défaut recueille celles créées sans banque choisie ;
2. une question se déplace ou se copie d'une banque à l'autre ;
3. les modules QCM et Leitner utilisent les mêmes banques ; le module QCM pose les QCM et les vrai / faux (comme QCM à deux réponses) et ignore les autres types avec un avertissement ;
4. les banques s'importent et s'exportent dans le format du PRD Banques §8 ;
5. les données existantes des deux modules sont conservées.

Critère de sortie (PRD §9.2) : une banque créée une seule fois sert à une étude QCM, à un questionnaire et à une suite Leitner ; les fichiers d'export QCM existants se réimportent ; les données existantes des deux modules sont conservées.

## 2. Périmètre

### Inclus

| PRD Banques | Contenu retenu pour B1 |
|---|---|
| UC-01 | Créer, renommer, supprimer (confirmation avec questions, questionnaires et suites concernés) ; banque par défaut non supprimable ; liste avec le nombre de questions et les usages. |
| UC-02 | Formulaire par type (code du MVP Leitner) ; modification mineure ou de fond ; suppression avec usages ; filtres ; création depuis un module dans la banque choisie ou la banque par défaut. |
| UC-03 | Déplacer, copier une sélection vers une banque ; effet sur les suites annoncé. |
| UC-04, UC-05 | Export et import CSV / ZIP des types QCM, vrai / faux, auto-évaluation, valeur numérique ; anciens fichiers QCM importables. |
| §7 | QCM : vrai / faux comme QCM à deux réponses ; autres types ignorés avec avertissement. |

### Reporté

| Élément | Lot |
|---|---|
| Duplication d'une banque, décomptes par type, difficulté et thème, prévisualisation, recherche plein texte, actions groupées autres que déplacer / copier, doublons à la saisie | B2 |
| Types réponse courte, énumération, correspondance ; images dans les propositions | B2 |
| Question ouverte corrigée par l'IA | B3 |
| Banques prédéfinies et personnelles, accès, signalements | v1 |

## 3. Décisions techniques

| Sujet | Décision | Alternatives écartées |
|---|---|---|
| Point de départ | Le modèle du MVP Leitner (`type` + `payload` JSON, un `QuestionTypeHandler` par type) devient celui du module Banques : il couvre déjà tous les types. Le code `App\Leitner\Questions`, les modèles `Bank` et `Question`, le formulaire et les fabriques passent dans `App\Banques\` ; les tables `leitner_banks` et `leitner_questions` sont renommées `banques_banks` et `banques_questions`. | Partir du modèle QCM (table de réponses) : ne convient pas aux autres types. Nouveau modèle de zéro : refait ce que le MVP Leitner a déjà testé. |
| Interface publique | Les modèles `Bank` et `Question` (lecture), les handlers de types (correction) et les services d'écriture du module (`QuestionWriter`, `QuestionMover`, `BankExchange`) forment l'interface publique. Les autres modules peuvent référencer une question par clé étrangère vers `banques_questions` et lire par les modèles, mais n'écrivent jamais dans les tables du module. | Objets de transfert pour toute lecture : alourdit les relations Eloquent et les tableaux Filament sans protéger davantage. Accès libre aux tables : contraire au PRD Mentor §1.4. |
| Interface utilisateur | Un **plugin Filament** `BanquesPlugin` qui enregistre les ressources des banques dans les panels `qcm` et `leitner` : l'utilisateur gère les banques depuis le module où il se trouve. Dans le panel `qcm`, il remplace l'ancienne ressource des banques du module QCM à l'étape B4 (même nom de route). | Panel `banques` séparé : un troisième espace où naviguer. Ressource dans un seul panel : l'autre module devrait y renvoyer. |
| Banque par défaut | Colonne `is_default` (une seule à vrai, contrôlée dans le modèle) ; banque « Banque par défaut » créée par la migration si aucune n'existe ; renommable, non supprimable (exception en français). | Banque par défaut par module : contraire au partage des banques. |
| Question QCM | `payload` du type QCM : `correct`, `wrong[]` (texte, explication), `shuffle`. Les propositions sont identifiées par des **clés stables** (`bonne`, `fausse-0`…), comme dans Leitner. | Garder `qcm_answers` : deux représentations d'une même question. |
| Vrai / faux dans le QCM | Le module QCM présente une question vrai / faux avec les propositions « Vrai » et « Faux » (clés `vrai`, `faux`), sans mélange ; l'explication de la question tient lieu d'explication de la mauvaise réponse. | Convertir les vrai / faux en QCM à l'import : perd le type pour Leitner. |
| Reprise des données QCM | Service `App\Qcm\Migration\QcmToBanquesTransfer`, appelé par la **migration de bascule** du module QCM (étape B4), dans une transaction : `qcm_banks` → banques communes (nom suffixé « (QCM) » en cas de conflit) ; `qcm_questions` + `qcm_answers` → questions de type QCM (ordre des mauvaises réponses, explications, dates et auteurs conservés, `module` → `theme`, description d'image « Illustration : … ») ; questions propres → banque par défaut ; renvoie la correspondance des identifiants, qui sert à réécrire `qcm_questionnaire_questions`. **Allègement** (données de développement seulement : 37 questions, aucun passage en cours) : les passages en cours ne sont pas convertis, la reprise est refusée tant qu'il en existe ; le détail des réponses des passages terminés (`qcm_attempt_items`) est supprimé, il ne sert qu'à la correction de fin de passage, qui n'est pas conservée ; les résultats ne référencent aucune question et ne changent pas. Images : chemins conservés. | Reprise complète des passages en cours : coût élevé pour aucune donnée réelle. Régénérer les questionnaires : obligerait à ressaisir les 36 questions propres. Migration de données livrée seule avant la bascule : le code QCM lirait des liens réécrits vers des tables qu'il ne connaît pas encore. |
| Utilisations par les modules | Contrat `App\Banques\Usage\QuestionUsage` que chaque module implémente et enregistre avec le tag `UsageRegistry::TAG` : phrases des confirmations de suppression, effet d'une modification de fond. Le module Banques réunit ces réponses par `UsageRegistry` sans connaître les modules ; un test d'architecture vérifie qu'il n'importe aucune classe de `App\Leitner` ni de `App\Qcm`. | Appels directs aux modules : dépendance de Banques vers QCM et Leitner, donc cycle. Événements Laravel : ne renvoient pas les phrases à afficher dans les confirmations. |
| Avertissement des types | Service QCM `UsableQuestions` : filtre une requête de questions sur les types QCM et vrai / faux et renvoie le nombre de questions écartées ; chaque point d'entrée (étude, tirage, ajout, import de questionnaire) affiche l'avertissement du PRD Banques §7. | Filtrer en silence : contraire à la règle « jamais d'échec silencieux ». |
| Échanges | Le code d'échange du module QCM (`App\Qcm\Exchange`) est repris et généralisé dans `App\Banques\Exchange` pour les banques ; la traduction entre colonnes et réponse attendue de chaque type est réunie dans `QuestionColumns` (lecture avec messages par colonne, écriture), la cohérence finale restant vérifiée par le type ; le module QCM garde l'export et l'import des questionnaires et des examens, en appelant l'interface du module Banques pour les questions (étape B4) ; son ancien code d'échange des banques est supprimé à l'étape B5. | Deux lecteurs CSV : formats qui divergent. Méthodes `toCsv()` / `fromCsv()` dans chaque type : mêle le format de fichier et la correction des réponses dans la même classe. |
| Sauvegarde | La reprise des données modifie la base en profondeur : sauvegarde de la base avant `php artisan migrate`, documentée dans le README. | — |

### Modèle de données (après B1)

```
banques_banks       id, name (unique), description?, is_default (bool), auditColumns
banques_questions   id, bank_id → banques_banks (cascade), type, statement, payload (json), explanation?,
                    difficulty?, theme?, image_path?, image_alt?, auditColumns
qcm_questionnaire_questions   question_id → banques_questions (cascade)
qcm_attempt_items             question_id? → banques_questions (nullOnDelete), answer_order (json : clés),
                              chosen_key?   (remplace chosen_answer_id)
leitner_suite_sources         bank_id → banques_banks ; leitner_progress_items, leitner_session_entries : question_id → banques_questions
supprimées : qcm_banks, qcm_questions, qcm_answers ; colonne qcm_questions.questionnaire_id disparue avec sa table
```

---

## 4. Étapes du lot B1

### Étape B1 — Module Banques à partir du code Leitner

- [x] Créer `App\Banques\` et y déplacer `App\Leitner\Questions\*`, `App\Leitner\Models\Bank`, `Question`, `App\Leitner\Filament\Schemas\QuestionFormSchema`, la ressource des banques et son gestionnaire de questions, les fabriques ; `config/banques.php` (taille d'image) ; mises à jour des imports dans Leitner.
- [x] Migration : renommer `leitner_banks` → `banques_banks`, `leitner_questions` → `banques_questions` ; ajouter `is_default` ; créer la banque par défaut ; clés étrangères Leitner recréées vers les nouvelles tables.
- [x] `BanquesPlugin` enregistré dans le panel `leitner` (dans le panel `qcm` à l'étape B4).
- [x] Contrat `QuestionUsage` et `UsageRegistry` ; implémentation Leitner (`LeitnerQuestionUsage`, la classe statique Leitner devenant `SuiteUsage`) enregistrée dans `AppServiceProvider` ; confirmations et modification de fond des banques branchées sur le registre.
- [x] Banque par défaut : non supprimable (action masquée et exception), renommable.
- [x] Tests existants des banques et questions Leitner déplacés dans `tests/Feature/Banques/` et passants ; tests de la banque par défaut (création, recréation, suppression refusée, unicité, renommage) ; ressource dans le panel `leitner` ; registre des utilisations ; test d'architecture (aucune dépendance vers QCM ou Leitner).
- [x] `composer qa` vert ; commit.

### Étape B2 — Service de reprise des données QCM

- [x] Service `QcmToBanquesTransfer` (§3, « Reprise des données QCM ») : lit les tables `qcm_*` sans les modifier, refuse la reprise s'il existe un passage en cours, arrête tout avec un message qui nomme la question en cas de question incomplète.
- [x] Tests sur un jeu de données QCM construit avec les fabriques du module QCM : réponses, explications, ordre, thème, image et sa description, dates ; questions propres dans la banque par défaut ; toutes les questions reprises ; conflit de nom de banque ; refus en présence d'un passage en cours ; question incomplète.
- [x] Essai à blanc sur la base de développement, dans une transaction annulée : 37 questions reprises sans erreur.
- [x] `composer qa` vert ; commit.

### Étape B3 — Import et export des banques (UC-04, UC-05)

Avant la bascule du module QCM, pour que l'import et l'export des banques ne disparaissent jamais.

- [x] `App\Banques\Exchange` à partir de `App\Qcm\Exchange` : format du PRD Banques §8 pour les types du lot B1 ; colonnes communes, colonnes par type via `QuestionColumns`, listes et caractères réservés, valeurs, rapport avant import, doublons, images, transaction.
- [x] Actions d'import et d'export dans la ressource des banques ; fichiers exemples `public/exemples/banques/` (un par type) ; les exemples historiques du module QCM s'importent.
- [x] Tests : aller-retour de chaque type ; import des fichiers exemples QCM historiques ; erreurs par type (message avec ligne, colonne et valeurs admises) ; aucune écriture à l'analyse ; échec en cours d'import sans trace.
- [x] Les messages d'erreur renvoient au guide d'utilisation des banques, écrit à l'étape B7 (le format est d'ici là décrit au PRD Banques §8).
- [x] `composer qa` vert ; commit.

### Étape B4 — Bascule du module QCM sur les banques communes

- [ ] Approche retenue pour limiter la réécriture : `App\Qcm\Models\Question` et `Bank` deviennent des sous-classes des modèles du module Banques (mêmes tables, portée limitée aux types QCM et vrai / faux, accesseurs `module` → `theme`, `answers`, `correctAnswer`, `wrongAnswers` calculés depuis la réponse attendue) ; `Answer` devient un objet sans table, identifié par une clé stable (`bonne`, `fausse-0`, `vrai`, `faux`) ; seuls les usages d'identifiants de réponse (étude, passages, correction), la création des questions de questionnaire et les échanges de questionnaires sont réécrits.
- [ ] **Sauvegarde** de `database/database.sqlite` avant de migrer ; procédure notée dans le README.
- [ ] Migration de bascule : clés étrangères de `qcm_questionnaire_questions.question_id` et `qcm_attempt_items.question_id` vers `banques_questions` ; reprise par `QcmToBanquesTransfer` ; liens des questionnaires réécrits avec la correspondance ; détail des passages terminés supprimé ; `qcm_attempt_items.chosen_key` ajouté (remplace `chosen_answer_id`, supprimé à l'étape B5).
- [ ] Étude libre (`ModuleCatalog`, `StudyDraw`, page d'étude) : questions des banques communes, thème comme module, filtre `UsableQuestions` et avertissement.
- [ ] Questionnaires : plus de questions propres ; « Nouvelle question » crée dans la banque choisie (par défaut la banque par défaut) et le message le dit ; ajout et tirage avec avertissement ; duplication et suppression sans questions propres.
- [ ] Passages et correction (`AttemptStarter`, `AttemptRecorder`, `AttemptFinisher`, `TakeExam`) : propositions par clés, vrai / faux comme QCM à deux réponses non mélangé.
- [ ] Export et import des questionnaires et des examens sur l'interface d'échange du module Banques ; l'import crée ses questions dans la banque choisie.
- [ ] Implémentation QCM de `QuestionUsage` (questionnaires qui utilisent une question ou une banque) ; `BanquesPlugin` enregistré dans le panel `qcm` ; ressource des banques et gestionnaire de questions du module QCM retirés.
- [ ] Tests QCM existants adaptés ; nouveaux tests : migration de bascule sur un jeu de données (liens des questionnaires conservés) ; vrai / faux en étude, en questionnaire et en passage ; avertissement avec le nombre de questions écartées ; banque sans question utilisable grisée.
- [ ] `composer qa` vert ; commit.

### Étape B5 — Suppression des anciennes tables QCM

- [ ] Migration qui supprime `qcm_answers`, `qcm_questions`, `qcm_banks` et `qcm_attempt_items.chosen_answer_id`, après vérification que plus aucun code ne les lit (recherche dans `app/` et `tests/`) ; suppression de `App\Qcm\Exchange` et des modèles QCM devenus inutiles.
- [ ] `composer qa` vert ; commit.

### Étape B6 — Déplacer et copier des questions (UC-03)

- [ ] Service `QuestionMover` : déplacement (la question garde ses utilisations ; retrait des progressions des suites dont la banque cible n'est pas source, annoncé) et copie (nouvelle question, image dupliquée).
- [ ] Actions groupées « Déplacer vers » et « Copier vers » dans la liste des questions ; confirmation qui annonce les suites touchées ; notification avec le nombre de questions.
- [ ] Tests : déplacement depuis la banque par défaut ; questionnaire QCM inchangé après déplacement ; suite Leitner qui perd la question ; copie indépendante ; thème conservé.
- [ ] `composer qa` vert ; commit.

### Étape B7 — Documentation utilisateur

- [ ] `doc/users/banques.md` : « Créer une banque », « Saisir une question » (par type), « Ranger les questions de la banque par défaut », « Déplacer ou copier des questions », « Exporter une banque », « Importer des questions », « Préparer un fichier dans un tableur », référence du format.
- [ ] `doc/users/qcm.md` et `doc/users/leitner.md` : sections sur les banques remplacées par des renvois ; vrai / faux dans le QCM ; avertissement des types.
- [ ] README : sauvegarde de la base avant la migration du lot B1.

### Étape B8 — Clôture du lot B1

- [ ] Sauvegarde de la base de développement, migration, puis critère de sortie du §1 déroulé dans le navigateur (QCM et Leitner).
- [ ] Mettre à jour le statut de ce plan, des plans QCM et Leitner et des PRD concernés.
- [ ] `composer qa` vert ; `php artisan laraskel:outillage --check` conforme ; PR.

---

## 5. Lots suivants

- **B2** : reprend les éléments « types et compléments d'édition » jusqu'ici prévus à l'étape 6 du [plan Leitner](leitner_plan.md) et au lot 5 du [plan QCM](qcm_plan.md) (types réponse courte, énumération, correspondance ; images dans les propositions ; duplication, décomptes, prévisualisation, recherche, doublons à la saisie).
- **B3** : type question ouverte et correction par l'IA, après la livraison de l'interface LLM du socle Laraskel.

Ces lots seront détaillés ici avant leur démarrage.
