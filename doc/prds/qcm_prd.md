# PRD — Module QCM

| | |
|---|---|
| **Version** | 0.1 (brouillon) |
| **Date** | 8 novembre 2026 |
| **Auteur** | Frédéric |
| **Statut** | À valider — voir §9 « Questions ouvertes » |
| **PRD d'ensemble** | [Mentor](mentor_prd.md), §1.4 « Architecture modulaire » |

---

## 1. Vision

Le module QCM gère des **banques de questions à choix multiples**, permet de composer des **examens** à partir de ces banques et propose deux **modes de passage** : un mode **étude**, où l'étudiant voit immédiatement s'il a bien répondu, et un mode **examen**, chronométré et sans correction avant la fin.

Le module est utilisable **seul**, dès sa première version, sans les autres modules de Mentor. Intégré à Mentor, il fournit les évaluations de fin de chapitre, les examens blancs et les questions des cartes Leitner (voir [PRD Mentor](mentor_prd.md), UC-11 à UC-13, UC-19, UC-20).

Premier cas d'application : la préparation à l'examen théorique des élèves pilotes (planeur, avion, ULM), dont les questions sont organisées par module (météorologie, réglementation, etc.).

### 1.1 Objectifs

- O1 — Permettre au formateur de constituer et maintenir rapidement des banques de questions, y compris par import de fichiers existants.
- O2 — Permettre à l'étudiant de s'entraîner librement sur les modules de son choix et de mesurer sa progression.
- O3 — Permettre à l'étudiant de passer des examens blancs dans des conditions proches de l'examen réel (durée, nombre de questions, seuils de réussite).
- O4 — Donner au formateur une vue des résultats de ses étudiants et de la qualité de ses questions.

### 1.2 Hors périmètre (v1)

- Questions à plusieurs bonnes réponses, réponses ouvertes, questions d'appariement ou de classement (voir Q1).
- Médias autres que l'image (son, vidéo) : prévus dans un lot ultérieur.
- Surveillance d'examen (webcam, verrouillage du navigateur).
- Délivrance de résultats officiels : les examens du module sont des examens d'entraînement.
- Répétition espacée : relève du module Leitner, qui réutilise les questions QCM.

---

## 2. Acteurs

| Acteur | Description |
|---|---|
| **Administrateur** | Gère les comptes et les inscriptions, ouvre ou ferme l'inscription libre, attribue les rôles. Il peut tout faire, y compris les actions du formateur. |
| **Formateur** | Gère les banques de questions, les questions et les examens ; donne aux étudiants l'accès aux banques et aux examens ; consulte leurs résultats. |
| **Étudiant** | Accède aux banques et aux examens qui lui sont ouverts ; s'entraîne en mode étude et passe des examens en mode examen ; consulte ses résultats. |

Un même compte peut cumuler plusieurs rôles (par exemple formateur et étudiant).

---

## 3. Glossaire

- **Banque de questions** : ensemble nommé de questions, avec une description. Une banque appartient à un formateur et peut être partagée avec d'autres formateurs (Q4).
- **Question** :
  - un **énoncé** ;
  - une **bonne réponse** ;
  - plusieurs **mauvaises réponses**, habituellement trois, chacune avec une **explication facultative** de ce qui la rend fausse ;
  - une **explication générale facultative** de la bonne réponse ;
  - une **illustration facultative** (image, schéma, carte…) sur laquelle la question peut porter ;
  - un **module facultatif** (météorologie, réglementation…) qui sert à organiser les examens et les séances d'étude ;
  - un **statut** : *brouillon* (invisible des étudiants) ou *publiée*.
- **Module** : thème d'une question. La liste des modules est commune à une banque ou à l'ensemble des banques (Q3). Une question sans module est rangée dans « Sans module ».
- **Examen** : épreuve définie par le formateur, avec un titre, une liste de questions ou des règles de tirage, une durée maximale et des seuils de réussite.
  - **Examen fixe** : les questions sont choisies une à une par le formateur ; tous les étudiants ont les mêmes.
  - **Examen à tirage** : le formateur indique un nombre de questions par module (et les banques sources) ; les questions sont tirées au hasard à chaque passage.
- **Examen libre** : examen à tirage lancé par l'étudiant lui-même sur les banques auxquelles il a accès, avec les paramètres par défaut (voir UC-12).
- **Session d'étude** : suite de questions choisie par l'étudiant, en mode étude.
- **Passage** : une tentative d'un étudiant sur un examen, avec ses réponses, ses marque-pages, son heure de début et de fin, et son résultat.
- **Taux de réussite** : pourcentage de bonnes réponses, calculé sur l'ensemble de l'examen (**taux global**) ou sur les questions d'un module (**taux par module**).
- **Marque-page** : repère posé par l'étudiant sur une question pendant un examen, pour y revenir.

---

## 4. Cas d'utilisation — vue d'ensemble

| ID | Cas d'utilisation | Acteur principal | Lot |
|---|---|---|---|
| **A. Comptes et accès** |||
| UC-01 | Gérer les comptes et les inscriptions | Administrateur | MVP |
| UC-02 | Donner accès aux banques et aux examens | Formateur | MVP |
| **B. Banques et questions** |||
| UC-03 | Gérer les banques de questions | Formateur | MVP |
| UC-04 | Gérer les questions | Formateur | MVP |
| UC-05 | Copier ou déplacer des questions entre banques | Formateur | MVP |
| UC-06 | Exporter des questions | Formateur | MVP |
| UC-07 | Importer des questions | Formateur | MVP |
| **C. Examens** |||
| UC-08 | Créer un examen | Formateur | MVP |
| UC-09 | Consulter les résultats des étudiants | Formateur | MVP |
| UC-10 | Consulter les statistiques des questions | Formateur | v1 |
| **D. Étude et examen** |||
| UC-11 | Étudier en mode étude | Étudiant | MVP |
| UC-12 | Passer un examen | Étudiant | MVP |
| UC-13 | Consulter la correction d'un examen | Étudiant | MVP |
| UC-14 | Consulter son historique et sa progression | Étudiant | MVP |
| UC-15 | Signaler une question | Étudiant | v1 |

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
- **Règles** : un compte désactivé n'est jamais supprimé tant qu'il a des passages, sauf demande RGPD (§7).

#### UC-02 — Donner accès aux banques et aux examens

- **Acteur** : Formateur (ou administrateur).
- **Scénario nominal**
  1. Le formateur choisit une banque ou un examen.
  2. Il donne l'accès à des étudiants, un par un ou par groupe (Q6).
  3. Les étudiants voient la banque dans leur liste et peuvent l'étudier ; ils voient l'examen et peuvent le passer.
- **Variantes**
  - 2a. Pour un examen, le formateur peut fixer une période d'ouverture (date de début, date de fin) et un nombre maximal de passages.
  - 2b. Le formateur retire l'accès : la banque ou l'examen disparaît de la liste de l'étudiant ; ses résultats passés restent consultables.

### B. Banques et questions

#### UC-03 — Gérer les banques de questions

- **Acteur** : Formateur.
- **Scénario** : créer une banque (nom, description), la renommer, la dupliquer, la supprimer. La liste des banques affiche pour chacune le nombre de questions, par module et par statut.
- **Règles**
  - La suppression d'une banque demande une confirmation qui indique le nombre de questions et d'examens concernés.
  - Une banque dont des questions sont utilisées par un examen ou par des passages passés ne perd pas l'historique : voir la règle d'archivage de UC-04.

#### UC-04 — Gérer les questions

- **Acteur** : Formateur.
- **Scénario nominal**
  1. Dans une banque, le formateur crée une question : énoncé, bonne réponse, mauvaises réponses avec explications facultatives, explication générale facultative, illustration facultative, module facultatif.
  2. Il prévisualise la question telle que l'étudiant la verra.
  3. Il l'enregistre en *brouillon* ou la *publie*.
- **Variantes**
  - 1a. Modifier une question existante.
  - 1b. Supprimer une question, après confirmation.
  - 1c. Rechercher et filtrer les questions d'une banque : texte, module, statut, présence d'une illustration.
  - 1d. Agir sur une sélection de questions : publier, repasser en brouillon, changer de module, supprimer.
- **Règles**
  - Une question a exactement une bonne réponse et au moins une mauvaise réponse ; trois mauvaises réponses sont proposées par défaut.
  - L'ordre des réponses est mélangé à chaque affichage pour l'étudiant ; le formateur peut l'interdire pour une question dont les réponses se réfèrent les unes aux autres (« aucune des réponses précédentes »).
  - **Archivage** : une question déjà utilisée dans un passage n'est pas effacée. La supprimer la retire de la banque et des examens futurs, mais les corrections des passages passés continuent de l'afficher telle qu'elle était.
  - **Modification d'une question utilisée** : la correction d'un passage passé montre la version de la question au moment du passage (Q7).
  - Le système signale les **doublons probables** (même énoncé dans la même banque) à la création et à l'import.
  - Formats d'illustration acceptés et taille maximale : à préciser (Q8).

#### UC-05 — Copier ou déplacer des questions entre banques

- **Acteur** : Formateur.
- **Scénario** : le formateur sélectionne une ou plusieurs questions, choisit « Copier vers » ou « Déplacer vers », puis la banque cible.
- **Règles**
  - Une copie est une nouvelle question indépendante de l'originale, illustration comprise.
  - Un déplacement conserve la question et son historique. Si la question est utilisée par un examen fixe, le système le signale et l'examen continue d'utiliser la question.
  - Le formateur doit avoir les droits de modification sur la banque cible.

#### UC-06 — Exporter des questions

- **Acteur** : Formateur.
- **Scénario** : le formateur exporte une banque entière ou une sélection de questions, dans l'un des deux formats :
  - **CSV** seul, sans les illustrations ;
  - **ZIP** contenant le fichier CSV et un répertoire avec les illustrations référencées.
- **Contenu d'une ligne CSV** : énoncé, bonne réponse, mauvaises réponses et leurs explications, explication générale, module, nom du fichier d'illustration, statut. Le format est documenté dans la documentation utilisateur, avec un fichier exemple téléchargeable.
- **Règle** : un fichier exporté peut être réimporté sans modification (UC-07).

#### UC-07 — Importer des questions

- **Acteur** : Formateur.
- **Scénario nominal**
  1. Le formateur choisit une banque cible et téléverse un fichier CSV ou ZIP.
  2. Le système analyse le fichier et présente un **rapport avant import** : nombre de questions valides, lignes en erreur avec le motif et le numéro de ligne, doublons probables, illustrations référencées mais absentes du ZIP.
  3. Le formateur choisit le traitement des doublons (ignorer, importer quand même) et confirme.
  4. Le système importe les questions valides et affiche le rapport final.
- **Variantes**
  - 2a. Fichier illisible (encodage, séparateur, colonnes manquantes) : rejet avec un message qui dit ce qui est attendu.
  - 3a. Le formateur annule : rien n'est importé.
- **Règles**
  - L'import est tout ou rien pour les lignes valides : une ligne en erreur n'empêche pas l'import des autres, mais aucune ligne n'est importée à moitié.
  - Les questions importées sont en *brouillon* par défaut, sauf si le fichier indique leur statut (Q9).
  - Les modules inconnus sont créés à l'import, et le rapport le signale.

### C. Examens

#### UC-08 — Créer un examen

- **Acteur** : Formateur.
- **Scénario nominal**
  1. Le formateur crée un examen : titre, consignes, durée maximale.
  2. Il choisit le mode de composition :
     - **examen fixe** : il sélectionne les questions dans une ou plusieurs banques, avec les filtres de UC-04 ;
     - **examen à tirage** : il indique, pour chaque module, le nombre de questions à tirer, et les banques sources.
  3. Il fixe les **seuils de réussite** : un taux global, et facultativement un taux par module.
  4. Il choisit les options du passage :
     - correction affichée à la fin du passage, après la date de fin de l'examen, ou jamais ;
     - nombre maximal de passages par étudiant ;
     - ordre des questions mélangé ou fixe.
  5. Il prévisualise l'examen, puis le publie et donne l'accès (UC-02).
- **Variantes**
  - 2a. Examen à tirage dont un module manque de questions publiées : le système le signale à la création et indique le nombre disponible.
  - 5a. Modifier un examen publié : les passages déjà commencés ou terminés ne changent pas.
  - 5b. Dupliquer un examen pour en créer une variante.
- **Règles**
  - Un examen est **réussi** si le taux global atteint son seuil **et** si chaque module qui a un seuil l'atteint.
  - Une question sans réponse compte comme fausse.
  - Les questions en *brouillon* ne sont jamais tirées ni proposées à un étudiant.

#### UC-09 — Consulter les résultats des étudiants

- **Acteur** : Formateur.
- **Scénario** : pour un examen, le formateur voit la liste des passages : étudiant, date, durée, taux global, taux par module, réussite. Pour un étudiant, il voit l'historique de ses passages et sa progression par module. Il peut ouvrir la correction d'un passage.
- **Variantes** : filtrer par examen, étudiant, période, réussite ; exporter les résultats en CSV.
- **Règle** : le formateur ne voit que les résultats des étudiants à qui il a donné accès (Q6).

#### UC-10 — Consulter les statistiques des questions

- **Acteur** : Formateur.
- **Scénario** : pour chaque question, le formateur voit le nombre de réponses, le taux de bonnes réponses et la mauvaise réponse la plus choisie, séparément en mode étude et en mode examen. Il peut trier les questions par taux de réussite pour repérer celles qui sont mal formulées, trop faciles ou trop difficiles.

### D. Étude et examen

#### UC-11 — Étudier en mode étude

- **Acteur** : Étudiant.
- **Préconditions** : l'étudiant a accès à au moins une banque.
- **Scénario nominal**
  1. L'étudiant choisit une ou plusieurs banques.
  2. Pour chaque module, le système affiche le nombre de questions disponibles ; l'étudiant indique combien il en veut, ou choisit « toutes ».
  3. Le système tire les questions au hasard et lance la session.
  4. Pour chaque question, l'étudiant choisit une réponse et valide. Le système indique **immédiatement** si la réponse est bonne, affiche la bonne réponse, l'explication de la mauvaise réponse choisie et l'explication générale quand elles existent.
  5. Le système affiche en permanence le **pourcentage de bonnes réponses depuis le début de la session** et le nombre de questions restantes.
  6. L'étudiant passe à la question suivante.
  7. En fin de session, le système affiche un bilan : taux global, taux par module, liste des questions échouées.
- **Variantes**
  - 2a. **Filtres de tirage** (lot v1) : questions jamais vues, ou questions déjà échouées.
  - 4a. L'étudiant passe une question sans répondre : elle n'entre pas dans le pourcentage.
  - 7a. Depuis le bilan, l'étudiant relance une session sur les seules questions échouées.
  - 7b. L'étudiant quitte avant la fin : la session est enregistrée telle quelle et il peut la reprendre (Q10).
- **Règle** : les réponses du mode étude alimentent la progression de l'étudiant (UC-14) et les statistiques des questions (UC-10).

#### UC-12 — Passer un examen

- **Acteur** : Étudiant.
- **Préconditions** : l'examen lui est ouvert, dans sa période d'ouverture, et il n'a pas atteint le nombre maximal de passages.
- **Scénario nominal**
  1. L'étudiant choisit un examen. Le système affiche les consignes, le nombre de questions, la durée maximale et les seuils de réussite.
  2. L'étudiant démarre ; le compte à rebours commence et reste visible.
  3. Pour chaque question, il choisit une réponse. Il **ne voit pas** si elle est bonne.
  4. Il navigue librement : question suivante, précédente, ou n'importe quelle question.
  5. Il peut poser ou retirer un **marque-page** sur la question en cours.
  6. Il ouvre à tout moment la **liste des questions**, qui indique pour chacune si elle est répondue, non répondue, marquée. Un clic sur une question la rouvre.
  7. Il termine l'examen. Avant validation, le système lui rappelle le nombre de questions non répondues et marquées, et lui demande de confirmer.
  8. Le système calcule le résultat : taux global, taux par module, réussite ou échec selon les seuils.
- **Variantes**
  - 1a. **Examen libre** : l'étudiant lance un examen tiré au hasard sur les banques auxquelles il a accès, en choisissant le nombre de questions par module ou en prenant les valeurs par défaut. Durée et seuils par défaut : à définir (Q11).
  - 2a. **Temps écoulé** : à la fin du compte à rebours, l'examen est terminé automatiquement avec les réponses déjà données. Un avertissement est affiché avant la fin (par exemple 5 minutes et 1 minute avant).
  - 2b. **Coupure** (réseau, fermeture du navigateur) : les réponses déjà données sont conservées ; l'étudiant reprend le passage là où il en était. Le chronomètre continue de tourner pendant la coupure (Q12).
  - 3a. L'étudiant change une réponse déjà donnée : la dernière réponse compte.
- **Règles**
  - Pendant l'examen, aucune correction ni explication n'est accessible.
  - Le temps restant affiché est celui du serveur : changer l'heure de l'appareil ne donne pas de temps supplémentaire.
  - Une question non répondue à la fin compte comme fausse.
  - L'ordre des questions et des réponses est mélangé selon les options de l'examen.

#### UC-13 — Consulter la correction d'un examen

- **Acteur** : Étudiant.
- **Préconditions** : la correction est disponible selon l'option choisie par le formateur (UC-08, étape 4).
- **Scénario** : pour chaque question : la réponse donnée, la bonne réponse, l'explication de la mauvaise réponse choisie et l'explication générale quand elles existent. Les questions peuvent être filtrées : fausses, non répondues, marquées. Le résultat global et par module est rappelé en tête.
- **Variante** : l'étudiant relance une session d'étude sur les questions échouées de l'examen (UC-11).

#### UC-14 — Consulter son historique et sa progression

- **Acteur** : Étudiant.
- **Scénario** : l'étudiant voit la liste de ses passages d'examen (date, examen, taux, réussite), l'historique de ses sessions d'étude, et sa **progression par module** : taux de bonnes réponses, nombre de questions vues sur le total disponible, évolution dans le temps.

#### UC-15 — Signaler une question

- **Acteur** : Étudiant.
- **Scénario** : en mode étude ou depuis une correction, l'étudiant signale une question qu'il pense erronée ou ambiguë, avec un commentaire. Le formateur voit les signalements dans la banque, corrige la question ou classe le signalement, et l'étudiant est informé de la suite donnée.
- **Règle** : il n'y a pas de signalement pendant un examen en cours, pour ne pas donner d'indice. Le signalement est possible depuis la correction.

---

## 6. Règles transverses

- **Message de résultat** : toute action (enregistrement, import, export, fin d'examen) affiche clairement son résultat, succès ou échec ; un message d'erreur dit ce qui ne va pas et comment le corriger.
- **Confirmation** : toute suppression demande une confirmation qui indique ce qui sera perdu.
- **Intégrité des résultats** : un résultat d'examen n'est jamais recalculé après coup par une modification de question ou d'examen, sauf décision explicite du formateur de neutraliser une question erronée (Q13).
- **Mélange** : l'ordre des réponses est mélangé à chaque affichage (UC-04) ; l'étudiant ne peut pas reconnaître une question à la position de sa bonne réponse.

---

## 7. Exigences non fonctionnelles

- **Utilisation autonome** : le module fonctionne sans les autres modules de Mentor ; les comptes et les rôles sont ceux du socle.
- **Plateforme** : application web responsive ; le mode étude et le mode examen sont utilisables sur téléphone.
- **Fiabilité de l'examen** : chaque réponse est enregistrée dès qu'elle est donnée ; une coupure ne fait perdre aucune réponse.
- **Performances** : passage d'une question à la suivante en moins d'une seconde ; import de 1 000 questions en moins d'une minute.
- **Sécurité** : un étudiant n'accède jamais aux bonnes réponses d'un examen avant la fin de son passage, ni aux banques et examens qui ne lui sont pas ouverts.
- **RGPD** : données personnelles minimales ; export et suppression des données d'un utilisateur sur demande ; hébergement en UE.
- **Accessibilité** : navigation complète au clavier, texte alternatif pour les illustrations, contrastes suffisants, taille de police ajustable. Un temps supplémentaire par étudiant (tiers-temps) est à envisager (Q14).
- **Langue** : français en v1, interface traduisible.

---

## 8. Découpage en lots

| Lot | Contenu | Critère de sortie |
|---|---|---|
| **MVP** | UC-01 à UC-09, UC-11 à UC-14 ; questions avec une illustration image | Un formateur importe une banque existante (CSV ou ZIP) ; un étudiant s'entraîne en mode étude et passe un examen blanc chronométré, puis consulte sa correction |
| **v1** | UC-10 statistiques des questions ; UC-15 signalements ; filtres de tirage (questions jamais vues, échouées) ; groupes d'étudiants (Q6) | Le formateur repère et corrige les questions problématiques à partir des données d'usage |
| **v2** | Autres médias (son, vidéo) ; autres types de questions (Q1) ; tiers-temps (Q14) | À définir |

---

## 9. Questions ouvertes

**Contenu des questions**
- Q1. Faut-il prévoir plus tard des questions à plusieurs bonnes réponses, ou d'autres types (vrai / faux, réponse numérique, appariement) ?
- Q2. Faut-il un niveau de difficulté par question, pour équilibrer les examens à tirage ?
- Q3. La liste des modules est-elle commune à toutes les banques, ou propre à chaque banque ? Une question peut-elle avoir plusieurs modules ?
- Q8. Formats d'illustration acceptés (JPEG, PNG, SVG, PDF ?) et taille maximale ?
- Q9. Statut des questions importées : toujours *brouillon*, toujours *publiée*, ou selon une colonne du fichier ?

**Accès et partage**
- Q4. Une banque peut-elle être partagée entre formateurs ? Avec quels droits : lecture seule, modification, ou les deux au choix ?
- Q5. Inscription libre : options possibles — fermée (comptes créés par l'administrateur seulement), ouverte avec validation de l'administrateur, ouverte sans validation, ouverte sur invitation ou code d'accès. Lesquelles retenir ?
- Q6. Faut-il gérer des groupes d'étudiants (promotions) pour donner l'accès en une fois ? Un formateur voit-il tous les étudiants ou seulement ceux de ses groupes ?

**Passage**
- Q7. Quand le formateur modifie une question déjà utilisée, la correction d'un passage passé montre-t-elle l'ancienne version (fidélité) ou la nouvelle (correction d'une erreur) ?
- Q10. Une session d'étude interrompue peut-elle être reprise, ou chaque session repart-elle de zéro ?
- Q11. Examen libre : quelles valeurs par défaut pour le nombre de questions, la durée et les seuils ? Faut-il des modèles d'examen libre définis par le formateur (par exemple « examen théorique planeur ») ?
- Q12. Lors d'une coupure pendant un examen, le chronomètre continue-t-il (règle la plus stricte) ou s'arrête-t-il ?
- Q13. Le formateur peut-il neutraliser une question erronée après coup, et recalculer les résultats des passages concernés ?
- Q14. Faut-il un temps supplémentaire par étudiant (tiers-temps) ?

**Intégration à Mentor**
- Q15. Intégré à Mentor, le module de la question correspond-il au chapitre de la formation, et les questions sont-elles rattachées aux notions ([PRD Mentor](mentor_prd.md), §3) ?
