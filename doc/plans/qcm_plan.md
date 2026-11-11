# Plan d'implémentation — Module QCM

- **Statut** : MVP en cours — étapes 0 à 2 livrées (vérification manuelle de l'étape 2 à faire) ; lot 2 en cours (étapes 4 à 6 livrées, clôture à faire)
- **Date** : 2026-11-08
- **Auteur** : Frédéric Peignot (assisté d'un agent IA)
- **PRD associé** : [qcm_prd.md](../prds/qcm_prd.md), §8 « Découpage en lots »
- **Documents liés** : [PRD Mentor](../prds/mentor_prd.md) §1.4 (architecture modulaire),
  [guide de l'application dérivée](../../../laraskel/doc/development/guide-application-derivee.md)

## Vue d'ensemble des lots

| Lot | Contenu (PRD §8) | Étapes | Statut |
|---|---|---|---|
| MVP | Banques, questions, étude libre | 0 à 3 (§4) | En cours |
| Lot 2 — Questionnaires, examens et échanges | Fichier d'échange, import / export des banques, des questionnaires et des examens, questionnaires (questions propres, de banque, génération par tirage), définition des examens | 4 à 7 (§5) | À faire |
| Lot 3 — Passage, résultats et certificats | Identification, passage chronométré, certificat imprimable, correction détaillée, liste des résultats, réédition | 8 à 12 (§6) | En cours |
| Lot 4 — Diffusion en ligne | Connexion du formateur, diffusion d'un examen par lien, envoi du certificat par e-mail | 13 à 16 (§7) | À faire |
| Lot B1 (module Banques) | Banques et questions confiées au module Banques de questions, partagé avec Leitner ; reprise des données ; vrai / faux comme QCM à deux réponses : voir le [plan Banques](banques_plan.md) | — | À faire, après la clôture du lot 3 |
| Lot 5, v1, v2 | Compléments d'étude, comptes et rôles, etc. (compléments d'édition des banques : lot B2 du module Banques) | À détailler (§8) | — |

Les lots 2 à 4 s'appuient sur des choix provisoires pour des questions ouvertes du PRD ; ils sont
signalés « (Qn, choix provisoire) » et à revoir si la question est tranchée autrement.

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
| Dupliquer une banque ; décompte par module dans la liste des banques | UC-03 | Lot B2 (module Banques) |
| Détection des doublons à l'import | UC-04, UC-07 | Lot 2 |
| Prévisualisation, recherche plein texte, actions groupées, doublons à la saisie ; interdiction du mélange des réponses par question | UC-04 | Lot B2 (module Banques) ; mélange : lot B1 |
| Copier / déplacer des questions | UC-05 | Lot B1 (module Banques) |
| Export / import CSV et ZIP des banques | UC-06, UC-07 | Lot 2 |
| Questionnaires, examens, export / import des questionnaires et des examens | UC-08, UC-20, UC-16, UC-17 | Lot 2 |
| Passage d'examen, certificat, correction détaillée, liste des résultats, réédition | UC-09, UC-12, UC-13, UC-19 | Lot 3 |
| Diffusion d'un examen par lien | UC-18 | Lot 4 |
| Passer une question sans répondre ; relancer sur les questions échouées | UC-11 (4a, 7a) | Lot 5 |
| Enregistrement des réponses d'étude, historique, filtres de tirage | UC-11, UC-14 | v1 |
| Comptes, rôles, accès, statistiques, signalements | UC-01, 02, 10, 15 | v1 |
| Statut brouillon / publié, appartenance des banques, groupes, reprise de session, autres médias | — | v2 |

## 3. Décisions techniques

| Sujet | Décision | Alternatives écartées |
|---|---|---|
| Interface | Un **panel Filament `qcm`** (`/qcm`) pour les banques, les questions et la page d'étude. Filament est déjà présent (brique `laraskel-users`) et c'est la brique CRUD retenue par Laraskel. | Pages Blade + Livewire écrites à la main : plus de code pour le même résultat. Panel `admin` de `laraskel-users` : il impose la connexion. |
| Sans connexion | Le panel `qcm` n'a ni page de connexion ni `authMiddleware`. Il devient le panel par défaut (`LARASKEL_USERS_ADMIN_PANEL_DEFAULT=false`) ; `/` redirige vers `/qcm`. Le panel `admin` reste en place, inutilisé. **Le MVP n'est pas fait pour être exposé sur Internet.** | Utilisateur fictif connecté automatiquement : complique le passage aux rôles en v1 sans rien apporter. |
| Emplacement du code | Dans l'application, sous l'espace de noms `App\Qcm\` (modèles, ressources Filament, services) ; tables préfixées `qcm_`. L'extraction en package reste possible (PRD Mentor, Q28). | Package `packages/qcm` dès maintenant : prématuré tant que Q28 n'est pas tranchée. |
| Réponses | Une table `qcm_answers` avec `is_correct` ; exactement une bonne réponse par question, contrôlée à l'enregistrement. | Bonne réponse en colonne de la question : complique le mélange et l'affichage uniforme des réponses. |
| Image | Fichier sur le disque `public`, chemin dans la question ; ancien fichier supprimé au remplacement et à la suppression. | `spatie/laravel-medialibrary` : la brique médias de Laraskel n'est pas encore extraite ; à reprendre quand elle le sera. |
| Session d'étude | État tenu dans le composant Livewire de la page (questions tirées, réponses, score) ; rien n'est écrit en base. Un rechargement de page perd la session (reprise reportée, Q10). | Table de sessions : utile seulement avec l'historique (v1). |
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

- [x] Migration `create_qcm_banks_table` : colonnes du §3, `name` unique, `auditColumns()`.
- [x] Migration `create_qcm_questions_table` : clé étrangère `bank_id` en cascade, index sur `(bank_id, module)`.
- [x] Migration `create_qcm_answers_table` : clé étrangère `question_id` en cascade, `position` entier.
- [x] Modèles `App\Qcm\Models\Bank`, `Question`, `Answer` avec relations (`Bank::questions`, `Question::answers`, `Question::correctAnswer`, `Question::wrongAnswers`) et trait `TracksAuthor`.
- [x] `Bank::moduleNames()` : noms de module distincts des questions de la banque, triés, pour l'autocomplétion.
- [x] Suppression du fichier image lors du remplacement de l'image et de la suppression d'une question (y compris en cascade depuis une banque).
- [x] Fabriques `BankFactory`, `QuestionFactory` (avec un état `withAnswers(wrong: 3)`), `AnswerFactory`.

**Interface**

- [x] Ressource Filament `BankResource` : liste (nom, description, nombre de questions), création, modification, suppression avec confirmation indiquant le nombre de questions supprimées.
- [x] Gestionnaire de relation `QuestionsRelationManager` dans la banque : liste (énoncé tronqué, module, présence d'image), filtre par module, création, modification, suppression avec confirmation.
- [x] Formulaire de question :
  - [x] énoncé (texte multiligne, obligatoire) ;
  - [x] bonne réponse (obligatoire) ;
  - [x] mauvaises réponses : répéteur, 3 lignes par défaut, au moins 1, chaque ligne avec texte obligatoire et explication facultative ;
  - [x] explication générale facultative ;
  - [x] image facultative (`FileUpload`, images seulement, taille maximale `config/qcm.php`, `QCM_IMAGE_MAX_KB`, 2 Mo par défaut, Q8) ;
  - [x] module : champ texte avec suggestions `Bank::moduleNames()` de la banque courante (`TextInput::datalist`).
- [x] Enregistrement de la question et de ses réponses dans une transaction (`databaseTransactions()` sur le panel) ; bonne réponse en position 0, mauvaises réponses en 1…n.
- [x] Après création d'une banque, ouverture directe de la banque pour y saisir les questions.
- [x] Messages de succès et d'erreur explicites pour chaque action (règle commune UX) : notifications Filament, confirmations de suppression qui annoncent ce qui sera perdu, messages propres au module (nom de banque déjà pris, image refusée, mauvaise réponse manquante).
- [ ] Messages de validation standard de Laravel en français (« Le champ … est obligatoire ») : ils sont aujourd'hui en anglais, faute de traductions françaises dans les briques Laraskel. À traiter dans Laraskel (brique de traductions), pas par copie dans Mentor.

**Tests**

- [x] Tests sur base SQLite en mémoire avec `RefreshDatabase` (base non partagée, propre à chaque exécution).
- [x] Modèles : relations ; module vide ramené à « sans module » ; `moduleNames()` ne renvoie que les modules de la banque, sans doublon ; suppression d'une banque → questions, réponses et images supprimées.
- [x] Ressource banque : créer, renommer, supprimer ; nom obligatoire et unique.
- [x] Questions : créer avec 3 mauvaises réponses ; refus sans énoncé, sans bonne réponse, sans mauvaise réponse ; modifier les réponses ; supprimer ; filtre par module.
- [x] Image : envoi stocké sur le disque `public` (`Storage::fake`) ; remplacement et suppression effacent l'ancien fichier ; refus d'un fichier qui n'est pas une image.

**Documentation utilisateur**

- [x] `doc/users/qcm.md` : « Créer une banque », « Saisir une question », « Réutiliser un nom de module ».
- [x] `composer qa` vert ; vérification manuelle dans le navigateur (ordinateur et largeur téléphone) ; commit.

### Étape 2 — Étude libre

**Logique**

- [x] Service `App\Qcm\Study\ModuleCatalog` : pour une liste de banques, regroupe les questions par module sans tenir compte des majuscules, avec libellé (forme la plus fréquente) et nombre de questions ; groupe « Sans module ».
- [x] Service `App\Qcm\Study\StudyDraw` : à partir d'un nombre demandé par module (ou « toutes »), tire les questions au hasard, sans doublon ; un nombre supérieur au disponible est ramené au disponible, avec un message.
- [x] Objet `StudySession` (sérialisable dans Livewire) : liste des identifiants de questions, ordre mélangé des réponses par question, réponses données, score courant, score par module.

**Page d'étude (`App\Qcm\Filament\Pages\Study`)**

- [x] Écran 1, configuration : choix d'une ou plusieurs banques ; tableau des modules avec le nombre disponible, un champ nombre et une case « toutes » par module ; bouton « Commencer » désactivé tant que le total est nul.
- [x] Écran 2, question : énoncé, image éventuelle, réponses mélangées ; l'utilisateur choisit et valide.
- [x] Après validation : bonne ou mauvaise réponse signalée, bonne réponse mise en évidence, explication de la mauvaise réponse choisie et explication générale si elles existent ; bouton « Question suivante ».
- [x] En permanence : pourcentage de bonnes réponses depuis le début de la session et nombre de questions restantes.
- [x] Écran 3, bilan : taux global, taux par module, liste des questions échouées avec leur bonne réponse ; bouton « Nouvelle session ».
- [x] Lien « Étudier » dans la navigation du panel et depuis la liste des banques (banque présélectionnée par `?bank=`, action visible si la banque a des questions).
- [x] Bouton « Terminer la session » : arrête la session et affiche le bilan des questions déjà répondues.
- [x] Question supprimée pendant la session : message et passage à la suivante.
- [x] Mise en page avec les composants Blade de Filament (section, badge, callout, bouton, champs) et quelques styles en ligne, le panel utilisant la feuille précompilée de Filament ; lignes repliables pour la largeur téléphone.

**Tests**

- [x] `ModuleCatalog` : « Météo » et « météo » forment un seul module ; libellé majoritaire ; groupe « Sans module » ; plusieurs banques.
- [x] `StudyDraw` : nombre exact par module ; « toutes » ; demande supérieure au disponible ; pas de doublon.
- [x] Page : session complète (configuration → réponses → bilan) avec Livewire ; bonne et mauvaise réponse mettent à jour le pourcentage ; le bilan par module est juste ; une question ne peut pas être validée sans réponse choisie.
- [x] Mélange : sur une question, l'ordre des réponses n'est pas toujours le même (tirage répété avec graine contrôlée).
- [x] Session : réponse d'une autre question ou double réponse refusées ; état conservé à travers la sérialisation Livewire ; démarrage refusé avec message (aucune banque, aucune question) ; fin anticipée.

**Documentation utilisateur**

- [x] `doc/users/qcm.md` : « Lancer une session d'étude », « Lire le bilan ».
- [x] `composer qa` vert ; commit.
- [ ] Vérification manuelle dans le navigateur (ordinateur et largeur téléphone).

### Étape 3 — Clôture du MVP

- [ ] Dérouler le critère de sortie du §1 dans le navigateur, sur ordinateur et à largeur téléphone.
- [ ] README : comment lancer l'application et accéder au module QCM.
- [ ] Mettre à jour le statut de ce plan (vue d'ensemble) et du PRD QCM (§8).
- [ ] `composer qa` vert ; `php artisan laraskel:outillage --check` conforme ; PR.

---

## 5. Lot 2 — Questionnaires, examens et échanges

### 5.1 Objectif et périmètre

Toujours sans connexion, l'utilisateur peut :

1. exporter et importer une banque (UC-06, UC-07) ;
2. composer des questionnaires, listes de questions propres et de questions de banque, à la main ou
   par tirage au hasard dans des banques (UC-08) ;
3. définir des examens qui utilisent un questionnaire, avec tous leurs paramètres, y compris ceux
   qui ne servent qu'au lot 3 (UC-20) ;
4. exporter et importer un questionnaire ou un examen (UC-16, UC-17).

Critère de sortie (PRD §8) : l'utilisateur importe une banque et un examen existants, modifie le
questionnaire, exporte l'examen, et le fichier réimporté redonne le même questionnaire et le même
examen.

Hors lot 2 : la prévisualisation de l'examen (lot 3, elle réutilise l'écran de passage) ; aucun
passage n'est encore possible.

### 5.2 Décisions techniques

| Sujet | Décision | Alternatives écartées |
|---|---|---|
| Appartenance d'une question | `qcm_questions.bank_id` devient nullable et une colonne `questionnaire_id` nullable est ajoutée ; exactement l'une des deux est renseignée, contrôlé dans le modèle (événement `saving`, exception en français) et par les tests. Formulaire de question, réponses, image et étude restent inchangés. | Propriétaire polymorphe (`owner_type` / `owner_id`) : perd les clés étrangères et la suppression en cascade. Table séparée pour les questions propres : duplique réponses, image, formulaire et export. |
| Liste d'un questionnaire | Table pivot ordonnée `qcm_questionnaire_questions` (questionnaire, question, position) pour **toutes** ses questions, propres et de banque : un seul ordre, une seule liste. | Deux listes (questions propres par `questionnaire_id`, questions de banque par pivot) : l'ordre mélangé des deux devient compliqué. |
| Examen | Table `qcm_exams` qui référence le questionnaire (`restrictOnDelete` : un questionnaire utilisé ne peut pas être supprimé, PRD UC-08 3b) ; l'examen pose toutes les questions du questionnaire (Q21 tranchée). | Paramètres d'examen portés par le questionnaire : mélange liste de questions et conditions de passage, ce que le PRD distingue. |
| Génération par tirage | Réutilise `ModuleCatalog::forBanks()` (modules disponibles) et `StudyDraw::draw()` (tirage par module) du MVP, en excluant les questions déjà présentes ; les questions tirées sont ajoutées au pivot comme questions de banque. Rien du tirage n'est conservé. | Règles de tirage enregistrées et rejouées à chaque passage : écarté par le PRD (Q21), les candidats doivent avoir le même questionnaire. |
| Paramètres de l'examen | Colonnes simples (durée, taux minimal, mélange, correction détaillée, passages maximum) ; champs d'identification en colonne JSON castée en tableau typé. | Table dédiée aux champs d'identification : aucune requête ne porte dessus. |
| Format CSV | En-tête obligatoire ; colonnes `enonce`, `bonne_reponse`, `explication`, `module`, `illustration`, puis paires `mauvaise_reponse_N` / `explication_N` (N = 1, 2…, sans limite, comme le formulaire). Séparateur `;` ou `,` détecté sur l'en-tête ; UTF-8, BOM toléré ; colonne inconnue signalée et ignorée. Lecture et écriture avec `fgetcsv` / `fputcsv`. | `league/csv` : nouvelle dépendance, non nécessaire. JSON seul : peu pratique à produire depuis un tableur. |
| Format ZIP | `questions.csv`, `images/<fichier>`, et un fichier de paramètres `banque.json`, `questionnaire.json` ou `examen.json` (ce dernier contient aussi les paramètres du questionnaire), avec `format_version` ; `ZipArchive` (extension `zip` présente). Taille d'archive maximale dans `config/qcm.php`. | CSV + JSON séparés sans archive : les images ne voyagent pas. |
| Import en deux temps | Assistant Filament en deux étapes : téléversement, puis rapport (`ImportReport`) et confirmation. Le fichier reste dans le stockage temporaire de Livewire (`storeFiles(false)`), qui le purge ; il est analysé à la fin de la première étape et de nouveau à la confirmation, sans rien écrire avant ; écriture dans une transaction, illustrations copiées effacées en cas d'échec. | Copie du fichier sur le disque `local` entre l'analyse et la confirmation : fichiers orphelins à purger si l'utilisateur ferme la fenêtre. | Actions `ImportAction` / `ExportAction` de Filament : elles exigent un utilisateur connecté, une file d'attente et des tables d'import, et traitent une ligne = un modèle, sans réponses imbriquées ni archive d'images. |
| Doublons | Énoncé normalisé (minuscules, espaces réduits, ponctuation finale retirée) comparé dans la cible et dans le fichier. | Distance d'édition : coûteuse sur 1 000 questions et source de faux positifs. |
| Code | Espace de noms `App\Qcm\Exchange\` : `QuestionCsv` (lecture et écriture), `ExchangeFile` (lecture CSV ou ZIP), `ExchangeWriter`, `ImportReport`, `QuestionPersister`, `BankExporter`, `BankImporter`, `QuestionnaireExporter`, `QuestionnaireImporter` (questionnaire seul ou avec examen) ; actions Filament dans `App\Qcm\Filament\Actions\`. Formulaire de question extrait de `QuestionsRelationManager` dans un schéma partagé `QuestionFormSchema`. | — |

### 5.3 Modèle de données (ajouts)

```
qcm_questions               bank_id → nullable ; + questionnaire_id? → qcm_questionnaires (cascade)
qcm_questionnaires          id, title, description?, version?, auditColumns
qcm_questionnaire_questions id, questionnaire_id (cascade), question_id (cascade), position, auditColumns
qcm_exams                   id, title, instructions?, questionnaire_id → qcm_questionnaires (restrict),
                            duration_minutes?, pass_rate, shuffle_questions,
                            shows_correction, max_attempts?, identification_fields (json),
                            identifier_label?, auditColumns
```

`identification_fields` : exigence par renseignement, `{"email": …, "date_naissance": …, "organisation": …, "identifiant": …}`,
chacune `non_demande`, `facultatif` ou `obligatoire` ; libellé de l'identifiant libre dans `identifier_label` ; nom et
prénom toujours demandés et obligatoires.

### Étape 4 — Fichier d'échange ; export et import des banques (UC-06, UC-07)

**Logique**

- [x] `config/qcm.php` : `import_max_kb` (taille maximale d'un fichier importé, 10 Mo par défaut, sous la limite de 12 Mo des téléversements temporaires de Livewire).
- [x] `QuestionCsv::write()` : en-tête et une ligne par question (mauvaises réponses dans l'ordre de `position`), nom de fichier d'image unique dans l'archive.
- [x] `QuestionCsv::parse()` : détection du séparateur ; vérification de l'en-tête (colonnes obligatoires manquantes → erreur de fichier qui liste les colonnes attendues) ; validation de chaque ligne (énoncé, bonne réponse, au moins une mauvaise réponse, explication sans réponse) ; lignes valides et erreurs avec numéro de ligne.
- [x] `ExchangeFile` : ouvre un ZIP ou un CSV seul ; lit le fichier de paramètres et `format_version` ; lit chaque élément par son nom sans rien extraire sur le disque ; refuse les noms d'illustration avec chemin (`../`), les fichiers d'image qui ne sont pas des images ou dépassent `image_max_kb`. `ExchangeWriter` écrit l'archive dans un fichier temporaire.
- [x] `ImportReport` : questions valides, erreurs, doublons (dans le fichier et avec la cible), modules absents de la cible, images référencées absentes, paramètres ignorés.
- [x] `BankExporter` : banque entière ou sélection → CSV ou ZIP, nom de fichier `banque-<slug>-<date>.zip`.
- [x] `BankImporter` : `analyse(fichier, banque cible | nouvelle)` → rapport ; `import(fichier, cible, nouvelle banque, traitementDoublons)` → questions, réponses et images (`QuestionPersister`) dans une transaction ; images copiées sur le disque `public`, effacées si l'import échoue.

**Interface**

- [x] Liste des banques : action « Importer une banque » (téléversement → rapport dans une fenêtre → nom et description modifiables, traitement des doublons → confirmer / annuler).
- [x] Dans une banque : « Importer des questions » (banque cible imposée) ; « Exporter (CSV) » et « Exporter (ZIP) » sur la banque et en action groupée sur une sélection de questions.
- [x] Rapport lisible : compteurs, tableau des erreurs (ligne, motif), doublons, modules nouveaux, images manquantes ; notification finale avec le nombre de questions importées et ignorées.
- [x] Fichiers exemples `public/exemples/qcm/banque-exemple.csv` et `banque-exemple.zip`, liés depuis la fenêtre d'import et la documentation.
- [x] Fabrique `AnswerFactory::correct()` : pas d'explication sur la bonne réponse (l'explication générale est portée par la question).

**Tests**

- [x] Aller-retour : banque avec images, modules et explications → export ZIP → import dans une nouvelle banque → contenu identique (énoncés, réponses, explications, modules, contenu des images).
- [x] CSV : séparateur `;` et `,`, BOM, nombre variable de mauvaises réponses, colonne inconnue signalée.
- [x] Erreurs : fichier non UTF-8, en-tête incomplet, ligne sans énoncé, sans bonne réponse, sans mauvaise réponse, explication orpheline, ligne trop longue ; une ligne en erreur n'empêche pas les autres ; aucune écriture à l'analyse ; échec en cours d'import → ni banque ni image.
- [x] Doublons dans le fichier et avec la banque cible ; « ignorer » et « importer quand même ».
- [x] Archive : nom d'illustration avec chemin refusé, image non image refusée, absence de CSV, plusieurs fichiers de paramètres, JSON invalide, `format_version` absente ou future ; taille maximale contrôlée par le champ de téléversement (message avec la taille).
- [x] Nom de nouvelle banque déjà pris : message et demande d'un autre nom.
- [x] Interface : actions d'import et d'export avec Livewire (`Storage::fake`).

**Documentation utilisateur**

- [x] `doc/users/qcm.md` : « Exporter une banque », « Importer des questions », « Préparer un fichier dans un tableur » ; référence du format (colonnes, fichiers de paramètres) avec liens vers les fichiers exemples.
- [x] `composer qa` vert ; commit.
- [x] Vérification manuelle dans le navigateur (avec l'étape 6).

### Étape 5 — Questionnaires et examens : modèle et édition (UC-08, UC-20)

**Base de données et modèles**

- [x] Migration : `bank_id` nullable et ajout de `questionnaire_id` sur `qcm_questions` (testée sur une copie de la base SQLite de développement, aller et retour ; le retour supprime les questions propres).
- [x] Migrations `qcm_questionnaires`, `qcm_questionnaire_questions`, `qcm_exams` (§5.3), `auditColumns()`.
- [x] Modèle `Questionnaire` : `questions()` (pivot ordonné), `ownQuestions()`, `exams()`, `moduleNames()` ; suppression → questions propres (et images) supprimées, refus si un examen l'utilise (message qui nomme les examens).
- [x] Modèle `Exam` : `questionnaire()` ; casts ; énumérations `IdentificationField` et `FieldRequirement` (valeurs du fichier d'échange) ; valeurs par défaut dans `config/qcm.php` (`exam_defaults`).
- [x] `Question` : `questionnaire()`, contrôle « banque ou questionnaire, exactement un », `isOwn()` ; suppression d'une question de banque utilisée par un questionnaire : la confirmation cite les questionnaires, le lien est retiré.
- [x] Fabriques `QuestionnaireFactory` (`withOwnQuestions(n)`, `withBankQuestions(n, bank)`), `ExamFactory`.
- [x] Service `App\Qcm\Questionnaires\QuestionnaireComposer` : ajout de questions de banque (sans doublon, ordre conservé), tirage (`StudyDraw` accepte désormais des questions à exclure), duplication (`Question::duplicate()`, image comprise).

**Interface**

- [x] Extraire le formulaire de question dans `QuestionFormSchema` ; `QuestionsRelationManager` l'utilise (pas de changement visible, tests existants verts).
- [x] Ressource `QuestionnaireResource` : liste (titre, version, nombre de questions, nombre d'examens), création, modification, duplication (questions propres copiées, questions de banque partagées), suppression avec confirmation.
- [x] Gestionnaire de relation « Questions » du questionnaire : liste ordonnée réordonnable (énoncé, module, « propre » ou nom de la banque) ; « Nouvelle question » (question propre) ; « Ajouter depuis une banque » (choix de la banque, filtre par module, sélection multiple, questions déjà présentes exclues) ; « Retirer » (question de banque) ou « Supprimer » (question propre) avec confirmations distinctes.
- [x] Action « Tirer des questions au hasard » dans le questionnaire, et « Générer un questionnaire par tirage » dans la liste des questionnaires (titre, banques, puis tableau des modules issu de `ModuleCatalog::forBanks()` avec nombre disponible, nombre à tirer ou « toutes ») ; notification du nombre de questions ajoutées et des modules ramenés au disponible.
- [x] Ressource `ExamResource` : liste (titre, questionnaire, nombre de questions posées, durée, taux minimal), création, modification, duplication, suppression avec confirmation.
- [x] Formulaire d'examen, en sections : général (titre, consignes, questionnaire) ; nombre de questions posées, au total et par module (lecture seule) ; réussite et durée (taux minimal, durée facultative) ; passage (correction détaillée oui / non, mélange, passages maximum) ; identification (nom et prénom affichés comme imposés ; e-mail, date de naissance, organisation, identifiant libre avec libellé, chacun « non demandé / facultatif / obligatoire »).
- [x] Action « Créer un examen » depuis un questionnaire (questionnaire présélectionné).
- [x] Navigation : « Questionnaires » et « Examens » dans le panel, après « Banques de questions ».

**Tests**

- [x] Modèle : une question ne peut avoir ni banque et questionnaire à la fois, ni aucun des deux ; suppression d'un questionnaire → questions propres, réponses et images supprimées, questions de banque intactes ; suppression refusée si un examen l'utilise ; suppression d'une banque → liens retirés des questionnaires.
- [x] Étude libre : les questions propres n'apparaissent jamais dans `ModuleCatalog` ni `StudyDraw` (Q22, choix provisoire).
- [x] Questionnaire : ajout propre, ajout depuis une banque, réordonnancement, retrait, suppression, duplication (compteurs de la liste non recopiés).
- [x] Génération par tirage : nombre exact par module, « toutes », demande supérieure au disponible ramenée avec message, modules regroupés sans majuscules entre banques, questions déjà présentes jamais tirées une seconde fois ; le questionnaire enregistré ne change plus ensuite.
- [x] Examen : validation (titre et questionnaire obligatoires, taux entre 0 et 100, durée positive, libellé obligatoire pour l'identifiant libre).

**Documentation utilisateur**

- [x] `doc/users/qcm.md` : « Composer un questionnaire », « Ajouter des questions propres ou prises dans une banque », « Générer un questionnaire par tirage », « Définir un examen », « Choisir les renseignements demandés aux candidats ».
- [x] `composer qa` vert ; commit.
- [x] Vérification manuelle dans le navigateur (avec l'étape 6) : page d'un questionnaire et formulaire d'examen.
- [ ] Essayer dans le navigateur « Ajouter depuis une banque », « Tirer au hasard » et « Générer par tirage » (couverts par les tests, pas encore essayés à la main).

### Étape 6 — Export et import des questionnaires et des examens (UC-16, UC-17)

**Logique**

- [x] `QuestionnaireExporter` : questionnaire → CSV (questions dans l'ordre, questions de banque écrites en entier) ou ZIP (CSV, images, `questionnaire.json`) ; examen → ZIP (son questionnaire et `examen.json` avec tous les paramètres).
- [x] `QuestionnaireImporter` : analyse → rapport (paramètres lus, invalides remplacés par défaut) ; import → nouveau questionnaire, toutes les questions en questions propres dans l'ordre du fichier, et nouvel examen si `examen.json` est présent (Q24, choix provisoire).
- [x] Valeurs par défaut d'un examen dans `config/qcm.php` (`exam_defaults.pass_rate`, durée nulle, correction détaillée oui) en attendant Q11 ; lecture tolérante des paramètres dans `ExchangeParameters` (valeur invalide remplacée et signalée au rapport).
- [x] Croisements : fichier de questionnaire ou d'examen importé dans une banque (étape 4) → questions seules, paramètres signalés comme ignorés ; fichier de banque importé comme questionnaire → titre = nom de la banque.

**Interface**

- [x] Liste des questionnaires : « Importer un questionnaire ou un examen » (téléversement → rapport → titres modifiables → confirmer / annuler) ; ouverture de ce qui a été créé.
- [x] Questionnaire : « Exporter (CSV) », « Exporter (ZIP) » ; examen : « Exporter (ZIP) ».
- [x] Fichiers exemples `questionnaire-exemple.zip` et `examen-exemple.zip` ; action d'import aussi proposée dans la liste des examens.

**Tests**

- [x] Aller-retour questionnaire : questions propres et de banque, images, version → export ZIP → import → questionnaire équivalent (questions, ordre, titre, version).
- [x] Aller-retour examen : tous les paramètres, champs d'identification compris → examen équivalent sur le nouveau questionnaire.
- [x] CSV seul → questionnaire seul, titre = nom du fichier.
- [x] Paramètres invalides (taux 150, champ d'identification inconnu, `format_version` future) → valeur par défaut ou rejet, avec message.
- [x] Croisements banque ↔ questionnaire.
- [x] Les quatre fichiers exemples s'importent sans erreur ni paramètre corrigé.

**Documentation utilisateur**

- [x] `doc/users/qcm.md` : « Exporter un questionnaire ou un examen », « Importer un questionnaire ou un examen » ; référence de `questionnaire.json` et `examen.json`.
- [x] Vérification manuelle dans le navigateur : import de `examen-exemple.zip` (rapport, titres, questionnaire et examen créés, paramètres repris).
- [x] `composer qa` vert ; commit.
- [ ] Téléversement bloqué sur « Traitement » lors des essais automatisés dans le navigateur, y compris sur le champ image existant du formulaire de question : à confirmer par un essai manuel (glisser-déposer).

### Étape 7 — Clôture du lot 2

- [ ] Dérouler le critère de sortie du §5.1 dans le navigateur, avec un fichier réel de questions de préparation à l'examen théorique.
- [ ] Mesurer l'import de 1 000 questions (exigence : moins d'une minute, PRD §7) ; noter le résultat ici.
- [ ] Mettre à jour le statut de ce plan et du PRD QCM (§8) ; `composer qa` vert ; PR.

---

## 6. Lot 3 — Passage, résultats et certificats

### 6.1 Objectif et périmètre

Toujours sans connexion, sur le poste où l'application est ouverte :

1. un candidat s'identifie, passe un examen (chronométré ou non), obtient son certificat de
   résultat imprimable et, si l'examen le prévoit, la correction détaillée (UC-12, UC-13) ;
2. l'utilisateur consulte et exporte les résultats (UC-09) et réédite un certificat (UC-19).

Critère de sortie (PRD §8) : un candidat s'identifie, passe un examen blanc chronométré et imprime
son certificat ; l'utilisateur modifie ensuite le questionnaire, retrouve le résultat et réédite le
même certificat.

### 6.2 Décisions techniques

| Sujet | Décision | Alternatives écartées |
|---|---|---|
| Passage et résultat séparés | Le **passage** (`qcm_attempts` + `qcm_attempt_items`) tient l'état pendant l'examen : questions posées, ordre des réponses, réponse choisie, marque-pages, échéance. Le **résultat** (`qcm_results`) est une ligne autonome écrite à la fin : identité, date, titres de l'examen et du questionnaire, version, taux par module, taux global, taux minimal, réussite, numéro de certificat. Le certificat ne lit que `qcm_results`. | Instantané complet des questions pour chaque passage : inutile, le certificat ne présente pas les questions (PRD UC-19). Résultat recalculé à partir des réponses : dépendrait des questions, modifiables. |
| Réponses après la fin | Les lignes `qcm_attempt_items` sont conservées (identifiants de question et de réponse) pour les statistiques de v1 (UC-10) et une éventuelle neutralisation (Q13) ; elles ne servent ni au certificat ni à la réédition. Leur conservation suivra Q20. | Suppression à la fin du passage : simple, mais rend UC-10 et Q13 impossibles pour les passages déjà faits. |
| Correction détaillée | Affichée à la fin du passage, dans le même écran, à partir des lignes du passage et des questions actuelles ; une question supprimée entre-temps est signalée comme telle. Non conservée. | — |
| Écran de passage | Composant Livewire pleine page **hors du panel Filament** (route `/examens/...`), mise en page Blade + Tailwind de l'application (Vite). Le panel lance le passage. Au lot 4, le même écran sert au lien de diffusion sans exposer le panel. | Page du panel : le candidat verrait la navigation du panel et, au lot 4, il faudrait refaire l'écran. |
| Chronomètre | `deadline_at` calculé au démarrage ; temps restant donné par le serveur, compte à rebours affiché par le navigateur et resynchronisé à chaque requête ; tout accès à un passage échu le termine (clôture paresseuse) ; commande planifiée `qcm:terminer-passages-echus` pour les passages abandonnés. | Chronomètre seulement côté navigateur : contournable (PRD UC-12). |
| Reprise après coupure | Identifiant du passage gardé dans la session Laravel ; rouvrir la page reprend le passage (Q12 : le chronomètre continue). | Lien de reprise à recopier : inutile sur un poste partagé. |
| Certificat | Vue Blade avec feuille d'impression (A4, noir et blanc) et bouton « Imprimer » ; le PDF passe par l'impression du navigateur (Q17, option a, choix provisoire, aucune dépendance nouvelle). | PDF produit par le serveur (`dompdf`, `browsershot`) : nouvelle dépendance à faire approuver ; à reprendre si Q17 retient (b) ou (c). |
| Numéro de certificat | `AAAAMMJJ-XXXXXX` (6 caractères aléatoires en base 32 de Crockford, sans I, L, O ni U), unique. | Séquentiel : devinable. ULID : trop long à dicter. |

### 6.3 Modèle de données (ajouts)

```
qcm_attempts       id, exam_id? → qcm_exams (nullOnDelete), status [in_progress|finished|expired],
                   identity (json), started_at, deadline_at?, finished_at?, auditColumns
qcm_attempt_items  id, attempt_id (cascade), position, question_id? → qcm_questions (nullOnDelete),
                   answer_order (json : identifiants des réponses dans l'ordre affiché),
                   chosen_answer_id?, bookmarked, answered_at?, auditColumns
qcm_results        id, attempt_id? → qcm_attempts (nullOnDelete), certificate_number (unique),
                   identity (json : clé, libellé, valeur), last_name, first_name, email? (index),
                   organisation? (index), identifier? (index), taken_at,
                   exam_title, questionnaire_title, questionnaire_version?,
                   module_rates (json : libellé, bonnes réponses, total, taux), global_rate, pass_rate, passed, auditColumns
```

### Étape 8 — Passages et résultats : modèle et logique

- [x] Migrations `qcm_attempts`, `qcm_attempt_items`, `qcm_results` (§6.3), `auditColumns()`.
- [x] Modèles `Attempt`, `AttemptItem`, `Result` ; énumération `AttemptStatus` ; fabriques (`finished()`, `expired()`).
- [x] Service `App\Qcm\Exams\AttemptStarter` : valide l'identité selon `identification_fields` (règles par champ, messages en français qui nomment le champ) ; vérifie le nombre maximal de passages (par e-mail s'il est demandé, sinon par nom + prénom) ; prend toutes les questions du questionnaire, dans l'ordre ou mélangées ; mélange les réponses ; calcule `deadline_at`.
- [x] Service `AttemptRecorder` : enregistrer / changer une réponse, poser / retirer un marque-page ; refus si le passage est terminé ou échu (message).
- [x] Service `AttemptFinisher` : termine le passage, calcule les taux par module (`ModuleCatalog::key()`) et global, compare au taux minimal, écrit le `Result` avec les titres et la version du moment et un numéro de certificat unique ; une question non répondue est fausse ; une question supprimée pendant le passage aussi, rangée dans « Sans module ». Sans effet sur un passage déjà terminé.
- [x] Clôture paresseuse des passages échus et commande `qcm:terminer-passages-echus` planifiée toutes les minutes.

**Tests**

- [x] Identité : champs obligatoires, e-mail et date mal formés, champ non demandé ignoré.
- [x] Composition : toutes les questions du questionnaire, ordre fixe et mélangé.
- [x] Réponses : dernière réponse retenue ; réponse après la fin refusée ; marque-pages.
- [x] Résultat : taux par module et global, réussite / échec au seuil exact ; non répondues comptées fausses ; titres et version enregistrés.
- [x] Indépendance : modifier puis supprimer questions, questionnaire et examen après le passage → `Result` inchangé.
- [x] Chronomètre : passage terminé automatiquement après l'échéance (horloge simulée), y compris par la commande.
- [x] Nombre maximal de passages atteint → démarrage refusé avec message.
- [x] `composer qa` vert ; commit.

### Étape 9 — Écran de passage, certificat et correction (UC-12, UC-13)

- [x] Mise en page publique `resources/views/qcm/layouts/examen.blade.php` (Vite, Tailwind), sans navigation du panel.
- [x] Composant Livewire `App\Qcm\Livewire\TakeExam` : accueil (titre, consignes, nombre de questions, durée, taux minimal, information sur les données personnelles) → identification → questions.
- [x] Écran question : énoncé, image, réponses ; précédent / suivant ; marque-page ; liste des questions (répondue, non répondue, marquée), cliquable ; compte à rebours avec avertissements à 5 minutes et 1 minute.
- [x] Fin : récapitulatif des non répondues et marquées, confirmation ; fin automatique à l'échéance.
- [x] Certificat (`resources/views/qcm/certificat.blade.php`) : contenu du PRD UC-12, lu dans `qcm_results` ; feuille d'impression A4 ; bouton « Imprimer ».
- [x] Correction détaillée après le certificat si l'examen la prévoit ; filtres fausses / non répondues / marquées.
- [x] Depuis le panel : « Faire passer » sur un examen (nouvel onglet) ; « Prévisualiser » (UC-20, étape 5) : même écran, identité fictive, aucun passage ni résultat enregistré.
- [x] Reprise après rechargement ou fermeture de l'onglet (session).

**Tests**

- [x] Livewire : parcours complet identification → réponses → fin → certificat ; refus de démarrer sans champ obligatoire ; navigation, marque-pages, liste des questions.
- [x] Aucune bonne réponse ni explication dans le HTML rendu avant la fin du passage.
- [x] Certificat : identité, titres, version, taux, réussite, numéro ; aucune question.
- [x] Correction détaillée présente ou absente selon l'examen.
- [x] Prévisualisation : rien d'enregistré.
- [x] Reprise : rechargement → même passage, mêmes réponses, même échéance.

**Documentation utilisateur**

- [x] `doc/users/qcm.md` : « Faire passer un examen sur ce poste », « Imprimer ou enregistrer son certificat », « Prévisualiser un examen ».
- [x] `composer qa` vert ; vérification manuelle dans le navigateur (prévisualisation complète sur ordinateur) ; commit.
- [ ] Vérification sur téléphone et aperçu d'impression : reportée à l'étape 12 (non réalisable depuis l'outil de navigation).

### Étape 10 — Liste des résultats (UC-09)

- [x] Ressource `ResultResource` « Résultats » : colonnes identité, date, examen, questionnaire et version, taux global, réussite, numéro ; détail avec les taux par module ; recherche (nom, e-mail, organisation, identifiant libre, numéro ; organisation et identifiant en colonnes de `qcm_results` pour la recherche) ; filtres (examen, période, réussite) ; résultats d'examens supprimés inclus.
- [x] Gestionnaire de relation « Résultats » dans l'examen.
- [x] Export CSV des résultats filtrés : identité, date, examen, questionnaire, version, taux par module, taux global, réussite.
- [x] Suppression d'un résultat avec confirmation (« le certificat ne pourra plus être réédité »).

**Tests**

- [x] Liste, recherche et filtres ; résultat d'un examen supprimé visible.
- [x] Export CSV : une ligne par résultat, colonnes attendues (une colonne par module rencontré), caractères accentués.
- [x] Suppression d'un résultat.

**Documentation utilisateur**

- [x] `doc/users/qcm.md` : « Consulter les résultats », « Exporter les résultats ».
- [x] `composer qa` vert ; commit.

### Étape 11 — Réédition d'un certificat (UC-19)

- [ ] Action « Rééditer le certificat » sur un résultat : vue du certificat avec la mention « Réédition du *date* ».
- [ ] Recherche directe par numéro de certificat ; numéro inconnu ou résultat supprimé → message explicite.

**Tests**

- [ ] Certificat réédité identique à l'original, à la mention de réédition près, après modification puis suppression des questions, du questionnaire et de l'examen.
- [ ] Numéro inconnu, résultat supprimé.

**Documentation utilisateur**

- [ ] `doc/users/qcm.md` : « Renvoyer un certificat à un candidat ».
- [ ] `composer qa` vert ; commit.

### Étape 12 — Clôture du lot 3

- [ ] Dérouler le critère de sortie du §6.1 dans le navigateur, sur ordinateur et téléphone, avec aperçu d'impression.
- [ ] RGPD : documenter dans `doc/users/qcm.md` comment supprimer les résultats d'une personne ; la durée de conservation attend Q20.
- [ ] Mettre à jour le statut de ce plan et du PRD QCM (§8) ; `composer qa` vert ; PR.

---

## 7. Lot 4 — Diffusion en ligne

### 7.1 Objectif et périmètre

Le formateur se connecte ; il diffuse un examen par un lien ; des candidats sans compte, sur leur
propre appareil, s'identifient et passent l'examen ; le formateur suit les résultats et renvoie les
certificats, y compris par e-mail (UC-18, compléments de UC-09 et UC-19).

### 7.2 Décisions techniques

| Sujet | Décision | Alternatives écartées |
|---|---|---|
| Connexion du formateur | Le panel `qcm` retrouve `->login()` et `authMiddleware` ; comptes du socle (`laraskel-users`), créés par l'administrateur dans `/admin`. Tout compte connecté est formateur jusqu'aux rôles de v1. `created_by` / `updated_by` se remplissent. | Mot de passe unique dans `.env` : non traçable, à jeter en v1. |
| Lien de diffusion | Colonne `share_token` (40 caractères aléatoires) sur l'examen ; route publique `/e/{token}` vers l'écran de passage du lot 3 ; régénérer le jeton invalide l'ancien lien. | Identifiant de l'examen dans l'URL : devinable (PRD UC-18). |
| Options de diffusion | Colonnes `share_open`, `share_opens_at`, `share_closes_at`, `share_end_message`, `share_emails_certificate`. Passages maximum : option existante, comptée par e-mail. | — |
| Abus | Limiteur de débit Laravel sur le démarrage d'un passage, par adresse IP (Q23, option b, choix provisoire). | Captcha : service tiers ou dépendance ; à décider (Q23). |
| E-mail | `Mailable` avec lien signé vers le certificat (pas de pièce jointe PDF tant que Q17 n'a pas retenu un PDF serveur) ; envoi en file d'attente. | — |

### Étape 13 — Connexion du formateur

- [ ] Réactiver la connexion du panel `qcm` ; garder `/` → `/qcm` ; l'écran de passage reste hors du panel.
- [ ] Tests : panel inaccessible sans connexion (ressources, exports, certificats) ; « Faire passer » du lot 3 accessible seulement depuis une session connectée ; colonnes d'audit remplies.
- [ ] README et `doc/users/qcm.md` : création du premier compte, connexion.
- [ ] `composer qa` vert ; commit.

### Étape 14 — Diffusion par lien (UC-18)

- [ ] Migration : colonnes de diffusion sur `qcm_exams`.
- [ ] Examen : section « Diffusion » (ouvrir / fermer, dates, message de fin, envoi du certificat par e-mail) ; affichage et copie du lien ; « Régénérer le lien » avec confirmation.
- [ ] Route publique `/e/{token}` : diffusion fermée, pas encore ouverte, terminée → page explicite avec les dates ; sinon écran de passage ; un passage commencé avant la fermeture peut se terminer.
- [ ] Limiteur de débit sur le démarrage ; message clair en cas de dépassement.
- [ ] Avertissement à la modification d'un examen ou de son questionnaire qui ont des passages en cours.

**Tests**

- [ ] Lien valide, inconnu, régénéré (ancien refusé) ; diffusion fermée, avant ouverture, après fermeture ; passage commencé avant la fermeture terminé.
- [ ] Par le lien, aucune page du panel, aucun autre examen, aucun résultat accessible.
- [ ] Passages maximum par e-mail ; limiteur de débit.

### Étape 15 — Certificat par e-mail et suivi (UC-18, UC-19, UC-09)

- [ ] `Mailable` du certificat (lien signé) envoyé à la fin du passage si l'option est active ; depuis la réédition, envoi à l'adresse du candidat ou à une adresse saisie.
- [ ] Liste des résultats rafraîchie automatiquement pendant une diffusion ouverte.
- [ ] Tests : `Mail::fake` (envoi, destinataire, lien) ; lien signé altéré refusé.
- [ ] `doc/users/qcm.md` : « Diffuser un examen par un lien », « Fermer la diffusion », « Envoyer un certificat par e-mail ».
- [ ] `composer qa` vert ; commit.

### Étape 16 — Clôture du lot 4

- [ ] Dérouler le critère de sortie du PRD §8 avec deux appareils (formateur, et candidat sur téléphone).
- [ ] Vérifier la tenue de 100 passages simultanés (PRD §7) ; noter le résultat ici.
- [ ] README : prérequis d'une exposition sur Internet (HTTPS, envoi d'e-mails, file d'attente, planificateur).
- [ ] Mettre à jour le statut de ce plan et du PRD QCM (§8) ; `composer qa` vert ; PR.

---

## 8. Lots suivants

Le lot 5 (compléments d'étude, dont la liste déroulante avec recherche au-delà de 6 propositions en étude, en passage et en correction, [PRD Banques](../prds/banques_prd.md) §6), v1 et v2 seront détaillés ici avant leur démarrage. Les banques et les questions passent au module Banques de questions au lot B1 ([plan Banques](banques_plan.md)), qui reprend aussi les compléments d'édition.
Le contenu des lots est décrit au PRD §8.
