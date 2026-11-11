# PRD — Module Banques de questions

| | |
|---|---|
| **Version** | 0.3 (brouillon) |
| **Date** | 11 novembre 2026 |
| **Auteur** | Frédéric |
| **Statut** | À valider — voir §10 « Questions ouvertes » |
| **PRD d'ensemble** | [Mentor](mentor_prd.md), §1.4 « Architecture modulaire » |
| **Modules utilisateurs** | [QCM](qcm_prd.md), [Leitner](leitner_prd.md) |
| **Plan** | [banques_plan.md](../plans/banques_plan.md) |

**Évolutions de la version 0.3** : **référence** facultative d'une question, stable d'un fichier à l'autre, avec laquelle l'import reconnaît les questions déjà présentes et propose de les mettre à jour (UC-02, UC-05, §8) ; **ordre des propositions** d'un QCM non mélangé, fixé à la saisie et dans le fichier d'échange (§6, §8.2) ; lot B1.1 (§9.2).

**Évolutions de la version 0.2** : affichage des propositions d'un QCM en liste déroulante avec recherche au-delà de 6 propositions (§6, règles communes).

---

## 1. Vision

Le module Banques de questions gère, **une seule fois pour toute l'application**, les banques et leurs questions : saisie, types de questions, illustrations, difficulté, thème, rangement, import et export. Les modules qui posent des questions s'appuient dessus sans gérer de banques eux-mêmes :

- le module **QCM** compose avec elles ses études, questionnaires et examens ;
- le module **Leitner** en fait des suites de révision.

Une même banque sert donc à la fois à un examen blanc QCM et à une suite Leitner. **Toute question vit dans une banque** ; une **banque par défaut** recueille celles qui sont créées sans banque choisie, et une question peut être déplacée d'une banque à l'autre.

Le module ne dépend d'aucun autre module. Il est utilisable seul pour constituer et échanger des banques, mais n'a d'intérêt qu'avec un module qui pose les questions.

### 1.1 Objectifs

- O1 — Saisir et maintenir une question une seule fois, quel que soit l'usage qui en est fait.
- O2 — Couvrir des formes de questions variées (§6) : choix, vrai / faux, valeurs, listes, associations, questions ouvertes, illustrations.
- O3 — Échanger des banques entre instances et avec des tableurs, dans un format unique (§8).
- O4 — Ranger les questions : banques, thèmes, difficulté, déplacement d'une banque à l'autre.

### 1.2 Hors périmètre

- L'usage des questions : étude, questionnaires, examens et résultats relèvent du [module QCM](qcm_prd.md) ; suites, séances et progression du [module Leitner](leitner_prd.md).
- Les statistiques d'usage d'une question (taux de réussite, contestations) : elles sont tenues par les modules qui posent les questions.

---

## 2. Acteurs

| Acteur | Description |
|---|---|
| **Formateur** | Crée et maintient les banques et leurs questions ; importe et exporte ; traite les signalements. |
| **Étudiant** | (À partir de v1.) Crée ses banques personnelles et leurs questions ; signale une question. |
| **Administrateur** | Gère les comptes et les rôles (socle Laraskel). |

Comme dans les modules QCM et Leitner, les premiers lots fonctionnent **sans connexion** : un utilisateur unique tient tous les rôles.

---

## 3. Glossaire

- **Banque de questions** : ensemble nommé de questions, avec une description. Plus tard, une banque est **prédéfinie** (créée par un formateur, en lecture seule pour l'étudiant) ou **personnelle** (créée par un étudiant, visible de lui seul) (v1).
- **Banque par défaut** : banque qui existe toujours et reçoit les questions créées sans banque choisie, par exemple depuis un questionnaire QCM. Elle ne peut pas être supprimée.
- **Question** : élément posé à l'étudiant. Elle appartient à **exactement une banque** et comporte :
  - un **type** (§6), qui fixe la forme de la réponse et la manière de la corriger ;
  - un **énoncé**, avec une **illustration facultative** (image, carte, schéma) et sa description ;
  - la **réponse attendue**, sous la forme propre à son type ;
  - une **explication facultative**, affichée après la réponse ;
  - un **niveau de difficulté** facultatif (facile, moyen, difficile ; sans niveau, la question compte comme moyenne) ;
  - un **thème** facultatif, en texte libre (appelé *module* dans les premières versions du module QCM) ;
  - une **référence** facultative, unique dans sa banque (`N1-13`, `meteo-042`) : l'identifiant que l'auteur donne à la question pour la retrouver d'un fichier d'échange à l'autre, même quand son énoncé change. Elle n'est pas montrée à l'étudiant.
- **Utilisation d'une question** : présence de la question dans un questionnaire QCM ou dans la progression d'une suite Leitner. Une question utilisée reste la même question : la modifier change ce que voient tous les modules.
- **Fichier d'échange** : fichier CSV, ou archive ZIP, qui contient une banque (§8).

---

## 4. Cas d'utilisation — vue d'ensemble

| ID | Cas d'utilisation | Acteur principal | Lot |
|---|---|---|---|
| UC-01 | Gérer les banques | Formateur | B1 (duplication B2) |
| UC-02 | Gérer les questions | Formateur | B1 (compléments B2) |
| UC-03 | Déplacer ou copier des questions | Formateur | B1 |
| UC-04 | Exporter une banque | Formateur | B1 |
| UC-05 | Importer des questions dans une banque | Formateur | B1 |
| UC-06 | Signaler une question | Étudiant | v1 |
| UC-07 | Donner accès aux banques | Formateur | v1 |

---

## 5. Cas d'utilisation détaillés

#### UC-01 — Gérer les banques

- **Acteur** : Formateur.
- **Scénario** : créer une banque (nom, description), la renommer, la dupliquer, la supprimer. La liste des banques affiche pour chacune le nombre de questions, au total, par type, par difficulté et par thème, et les usages : questionnaires QCM et suites Leitner qui en prennent des questions.
- **Règles**
  - Le nom d'une banque est unique ; un nom déjà pris est refusé avec un message qui le dit.
  - La **banque par défaut** peut être renommée mais pas supprimée.
  - La suppression d'une banque demande une confirmation qui annonce ce qui sera perdu : nombre de questions, questionnaires qui perdront des questions, suites dont des questions sortiront de la progression. Les résultats d'examen déjà enregistrés ne changent pas.
  - Une duplication est une nouvelle banque indépendante, images comprises.

#### UC-02 — Gérer les questions

- **Acteur** : Formateur.
- **Scénario nominal**
  1. Dans une banque, le formateur choisit le type de question (§6).
  2. Il saisit l'énoncé, l'illustration facultative et sa description, la réponse attendue sous la forme propre au type, l'explication facultative, la difficulté et le thème.
  3. Il prévisualise la question telle qu'elle sera posée, y compris sa correction.
  4. Il l'enregistre ; la question est aussitôt utilisable par tous les modules.
- **Variantes**
  - 1a. **Modifier** une question. Si elle est utilisée par une suite Leitner, le système demande si la modification est **mineure** (correction de forme : les suites ne changent rien) ou **de fond** (les suites la remettent en boîte 1, voir [PRD Leitner](leitner_prd.md), UC-02).
  - 1b. **Supprimer** une question, après une confirmation qui annonce ses utilisations (questionnaires, suites).
  - 1c. Rechercher et filtrer les questions : texte, type, difficulté, thème, présence d'une illustration.
  - 1d. Agir sur une sélection : changer de thème ou de difficulté, déplacer (UC-03), supprimer.
  - 1e. Créer une question **hors banque**, depuis un module (questionnaire QCM, suite Leitner) : elle va dans la banque proposée par le module, ou à défaut dans la banque par défaut, et le message de confirmation dit laquelle.
- **Règles**
  - Le type ne change plus après la création ; pour un autre type, on crée une nouvelle question.
  - Le système vérifie la cohérence propre au type avant l'enregistrement (§6) ; le message dit quel champ corriger.
  - Une illustration a une description obligatoire (accessibilité). Une question sans illustration n'affiche rien à sa place.
  - Le système signale les doublons probables (même énoncé dans la même banque) à la saisie et à l'import.
  - Saisie du thème : texte libre, avec proposition des thèmes déjà utilisés dans la banque.
  - La référence est unique dans la banque ; une référence déjà prise est refusée, avec la question qui la porte. Elle peut être modifiée ou effacée.

#### UC-03 — Déplacer ou copier des questions

- **Acteur** : Formateur.
- **Scénario** : le formateur sélectionne une ou plusieurs questions, choisit « Déplacer vers » ou « Copier vers », puis la banque cible. Cas typique : ranger dans une banque thématique les questions créées dans la banque par défaut.
- **Règles**
  - Un **déplacement** conserve la question et toutes ses utilisations : un questionnaire QCM continue de la poser. Une suite Leitner dont la banque cible n'est pas une source perd la question (et sa progression) ; la confirmation annonce les suites concernées.
  - Une **copie** est une nouvelle question indépendante, image comprise, sans aucune utilisation.
  - Le thème est conservé ; s'il n'existait pas dans la banque cible, il s'ajoute à ses thèmes.
  - La référence est conservée, sauf si une question de la banque cible la porte déjà : la question déplacée ou copiée perd alors sa référence, et le message de fin le dit.
  - Le message de fin donne le nombre de questions déplacées ou copiées.

#### UC-04 — Exporter une banque

- **Acteur** : Formateur.
- **Scénario** : export d'une banque entière ou d'une sélection de questions, en CSV seul ou en ZIP avec les illustrations et les paramètres de la banque (§8).

#### UC-05 — Importer des questions dans une banque

- **Acteur** : Formateur.
- **Scénario nominal**
  1. Le formateur choisit une banque cible, ou demande la création d'une nouvelle banque, et téléverse un fichier d'échange CSV ou ZIP.
  2. Le système présente le **rapport avant import** (§8.5).
  3. Le formateur choisit le traitement des **questions déjà présentes**, reconnues par leur référence (les mettre à jour, ou les laisser telles quelles et ignorer ces lignes), puis celui des doublons probables (ignorer, importer quand même), et confirme.
  4. Le système importe les questions valides et affiche le rapport final : questions créées, mises à jour, lignes ignorées.
- **Variantes**
  - 1a. Nouvelle banque : nom et description repris du ZIP, ou à défaut du nom du fichier, modifiables avant confirmation.
  - 2a. Fichier illisible : rejet avec un message qui dit ce qui est attendu et renvoie à la documentation du format.
  - 3a. Le formateur annule : rien n'est importé.
- **Règles**
  - Une ligne dont la référence est celle d'une question de la banque cible désigne **cette question** : elle n'est jamais un doublon et ne crée pas de nouvelle question. Les lignes sans référence, ou de référence inconnue dans la banque, sont de nouvelles questions.
  - **Mise à jour** : la question prend l'énoncé, la réponse attendue, l'explication, la difficulté, le thème et la description de l'illustration de la ligne ; une cellule `illustration` remplie remplace l'image, une cellule vide garde l'image actuelle (un CSV exporté sans les images se réimporte sans les perdre). La question garde ses utilisations.
  - Le type d'une question ne change pas (UC-02) : une ligne dont la référence désigne une question d'un autre type est en erreur.
  - Une mise à jour qui change la **réponse attendue** est une modification **de fond** : la question revient en boîte 1 dans les suites Leitner ([PRD Leitner](leitner_prd.md), UC-02). Les autres sont mineures. Le rapport avant import annonce, pour chaque question mise à jour, si sa réponse attendue change ; le rapport final donne le nombre de suites touchées.
  - Une ligne mise à jour sans aucune différence est comptée comme inchangée.
  - Deux lignes du fichier qui portent la même référence : la seconde est en erreur.
  - L'import d'un questionnaire du module QCM ([PRD QCM](qcm_prd.md), UC-17) crée toujours de nouvelles questions : une référence déjà portée par une question de la banque d'arrivée n'est pas reprise, et le rapport final le dit.

#### UC-06 — Signaler une question

- **Acteur** : Étudiant (v1).
- **Scénario** : depuis un module (étude ou correction QCM, séance Leitner), l'étudiant signale une question qu'il pense erronée ou ambiguë, avec un commentaire. Le formateur voit les signalements dans la banque, corrige la question ou classe le signalement ; l'étudiant est informé de la suite donnée.
- **Règle** : le module QCM n'autorise pas le signalement pendant un passage d'examen en cours (pas d'indice), seulement depuis la correction.

#### UC-07 — Donner accès aux banques

- **Acteur** : Formateur (v1).
- **Scénario** : le formateur ouvre une banque prédéfinie à des étudiants ; ils peuvent alors l'utiliser dans les modules (étude QCM, suites Leitner). Le retrait de l'accès est annoncé aux étudiants concernés ; leurs résultats et leur historique restent consultables.

---

## 6. Types de questions

Pour chaque type : ce que contient la question, comment l'étudiant répond, comment le verdict est rendu. Le type est choisi à la création de la question et ne change plus. La façon dont chaque module utilise les types est décrite au §7.

| Type | Contenu de la question | Réponse de l'étudiant | Verdict | Lot (§9.2) |
|---|---|---|---|---|
| **QCM** | Une bonne réponse, plusieurs mauvaises avec explication facultative ; mélange des propositions ou ordre fixe | Choisit une proposition | Automatique | B1 (ordre fixe : B1.1) |
| **Vrai / faux** | Une affirmation et sa valeur de vérité | Vrai ou Faux | Automatique | B1 |
| **Auto-évaluation** (recto / verso) | Une question et une réponse de référence | Réfléchit (ou écrit pour lui-même), puis « Voir la réponse » | L'étudiant : « Je savais » / « Je ne savais pas » | B1 |
| **Valeur numérique** | La valeur attendue, l'unité facultative, une tolérance (absolue ou en %, ou aucune) | Saisit un nombre | Automatique : juste si l'écart est dans la tolérance | B1 |
| **Énumération** | La liste des éléments acceptables, le nombre demandé (ex. « 8 capitales européennes » parmi 44), ordre imposé ou non, variantes acceptées par élément | Saisit des éléments | Automatique : chaque élément est reconnu ou non ; les doublons ne comptent pas | B2 |
| **Correspondance** | Des paires (capitale ↔ pays), avec éventuellement des leurres d'un côté ; nombre de paires présentées à chaque fois (tirées au hasard si la liste est longue) | Associe chaque élément de gauche à un élément de droite | Automatique, paire par paire | B2 |
| **Question ouverte corrigée par l'IA** | Une question, une réponse de référence, des critères facultatifs (éléments obligatoires, tolérances, réponses acceptables) | Saisit un texte libre | Agent IA : verdict et justification courte | B3 |
| **Réponse courte** | Une réponse attendue et ses variantes acceptées | Saisit quelques mots | Automatique, avec tolérance ([PRD Leitner](leitner_prd.md), Q10) | B2 |
| **Texte à trous** | Un texte dont certains mots sont masqués | Complète les trous | Automatique, trou par trou | v2 |
| **Classement** | Des éléments et leur ordre attendu (chronologie, étapes d'une procédure) | Remet les éléments dans l'ordre | Automatique | v2 |
| **Désignation sur image** | Une image et une ou plusieurs zones attendues | Clique sur l'image | Automatique : juste si le clic est dans la zone | v2 |

**Règles communes**

- Le verdict d'une énumération, d'une correspondance, d'un texte à trous ou d'un classement est *juste* si tout est juste. Le détail (éléments reconnus, paires justes) est toujours affiché. Le traitement d'une réponse **partiellement juste** est à trancher ([PRD Leitner](leitner_prd.md), Q9).
- Les réponses saisies sont comparées sans tenir compte des majuscules, des accents ni des espaces en trop ; la tolérance aux fautes de frappe est à trancher ([PRD Leitner](leitner_prd.md), Q10).
- La correction par l'IA juge le **fond** (notion, valeurs, unités), pas la formulation ni l'orthographe. Elle ne voit que la question, la réponse de référence, les critères et la réponse de l'étudiant.
- Une question d'auto-évaluation de type « Vous rappelez-vous comment démontrer le théorème de Pythagore ? » est la forme simple de la question ouverte : l'étudiant juge seul, sans IA.
- Toute question peut porter une illustration, dans l'énoncé (« Quel est ce monument ? ») ou dans les propositions (QCM, correspondance) *(lot B2)*.
- **Affichage des propositions d'un QCM** : jusqu'à **6 propositions** (bonne réponse comprise), elles sont affichées toutes ensemble, sous forme de boutons ou de cases à choisir ; à partir de **7**, elles sont présentées dans une **liste déroulante avec recherche**, pour qu'une longue liste (« Quelle est la capitale de… ? » parmi 30 capitales) reste utilisable. Le mélange des réponses s'applique de la même façon. Dans une correction, une question en liste déroulante n'affiche que la réponse choisie, la bonne réponse et l'explication de la mauvaise réponse choisie, pas la liste complète. Cette règle vaut pour tous les modules qui posent des QCM (étude et passage QCM, séance Leitner).
- **Ordre des propositions d'un QCM** : par défaut, les propositions sont mélangées à chaque affichage. Sans mélange, elles sont toujours affichées dans l'ordre fixé par le formateur : les mauvaises réponses dans leur ordre de saisie, la bonne réponse à la place qu'il choisit (1re par défaut). Un ordre utile (« A · B · Partage », les dix combinaisons du poker dans l'ordre du classement) devient ainsi un repère pour l'étudiant. Cette règle vaut pour tous les modules.

---

## 7. Utilisation des types par les modules

| Type | Module QCM | Module Leitner |
|---|---|---|
| QCM | Oui | Oui |
| Vrai / faux | Oui, comme un **QCM à deux réponses** « Vrai » et « Faux » ; l'explication de la question tient lieu d'explication de la mauvaise réponse | Oui |
| Autres types | Non | Oui |

- Quand le module QCM utilise une banque (étude, tirage d'un questionnaire, ajout de questions, import d'un questionnaire), il **ignore les questions d'autres types** et l'annonce par un avertissement : « 12 questions n'étaient pas de type QCM ou vrai / faux et n'ont pas été utilisées. » Une banque qui ne contient aucune question utilisable par le module QCM y est proposée grisée, avec ce motif.
- Le module Leitner utilise tous les types qu'il sait poser (§6, colonne « Lot » et lots du [PRD Leitner](leitner_prd.md)).

---

## 8. Fichier d'échange

Les banques s'importent et s'exportent (UC-04, UC-05) dans un format unique, quel que soit le module qui utilisera leurs questions. Il **étend le format historique du module QCM** : un fichier de banque produit par le module QCM est un fichier valide, dont toutes les questions sont de type QCM. Le format est décrit dans la documentation utilisateur, avec un fichier exemple par type de question et une banque de capitales (énumération et correspondance).

### 8.1 Fichier CSV

- Une ligne d'en-tête, puis **une ligne par question**. Encodage UTF-8 (BOM toléré), séparateur `;` ou `,`, champs entre guillemets quand ils contiennent le séparateur ou un retour à la ligne.
- L'en-tête tolère majuscules, accents et espaces (« Bonne réponse » vaut `bonne_reponse`). Une colonne inconnue est signalée et ignorée.
- L'ordre des colonnes est libre. Une colonne qui ne concerne pas le type d'une ligne est ignorée pour cette ligne ; si elle est remplie, le rapport le signale.

**Colonnes communes**

| Colonne | Obligatoire | Contenu |
|---|---|---|
| `type` | non | Type de la question (§8.2). Colonne absente ou cellule vide : `qcm`. |
| `enonce` | oui | Énoncé de la question. |
| `explication` | non | Explication affichée après la réponse. |
| `theme` | non | Thème, en texte libre. La colonne `module` des anciens fichiers QCM est acceptée à sa place ; si les deux sont présentes, `theme` l'emporte et le rapport le signale. |
| `difficulte` | non | `1`, `2` ou `3`, ou `facile`, `moyen`, `difficile`. Vide : non précisée (comptée comme moyenne). Autre valeur : question importée sans difficulté, signalée au rapport. |
| `illustration` | non | Nom du fichier image, rangé dans `images/` de l'archive ZIP. Vide : la question n'a pas d'image, rien n'est affiché à sa place. |
| `reference` | non | Référence de la question (§3, UC-05), unique dans le fichier. Vide : question sans référence. |
| `texte_alternatif` | si `illustration` | Description de l'image, ignorée sans illustration. Absente alors qu'il y a une illustration, elle est remplacée par « Illustration : » suivi du début de l'énoncé, et le rapport liste les questions à compléter. |

### 8.2 Colonnes propres à chaque type

| `type` | Colonnes | Règles |
|---|---|---|
| `qcm` | `bonne_reponse` (oblig.) ; `mauvaise_reponse_1`, `mauvaise_reponse_2`… (au moins la 1re) ; `explication_1`, `explication_2`… ; `melange` ; `ordre` | Comme le module QCM. `explication_N` explique la mauvaise réponse de même numéro. `melange` : mélanger les réponses à l'affichage, `oui` par défaut. `ordre` : liste (§8.3) de toutes les propositions dans l'ordre d'affichage, chacune écrite comme dans sa colonne ; ignorée (et signalée) si `melange` vaut `oui`. Sans `ordre`, la bonne réponse vient en premier, puis les mauvaises dans l'ordre de leurs numéros. |
| `vrai_faux` | `reponse` (oblig.) | `vrai` ou `faux`. |
| `auto_evaluation` | `reponse` (oblig.) | Réponse de référence, affichée quand l'étudiant demande à la voir. |
| `valeur_numerique` | `valeur` (oblig.) ; `type_tolerance` ; `tolerance` ; `unite` | `type_tolerance` : `aucune` (par défaut), `absolue` ou `pourcentage`. `tolerance` : nombre ≥ 0, obligatoire si le type de tolérance n'est pas `aucune`, ignoré sinon (signalé). `unite` : texte facultatif. |
| `reponse_courte` | `reponse` (oblig.) ; `variantes` | `variantes` : liste des autres réponses acceptées. |
| `enumeration` | `elements` (oblig.) ; `nombre_demande` ; `ordre_impose` | `elements` : liste des éléments acceptables, chacun avec ses variantes séparées par `=` (`Bruxelles=Brussel`). `nombre_demande` : entier de 1 au nombre d'éléments, tous par défaut. `ordre_impose` : `non` par défaut. |
| `correspondance` | `paires` (oblig.) ; `leurres` ; `paires_presentees` | `paires` : liste d'au moins deux paires `gauche=droite` (`Paris=France`). `leurres` : liste de propositions de droite sans paire. `paires_presentees` : entier de 2 au nombre de paires, toutes par défaut. |
| `question_ouverte` | `reponse` (oblig.) ; `criteres` | Réponse de référence et critères de correction, en texte libre (lot B3). |

Les types texte à trous, classement et désignation sur image (v2) compléteront ce tableau quand ils seront définis.

### 8.3 Valeurs dans une cellule

- **Liste** : éléments séparés par `|`, espaces de bord retirés, éléments vides ignorés (`Paris=France | Berlin=Allemagne`).
- **Caractères réservés** : dans une liste, `\|` et `\=` désignent les caractères `|` et `=` eux-mêmes, `\\` la barre oblique inverse.
- **Oui / non** : `oui` ou `non`, aussi `vrai` / `faux` et `1` / `0`, sans tenir compte des majuscules.
- **Nombre** : virgule ou point décimal, espaces entre les milliers ignorés (`4 806`, `1,225`, `1.225`).
- **Type** : sans tenir compte des majuscules ni des accents ; une espace ou un tiret vaut un tiret bas (`Vrai faux` vaut `vrai_faux`).

### 8.4 Archive ZIP

À la racine : `questions.csv`, le répertoire `images/` et le fichier de paramètres `banque.json` : `{"format_version": 1, "nom": "…", "description": "…"}`, identique à celui du module QCM. Une image doit être un fichier image lisible d'au plus 2 Mo (Q2) ; sinon la question est importée sans image et le rapport le signale. Un fichier de questionnaire ou d'examen exporté par le module QCM s'importe aussi dans une banque : seules ses questions sont reprises, le rapport signale les paramètres ignorés.

### 8.5 Import et export

- **Rapport avant import** (UC-05) : questions valides par type ; questions déjà présentes, reconnues par leur référence, avec pour chacune « inchangée », « modifiée » ou « réponse attendue modifiée » ; lignes en erreur avec leur numéro, la colonne en cause et ce qui est attendu (« ligne 12, colonne `type_tolerance` : valeurs admises `aucune`, `absolue`, `pourcentage` ») ; doublons probables ; thèmes nouveaux ; images absentes ; colonnes ignorées.
- **Erreurs propres à `ordre`** : une proposition absente de la liste, une proposition inconnue ou répétée ; le message cite la proposition en cause.
- Une ligne en erreur n'empêche pas l'import des autres ; aucune question n'est importée à moitié.
- **Export** : séparateur `;`, virgule décimale, `oui` / `non`, colonnes communes puis colonnes des types présents dans la banque, mauvaises réponses aussi nombreuses que nécessaire. Une banque qui ne contient que des QCM s'exporte avec les colonnes historiques du module QCM, plus `type` et `difficulte`.
- L'export écrit la `reference` des questions qui en ont une, et l'`ordre` des QCM non mélangés dont la bonne réponse n'est pas en premier.
- Un fichier exporté se **réimporte sans modification** et redonne les mêmes questions (aux identifiants internes près). Réimporté dans sa banque d'origine, il reconnaît les questions qui ont une référence et les met à jour (UC-05).
- Les imports et exports se font **au niveau des banques** : ni les questionnaires du module QCM ni les suites du module Leitner n'y figurent, ni les résultats ou la progression des étudiants (leur export relève des droits RGPD, §9.1).

### 8.6 Exemple

```
type;enonce;theme;difficulte;reponse;variantes;valeur;type_tolerance;tolerance;unite;elements;nombre_demande;paires;paires_presentees;bonne_reponse;mauvaise_reponse_1
qcm;Capitale de la Suisse ?;Europe;1;;;;;;;;;;;Berne;Zurich
vrai_faux;Le Danube traverse Vienne.;Europe;1;vrai;;;;;;;;;;;
auto_evaluation;Comment démontrer le théorème de Pythagore ?;Géométrie;2;Par les aires de quatre triangles rectangles.;;;;;;;;;;;
valeur_numerique;Altitude du mont Blanc ?;Relief;2;;;4806;absolue;10;m;;;;;;
reponse_courte;Capitale de la Belgique ?;Europe;1;Bruxelles;Brussel|Brüssel;;;;;;;;;;
enumeration;Citez trois capitales scandinaves.;Europe;2;;;;;;;Oslo|Stockholm|Copenhague=København;3;;;;
correspondance;Associez chaque capitale à son pays.;Europe;2;;;;;;;;;Paris=France|Berlin=Allemagne|Rome=Italie|Madrid=Espagne;3;;
```

---

## 9. Exigences non fonctionnelles et découpage

### 9.1 Exigences non fonctionnelles

- **Indépendance** : le module ne dépend d'aucun autre ; les modules QCM et Leitner n'utilisent que son interface publique, jamais son stockage.
- **Intégrité** : supprimer ou modifier une question ne change aucun résultat d'examen déjà enregistré (le module QCM conserve ce qu'il a besoin d'afficher).
- **Sans connexion** jusqu'aux lots v1, comme les modules QCM et Leitner.
- **Performances** : import de 1 000 questions en moins d'une minute ; banque de 5 000 questions sans ralentissement perceptible.
- **Accessibilité** : description obligatoire des illustrations ; formulaires utilisables au clavier.
- **RGPD** (v1) : les banques personnelles d'un étudiant font partie de ses données, exportables et supprimables sur demande.
- **Langue** : français, interface traduisible.

### 9.2 Découpage en lots

| Lot | Contenu | Critère de sortie |
|---|---|---|
| **B1 — Banques communes** | Module Banques : UC-01 (sans duplication), UC-02, UC-03, UC-04, UC-05 ; banque par défaut ; types QCM, vrai / faux, auto-évaluation, valeur numérique ; format d'échange (§8) pour ces types ; **reprise des banques existantes** des modules QCM et Leitner ; modules QCM et Leitner branchés sur les banques communes, avec l'avertissement du §7 | Une banque créée une seule fois sert à une étude QCM, à un questionnaire et à une suite Leitner ; les fichiers d'export QCM existants se réimportent ; les données existantes des deux modules sont conservées |
| **B1.1 — Référence et ordre** | Référence des questions (saisie, déplacement, export, import avec mise à jour des questions déjà présentes) ; ordre fixe des propositions d'un QCM (saisie, affichage dans tous les modules, colonne `ordre`) | Une banque exportée, corrigée dans un tableur puis réimportée met à jour ses questions sans perdre la progression des suites Leitner ; un QCM non mélangé s'affiche dans l'ordre du fichier |
| **B2 — Types complémentaires** | Types réponse courte, énumération, correspondance ; illustrations dans les propositions ; UC-01 duplication et décomptes ; UC-02 prévisualisation, recherche, actions groupées, doublons à la saisie | Une banque de capitales importée contient des énumérations et des correspondances utilisables dans une suite Leitner |
| **B3 — Questions ouvertes** | Type question ouverte corrigée par l'IA (interface LLM du socle Laraskel) | Une question ouverte est corrigée par l'IA dans une séance Leitner |
| **v1** | Banques prédéfinies et personnelles ; UC-06 signalements ; UC-07 accès | Un formateur ouvre une banque à des étudiants, qui la complètent de leurs propres banques |
| **v2** | Texte à trous, classement, désignation sur image ; autres médias ; import d'autres formats ([PRD Leitner](leitner_prd.md), Q16) | À définir |

---

## 10. Questions ouvertes

- Q1. La banque par défaut est-elle unique pour toute l'application, ou une par formateur (puis par étudiant en v1) ?
- Q2. Formats d'image acceptés et taille maximale (aujourd'hui 2 Mo, tout format d'image lisible par le navigateur) ? Rejoint la Q8 du [PRD QCM](qcm_prd.md) et la Q18 du [PRD Leitner](leitner_prd.md).
- Q3. Statut brouillon / publié des questions (reporté par le module QCM, Q9) : relève-t-il désormais de ce module ?
- Q4. Faut-il un historique des modifications d'une question (qui, quand, quoi), utile quand plusieurs modules et plusieurs formateurs la partagent ?

Les questions sur la difficulté (trois niveaux), la tolérance des réponses saisies, les réponses partielles et la génération de questions par l'IA restent suivies dans le [PRD Leitner](leitner_prd.md) (Q9, Q10, Q13, Q17), qui les a ouvertes.
