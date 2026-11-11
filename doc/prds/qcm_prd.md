# PRD — Module QCM

| | |
|---|---|
| **Version** | 0.5 (brouillon) |
| **Date** | 8 novembre 2026 |
| **Auteur** | Frédéric |
| **Statut** | À valider — voir §9 « Questions ouvertes » |
| **PRD d'ensemble** | [Mentor](mentor_prd.md), §1.4 « Architecture modulaire » |
| **Module utilisé** | [Banques de questions](banques_prd.md) |
| **Plan** | [qcm_plan.md](../plans/qcm_plan.md) |

**Évolutions de la version 0.5** : au-delà de 6 propositions, les réponses d'une question sont présentées dans une liste déroulante avec recherche, en étude, en passage et en correction ([PRD Banques](banques_prd.md), §6 ; lot 5).

**Évolutions de la version 0.4** : les banques et les questions sont gérées par le module [Banques de questions](banques_prd.md), partagé avec le module Leitner ; toute question vit dans une banque (plus de question propre à un questionnaire : elle va dans une banque, par défaut la banque par défaut) ; le module QCM utilise les questions QCM et les vrai / faux, posés comme des QCM à deux réponses, et ignore les autres types avec un avertissement (§6) ; le fichier d'échange des questions est celui du module Banques.

**Évolutions de la version 0.3** : un **questionnaire** est une liste de questions, dont certaines n'appartiennent à aucune banque ; un **examen** utilise un questionnaire et fixe la durée, le taux de réussite minimal et les options de passage ; import / export des questionnaires et des examens (UC-16, UC-17) dans un format d'échange commun (§6.1) ; identification du candidat, certificat de résultat imprimable et réédition (UC-12, UC-19) ; collecte des passages par lien, à la manière d'un formulaire en ligne (UC-18) ; nouveau découpage en lots (§8).

---

## 1. Vision

Le module QCM fait travailler et évaluer sur des **questions à choix multiples** rangées dans les banques du module [Banques de questions](banques_prd.md), qu'il partage avec le module Leitner. Il gère des **questionnaires**, listes de questions prises dans les banques. Il propose deux usages : l'**étude**, où l'étudiant voit immédiatement s'il a bien répondu, et l'**examen**, où un candidat identifié répond à un questionnaire dans un temps imparti, sans correction avant la fin.

Chaque passage d'examen produit un **certificat de résultat imprimable**. Les résultats sont enregistrés, ce qui permet de **rééditer** un certificat plus tard, pour le renvoyer à qui le demande. Un examen peut être **diffusé par un lien** pour collecter les passages de candidats sans compte. Banques, questionnaires et examens s'**importent** et s'**exportent** dans un même format de fichier.

Le module est utilisable sans les autres modules de Mentor, à l'exception du module Banques de questions dont il dépend. Intégré à Mentor, il fournit les évaluations de fin de chapitre, les examens blancs et les questions des cartes Leitner (voir [PRD Mentor](mentor_prd.md), UC-11 à UC-13, UC-19, UC-20).

Premier cas d'application : la préparation à l'examen théorique des élèves pilotes (planeur, avion, ULM), dont les questions sont organisées par module (météorologie, réglementation, etc.).

### 1.1 Objectifs

- O1 — Permettre au formateur de constituer et maintenir rapidement des banques de questions et des questionnaires, y compris par import de fichiers existants, et de les échanger avec d'autres instances.
- O2 — Permettre à l'étudiant de s'entraîner librement sur les modules de son choix et de mesurer sa progression.
- O3 — Permettre de passer des examens blancs dans des conditions proches de l'examen réel (durée, nombre de questions, taux de réussite minimal).
- O4 — Permettre au formateur de faire passer un examen à des candidats identifiés, d'en conserver les résultats et de leur fournir, à tout moment, un certificat de résultat.
- O5 — Donner au formateur une vue des résultats de ses étudiants et de la qualité de ses questions.

### 1.2 Hors périmètre

- Questions à plusieurs bonnes réponses, réponses ouvertes, questions d'appariement ou de classement (voir Q1).
- Médias autres que l'image (son, vidéo) : prévus dans un lot ultérieur.
- Surveillance d'examen (webcam, verrouillage du navigateur).
- Délivrance de résultats officiels : un certificat de résultat atteste d'un passage dans l'application, il n'a pas de valeur réglementaire.
- Répétition espacée : relève du module Leitner, qui réutilise les questions QCM.
- Temps de réponse limité en mode étude : l'entraînement chronométré se fait dans le module Leitner, qui utilise les mêmes banques ([PRD Leitner](leitner_prd.md), §7.7).

### 1.3 Périmètre du MVP et des lots sans connexion

Le MVP se limite à l'**édition des questions d'une banque** et à l'**étude libre** (UC-03, UC-04 et UC-11, chacun en version réduite, détail dans le [plan](../plans/qcm_plan.md) §2). Il vise un usage immédiat, sans administration :

- **pas de connexion ni de rôles** : un utilisateur unique gère les banques, les questions, les questionnaires et les examens, et s'entraîne en mode étude ;
- **pas de statut brouillon / publié** : toute question enregistrée est utilisable ; le statut est reporté à un lot ultérieur ;
- **pas d'appartenance des banques, des questionnaires ni des examens** : la notion de propriétaire, et la visibilité qu'en ont les autres formateurs, sont reportées.

Les lots 2 et 3 restent **sans connexion** : au lot 3, les candidats passent les examens sur le poste où l'application est ouverte (poste partagé, salle de formation) en s'identifiant. La **connexion du formateur** arrive au lot 4, avec la diffusion par lien ; les comptes étudiants et les rôles arrivent au lot v1 (§8). Les cas d'utilisation sont rédigés avec les rôles cibles ; avant le lot 4, l'utilisateur unique tient tous les rôles sauf celui de candidat.

---

## 2. Acteurs

| Acteur | Description |
|---|---|
| **Administrateur** | Gère les comptes et les inscriptions, ouvre ou ferme l'inscription libre, attribue les rôles. Il peut tout faire, y compris les actions du formateur. |
| **Formateur** | Gère les banques, les questions, les questionnaires et les examens ; diffuse les examens ; consulte les résultats ; réédite les certificats. |
| **Étudiant** | (À partir de v1.) Titulaire d'un compte. Accède aux banques et aux examens qui lui sont ouverts ; s'entraîne en mode étude, passe des examens et consulte ses résultats. |
| **Candidat** | Personne qui passe un examen en s'identifiant, sans compte : sur le poste où l'application est ouverte (lot 3) ou par un lien de diffusion (lot 4). Un étudiant connecté est un candidat dont l'identité est reprise de son compte. |

Un même compte peut cumuler plusieurs rôles (par exemple formateur et étudiant).

Dans le MVP et les lots 2 et 3, il n'y a ni compte ni rôle : un **utilisateur unique**, sans connexion, fait tout (§1.3).

---

## 3. Glossaire

- **Banque de questions**, **question**, **thème**, **banque par défaut** : voir le [PRD Banques de questions](banques_prd.md), §3. Toute question appartient à exactement une banque.
- **Question QCM** : question de type QCM, avec :
  - un **énoncé** ;
  - une **bonne réponse** ;
  - plusieurs **mauvaises réponses**, habituellement trois, chacune avec une **explication facultative** de ce qui la rend fausse ;
  - une **explication générale facultative** de la bonne réponse ;
  - une **illustration facultative** (image, schéma, carte…) sur laquelle la question peut porter ; sans illustration, rien n'est affiché à sa place ;
  - un **module** facultatif, le *thème* de la question dans le module Banques, qui sert à organiser les séances d'étude et à calculer les taux par module.
- **Questions utilisables** : le module QCM pose les questions de type QCM et les **vrai / faux**, présentés comme des QCM à deux réponses « Vrai » et « Faux ». Il ignore les questions des autres types (§6).
- **Module** : thème d'une question, saisi en texte libre. La liste des modules d'une banque (ou d'un questionnaire) est l'ensemble des noms de module utilisés par ses questions. Une question sans module est rangée dans « Sans module ».
- **Questionnaire** : liste ordonnée de questions prises dans les banques, avec un titre, une description facultative et une **version** facultative (Q25). Un questionnaire peut être composé à la main ou **généré par tirage** au hasard dans des banques (UC-08).
- **Examen** : épreuve définie par le formateur, qui **utilise un questionnaire** et en pose **toutes les questions**, et fixe : les consignes, la durée maximale (facultative), le **taux de réussite minimal**, l'affichage ou non d'une **correction détaillée** à la fin du passage, les champs d'identification demandés au candidat et le nombre maximal de passages. Un **examen blanc** est un examen d'entraînement qui reproduit les conditions de l'examen réel.
- **Examen libre** : examen lancé par l'étudiant lui-même sur un questionnaire généré par tirage dans les banques auxquelles il a accès, avec les paramètres par défaut (voir UC-12).
- **Session d'étude** : suite de questions choisie par l'étudiant, en mode étude.
- **Identification** : renseignements que le candidat saisit avant de commencer un examen (nom, prénom et les champs choisis par le formateur, UC-20).
- **Passage** : une tentative d'un candidat sur un examen. Pendant le passage, le système conserve ses réponses et ses marque-pages ; à la fin, il enregistre le **résultat**.
- **Résultat** : ce qui est conservé d'un passage terminé : l'identité du candidat, la date du passage, le titre de l'examen, le titre et la version du questionnaire, le taux de réussite par module, le taux global, le taux minimal exigé et la mention réussi / non réussi.
- **Certificat de résultat** : page imprimable qui présente un résultat. Il est produit à la fin du passage et peut être **réédité** à tout moment depuis la liste des résultats (UC-19).
- **Diffusion** : mise à disposition d'un examen par un lien, pendant une période où il accepte des passages de candidats sans compte (UC-18).
- **Fichier d'échange** : fichier CSV, ou archive ZIP, qui contient une banque, un questionnaire, ou un questionnaire et son examen, pour l'export et l'import (§6.1).
- **Taux de réussite** : pourcentage de bonnes réponses, calculé sur l'ensemble des questions posées (**taux global**) ou sur les questions d'un module (**taux par module**).
- **Marque-page** : repère posé par le candidat sur une question pendant un passage, pour y revenir.

---

## 4. Cas d'utilisation — vue d'ensemble

| ID | Cas d'utilisation | Acteur principal | Lot |
|---|---|---|---|
| **A. Comptes et accès** |||
| UC-01 | Gérer les comptes et les inscriptions | Administrateur | v1 |
| UC-02 | Donner accès aux examens (accès aux banques → [PRD Banques](banques_prd.md), UC-07) | Formateur | v1 |
| **B. Banques et questions** |||
| UC-03 | Gérer les banques de questions → [PRD Banques](banques_prd.md), UC-01 | Formateur | MVP, puis lot B1 |
| UC-04 | Gérer les questions → [PRD Banques](banques_prd.md), UC-02 | Formateur | MVP, puis lot B1 |
| UC-05 | Copier ou déplacer des questions → [PRD Banques](banques_prd.md), UC-03 | Formateur | Lot B1 |
| UC-06 | Exporter une banque → [PRD Banques](banques_prd.md), UC-04 | Formateur | Lot 2, puis lot B1 |
| UC-07 | Importer des questions dans une banque → [PRD Banques](banques_prd.md), UC-05 | Formateur | Lot 2, puis lot B1 |
| **C. Questionnaires et examens** |||
| UC-08 | Composer un questionnaire | Formateur | Lot 2 |
| UC-20 | Définir un examen | Formateur | Lot 2 (prévisualisation lot 3) |
| UC-16 | Exporter un questionnaire ou un examen | Formateur | Lot 2 |
| UC-17 | Importer un questionnaire ou un examen | Formateur | Lot 2 |
| UC-18 | Diffuser un examen par un lien | Formateur | Lot 4 |
| UC-09 | Consulter les résultats | Formateur | Lot 3 (compléments v1) |
| UC-19 | Rééditer un certificat de résultat | Formateur | Lot 3 (envoi par e-mail lot 4) |
| UC-10 | Consulter les statistiques des questions | Formateur | v1 |
| **D. Étude et examen** |||
| UC-11 | Étudier en mode étude | Étudiant | MVP (compléments lot 5) |
| UC-12 | Passer un examen | Candidat | Lot 3 |
| UC-13 | Consulter la correction détaillée | Candidat | Lot 3 |
| UC-14 | Consulter son historique et sa progression | Étudiant | v1 |
| UC-15 | Signaler une question → [PRD Banques](banques_prd.md), UC-06 | Étudiant | v1 |

Avant le lot 4, les cas d'utilisation du formateur et de l'étudiant sont réalisés par l'utilisateur unique, sans connexion (§1.3).

---

## 5. Cas d'utilisation détaillés

### A. Comptes et accès

#### UC-01 — Gérer les comptes et les inscriptions

- **Acteur** : Administrateur.
- **Scénario nominal**
  1. L'administrateur crée un compte, ou valide une demande d'inscription.
  2. Il attribue un ou plusieurs rôles : administrateur, formateur, étudiant.
  3. Il peut désactiver un compte : l'utilisateur ne peut plus se connecter, mais ses résultats sont conservés.
- **Variantes**
  - 1a. **Inscription libre** : si l'administrateur l'a ouverte, un visiteur crée lui-même son compte ; il reçoit le rôle étudiant. Les options possibles sont listées en Q5.
  - 1b. **Création en lot** : import d'une liste d'étudiants (nom, e-mail).
- **Règles** : un compte désactivé n'est jamais supprimé tant qu'il a des résultats, sauf demande RGPD (§7).

#### UC-02 — Donner accès aux banques et aux examens

- **Acteur** : Formateur (ou administrateur).
- **Scénario nominal**
  1. Le formateur choisit une banque ou un examen.
  2. Il donne l'accès à des étudiants, un par un. L'accès par groupe est reporté (v2, Q6).
  3. Les étudiants voient la banque dans leur liste et peuvent l'étudier ; ils voient l'examen et peuvent le passer.
- **Variantes**
  - 2a. Pour un examen, le formateur peut fixer une période d'ouverture (date de début, date de fin).
  - 2b. Le formateur retire l'accès : la banque ou l'examen disparaît de la liste de l'étudiant ; ses résultats passés restent consultables.
- **Règle** : cet accès par compte s'ajoute à la diffusion par lien (UC-18), qui ne demande pas de compte.

### B. Banques et questions

Les banques et leurs questions sont gérées par le module [Banques de questions](banques_prd.md) : UC-03 à UC-07 de ce PRD y sont devenus UC-01 à UC-05 (gestion des banques et des questions, déplacement et copie, export et import). Les banques et questions créées par le module QCM avant ce découpage sont reprises par le module Banques (lot B1).

### C. Questionnaires et examens

#### UC-08 — Composer un questionnaire

- **Acteur** : Formateur.
- **Scénario nominal**
  1. Le formateur crée un questionnaire : titre, description facultative, version facultative.
  2. Il y ajoute des questions :
     - des **questions nouvelles**, saisies depuis le questionnaire comme dans une banque ; elles sont rangées dans une banque choisie par le formateur, la **banque par défaut** si aucune n'est choisie, et le message de confirmation dit laquelle ;
     - des **questions de banque**, sélectionnées dans une ou plusieurs banques avec les filtres du module Banques ;
     - des **questions tirées au hasard** dans des banques : il choisit une ou plusieurs banques ; pour chaque module, le système affiche le nombre de questions disponibles et le formateur indique combien en tirer, ou « toutes » ; le système tire les questions et les ajoute à la liste.
  3. Il fixe l'ordre des questions et enregistre.
- **Variantes**
  - 1a. **Générer un questionnaire par tirage** : le formateur crée le questionnaire directement à partir d'un tirage (étape 2, troisième cas), par exemple pour préparer un nouvel examen blanc ; il peut ensuite retoucher la liste comme n'importe quel questionnaire.
  - 2b. Tirage dont un module manque de questions : le système le signale, indique le nombre disponible et tire toutes celles du module.
  - 2c. Les questions déjà présentes dans le questionnaire ne sont pas tirées une seconde fois.
  - 2a. Modifier une question (elle est modifiée dans sa banque, donc partout où elle sert) ; retirer une question du questionnaire (elle reste dans sa banque).
  - 3a. Dupliquer un questionnaire pour en créer une variante : les questions restent partagées avec leurs banques.
  - 3b. Supprimer un questionnaire, après une confirmation qui indique les examens qui l'utilisent ; ses questions restent dans leurs banques. Un questionnaire utilisé par un examen ne peut pas être supprimé tant que l'examen existe ; le message le dit et nomme les examens.
- **Règles**
  - Seules les questions QCM et vrai / faux sont proposées, tirées ou ajoutées ; les autres types sont ignorés avec l'avertissement du §6.
  - Une question modifiée dans sa banque l'est aussi dans tous les questionnaires qui l'utilisent, pour les passages futurs.
  - Le tirage n'a lieu qu'à la génération : une fois enregistré, le questionnaire est une liste fixe, la même pour tous les candidats.
  - Pour le tirage, les modules de même nom sont regroupés entre banques **sans tenir compte des majuscules** (« Météo » et « météo » forment un seul module).
  - Modifier un questionnaire ne change pas les résultats déjà enregistrés ; le formateur peut changer la version pour distinguer les certificats émis avant et après (Q25).

#### UC-20 — Définir un examen

- **Acteur** : Formateur.
- **Scénario nominal**
  1. Le formateur crée un examen : titre, consignes, **questionnaire utilisé**.
  2. L'examen pose toutes les questions du questionnaire ; le système affiche leur nombre, au total et par module.
  3. Il fixe la **durée maximale** (facultative ; sans durée, le passage n'est pas chronométré) et le **taux de réussite minimal**.
  4. Il choisit les options du passage :
     - **correction détaillée** affichée au candidat à la fin du passage : oui ou non ;
     - ordre des questions mélangé ou fixe ;
     - nombre maximal de passages par candidat ;
     - **champs d'identification** demandés au candidat, chacun obligatoire ou facultatif : nom et prénom (toujours demandés et obligatoires), adresse e-mail, date de naissance, organisation (club, entreprise), identifiant libre dont le formateur choisit le libellé (par exemple « numéro de licence »).
  5. Il prévisualise l'examen tel que le candidat le verra, et l'enregistre.
- **Variantes**
  - 5a. Modifier un examen déjà passé : les passages en cours et les résultats enregistrés ne changent pas.
  - 5b. Dupliquer un examen, par exemple pour l'utiliser avec un autre questionnaire.
  - 5c. Supprimer un examen, après confirmation : ses résultats sont conservés et leurs certificats restent rééditables (UC-09, UC-19).
- **Règles**
  - Un passage est **réussi** si le taux global atteint le taux minimal de l'examen (Q26 pour des taux minimaux par module).
  - Une question sans réponse compte comme fausse.
  - Lot ultérieur : les questions en *brouillon* ne sont jamais posées à un candidat.
  - Les taux par module se calculent en regroupant les modules de même nom **sans tenir compte des majuscules**, comme pour le tirage (UC-08).

#### UC-16 — Exporter un questionnaire ou un examen

- **Acteur** : Formateur.
- **Scénario** : le formateur exporte, dans l'un des deux formats du fichier d'échange (§6.1) :
  - un **questionnaire** : CSV seul (les questions, dans l'ordre) ou ZIP (le CSV, les illustrations et les paramètres du questionnaire : titre, description, version) ;
  - un **examen** : ZIP contenant son questionnaire, comme ci-dessus, et les paramètres de l'examen (titre, consignes, durée, taux de réussite minimal, options de passage, champs d'identification).
- **Règles**
  - Le fichier est autonome : les questions y sont écrites en entier.
  - Les résultats ne sont pas exportés avec l'examen ; ils s'exportent depuis la liste des résultats (UC-09).
  - Un fichier exporté peut être réimporté sans modification (UC-17).

#### UC-17 — Importer un questionnaire ou un examen

- **Acteur** : Formateur.
- **Scénario nominal**
  1. Le formateur téléverse un fichier d'échange CSV ou ZIP.
  2. Le système analyse le fichier et présente un **rapport avant import** : titre et paramètres lus, nombre de questions valides, lignes en erreur avec le motif et le numéro de ligne, doublons probables dans le fichier, illustrations absentes du ZIP, paramètres d'examen invalides (taux hors de 0 à 100 %, durée négative, champ d'identification inconnu).
  3. Le formateur vérifie ou modifie les titres et confirme.
  4. Le système crée les questions dans une banque choisie par le formateur (la banque par défaut si aucune n'est choisie), puis un **nouveau questionnaire** qui les utilise et, si le fichier contient des paramètres d'examen, un **nouvel examen** qui l'utilise. Il affiche le rapport final et ouvre ce qu'il a créé.
- **Variantes**
  - 1a. **CSV seul**, ou fichier de banque : seul un questionnaire est créé, avec le nom du fichier (ou de la banque) comme titre.
  - 2a. Fichier illisible : rejet avec un message qui dit ce qui est attendu et renvoie à la documentation du format.
  - 2b. Paramètre d'examen invalide : il est remplacé par la valeur par défaut (Q11) et le rapport le signale ; le formateur peut annuler.
  - 3a. Le formateur annule : rien n'est importé.
- **Règles**
  - L'import crée toujours de nouveaux objets ; il ne modifie jamais un questionnaire ou un examen existant (Q24).
  - Une ligne en erreur n'empêche pas l'import des autres, mais aucune ligne n'est importée à moitié.

#### UC-18 — Diffuser un examen par un lien

- **Acteur** : Formateur ; acteur secondaire : Candidat.
- **Préconditions** : le formateur est connecté (lot 4) ; le questionnaire de l'examen a au moins une question.
- **Scénario nominal**
  1. Le formateur **ouvre la diffusion** d'un examen. Le système lui fournit un **lien** à transmettre aux candidats (e-mail, messagerie, affichage).
  2. Il règle les options de la diffusion :
     - dates d'ouverture et de fermeture, facultatives ;
     - envoi du certificat de résultat par e-mail au candidat, si l'e-mail est demandé à l'identification ;
     - message affiché à la fin du passage.
  3. Les candidats ouvrent le lien, s'identifient et passent l'examen (UC-12), sans compte.
  4. Le formateur suit les résultats au fur et à mesure (UC-09).
  5. Il **ferme la diffusion** ; le lien n'accepte plus de nouveau passage.
- **Variantes**
  - 3a. Lien ouvert avant l'ouverture, après la fermeture ou diffusion fermée : le candidat voit un message clair (« Cet examen n'accepte pas de passage actuellement ») avec les dates d'ouverture s'il y en a.
  - 3b. Nombre maximal de passages atteint (candidat reconnu à son adresse e-mail, qu'il faut alors demander) : le candidat en est informé avant de commencer.
  - 4a. Le formateur modifie l'examen ou son questionnaire pendant la diffusion : le système l'avertit que des passages sont en cours ; ceux-ci ne changent pas.
  - 5a. Le formateur rouvre la diffusion ; il peut aussi régénérer le lien, ce qui rend l'ancien inutilisable.
  - 5b. Un passage commencé avant la fermeture peut être terminé dans sa durée maximale.
- **Règles**
  - Par le lien, le candidat n'accède qu'à cet examen : ni aux banques, ni aux questionnaires, ni aux autres examens, ni aux résultats des autres candidats.
  - Le lien ne peut pas être deviné à partir de celui d'un autre examen.
  - Protection contre les abus (envois automatisés) : options en Q23.

#### UC-09 — Consulter les résultats

- **Acteur** : Formateur.
- **Scénario nominal**
  1. Le formateur voit la **liste des résultats**, tous examens confondus ou pour un examen : identité du candidat, date, examen, questionnaire et version, taux par module, taux global, réussite, numéro de certificat.
  2. Il ouvre un résultat et peut en rééditer le certificat (UC-19).
- **Variantes**
  - 1a. Rechercher un résultat par nom, e-mail, organisation, identifiant libre ou numéro de certificat ; filtrer par examen, période, réussite.
  - 1b. **Exporter les résultats en CSV** : une ligne par résultat, avec l'identité, la date, l'examen, le questionnaire et sa version, les taux par module, le taux global et la réussite.
  - 1c. Supprimer un résultat, après confirmation (erreur de saisie, demande RGPD) : son certificat ne peut plus être réédité.
  - 1d. Les résultats d'un examen supprimé restent dans la liste, sous le titre qu'avait l'examen.
  - 1e. *v1* : pour un étudiant, voir l'historique de ses résultats et sa progression par module.
- **Règle** : à partir de v1, le formateur ne voit que les résultats des étudiants à qui il a donné accès et des candidats de ses diffusions.

#### UC-19 — Rééditer un certificat de résultat

- **Acteur** : Formateur.
- **Déclencheur** : un candidat demande son certificat, par exemple après l'avoir perdu.
- **Scénario nominal**
  1. Le formateur retrouve le résultat dans la liste (UC-09, 1a).
  2. Il demande le certificat : le système le produit à partir du résultat enregistré, avec le même contenu que le certificat d'origine (UC-12) et la mention « Réédition du *date* ».
  3. Il l'imprime ou l'enregistre (formats en Q17) et le transmet au candidat.
- **Variantes**
  - 1a. Plusieurs résultats pour le même candidat : le formateur choisit celui qui est demandé.
  - 3a. *Lot 4* : il l'envoie par e-mail à l'adresse saisie par le candidat, ou à une autre adresse qu'il indique.
- **Règles**
  - Le certificat ne présente que le résultat enregistré, jamais recalculé : les modifications ou suppressions de questions, de questionnaires ou d'examens depuis le passage n'ont aucun effet sur lui.
  - Le certificat ne contient pas le détail des questions et des réponses.
  - Un résultat supprimé ne peut plus être réédité ; la recherche le dit clairement.

#### UC-10 — Consulter les statistiques des questions

- **Acteur** : Formateur.
- **Scénario** : pour chaque question, le formateur voit le nombre de réponses, le taux de bonnes réponses et la mauvaise réponse la plus choisie, séparément en mode étude et en examen, ainsi que la répartition des réponses choisies pour un examen donné. Il peut trier les questions par taux de réussite pour repérer celles qui sont mal formulées, trop faciles ou trop difficiles.

### D. Étude et examen

#### UC-11 — Étudier en mode étude

- **Acteur** : Étudiant.
- **Préconditions** : au moins une banque contient des questions ; à partir de v1, l'étudiant y a accès (UC-02).
- **Scénario nominal**
  1. L'étudiant choisit une ou plusieurs banques.
  2. Pour chaque module, le système affiche le nombre de questions disponibles ; l'étudiant indique combien il en veut, ou choisit « toutes ».
  3. Le système tire les questions au hasard et lance la session.
  4. Pour chaque question, l'étudiant choisit une réponse et valide ; au-delà de 6 propositions, il la choisit dans une liste déroulante avec recherche ([PRD Banques](banques_prd.md), §6). Le système indique **immédiatement** si la réponse est bonne, affiche la bonne réponse, l'explication de la mauvaise réponse choisie et l'explication générale quand elles existent.
  5. Le système affiche en permanence le **pourcentage de bonnes réponses depuis le début de la session** et le nombre de questions restantes.
  6. L'étudiant passe à la question suivante.
  7. En fin de session, le système affiche un bilan : taux global, taux par module, liste des questions échouées.
- **Variantes**
  - 2a. **Filtres de tirage** (lot v1) : questions jamais vues, ou questions déjà échouées.
  - 4a. L'étudiant passe une question sans répondre : elle n'entre pas dans le pourcentage.
  - 7a. Depuis le bilan, l'étudiant relance une session sur les seules questions échouées.
  - 7b. L'étudiant quitte avant la fin : les réponses déjà données sont conservées et comptent dans sa progression (UC-14), mais la session ne peut pas être reprise. La reprise d'une session interrompue est reportée (v2, Q10).
- **Règles**
  - Les réponses du mode étude alimentent la progression de l'étudiant (UC-14) et les statistiques des questions (UC-10).
  - Seules les questions QCM et vrai / faux des banques choisies sont tirées ; les autres types sont ignorés avec l'avertissement du §6.

#### UC-12 — Passer un examen

- **Acteur** : Candidat.
- **Préconditions** : le nombre maximal de passages n'est pas atteint ; au lot 4, la diffusion est ouverte (UC-18) ; à partir de v1, pour un étudiant connecté, l'examen lui est ouvert et dans sa période d'ouverture (UC-02).
- **Scénario nominal**
  1. Le candidat ouvre l'examen : au lot 3, l'utilisateur unique le lance sur le poste ; au lot 4, le candidat suit le lien de diffusion. Le système affiche le titre, les consignes, le nombre de questions, la durée maximale et le taux de réussite minimal.
  2. **Identification** : le candidat remplit les champs d'identification demandés (UC-20, étape 4). Il ne peut pas commencer tant qu'un champ obligatoire est vide ou mal formé (adresse e-mail, date) ; le message dit quel champ corriger.
  3. Le candidat démarre ; si l'examen a une durée, le compte à rebours commence et reste visible.
  4. Pour chaque question, il choisit une réponse, dans une liste déroulante avec recherche au-delà de 6 propositions. Il **ne voit pas** si elle est bonne.
  5. Il navigue librement : question suivante, précédente, ou n'importe quelle question.
  6. Il peut poser ou retirer un **marque-page** sur la question en cours.
  7. Il ouvre à tout moment la **liste des questions**, qui indique pour chacune si elle est répondue, non répondue, marquée. Un clic sur une question la rouvre.
  8. Il termine. Avant validation, le système lui rappelle le nombre de questions non répondues et marquées, et lui demande de confirmer.
  9. Le système calcule et enregistre le **résultat**, puis affiche le **certificat de résultat**, que le candidat peut imprimer ou enregistrer ; si l'examen le prévoit, la **correction détaillée** suit (UC-13).
- **Certificat de résultat** — contenu :
  - titre de l'examen ; titre et version du questionnaire ;
  - identité du candidat, telle que saisie ;
  - date et heure du passage ;
  - taux de réussite par module et taux global ;
  - taux de réussite minimal et mention **réussi** ou **non réussi** ;
  - **numéro de certificat** unique, qui permet de retrouver le résultat (UC-09, UC-19) ;
  - une mise en page lisible à l'impression (une page A4, noir et blanc).
- **Variantes**
  - 1a. **Examen libre** : l'étudiant choisit des banques (à partir de v1, celles auxquelles il a accès) et le nombre de questions par module, ou prend les valeurs par défaut ; le système génère un questionnaire par tirage (UC-08) et lance l'examen. Durée et taux minimal par défaut : à définir (Q11).
  - 2a. *v1* : un étudiant connecté n'a pas à s'identifier ; son identité est reprise de son compte et il complète seulement les champs supplémentaires demandés.
  - 3a. **Temps écoulé** : à la fin du compte à rebours, le passage est terminé automatiquement avec les réponses déjà données, et le certificat est produit. Un avertissement est affiché avant la fin (par exemple 5 minutes et 1 minute avant).
  - 3b. **Coupure** (réseau, fermeture du navigateur) : les réponses déjà données sont conservées ; le candidat reprend le passage là où il en était depuis le même navigateur. Le chronomètre continue de tourner pendant la coupure (Q12).
  - 4a. Le candidat change une réponse déjà donnée : la dernière réponse compte.
  - 9a. Le candidat ferme le certificat sans l'imprimer : il peut le demander au formateur (UC-19).
- **Règles**
  - Pendant le passage, aucune correction ni explication n'est accessible.
  - Le temps restant affiché est celui du serveur : changer l'heure de l'appareil ne donne pas de temps supplémentaire.
  - Une question non répondue à la fin compte comme fausse.
  - L'ordre des questions et des réponses est mélangé selon les options de l'examen.

#### UC-13 — Consulter la correction détaillée

- **Acteur** : Candidat.
- **Préconditions** : l'examen prévoit la correction détaillée (UC-20, étape 4).
- **Scénario** : à la fin du passage, après le certificat, le candidat voit pour chaque question : la réponse donnée, la bonne réponse, l'explication de la mauvaise réponse choisie et l'explication générale quand elles existent. Les questions peuvent être filtrées : fausses, non répondues, marquées. Pour une question de plus de 6 propositions, seules la réponse donnée et la bonne réponse sont affichées, pas la liste complète.
- **Variante** : l'étudiant relance une session d'étude sur les questions échouées qui viennent d'une banque (UC-11).
- **Règle** : la correction détaillée n'est présentée qu'à la fin du passage ; elle n'est pas conservée avec le résultat et ne peut pas être rééditée.

#### UC-14 — Consulter son historique et sa progression

- **Acteur** : Étudiant.
- **Scénario** : l'étudiant voit la liste de ses résultats (date, examen, taux, réussite) et leurs certificats, l'historique de ses sessions d'étude, et sa **progression par module** : taux de bonnes réponses, nombre de questions vues sur le total disponible, évolution dans le temps.

#### UC-15 — Signaler une question

- **Acteur** : Étudiant.
- **Scénario** : en mode étude ou depuis une correction, l'étudiant signale une question ; le signalement est traité dans le module Banques de questions ([PRD Banques](banques_prd.md), UC-06).
- **Règle** : il n'y a pas de signalement pendant un passage en cours, pour ne pas donner d'indice. Le signalement est possible depuis la correction.

---

## 6. Règles transverses

- **Message de résultat** : toute action (enregistrement, import, export, fin de passage, réédition) affiche clairement son résultat, succès ou échec ; un message d'erreur dit ce qui ne va pas et comment le corriger.
- **Confirmation** : toute suppression demande une confirmation qui indique ce qui sera perdu.
- **Intégrité des résultats** : un résultat enregistré n'est jamais recalculé après coup par une modification de question, de questionnaire ou d'examen, sauf décision explicite du formateur de neutraliser une question erronée (Q13).
- **Mélange** : l'ordre des réponses est mélangé à chaque affichage, sauf interdiction sur la question ; le candidat ne peut pas reconnaître une question à la position de sa bonne réponse. Les réponses « Vrai » et « Faux » gardent cet ordre.
- **Types de questions** : le module pose les questions QCM et les vrai / faux, comme QCM à deux réponses (l'explication de la question tient lieu d'explication de la mauvaise réponse). Quand il utilise une banque (étude, tirage, ajout à un questionnaire, import d'un questionnaire), il ignore les autres types et l'annonce : « 12 questions n'étaient pas de type QCM ou vrai / faux et n'ont pas été utilisées. »

### 6.1 Fichier d'échange

- Les **questions** s'échangent dans le format du module Banques de questions ([PRD Banques](banques_prd.md), §8), dont l'ancien format QCM est un cas particulier : un fichier exporté avant le découpage reste importable.
- Pour un **questionnaire** ou un **examen**, l'archive ZIP contient en plus un **fichier de paramètres** (JSON), qui porte une version du format :
  - pour un questionnaire : titre, description, version ;
  - pour un examen : les paramètres du questionnaire, plus ceux de l'examen (titre, consignes, durée maximale, taux de réussite minimal, options de passage, champs d'identification).
- Un fichier exporté se réimporte sans modification et redonne le même contenu (aux identifiants internes près).
- Le format est décrit dans la documentation utilisateur, avec des fichiers exemples téléchargeables (questionnaire, examen).

---

## 7. Exigences non fonctionnelles

- **Utilisation autonome** : le module fonctionne sans les autres modules de Mentor. Jusqu'au lot 3, il fonctionne sans compte ; au lot 4, le formateur se connecte ; à partir de v1, les comptes et les rôles sont ceux du socle.
- **Exposition sur Internet** : jusqu'au lot 3 inclus, l'application n'est pas faite pour être exposée sur Internet (aucune connexion). À partir du lot 4, seules les pages de passage ouvertes par un lien de diffusion sont accessibles sans connexion.
- **Plateforme** : application web responsive ; le mode étude et le passage d'un examen sont utilisables sur téléphone.
- **Impression** : le certificat de résultat s'imprime sur une page A4 depuis un navigateur courant.
- **Fiabilité du passage** : chaque réponse est enregistrée dès qu'elle est donnée ; une coupure ne fait perdre aucune réponse.
- **Performances** : passage d'une question à la suivante en moins d'une seconde ; import de 1 000 questions en moins d'une minute ; 100 passages simultanés sans ralentissement perceptible (lot 4).
- **Sécurité** : un candidat n'accède jamais aux bonnes réponses avant la fin de son passage, ni à rien d'autre que l'examen qu'il passe ; à partir de v1, un étudiant n'accède qu'aux banques et examens qui lui sont ouverts.
- **RGPD** (à partir du lot 3, qui enregistre l'identité des candidats ; le MVP et le lot 2 ne gèrent aucune donnée personnelle) : seuls les champs d'identification choisis par le formateur sont demandés ; le candidat est informé, avant de commencer, de l'usage de ses données et de leur durée de conservation (Q20) ; export et suppression des données d'une personne sur demande ; hébergement en UE.
- **Accessibilité** : navigation complète au clavier, texte alternatif pour les illustrations, contrastes suffisants, taille de police ajustable. Un temps supplémentaire par étudiant (tiers-temps) est à envisager (Q14).
- **Langue** : français, interface traduisible.

---

## 8. Découpage en lots

| Lot | Contenu | Critère de sortie |
|---|---|---|
| **MVP** | UC-03, UC-04 et UC-11 en version réduite : banques, édition des questions avec une image, étude libre avec correction immédiate et bilan par module ; sans connexion ni rôles, un utilisateur unique (§1.3). Détail et éléments reportés : [plan](../plans/qcm_plan.md) §2 | L'utilisateur crée une banque, y saisit des questions sur plusieurs modules, et fait une session d'étude dont le bilan par module est juste |
| **Lot 2 — Questionnaires, examens et échanges** | Fichier d'échange (§6.1) ; UC-06 et UC-07 export / import des banques ; UC-08 questionnaires (questions propres, de banque, génération par tirage) ; UC-20 définition des examens ; UC-16 et UC-17 export / import des questionnaires et des examens ; détection des doublons à l'import (UC-04) ; sans connexion | L'utilisateur importe une banque et un examen existants, modifie le questionnaire, exporte l'examen, et le fichier réimporté redonne le même questionnaire et le même examen |
| **Lot 3 — Passage, résultats et certificats** | UC-12 passage avec identification, chronomètre et certificat de résultat imprimable ; UC-13 correction détaillée en fin de passage ; UC-09 liste des résultats et export CSV ; UC-19 réédition du certificat ; prévisualisation des examens (UC-20) ; sur le poste où l'application est ouverte, sans connexion | Un candidat s'identifie, passe un examen blanc chronométré et imprime son certificat ; l'utilisateur modifie ensuite le questionnaire, retrouve le résultat et réédite le même certificat |
| **Lot 4 — Diffusion en ligne** | Connexion du formateur ; UC-18 diffusion par lien ; envoi du certificat par e-mail (UC-18, UC-19) ; suivi des résultats au fil de l'eau (UC-09) | Le formateur diffuse un lien ; des candidats sans compte, sur leur propre appareil, s'identifient et passent l'examen ; le formateur voit leurs résultats et leur renvoie un certificat |
| **Lot B1 (module Banques)** | Banques et questions confiées au module Banques de questions, avec reprise des données existantes ; questionnaires sans questions propres ; vrai / faux posés comme QCM à deux réponses ; avertissement pour les autres types (§6) | Les études, questionnaires, examens et résultats existants fonctionnent comme avant ; une banque Leitner utilisée dans le module QCM produit l'avertissement attendu |
| **Lot 5 — Compléments d'étude** | UC-11 passer une question, relancer sur les échecs ; liste déroulante avec recherche au-delà de 6 propositions (UC-11, UC-12, UC-13) (les compléments d'édition des banques et des questions sont au lot B2 du module Banques) | À définir |
| **v1** | UC-01 comptes et rôles ; UC-02 accès aux examens ; UC-09 vue par étudiant ; UC-10 statistiques des questions ; UC-14 historique et progression ; UC-15 signalements (traités dans le module Banques) ; filtres de tirage (questions jamais vues, échouées) | Un formateur suit les résultats de ses étudiants et corrige les questions problématiques à partir des données d'usage |
| **v2** | Statut brouillon / publié ; groupes d'étudiants (Q6) ; reprise d'une session d'étude interrompue (Q10) ; appartenance des banques, questionnaires et examens, visibilité entre formateurs (Q4) ; autres médias (son, vidéo) ; tiers-temps (Q14) | À définir |

---

## 9. Questions ouvertes

**Contenu des questions**
- ~~Q1. Faut-il prévoir d'autres types de questions ?~~ **Tranché** : les types sont définis par le module Banques de questions ; le module QCM pose les QCM et les vrai / faux (comme QCM à deux réponses), les autres types sont utilisés par le module Leitner. Les questions à plusieurs bonnes réponses restent à décider.
- Q2. Faut-il utiliser le niveau de difficulté des questions (désormais saisi dans le module Banques) pour équilibrer les questionnaires générés par tirage ?
- ~~Q3. La liste des modules est-elle commune à toutes les banques, ou propre à chaque banque ?~~ **Tranché** : propre à chaque banque, en texte libre, avec proposition des noms existants (§3, UC-04).
- ~~Q3b. Une question peut-elle avoir plusieurs modules ?~~ **Tranché** : non, une question a au plus un module.
- ~~Q16. Regroupement des modules entre banques : sur le nom exact, ou sans tenir compte des majuscules, des accents et des espaces en trop ?~~ **Tranché** : sans tenir compte des majuscules (UC-08, UC-20).
- Q8. Formats d'illustration acceptés et taille maximale : suivie par le module Banques ([PRD Banques](banques_prd.md), Q2).
- Q9. Statut des questions importées : toujours *brouillon*, toujours *publiée*, ou selon une colonne du fichier ? *Reporté avec le statut (v2).*

**Questionnaires, examens et échanges**
- ~~Q21. Tirage d'un examen : parmi les questions de son questionnaire, ou directement dans des banques ?~~ **Tranché** : un examen pose toujours un questionnaire complet ; le tirage au hasard dans des banques sert à générer un questionnaire (UC-08).
- ~~Q22. Questions propres et étude libre~~ **Sans objet** : toute question vit dans une banque.
- Q24. Import — options : (a) toujours de nouveaux questionnaires et examens (choix provisoire, UC-17) ; (b) possibilité aussi de remplacer les questions d'un questionnaire existant.
- Q25. Version d'un questionnaire — options : (a) texte libre saisi par le formateur, facultatif (choix provisoire, UC-08) ; (b) numéro incrémenté automatiquement à chaque modification de la liste des questions ou d'une question propre ; (c) date de dernière modification.
- Q26. Faut-il, en plus du taux global, des taux de réussite minimaux par module (un passage n'est alors réussi que si chaque module atteint son taux) ?

**Certificats et identification**
- Q17. Forme du certificat — options : (a) page mise en page pour l'impression, imprimée ou enregistrée en PDF par le navigateur ; (b) fichier PDF produit par l'application et téléchargeable ; (c) les deux. L'envoi par e-mail (lot 4) joint le certificat en PDF avec (b) ou (c), sinon il envoie un lien.
- Q18. Authenticité du certificat — options : (a) aucun contrôle ; (b) numéro de certificat, que le formateur vérifie dans l'application (choix minimal, UC-12) ; (c) lien ou code QR de vérification publique sur le certificat ; (d) PDF signé numériquement.
- Q19. Vérification de l'identité du candidat — options : (a) déclarative, sans vérification (choix provisoire) ; (b) adresse e-mail confirmée par un code envoyé avant de commencer ; (c) compte obligatoire (v1).
- Q20. Durée de conservation des résultats et des identités — options : (a) illimitée, suppression sur demande seulement ; (b) durée fixée par l'administrateur (par exemple 3 ans), puis suppression ; (c) durée fixée, puis anonymisation (les statistiques sont conservées).

**Accès et partage**
- ~~Q4. Une banque peut-elle être partagée entre formateurs ?~~ **Tranché pour l'instant** : tous les formateurs voient toutes les banques. L'appartenance d'une banque à un formateur et la visibilité qu'en ont les autres (lecture seule, modification) sont reportées (v2).
- Q5. Inscription libre : options possibles — fermée (comptes créés par l'administrateur seulement), ouverte avec validation de l'administrateur, ouverte sans validation, ouverte sur invitation ou code d'accès. Lesquelles retenir ?
- Q6. Faut-il gérer des groupes d'étudiants (promotions) pour donner l'accès en une fois ? Un formateur voit-il tous les étudiants ou seulement ceux de ses groupes ? *Reporté (v2).*
- Q23. Protection du lien de diffusion contre les abus — options : (a) aucune ; (b) limitation du nombre de passages démarrés par adresse réseau et par minute ; (c) code d'accès à saisir en plus du lien ; (d) test anti-robot (captcha). Plusieurs options peuvent se cumuler.

**Passage**
- ~~Q7. Quand le formateur modifie une question déjà utilisée, la correction d'un passage passé montre-t-elle l'ancienne version ou la nouvelle ?~~ **Sans objet** : la correction détaillée n'est présentée qu'à la fin du passage (UC-13) et le certificat ne contient pas les questions (UC-19).
- Q10. Une session d'étude interrompue peut-elle être reprise, ou chaque session repart-elle de zéro ? *Reporté (v2) : en attendant, une session interrompue n'est pas reprise (UC-11).*
- Q11. Valeurs par défaut d'un examen (libre, ou importé avec des paramètres invalides) : nombre de questions, durée, taux minimal ? Faut-il des modèles d'examen libre définis par le formateur (par exemple « examen théorique planeur ») ?
- Q12. Lors d'une coupure pendant un examen, le chronomètre continue-t-il (règle la plus stricte) ou s'arrête-t-il ?
- Q13. Le formateur peut-il neutraliser une question erronée après coup, et recalculer les résultats des passages concernés ? (Cela suppose de conserver les réponses de chaque passage.)
- Q14. Faut-il un temps supplémentaire par étudiant (tiers-temps) ?

**Intégration à Mentor**
- Q15. Intégré à Mentor, le module de la question correspond-il au chapitre de la formation, et les questions sont-elles rattachées aux notions ([PRD Mentor](mentor_prd.md), §3) ?
