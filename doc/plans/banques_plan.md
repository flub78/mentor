# Plan d'implémentation — Module Banques de questions

- **Statut** : lots B1 et B1.1 livrés
- **Date** : 2026-11-11
- **Auteur** : Frédéric Peignot (assisté d'un agent IA)
- **PRD associé** : [banques_prd.md](../prds/banques_prd.md), §9.2 « Découpage en lots »
- **Documents liés** : [plan QCM](qcm_plan.md), [plan Leitner](leitner_plan.md), [PRD Mentor](../prds/mentor_prd.md) §1.4

## Vue d'ensemble des lots

| Lot | Contenu (PRD §9.2) | Étapes | Statut |
|---|---|---|---|
| B1 — Banques communes | Module Banques, banque par défaut, reprise des banques QCM et Leitner, import / export, modules QCM et Leitner branchés dessus, déplacement | B1 à B8 (§4) | Livré |
| B1.1 — Référence et ordre | Référence stable des questions, import qui met à jour les questions déjà présentes ; ordre fixe des propositions d'un QCM | R1 à R4 (§5) | Livré |
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

Approche réalisée : `App\Qcm\Models\Question` et `Bank` sont des sous-classes des modèles du module Banques (portée QCM et vrai / faux, accesseurs `module`, `answers`, `correctAnswer`, `wrongAnswers`) ; `Answer` est un objet sans table, identifié par une clé ; les passages et l'étude enregistrent des clés de réponse.


- [x] Approche retenue pour limiter la réécriture : `App\Qcm\Models\Question` et `Bank` deviennent des sous-classes des modèles du module Banques (mêmes tables, portée limitée aux types QCM et vrai / faux, accesseurs `module` → `theme`, `answers`, `correctAnswer`, `wrongAnswers` calculés depuis la réponse attendue) ; `Answer` devient un objet sans table, identifié par une clé stable (`bonne`, `fausse-0`, `vrai`, `faux`) ; seuls les usages d'identifiants de réponse (étude, passages, correction), la création des questions de questionnaire et les échanges de questionnaires sont réécrits.
- [x] **Sauvegarde** de `database/database.sqlite` avant de migrer ; procédure notée dans le README.
- [x] Migration de bascule : clés étrangères de `qcm_questionnaire_questions.question_id` et `qcm_attempt_items.question_id` vers `banques_questions` ; reprise par `QcmToBanquesTransfer` ; liens des questionnaires réécrits avec la correspondance ; détail des passages terminés supprimé ; `qcm_attempt_items.chosen_key` ajouté (remplace `chosen_answer_id`, supprimé à l'étape B5).
- [x] Étude libre (`ModuleCatalog`, `StudyDraw`, page d'étude) : questions des banques communes, thème comme module, filtre `UsableQuestions` et avertissement.
- [x] Questionnaires : plus de questions propres ; « Nouvelle question » crée dans la banque choisie (par défaut la banque par défaut) et le message le dit ; ajout et tirage avec avertissement ; duplication et suppression sans questions propres.
- [x] Passages et correction (`AttemptStarter`, `AttemptRecorder`, `AttemptFinisher`, `TakeExam`) : propositions par clés, vrai / faux comme QCM à deux réponses non mélangé.
- [x] Export et import des questionnaires et des examens sur l'interface d'échange du module Banques ; l'import crée ses questions dans la banque choisie et écarte les autres types avec l'avertissement. Le code d'échange des banques du module QCM (`QuestionCsv`, `ExchangeFile`, `ExchangeWriter`, `ImportReport`, `QuestionPersister`, import et export des banques) est supprimé dès cette étape, ses tests de format déplacés dans `tests/Feature/Banques/ArchiveFormatTest.php`.
- [x] Implémentation QCM de `QuestionUsage` (`QcmQuestionUsage` : questionnaires qui utilisent une question ou une banque) ; `BanquesPlugin` enregistré dans le panel `qcm` ; ressource des banques et gestionnaire de questions du module QCM retirés.
- [x] Tests QCM existants adaptés ; nouveaux tests : migration de bascule sur un jeu de données (liens des questionnaires conservés) ; vrai / faux en étude, en questionnaire et en passage ; avertissement avec le nombre de questions écartées ; banque sans question utilisable grisée.
- [x] Migration appliquée à la base de développement après sauvegarde (`database/database.sqlite.avant-b4`) : 37 questions reprises, les 36 liens des 3 questionnaires conservés dans le même ordre, examens et résultat intacts ; pages principales des deux panels vérifiées.
- [x] `composer qa` vert ; commit.

### Étape B5 — Suppression des anciennes tables QCM

- [x] Migration qui supprime `qcm_answers`, `qcm_questions` et `qcm_banks` (`qcm_attempt_items.chosen_answer_id` l'est déjà à l'étape B4), après vérification que plus aucun code ne les lit (recherche dans `app/` et `tests/`) (le code d'échange QCM des banques est déjà supprimé à l'étape B4) ; suppression du test `QcmToBanquesTransferTest`, qui écrit dans ces tables ; le service `QcmToBanquesTransfer` reste, appelé par la migration de bascule sur une base neuve.
- [x] Migration appliquée à la base de développement : il ne reste que les tables `banques_*` et les tables QCM des questionnaires, examens, passages et résultats ; 36 liens de questionnaires et 37 questions conservés.
- [x] `composer qa` vert ; commit.

### Étape B6 — Déplacer et copier des questions (UC-03)

- [x] Service `QuestionMover` : déplacement (la question garde ses utilisations ; retrait des progressions des suites dont la banque cible n'est pas source, annoncé) et copie (nouvelle question, image dupliquée).
- [x] Contrat `QuestionUsage` complété (`moveNotice()`, `applyMove()`) : le module Leitner annonce et applique le retrait des suites, le module QCM ne change rien ; le module Banques reste indépendant.
- [x] Actions groupées « Déplacer vers » et « Copier vers » dans la liste des questions ; confirmation qui annonce les suites touchées ; notification avec le nombre de questions.
- [x] Tests : déplacement depuis la banque par défaut ; questionnaire QCM inchangé après déplacement ; suite Leitner qui perd la question ; copie indépendante ; thème conservé.
- [x] `composer qa` vert ; commit.

### Étape B7 — Documentation utilisateur

- [x] `doc/users/banques.md` : « Créer une banque », « Saisir une question » (par type), « Ranger les questions de la banque par défaut », « Déplacer ou copier des questions », « Exporter une banque », « Importer des questions », « Préparer un fichier dans un tableur », référence du format.
- [x] `doc/users/qcm.md` et `doc/users/leitner.md` : sections sur les banques remplacées par des renvois ; vrai / faux dans le QCM ; avertissement des types ; questions créées ou importées depuis un questionnaire rangées dans une banque.
- [x] README : tableau des modules et section Documentation (PRD, plans et guides des trois modules).
- [x] README : sauvegarde de la base avant la migration du lot B1 (faite à l'étape B4).

### Étape B8 — Clôture du lot B1

- [x] Sauvegarde de la base de développement, migration, puis critère de sortie du §1 déroulé dans le navigateur (QCM et Leitner) : banques partagées visibles dans les deux modules, ancien fichier QCM réimporté dans une banque, étude QCM et suite Leitner sur cette banque. Le parcours a révélé que les pages d'examen, d'étude QCM et de séance Leitner écrivaient les clés des réponses dans le HTML ; elles n'envoient plus que le rang affiché (tests de non-régression).
- [x] Mettre à jour le statut de ce plan, des plans QCM et Leitner et des PRD concernés (les PRD ne portent pas de statut de lot).
- [x] `composer qa` vert ; `php artisan laraskel:outillage --check` conforme.
- [x] PR : [#1](https://github.com/flub78/mentor/pull/1).

---

## 5. Lot B1.1 — Référence et ordre des propositions

**Objectif** (PRD §9.2) : une banque exportée, corrigée dans un tableur puis réimportée met à jour ses questions sans perdre la progression des suites Leitner ; un QCM non mélangé s'affiche dans l'ordre du fichier. Origine : suggestions S-018 et S-021 du projet poker, qui produit des banques au format de Mentor.

### Décisions techniques

| Sujet | Choix | Alternatives écartées |
|---|---|---|
| Référence | Colonne `reference` (texte court, facultatif) de `banques_questions`, index unique (`bank_id`, `reference`) : une banque copiée par export / import garde ses références. | Unique dans toute l'application : interdirait de réimporter une banque dans une nouvelle banque. Identifiant interne exporté : n'existe pas dans un fichier écrit à la main ou par un autre outil. |
| Questions déjà présentes à l'import | Choix du formateur dans le rapport : mettre à jour, ou ignorer ces lignes (PRD UC-05). | Toujours créer (la référence ne servirait qu'à l'aller-retour) ; toujours mettre à jour (pas de garde-fou). |
| Nature d'une mise à jour par import | De fond si la réponse attendue (`payload`) change, mineure sinon ; appliquée par le registre des utilisations (`UsageRegistry`), comme une modification à la main. | Demander la nature ligne par ligne : trop lourd pour un import. |
| Ordre fixe d'un QCM | `payload.correct_position` : rang de la bonne réponse parmi les propositions (0 par défaut), les mauvaises restant dans leur ordre. Toute permutation s'écrit ainsi ; les clés des réponses (`bonne`, `fausse-N`) ne changent pas. Remis à 0 quand les réponses sont mélangées, pour qu'un réimport ne voie pas de faux changement. | Liste complète des clés dans le `payload` : redondante, et à réparer à chaque modification des mauvaises réponses. |
| Colonne `ordre` | Liste des textes des propositions ; à l'import, les mauvaises réponses (et leurs explications) sont rangées dans cet ordre et la place de la bonne réponse en est déduite. | Colonne « position de la bonne réponse » : plus simple, mais ne fixe pas l'ordre voulu des mauvaises réponses quand le fichier les numérote autrement. |

### Étape R1 — Référence des questions

- [x] Migration : colonne `reference` et index unique (`bank_id`, `reference`) ; modèle (`fillable`).
- [x] Formulaire de question, section Compléments : champ « Référence », unique dans la banque, message qui nomme la question qui la porte.
- [x] Déplacement et copie (`QuestionMover`) : référence gardée si elle est libre dans la banque cible, retirée sinon, et dite dans le message de fin.
- [x] Tests : unicité dans une banque, même référence dans deux banques, déplacement et copie en conflit.

### Étape R2 — Ordre fixe des propositions d'un QCM

- [x] `QcmHandler` : `correct_position` (normalisation, règle de cohérence, `options()` dans l'ordre d'affichage) ; formulaire : « Place de la bonne réponse », visible sans mélange.
- [x] Module QCM : les réponses d'une question (`Question::answers`) suivent l'ordre d'affichage (étude, passage, correction) ; séance Leitner idem.
- [x] Format d'échange : colonnes `reference` et `ordre` (lecture, erreurs, avertissement quand `melange` vaut `oui`), écriture à l'export.
- [x] Tests : ordre affiché dans les trois modules, lecture et erreurs de `ordre`, aller-retour export / import.

### Étape R3 — Import avec mise à jour

- [x] Analyse : lignes reconnues par leur référence, comparées à la question (inchangée, modifiée, réponse attendue modifiée) ; erreurs (type différent, référence répétée dans le fichier) ; ces lignes ne sont pas des doublons.
- [x] Rapport avant import et choix « Mettre à jour » / « Ignorer ces lignes » ; import : mise à jour (image gardée si la cellule est vide), effet sur les suites Leitner, notification (créées, mises à jour, inchangées, ignorées, suites touchées).
- [x] Import d'un questionnaire QCM : références déjà prises dans la banque d'arrivée non reprises, et dites.
- [x] Tests : mise à jour mineure et de fond (progression Leitner), choix « ignorer », type différent, image gardée, questionnaire.

### Étape R4 — Documentation et clôture

- [x] `doc/users/banques.md` : référence, place de la bonne réponse, « Corriger une banque dans un tableur » (export, correction, réimport) ; format (`reference`, `ordre`) ; fichiers exemples.
- [x] Vérification dans le navigateur : saisie d'un QCM à ordre fixe avec référence, ordre affiché en étude QCM et en séance Leitner, export (`reference`, `ordre`). Le réimport avec mise à jour est couvert par les tests (le téléversement n'aboutit pas dans le navigateur piloté par l'agent).
- [x] Statuts de ce plan ; `composer qa` vert.
- [ ] PR (après accord pour pousser).

---

## 6. Lots suivants

- **B2** : reprend les éléments « types et compléments d'édition » jusqu'ici prévus à l'étape 6 du [plan Leitner](leitner_plan.md) et au lot 5 du [plan QCM](qcm_plan.md) (types réponse courte, énumération, correspondance ; images dans les propositions ; duplication, décomptes, prévisualisation, recherche, doublons à la saisie).
- **B3** : type question ouverte et correction par l'IA, après la livraison de l'interface LLM du socle Laraskel.

Ces lots seront détaillés ici avant leur démarrage.
