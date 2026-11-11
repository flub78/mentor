# Plan d'implémentation — Module Banques de questions

- **Statut** : à démarrer
- **Date** : 2026-11-11
- **Auteur** : Frédéric Peignot (assisté d'un agent IA)
- **PRD associé** : [banques_prd.md](../prds/banques_prd.md), §9.2 « Découpage en lots »
- **Documents liés** : [plan QCM](qcm_plan.md), [plan Leitner](leitner_plan.md), [PRD Mentor](../prds/mentor_prd.md) §1.4

## Vue d'ensemble des lots

| Lot | Contenu (PRD §9.2) | Étapes | Statut |
|---|---|---|---|
| B1 — Banques communes | Module Banques, banque par défaut, reprise des banques QCM et Leitner, modules QCM et Leitner branchés dessus, import / export, déplacement | B1 à B8 (§4) | À faire |
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
| Interface utilisateur | Un **plugin Filament** `BanquesPlugin` qui enregistre les ressources des banques dans les panels `qcm` et `leitner` : l'utilisateur gère les banques depuis le module où il se trouve. | Panel `banques` séparé : un troisième espace où naviguer. Ressource dans un seul panel : l'autre module devrait y renvoyer. |
| Banque par défaut | Colonne `is_default` (une seule à vrai, contrôlée dans le modèle) ; banque « Banque par défaut » créée par la migration si aucune n'existe ; renommable, non supprimable (exception en français). | Banque par défaut par module : contraire au partage des banques. |
| Question QCM | `payload` du type QCM : `correct`, `wrong[]` (texte, explication), `shuffle`. Les propositions sont identifiées par des **clés stables** (`bonne`, `fausse-0`…), comme dans Leitner. | Garder `qcm_answers` : deux représentations d'une même question. |
| Vrai / faux dans le QCM | Le module QCM présente une question vrai / faux avec les propositions « Vrai » et « Faux » (clés `vrai`, `faux`), sans mélange ; l'explication de la question tient lieu d'explication de la mauvaise réponse. | Convertir les vrai / faux en QCM à l'import : perd le type pour Leitner. |
| Reprise des données QCM | Migration de données en PHP (pas en SQL brut), dans une transaction : `qcm_banks` → `banques_banks` (nom suffixé « (QCM) » en cas de conflit avec une banque Leitner) ; `qcm_questions` + `qcm_answers` → `banques_questions` de type QCM (ordre des mauvaises réponses conservé, `module` → `theme`) ; questions propres d'un questionnaire → banque par défaut ; `qcm_questionnaire_questions.question_id` et `qcm_attempt_items.question_id` réécrits ; `qcm_attempt_items.chosen_answer_id` et `answer_order` convertis en clés (`chosen_key`, ordre de clés) ; table de correspondance des identifiants conservée le temps de la migration. Les résultats (`qcm_results`) ne référencent aucune question et ne changent pas. Images : chemins conservés, fichiers non déplacés. Les anciennes tables sont supprimées par une migration distincte, après vérification. | Recopie manuelle par export / import : perd les passages en cours et les liens des questionnaires. |
| Avertissement des types | Service QCM `UsableQuestions` : filtre une requête de questions sur les types QCM et vrai / faux et renvoie le nombre de questions écartées ; chaque point d'entrée (étude, tirage, ajout, import de questionnaire) affiche l'avertissement du PRD Banques §7. | Filtrer en silence : contraire à la règle « jamais d'échec silencieux ». |
| Échanges | Le code d'échange du module QCM (`App\Qcm\Exchange`) est généralisé et déplacé dans `App\Banques\Exchange` pour les banques ; la lecture et l'écriture des lignes de questions passent par les handlers (`toCsv()` / `fromCsv()`) ; le module QCM garde l'export et l'import des questionnaires et des examens, en appelant l'interface du module Banques pour les questions. | Deux lecteurs CSV : formats qui divergent. |
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

- [ ] Créer `App\Banques\` et y déplacer `App\Leitner\Questions\*`, `App\Leitner\Models\Bank`, `Question`, `App\Leitner\Filament\Schemas\QuestionFormSchema`, la ressource des banques et son gestionnaire de questions, les fabriques ; `config/banques.php` (taille d'image) ; mises à jour des imports dans Leitner.
- [ ] Migration : renommer `leitner_banks` → `banques_banks`, `leitner_questions` → `banques_questions` ; ajouter `is_default` ; créer la banque par défaut ; clés étrangères Leitner recréées vers les nouvelles tables.
- [ ] `BanquesPlugin` enregistré dans les panels `qcm` et `leitner`.
- [ ] Banque par défaut : non supprimable (action masquée et exception), renommable.
- [ ] Tests existants des banques et questions Leitner déplacés dans `tests/Feature/Banques/` et passants ; tests de la banque par défaut ; ressource visible dans les deux panels.
- [ ] `composer qa` vert ; commit.

### Étape B2 — Reprise des données QCM

- [ ] Migration de données (§3, « Reprise des données QCM »), réversible jusqu'à la suppression des anciennes tables (étape B4).
- [ ] Tests de la migration sur un jeu de données QCM construit avec les anciennes fabriques : banques, questions avec 1 à 4 mauvaises réponses et explications, questions propres, questionnaires, passage en cours avec réponse choisie, passage terminé et son résultat ; conflit de nom avec une banque Leitner ; aucune perte (mêmes nombres, mêmes textes, même ordre, même réponse choisie).
- [ ] `composer qa` vert ; commit.

### Étape B3 — Module QCM sur les banques communes

- [ ] Étude libre (`ModuleCatalog`, `StudyDraw`, page d'étude) : questions des banques communes, thème comme module, filtre `UsableQuestions` et avertissement.
- [ ] Questionnaires : plus de questions propres ; « Nouvelle question » crée dans la banque choisie (par défaut la banque par défaut) et le message le dit ; ajout et tirage avec avertissement ; duplication et suppression sans questions propres.
- [ ] Passages et correction (`AttemptStarter`, `AttemptRecorder`, `AttemptFinisher`, `TakeExam`) : propositions par clés, vrai / faux comme QCM à deux réponses non mélangé.
- [ ] Retirer la ressource des banques et le gestionnaire de questions du module QCM (remplacés par le plugin).
- [ ] Tests QCM existants adaptés ; nouveaux tests : vrai / faux en étude, en questionnaire et en passage ; avertissement avec le nombre de questions écartées ; banque sans question utilisable grisée.
- [ ] `composer qa` vert ; commit.

### Étape B4 — Suppression des anciennes tables QCM

- [ ] Migration qui supprime `qcm_answers`, `qcm_questions`, `qcm_banks` et `qcm_attempt_items.chosen_answer_id`, après vérification que plus aucun code ne les lit (recherche dans `app/` et `tests/`).
- [ ] `composer qa` vert ; commit.

### Étape B5 — Import et export des banques (UC-04, UC-05)

- [ ] `App\Banques\Exchange` à partir de `App\Qcm\Exchange` : format du PRD Banques §8 pour les types du lot B1 ; colonnes communes, colonnes par type via les handlers, listes et caractères réservés, valeurs, rapport avant import, doublons, images, transaction.
- [ ] Actions d'import et d'export dans la ressource des banques ; fichiers exemples `public/exemples/banques/` (un par type) ; anciens exemples QCM conservés et importables.
- [ ] Module QCM : export et import des questionnaires et des examens réécrits sur l'interface d'échange du module Banques ; l'import crée ses questions dans la banque choisie.
- [ ] Tests : aller-retour de chaque type ; import des fichiers exemples QCM historiques et d'exports QCM des tests existants ; erreurs par type (message avec ligne, colonne et valeurs admises) ; questionnaire et examen exportés puis réimportés à l'identique.
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
