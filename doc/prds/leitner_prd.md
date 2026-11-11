# PRD — Module Leitner

| | |
|---|---|
| **Version** | 0.5 (brouillon) |
| **Date** | 11 novembre 2026 |
| **Auteur** | Frédéric |
| **Statut** | À valider — voir §10 « Questions ouvertes » |
| **PRD d'ensemble** | [Mentor](mentor_prd.md), §1.4 « Architecture modulaire » |
| **Module utilisé** | [QCM](qcm_prd.md) |
| **Plan** | [leitner_plan.md](../plans/leitner_plan.md) |

**Évolutions de la version 0.5** : taille d'étude facultative, fixée à la création d'une suite, pour ne travailler qu'un échantillon d'une grosse banque (UC-05, v2).

**Évolutions de la version 0.4** : les banques sont partagées et jamais vidées par l'entraînement (§3) ; reprise des erreurs en fin de séance jusqu'à la première réussite (Q6 tranchée) ; recul d'une boîte sur erreur, retour en boîte 1 au second échec consécutif (§7.2) ; test de placement (UC-15).

**Évolutions de la version 0.3** : 5 boîtes par défaut, paramètre de la suite et non de la banque (Q4 tranchée) ; suite **acquise** et **contrôle d'entretien** par échantillon aléatoire des questions maîtrisées (UC-14, §7.6, Q3 tranchée).

**Évolutions de la version 0.2** : le rythme des révisions est compté en séances, librement choisies par l'étudiant (Q1 tranchée) ; avertissement en dessous de trois séances par semaine (§7.5).

---

## 1. Vision

Le module Leitner fait **mémoriser durablement** des connaissances par répétition espacée, selon la méthode des boîtes de Leitner. L'étudiant s'entraîne sur des **suites de questions** : chaque question est rangée dans une boîte ; une question bien répondue monte d'une boîte et sera reposée moins souvent, une question mal répondue revient dans la première boîte et sera reposée à chaque séance.

Les questions sont de **plusieurs types** (QCM, vrai / faux, énumération, correspondance, valeur numérique, question ouverte en auto-évaluation ou corrigée par l'IA…) et peuvent porter sur une **image** (« Quel est ce monument ? », « Quel pays est marqué sur la carte ? »). L'étudiant travaille sur des **banques prédéfinies** et les complète avec **ses propres questions**.

Le rythme est compté en **séances**, pas en jours : l'idéal est une séance par jour, ou par jour ouvré, mais l'étudiant choisit librement quand il travaille. S'il travaille moins de trois fois par semaine, le système l'en avertit.

L'objectif est de **terminer** une suite : quand toutes ses questions sont maîtrisées, la suite est **acquise**. Un **contrôle d'entretien**, sur un échantillon de questions tirées au hasard, vérifie ensuite rapidement que le sujet est toujours su.

À tout moment, l'étudiant sait **où il en est** : combien de questions sont dans chaque boîte, combien n'ont pas encore été posées, ce que contiendra sa prochaine séance.

Le module est utilisable **seul**. Il réutilise les questions des banques du [module QCM](qcm_prd.md) par l'interface publique de celui-ci. Intégré à Mentor, il fournit les révisions Leitner du parcours (cartes rattachées aux notions, voir [PRD Mentor](mentor_prd.md), UC-10, UC-10b, UC-10c).

### 1.1 Objectifs

- O1 — Faire retenir à long terme un grand nombre de connaissances factuelles et de raisonnements, avec un effort de révision limité et régulier.
- O2 — Concentrer l'effort sur ce que l'étudiant ne sait pas encore, sans reposer inutilement ce qu'il sait.
- O3 — Couvrir des formes de connaissance variées : choix, listes, associations, valeurs, explications, reconnaissance visuelle.
- O4 — Donner à l'étudiant une vision claire et permanente de sa progression.
- O5 — Permettre au formateur de fournir des banques prêtes à l'emploi, et à l'étudiant de les enrichir.

### 1.2 Hors périmètre

- Examens notés, certificats, conditions d'examen : relèvent du module QCM.
- Algorithmes de répétition espacée autres que les boîtes de Leitner (SM-2, FSRS…).
- Questions orales (réponse prononcée et reconnue par la voix) : envisagées plus tard (§9, v2).
- Jeux compétitifs entre étudiants, classements.

---

## 2. Acteurs

| Acteur | Description |
|---|---|
| **Administrateur** | Gère les comptes et les rôles (socle Laraskel). |
| **Formateur** | Crée et maintient les banques prédéfinies ; définit des suites proposées aux étudiants ; traite les signalements. |
| **Étudiant** | Crée ses suites ou adopte celles du formateur ; crée ses propres questions ; fait ses séances ; suit sa progression. |
| **Agent IA** | Corrige les questions ouvertes en donnant un verdict et une justification ; peut aider à rédiger des questions (§10, Q17). |

Comme pour le module QCM, les premiers lots fonctionnent **sans connexion** : un utilisateur unique tient les rôles de formateur et d'étudiant (§9). Les cas d'utilisation sont rédigés avec les rôles cibles.

---

## 3. Glossaire

- **Banque de questions** : ensemble nommé de questions, avec une description. Une banque est **prédéfinie** (créée par un formateur, en lecture seule pour l'étudiant) ou **personnelle** (créée par un étudiant, visible de lui seul).
- **Partage des banques** : une banque est utilisée **simultanément** par plusieurs étudiants et par plusieurs suites. L'entraînement ne la modifie jamais : les boîtes, l'état *en banque* et l'historique appartiennent à la progression de chaque étudiant dans chaque suite.
- **Banque QCM** : banque du module QCM, utilisable comme source d'une suite ; ses questions sont en lecture seule dans Leitner.
- **Question** : élément à mémoriser. Elle comporte :
  - un **type** (§6) qui fixe la forme de la réponse et la manière de la corriger ;
  - un **énoncé**, avec une **illustration facultative** (image, carte, schéma) sur laquelle il peut porter ;
  - la **réponse attendue**, sous la forme propre à son type ;
  - une **explication facultative**, affichée après la réponse ;
  - un **niveau de difficulté** (§10, Q13) ;
  - un **thème facultatif**, en texte libre, qui sert à filtrer et à présenter les statistiques (même principe que le *module* d'une question QCM).
- **Suite** : ensemble de questions sur lequel l'étudiant s'entraîne. Elle a un **nom**, une **description**, une ou plusieurs **banques sources** et des **paramètres** : taille d'étude facultative, nombre de boîtes, nombre minimal de questions en boîte 1, ordre de présentation (§5, UC-05).
- **Boîte** : niveau de mémorisation d'une question dans une suite, de 1 à N. Plus le numéro est élevé, moins la question est posée souvent.
- **Question en banque** : pour un étudiant et une suite, question d'une banque source qui n'a encore jamais été introduite dans **sa** progression. Elle n'est dans aucune de ses boîtes ; la même question peut être en boîte 4 chez un autre étudiant.
- **Introduction** : passage d'une question en banque dans la boîte 1, lorsque la boîte 1 compte moins de questions que le minimum fixé (§7.3).
- **Question maîtrisée** : question bien répondue alors qu'elle était dans la dernière boîte (§7.2, Q3).
- **Suite acquise** : suite dont toutes les questions sont maîtrisées ou suspendues, sans question restant en banque (§7.6).
- **Contrôle d'entretien** : vérification d'une suite acquise sur un échantillon de questions maîtrisées tirées au hasard (UC-14).
- **Question suspendue** : question retirée temporairement des séances par l'étudiant, qui garde sa boîte.
- **Séance** : session d'entraînement sur une suite. Les séances d'une suite sont **numérotées** à partir de 1 ; le numéro décide des boîtes posées (§7.1).
- **Question due** : question qui sera posée à la prochaine séance.
- **Régularité** : nombre de séances faites sur une suite au cours des 7 derniers jours (§7.5).
- **Verdict** : résultat de la correction d'une réponse : *juste* ou *fausse* (et éventuellement *partiellement juste*, Q9), accompagné d'une **justification** pour une correction par l'IA.
- **Auto-évaluation** : l'étudiant compare lui-même sa réponse à la réponse attendue et donne le verdict.
- **Échecs consécutifs** : nombre de réponses fausses d'affilée données à une question, en ne comptant que les réponses qui décident de sa boîte (§7.2).
- **Test de placement** : passage unique sur des questions en banque, qui place directement en boîte haute celles que l'étudiant sait déjà (UC-15).
- **Progression** : état d'une suite pour un étudiant : contenu des boîtes, questions en banque, numéro de la prochaine séance, historique.

---

## 4. Cas d'utilisation — vue d'ensemble

| ID | Cas d'utilisation | Acteur principal | Lot |
|---|---|---|---|
| **A. Banques et questions** |||
| UC-01 | Gérer les banques de questions | Formateur / Étudiant | MVP |
| UC-02 | Gérer les questions | Formateur / Étudiant | MVP (types complémentaires lots 2 et 3) |
| UC-03 | Ajouter ses propres questions à une suite | Étudiant | MVP |
| UC-04 | Importer et exporter une banque | Formateur / Étudiant | Lot 2 |
| **B. Suites** |||
| UC-05 | Créer et paramétrer une suite | Étudiant / Formateur | MVP |
| UC-06 | Agir sur les questions d'une suite | Étudiant | Lot 2 |
| UC-07 | Réinitialiser une suite | Étudiant | Lot 2 |
| **C. Entraînement** |||
| UC-08 | Faire une séance | Étudiant | MVP |
| UC-09 | Contester ou corriger un verdict | Étudiant | Lot 2 (IA lot 3) |
| UC-10 | Signaler une question | Étudiant | v1 |
| **D. Suivi** |||
| UC-11 | Consulter l'état d'une suite | Étudiant | MVP |
| UC-12 | Consulter l'historique et les statistiques | Étudiant / Formateur | Lot 2 (vue formateur v1) |
| UC-14 | Faire un contrôle d'entretien | Étudiant | Lot 2 |
| UC-15 | Passer un test de placement | Étudiant | Lot 2 |
| **E. Comptes et accès** |||
| UC-13 | Donner accès aux banques et aux suites | Formateur | v1 |

---

## 5. Cas d'utilisation détaillés

### A. Banques et questions

#### UC-01 — Gérer les banques de questions

- **Acteurs** : Formateur (banques prédéfinies), Étudiant (banques personnelles).
- **Scénario** : créer une banque (nom, description), la renommer, la dupliquer, la supprimer. La liste des banques affiche pour chacune le nombre de questions, au total, par type, par niveau de difficulté et par thème.
- **Variante** : l'étudiant duplique une banque prédéfinie pour en faire une banque personnelle qu'il peut modifier.
- **Règles**
  - La suppression d'une banque demande une confirmation qui indique le nombre de questions et de suites concernées. Les questions de la banque quittent les boîtes des suites qui l'utilisent.
  - Un étudiant ne modifie jamais une banque prédéfinie ; il peut la dupliquer ou signaler une question (UC-10).

#### UC-02 — Gérer les questions

- **Acteurs** : Formateur, Étudiant (dans ses banques personnelles).
- **Scénario nominal**
  1. Dans une banque, l'acteur choisit le type de question (§6).
  2. Il saisit l'énoncé, l'illustration facultative, la réponse attendue sous la forme propre au type, l'explication facultative, le niveau de difficulté et le thème.
  3. Il prévisualise la question telle qu'elle sera posée, y compris sa correction.
  4. Il l'enregistre ; la question est aussitôt utilisable.
- **Variantes**
  - 1a. Modifier une question existante. Si la question figure déjà dans des boîtes, le système demande si la modification est **mineure** (correction de forme : la question garde sa boîte) ou **de fond** (la question revient en boîte 1 dans toutes les suites qui l'utilisent).
  - 1b. Supprimer une question, après confirmation qui indique le nombre de suites où elle est en boîte.
  - 1c. Rechercher et filtrer les questions : texte, type, difficulté, thème, présence d'une illustration.
  - 2a. L'étudiant transforme une question en question **inversée** (« capitale → pays » devient « pays → capitale ») lorsque le type s'y prête *(v2)*.
- **Règles**
  - Le système vérifie la cohérence propre au type avant l'enregistrement (§6) ; un message dit précisément quel champ corriger.
  - Le système signale les doublons probables (même énoncé dans la même banque).
  - Une illustration a un **texte alternatif** obligatoire (accessibilité).
  - Formats d'image acceptés et taille maximale : alignés sur le module QCM (Q18).

#### UC-03 — Ajouter ses propres questions à une suite

- **Acteur** : Étudiant.
- **Scénario nominal**
  1. Depuis une suite, l'étudiant choisit « Nouvelle question ».
  2. Il saisit la question (UC-02) ; elle est rangée dans sa **banque personnelle** associée à la suite, créée au besoin.
  3. Il choisit de la placer **directement en boîte 1** ou de la laisser **en banque**, à introduire comme les autres (§7.3).
- **Variante** : pendant une séance, après une question, l'étudiant crée une question complémentaire (« je veux aussi retenir ceci ») sans quitter la séance.
- **Règle** : les questions de l'étudiant sont privées (Q14).

#### UC-04 — Importer et exporter une banque

- **Acteurs** : Formateur, Étudiant (banques personnelles).
- **Scénario** : export d'une banque, ou d'une sélection, en fichier d'échange (§8) ; import dans une banque existante ou nouvelle, avec **rapport avant import** (questions valides, lignes en erreur avec motif et numéro de ligne, doublons probables, illustrations manquantes), choix du traitement des doublons, puis rapport final.
- **Règles**
  - Une ligne en erreur n'empêche pas l'import des autres ; aucune question n'est importée à moitié.
  - Un fichier exporté se réimporte sans modification.
  - Le format reprend celui du module QCM pour les questions QCM, de sorte qu'un fichier de banque QCM s'importe dans Leitner.

### B. Suites

#### UC-05 — Créer et paramétrer une suite

- **Acteurs** : Étudiant ; Formateur pour une suite proposée aux étudiants.
- **Scénario nominal**
  1. L'acteur saisit le **nom** et la **description** de la suite.
  2. Il choisit une ou plusieurs **banques sources** : banques prédéfinies, banques personnelles, banques QCM. Il peut restreindre chaque source à certains thèmes ou niveaux de difficulté.
  3. Il fixe les paramètres, pré-remplis avec les valeurs par défaut :
     - **taille d'étude** *(v2)* : facultative ; par défaut, toutes les questions des sources. Si elle est fixée (ex. 300 questions sur une banque de 1 200), le système tire ce nombre de questions au hasard parmi les sources et la suite ne porte que sur elles ;
     - **nombre de boîtes** (défaut : 5 ; de 3 à 10) ; le système affiche, pour la valeur choisie, la durée estimée pour maîtriser une question (§7.2) ;
     - **nombre minimal de questions en boîte 1** (défaut : 10, Q4) ;
     - **ordre de présentation** des questions d'une séance : *mélangé* (défaut), *boîte 1 d'abord*, *boîtes hautes d'abord* ;
     - **reprise des erreurs en fin de séance** : oui (défaut) / non (§7.2) ;
     - **boîte d'arrivée après un test de placement** réussi (défaut : 3, Q23).
  4. Le système crée la suite et sa progression : toutes les questions sont *en banque*, aucune séance n'a eu lieu. La première introduction aura lieu au début de la première séance.
- **Variantes**
  - 1a. L'étudiant **adopte** une suite proposée par le formateur : il reçoit sa propre progression ; il peut changer l'ordre de présentation, mais pas les sources.
  - 3a. Modifier les paramètres d'une suite en cours :
    - réduire le nombre de boîtes : les questions des boîtes supprimées vont dans la nouvelle dernière boîte ;
    - augmenter le nombre de boîtes : les questions restent dans leur boîte ;
    - ajouter une source : ses questions arrivent *en banque* ;
    - retirer une source : après confirmation, ses questions quittent les boîtes ; la progression des autres questions est conservée.
- **Règles**
  - Une même question peut figurer dans plusieurs suites ; sa boîte est propre à chaque suite (Q15).
  - L'échantillon tiré pour la taille d'étude est fixé à la création de la suite : les questions non tirées ne font pas partie de la suite, ne comptent pas *en banque* et ne sont pas posées aux contrôles d'entretien. La suite est acquise quand l'échantillon est maîtrisé (§7.6). Pour travailler d'autres questions, l'étudiant crée une nouvelle suite.
  - Le nombre de boîtes est un paramètre de la **suite**, jamais de la banque : une même banque peut servir à deux suites qui ont des nombres de boîtes différents (ex. révision rapide avant un examen, mémorisation durable).

#### UC-06 — Agir sur les questions d'une suite

- **Acteur** : Étudiant.
- **Scénario** : depuis la liste des questions d'une suite (filtrable par boîte, thème, difficulté, état), l'étudiant peut, sur une question ou une sélection :
  - **introduire** maintenant des questions en banque, au-delà du minimum ;
  - **suspendre** ou **réactiver** une question (elle garde sa boîte) ;
  - **déclarer connue** une question en banque : elle est placée directement dans une boîte choisie, ou marquée maîtrisée ;
  - **remettre en boîte 1** ;
  - **réintroduire** une question maîtrisée.
- **Règle** : chaque action affiche son résultat (« 5 questions remises en boîte 1 »).

#### UC-07 — Réinitialiser une suite

- **Acteur** : Étudiant.
- **Scénario** : après confirmation indiquant ce qui sera perdu, toutes les questions repassent *en banque* et la numérotation des séances repart à 1. L'historique des séances passées est conservé, marqué comme antérieur à la réinitialisation.

### C. Entraînement

#### UC-08 — Faire une séance

- **Acteur** : Étudiant.
- **Préconditions** : la suite a au moins une question dans ses boîtes ou en banque.
- **Scénario nominal**
  1. L'étudiant ouvre une suite. Le système affiche l'état de la suite (UC-11) et la composition de la prochaine séance : numéro de séance, boîtes posées, nombre de questions par boîte.
  2. Il lance la séance. Si la boîte 1 compte moins de questions que le minimum, le système **introduit** des questions en banque (§7.3) et l'indique (« 4 nouvelles questions »).
  3. Le système compose la séance : toutes les questions de la boîte 1 et celles des boîtes dues (§7.1), sauf les questions suspendues, dans l'ordre de présentation choisi.
  4. Pour chaque question :
     1. l'énoncé et l'éventuelle illustration s'affichent ; la boîte d'origine est indiquée discrètement ;
     2. l'étudiant répond sous la forme propre au type (§6) ;
     3. le système affiche le **verdict**, la réponse attendue, l'explication et, pour une correction IA, la justification ;
     4. la question change de boîte selon le verdict (§7.2).
  5. En permanence, le système affiche la position dans la séance (question 7 / 23) et le taux de bonnes réponses de la séance.
  6. En fin de séance, le système affiche un **bilan** : nombre de questions posées, réussies, échouées ; mouvements entre boîtes ; nouvelles questions introduites ; questions maîtrisées ; liste des questions échouées avec leur explication ; composition prévue de la prochaine séance.
- **Variantes**
  - 2a. **Plus de questions à introduire** : il ne reste plus assez de questions *en banque* dans la progression de l'étudiant pour atteindre le minimum (les banques elles-mêmes ne changent pas). Le système introduit celles qui restent et l'indique ; il suggère d'ajouter des questions ou une source.
  - 3a. **Rien à poser** (toutes les questions sont maîtrisées ou suspendues) : la suite est acquise ; le système l'indique et propose un contrôle d'entretien (UC-14), de réintroduire des questions, d'ajouter une source ou de réinitialiser la suite.
  - 4a. **Passer** une question : elle reste dans sa boîte et sera reposée à la prochaine séance.
  - 4b. **Erreur de manipulation** : juste après un verdict, l'étudiant peut annuler sa dernière réponse et répondre de nouveau.
  - 4c. **Reprise des erreurs** (active par défaut) : chaque question échouée dans la séance est reposée en fin de séance, jusqu'à être réussie une fois ; seule la première réponse de la séance compte pour la boîte (§7.2).
  - 4d. **Correction IA indisponible** : la question ouverte bascule en auto-évaluation, et le système le signale.
  - 6a. **Séance interrompue** (fermeture, coupure, abandon) : les réponses déjà données sont conservées et leurs mouvements de boîte appliqués. À la réouverture, le système propose de **reprendre** la séance là où elle s'est arrêtée ; la séance suivante ne commence qu'une fois celle-ci terminée ou abandonnée (Q7).
- **Règles**
  - Une séance compte dans la numérotation dès qu'une question y a reçu une réponse.
  - Chaque réponse est enregistrée dès qu'elle est donnée.
  - Les réponses aux propositions (QCM, correspondance) sont mélangées à chaque affichage, sauf interdiction sur la question.

#### UC-09 — Contester ou corriger un verdict

- **Acteur** : Étudiant.
- **Scénario** : après un verdict automatique ou IA, l'étudiant indique qu'il le conteste, avec un commentaire facultatif (« synonyme accepté », « orthographe différente »).
- **Règles**
  - Pour une correction IA ou une réponse saisie (énumération, réponse courte), l'étudiant peut retourner le verdict ; la question change alors de boîte en conséquence *(dernier mot à l'étudiant, Q12)*.
  - Toute contestation est journalisée et visible du propriétaire de la question, pour améliorer la réponse attendue (variantes acceptées, critères).
  - Une question QCM ou vrai / faux ne se conteste pas : on la signale (UC-10).

#### UC-10 — Signaler une question

- **Acteur** : Étudiant.
- **Scénario** : pendant une séance ou depuis la liste des questions, l'étudiant signale une question prédéfinie qu'il pense erronée ou ambiguë, avec un commentaire. Le formateur la corrige ou classe le signalement ; l'étudiant est informé de la suite donnée.
- **Règle** : pour une question QCM, le signalement est transmis au module QCM (UC-15 du [PRD QCM](qcm_prd.md)).

### D. Suivi

#### UC-11 — Consulter l'état d'une suite

- **Acteur** : Étudiant.
- **Scénario** : à tout moment, depuis la liste des suites et en tête de chaque suite, l'étudiant voit :
  - le **nombre de questions par boîte**, sous forme de barres ;
  - le nombre de questions **en banque** (jamais posées), par niveau de difficulté ;
  - le nombre de questions **maîtrisées** et **suspendues** ;
  - le **numéro de la prochaine séance**, les boîtes qu'elle posera et son nombre de questions ;
  - l'état **acquise** avec sa date, la date et le résultat du dernier contrôle d'entretien et la date du prochain ;
  - la date de la dernière séance et la **régularité** (séances des 7 derniers jours), avec l'avertissement prévu au §7.5.
- **Variante** : un clic sur une boîte ouvre la liste de ses questions (UC-06).
- **Règle** : la somme *en banque + boîtes + maîtrisées + suspendues* est égale au nombre de questions de la suite, et cette égalité est visible.

#### UC-12 — Consulter l'historique et les statistiques

- **Acteurs** : Étudiant ; Formateur à partir de v1, pour les suites qu'il a proposées.
- **Scénario** : l'étudiant voit l'historique de ses séances (date, durée, questions posées, taux de réussite, mouvements), l'évolution de la répartition dans les boîtes au fil des séances, ses taux par thème et par type, et ses **questions difficiles** (les plus souvent échouées).
- **Variante** : depuis la liste des questions difficiles, l'étudiant les remet en boîte 1, ou lance une séance d'entraînement libre sur elles, sans effet sur les boîtes.
- **Côté formateur** : taux de réussite et de contestation par question, pour repérer les questions mal rédigées.

#### UC-14 — Faire un contrôle d'entretien

- **Acteur** : Étudiant.
- **Préconditions** : la suite est acquise.
- **Déclencheurs** : l'étudiant le demande, ou le système le propose quand le délai depuis l'acquisition ou depuis le dernier contrôle est atteint (§7.6).
- **Scénario nominal**
  1. Le système tire au hasard un **échantillon** de questions maîtrisées (taille par défaut au §7.6) et l'annonce (« contrôle de 20 questions »).
  2. Les questions sont posées comme dans une séance (UC-08, étape 4), sans effet sur la numérotation des séances.
  3. En fin de contrôle, le système affiche le taux de réussite, le compare au seuil et conclut :
     - **acquis confirmé** : la date du contrôle est enregistrée et le prochain contrôle est planifié ;
     - **à consolider** : la suite n'est plus acquise.
  4. Dans les deux cas, chaque question échouée quitte l'état maîtrisé, revient dans la **dernière boîte** et reprend le circuit normal ; si elle est de nouveau échouée à sa présentation suivante, elle revient en boîte 1 (§7.2).
- **Variantes**
  - 1a. L'étudiant restreint l'échantillon à certains thèmes.
  - 3a. L'étudiant interrompt le contrôle : les réponses données valent pour les questions concernées (les échecs reviennent dans la dernière boîte), mais aucune conclusion n'est tirée.
- **Règle** : l'historique (UC-12) conserve chaque contrôle : date, taille de l'échantillon, taux, conclusion.

#### UC-15 — Passer un test de placement

- **Acteur** : Étudiant.
- **Objectif** : ne pas faire refaire tout le circuit des boîtes à un étudiant qui connaît déjà une partie du sujet.
- **Déclencheurs** : à la création ou à l'adoption d'une suite, le système le propose ; l'étudiant peut aussi le lancer à tout moment sur les questions encore en banque.
- **Scénario nominal**
  1. L'étudiant choisit le périmètre : toutes les questions en banque, certains thèmes, ou un nombre maximal de questions.
  2. Le système pose chaque question une fois, comme dans une séance, sans reprise des erreurs et sans effet sur la numérotation des séances.
  3. Une question **réussie** est placée directement dans la boîte d'arrivée de la suite (défaut : boîte 3, UC-05). Une question **échouée** reste *en banque* et sera introduite normalement (§7.3).
  4. En fin de test, le système affiche le nombre de questions placées, par thème, et la nouvelle répartition dans les boîtes.
- **Variantes**
  - 2a. L'étudiant interrompt le test : les questions déjà répondues sont placées ; il peut reprendre plus tard sur les questions restantes.
  - 2b. L'étudiant passe une question : elle reste en banque.
- **Règles**
  - Pour limiter l'effet du hasard, une question QCM ou vrai / faux réussie au test est placée une boîte plus bas que les autres types *(choix provisoire, Q23)*.
  - Le test de placement est enregistré dans l'historique (UC-12).

### E. Comptes et accès

#### UC-13 — Donner accès aux banques et aux suites

- **Acteur** : Formateur.
- **Scénario** : le formateur ouvre une banque prédéfinie ou une suite proposée à des étudiants ; ceux-ci la voient dans leur liste et peuvent l'utiliser. Le retrait de l'accès retire la banque des sources de leurs suites, après les en avoir informés ; leur historique reste consultable.

---

## 6. Types de questions

Pour chaque type : ce que contient la question, comment l'étudiant répond, comment le verdict est rendu.

| Type | Contenu de la question | Réponse de l'étudiant | Verdict | Lot |
|---|---|---|---|---|
| **QCM** | Une bonne réponse, plusieurs mauvaises avec explication facultative (voir [PRD QCM](qcm_prd.md), §3) | Choisit une proposition | Automatique | MVP |
| **Vrai / faux** | Une affirmation et sa valeur de vérité | Vrai ou Faux | Automatique | MVP |
| **Auto-évaluation** (recto / verso) | Une question et une réponse de référence | Réfléchit (ou écrit pour lui-même), puis « Voir la réponse » | L'étudiant : « Je savais » / « Je ne savais pas » | MVP |
| **Valeur numérique** | La valeur attendue, l'unité facultative, une tolérance (absolue ou en %, ou aucune) | Saisit un nombre | Automatique : juste si l'écart est dans la tolérance | MVP |
| **Énumération** | La liste des éléments acceptables, le nombre demandé (ex. « 8 capitales européennes » parmi 44), ordre imposé ou non, variantes acceptées par élément | Saisit des éléments | Automatique : chaque élément est reconnu ou non ; les doublons ne comptent pas | Lot 2 |
| **Correspondance** | Des paires (capitale ↔ pays), avec éventuellement des leurres d'un côté ; nombre de paires présentées à chaque fois (tirées au hasard si la liste est longue) | Associe chaque élément de gauche à un élément de droite | Automatique, paire par paire | Lot 2 |
| **Question ouverte corrigée par l'IA** | Une question, une réponse de référence, des critères facultatifs (éléments obligatoires, tolérances, réponses acceptables) | Saisit un texte libre | Agent IA : verdict et justification courte | Lot 3 |
| **Réponse courte** | Une réponse attendue et ses variantes acceptées | Saisit quelques mots | Automatique, avec tolérance (Q10) | Lot 2 |
| **Texte à trous** | Un texte dont certains mots sont masqués | Complète les trous | Automatique, trou par trou | v2 |
| **Classement** | Des éléments et leur ordre attendu (chronologie, étapes d'une procédure) | Remet les éléments dans l'ordre | Automatique | v2 |
| **Désignation sur image** | Une image et une ou plusieurs zones attendues | Clique sur l'image | Automatique : juste si le clic est dans la zone | v2 |

**Règles communes**

- Le verdict d'une énumération, d'une correspondance, d'un texte à trous ou d'un classement est *juste* si tout est juste. Le détail (éléments reconnus, paires justes) est toujours affiché. Le traitement d'une réponse **partiellement juste** est à trancher (Q9).
- Les réponses saisies sont comparées sans tenir compte des majuscules, des accents ni des espaces en trop ; la tolérance aux fautes de frappe est à trancher (Q10).
- La correction par l'IA juge le **fond** (notion, valeurs, unités), pas la formulation ni l'orthographe. Elle ne voit que la question, la réponse de référence, les critères et la réponse de l'étudiant.
- Une question d'auto-évaluation de type « Vous rappelez-vous comment démontrer le théorème de Pythagore ? » est la forme simple de la question ouverte : l'étudiant juge seul, sans IA.
- Toute question peut porter une illustration, dans l'énoncé (« Quel est ce monument ? ») ou dans les propositions (QCM, correspondance) *(lot 2)*.

---

## 7. Règles de la méthode Leitner

### 7.1 Boîtes posées à une séance

À la séance numéro *n*, la boîte *k* est posée si *n* est un multiple de 2<sup>k−1</sup> :

- la boîte 1 est posée à **toutes** les séances ;
- la boîte 2, **une séance sur deux** ;
- la boîte 3, **une sur quatre** ; la boîte 4, une sur huit ; et ainsi de suite.

| Séance | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | … | 16 |
|---|---|---|---|---|---|---|---|---|---|---|
| Boîtes posées | 1 | 1, 2 | 1 | 1, 2, 3 | 1 | 1, 2 | 1 | 1 à 4 | … | 1 à 5 |

Le rythme est compté en **séances**, pas en jours : l'étudiant choisit quand il travaille, et le numéro de séance n'avance que lorsqu'il fait une séance (Q1, tranchée). Plusieurs séances le même jour comptent chacune. Le mode de calcul (numéro de séance commun à la suite ou compteur propre à chaque question) est à confirmer (Q2).

### 7.2 Mouvements entre boîtes

- **Réponse juste** : la question monte d'une boîte (de la boîte 1 à la boîte 2, etc.).
- **Réponse fausse** : la question **recule d'une boîte** ; une question de la boîte 1 y reste.
- **Second échec consécutif** : si la réponse précédente qui a décidé de sa boîte était déjà fausse, la question **revient en boîte 1**, quelle que soit sa boîte.
- **Seule la première réponse d'une séance compte** pour la boîte et pour les échecs consécutifs. Avec la reprise des erreurs (défaut), une question échouée est reposée en fin de séance jusqu'à être réussie une fois ; ces réponses de reprise ne changent pas sa boîte.
- **Réponse juste dans la dernière boîte** : la question est **maîtrisée** et ne sera plus posée (Q3).
- **Question passée** ou non posée : elle reste dans sa boîte.

**Durée pour maîtriser une question** (sans erreur, à 5 séances par semaine) : environ 2<sup>N−1</sup> séances pour N boîtes. C'est la durée estimée affichée lors du choix du nombre de boîtes (UC-05).

| Boîtes | Séances | Durée | Dernier écart avant la maîtrise |
|---|---|---|---|
| 4 | 8 | ≈ 2 semaines | 4 séances |
| **5 (défaut)** | 16 | ≈ 3 semaines | 8 séances (≈ 1,5 semaine) |
| 6 | 32 | ≈ 6 semaines | 16 séances (≈ 3 semaines) |
| 7 | 64 | ≈ 13 semaines | 32 séances (≈ 6 semaines) |

Chaque erreur fait reculer la question et allonge la durée : avec les introductions successives, une banque se termine en **2 à 3 mois** environ avec 5 boîtes. La durée de rétention au-delà de la maîtrise est assurée par les contrôles d'entretien (§7.6), plutôt que par des boîtes supplémentaires.

### 7.3 Introduction des nouvelles questions

- Au début d'une séance, si la boîte 1 compte moins de questions que le minimum de la suite, le système complète avec des questions **en banque**.
- Les questions sont introduites **par niveau de difficulté croissant** : toutes les questions faciles avant les moyennes, toutes les moyennes avant les difficiles. Au sein d'un même niveau, le tirage est au hasard (Q8).
- Les questions sans niveau de difficulté sont traitées comme de niveau moyen.
- Une question ajoutée par l'étudiant directement en boîte 1 (UC-03) compte dans le minimum.

### 7.4 Ordre de présentation

- **Mélangé** (défaut) : les questions de toutes les boîtes posées sont mélangées.
- **Boîte 1 d'abord** : on commence par ce qui est le moins su.
- **Boîtes hautes d'abord** : on commence par vérifier ce qui est censé être su.
- L'étudiant change l'ordre au lancement de la séance, ou dans les paramètres de la suite.

### 7.5 Régularité

- Le rythme conseillé est d'**une séance par jour**, ou par jour ouvré.
- Si une suite compte **moins de trois séances sur les 7 derniers jours**, un **avertissement** s'affiche dans l'état de la suite (UC-11) et à l'ouverture de la suite (UC-08). Il indique le nombre de séances faites et le rythme conseillé.
- L'avertissement n'apparaît qu'à partir de 7 jours après la première séance de la suite, et ne s'affiche pas pour une suite acquise.
- L'avertissement ne bloque rien : l'étudiant reste libre de son rythme.

### 7.6 Suite acquise et contrôles d'entretien

- Une suite est **acquise** dès que toutes ses questions sont maîtrisées ou suspendues et qu'aucune ne reste en banque. La date d'acquisition est enregistrée et affichée (UC-11).
- Ajouter des questions à une suite acquise (nouvelle source, nouvelles questions) lui fait perdre cet état jusqu'à ce qu'elles soient maîtrisées.
- **Taille de l'échantillon** : 20 questions par défaut, ou toutes les questions maîtrisées s'il y en a moins (Q22). Repère : si *n* questions tirées au hasard sont toutes réussies, le taux d'oubli réel est inférieur à 3/*n* avec 95 % de confiance (20 questions : moins de 15 % ; 30 questions : moins de 10 %).
- **Seuil** : 90 % de bonnes réponses par défaut (Q22).
- **Délais** : le premier contrôle est proposé 1 mois après l'acquisition, le suivant 3 mois après un contrôle réussi, puis tous les 6 mois (Q22). Un contrôle échoué n'en planifie pas d'autre : la suite reprend le circuit normal jusqu'à être de nouveau acquise.
- Les contrôles proposés apparaissent dans l'état de la suite (UC-11) ; ils ne bloquent rien.

---

## 8. Fichier d'échange

- Le format est celui du module QCM ([PRD QCM](qcm_prd.md), §6.1), **étendu** : une colonne *type* et des colonnes propres à chaque type (éléments acceptables, paires, valeur et tolérance…), plus la difficulté et le thème.
- **CSV** seul (sans illustrations) ou **ZIP** (CSV, illustrations, fichier de paramètres de la banque ; pour une suite, ses paramètres).
- Un fichier de banque QCM s'importe sans modification : ses questions sont de type QCM.
- La progression d'un étudiant (boîtes, historique) n'est pas exportée avec la banque ; son export relève des droits RGPD (§9).
- Le format est décrit dans la documentation utilisateur, avec un fichier exemple par type de question.

---

## 9. Exigences non fonctionnelles et découpage

### 9.1 Exigences non fonctionnelles

- **Utilisation autonome** : le module fonctionne sans les autres modules de Mentor. Sans le module QCM, seules les banques Leitner sont disponibles comme sources. Sans interface IA configurée, les questions ouvertes passent en auto-évaluation.
- **Sans connexion** jusqu'au lot 3 : un utilisateur unique, sur un poste non exposé à Internet (même principe que le module QCM, §1.3 de son PRD).
- **Plateforme** : application web responsive ; les séances sont confortables sur téléphone (gros boutons, saisie courte, image zoomable).
- **Clavier** : une séance se fait entièrement au clavier (raccourcis pour vrai / faux, choix QCM, « voir la réponse », « je savais / je ne savais pas », question suivante).
- **Fiabilité** : chaque réponse et chaque mouvement de boîte sont enregistrés immédiatement ; une coupure ne fait perdre aucune réponse.
- **Performances** : passage d'une question à la suivante en moins d'une seconde ; verdict IA en moins de 10 secondes, avec indicateur d'attente ; suite de 5 000 questions sans ralentissement perceptible.
- **IA** : seules la question, la réponse attendue, les critères et la réponse de l'étudiant sont envoyés à l'agent, sans donnée d'identité ; le fournisseur passe par l'interface LLM du socle Laraskel.
- **Accessibilité** : texte alternatif obligatoire des illustrations, contrastes suffisants, taille de police ajustable, verdict signalé autrement que par la couleur.
- **RGPD** (à partir de v1, avec les comptes) : export et suppression de la progression et des questions personnelles d'un étudiant sur demande ; hébergement en UE.
- **Langue** : français, interface traduisible.

### 9.2 Découpage en lots

| Lot | Contenu | Critère de sortie |
|---|---|---|
| **MVP** | UC-01, UC-02 (types QCM, vrai / faux, auto-évaluation, valeur numérique), UC-03, UC-05, UC-08, UC-11 ; règles §7 complètes (boîtes, introduction par difficulté, ordre de présentation, avertissement de régularité) ; sans connexion | L'utilisateur crée une banque de questions de quatre types, une suite de 5 boîtes, enchaîne au moins 8 séances, et l'état affiché (boîtes, banque, prochaine séance) correspond aux règles §7 |
| **Lot 2 — Types et outils** | Types énumération, correspondance, réponse courte ; UC-14 suite acquise et contrôle d'entretien (§7.6) ; UC-15 test de placement ; illustrations dans les propositions ; UC-04 import / export ; UC-06 actions sur les questions ; UC-07 réinitialisation ; UC-09 contestation (types automatiques) ; UC-12 historique et statistiques étudiant | L'utilisateur importe une banque de capitales, s'entraîne en énumération et en correspondance, conteste un verdict, et retrouve ses séances dans l'historique |
| **Lot 3 — IA et banques QCM** | Question ouverte corrigée par l'IA, avec bascule en auto-évaluation ; UC-09 pour l'IA ; banques QCM comme sources (UC-05) via l'interface publique du module QCM | Une suite mêle une banque QCM et des questions ouvertes ; l'IA rend verdict et justification ; IA coupée, la séance continue en auto-évaluation |
| **v1** | Comptes et rôles du socle ; banques prédéfinies et personnelles distinctes ; suites proposées et adoptées (UC-05, 1a) ; UC-10 signalements ; UC-12 vue formateur ; UC-13 accès | Un formateur propose une suite à deux étudiants ; chacun la complète de ses propres questions et progresse indépendamment ; le formateur voit les questions les plus contestées |
| **v2** | Taille d'étude par échantillon (UC-05) ; texte à trous, classement, désignation sur image ; questions inversées (UC-02, 2a) ; génération de questions par l'IA (Q17) ; import d'autres formats (Q16) ; hors ligne (Q19) ; rappels (Q20) ; questions orales | À définir |

---

## 10. Questions ouvertes

**Rythme et boîtes**

- ~~Q1. Rythme des révisions : en séances, en jours, ou en séances avec au plus une séance comptée par jour ?~~ **Tranché** : en séances, librement choisies par l'étudiant, sans limite par jour ; avertissement en dessous de trois séances par semaine (§7.1, §7.5). Repris dans le [PRD Mentor](mentor_prd.md) (UC-10).
- Q2. Calcul de l'échéance — options : (a) **numéro de séance commun** (choix provisoire, §7.1) : simple et prévisible, mais une question arrivée en boîte 2 juste avant une séance paire est reposée dès la séance suivante ; (b) **compteur propre à chaque question** : la question est reposée exactement 2<sup>k−1</sup> séances après son arrivée dans la boîte *k*, la charge par séance est plus irrégulière.
- ~~Q3. Dernière boîte : la question maîtrisée sort-elle du circuit, reste-t-elle dans la dernière boîte, ou revient-elle de temps en temps ?~~ **Tranché** : elle est maîtrisée et sort du circuit ; la suite acquise est vérifiée par des contrôles d'entretien sur échantillon (UC-14, §7.6).
- ~~Q4. Nombre de boîtes par défaut ?~~ **Tranché** : 5 boîtes (environ 2 à 3 mois pour terminer une banque), modifiable ; paramètre de la suite, pas de la banque (UC-05, §7.2).
- Q4b. Nombre minimal de questions en boîte 1 par défaut : 10 (provisoire) ?
- Q5. Taille d'une séance — options : (a) **aucune limite** : toutes les questions dues sont posées (choix provisoire) ; (b) **plafond** configurable (nombre de questions ou durée) ; les questions non posées restent dues et passent en priorité à la séance suivante.
- ~~Q6. Reprise des erreurs dans la séance ?~~ **Tranché** : oui par défaut, désactivable par suite ; la question échouée est reposée en fin de séance jusqu'à la première réussite, sans effet sur sa boîte (§7.2).
- Q7. Séance interrompue — options : (a) **reprise** proposée, la séance suivante n'est pas ouverte avant (choix provisoire) ; (b) la séance interrompue est close, les questions non posées restent dues à la suivante.
- Q8. Ordre d'introduction au sein d'un même niveau de difficulté — options : (a) au hasard (choix provisoire) ; (b) dans l'ordre de la banque (utile pour une progression construite par le formateur) ; (c) au choix, par suite.

**Types et correction**

- Q9. Réponse **partiellement juste** (énumération, correspondance, IA) — options : (a) comptée fausse, avec recul d'une boîte (choix provisoire, §7.2) ; (b) la question reste dans sa boîte ; (c) juste au-delà d'un seuil configurable (ex. 80 % des éléments). Rejoint la Q18 du PRD Mentor.
- Q10. Tolérance des réponses saisies — options : (a) exacte, aux majuscules, accents et espaces près (choix provisoire) ; (b) tolérance de quelques fautes de frappe selon la longueur du mot ; (c) jugement par l'IA ; (d) au choix sur chaque question.
- Q11. Où vivent les nouveaux types de questions — options : (a) **dans le module Leitner** (choix provisoire) : le module QCM reste limité aux questions à choix unique ; (b) **dans le module QCM** (réponse à sa Q1), et Leitner ne gère que les boîtes ; (c) dans un **module commun de questions** dont QCM et Leitner dépendent.
- Q12. Contestation d'un verdict IA ou automatique : l'étudiant a-t-il le dernier mot (choix provisoire, UC-09), ou la contestation est-elle seulement journalisée ? Rejoint la Q17 du PRD Mentor.
- Q13. Niveaux de difficulté — options : (a) trois niveaux, facile / moyen / difficile (choix provisoire) ; (b) cinq niveaux ; (c) valeur numérique libre. Le niveau doit-il pouvoir être ajusté automatiquement d'après les taux de réussite observés ?

**Banques et partage**

- Q14. Les questions créées par un étudiant restent-elles privées, ou peut-il les proposer au formateur pour la banque prédéfinie ? Rejoint la Q19 du PRD Mentor.
- Q15. Une question présente dans plusieurs suites — options : (a) boîte propre à chaque suite (choix provisoire, UC-05) ; (b) boîte commune : une réponse dans une suite fait bouger la question dans les autres.
- Q16. Import depuis d'autres outils (Anki, Quizlet, simple tableur à deux colonnes question / réponse) ?
- Q17. L'agent IA peut-il proposer des questions à partir d'un texte ou d'un document fourni par l'étudiant, et avec quelle validation ? En intégration Mentor, cette fonction relève du Coach IA (UC-10c du PRD Mentor).
- Q18. Formats d'image et taille maximale : alignés sur la Q8 du PRD QCM ?

- Q22. Paramètres du contrôle d'entretien : taille de l'échantillon (20 provisoire, ou un pourcentage de la suite ?), seuil (90 % provisoire), délais (1, 3 puis 6 mois provisoires) ; sont-ils réglables par suite ?

- Q23. Test de placement : boîte d'arrivée par défaut (3 provisoire) ; placer une boîte plus bas les questions devinables (QCM, vrai / faux) ? Faut-il plutôt un bouton « j'ai deviné » ?

**Usage**

- Q19. Faut-il pouvoir faire ses séances hors connexion, sur téléphone ? Rejoint la Q14 du PRD Mentor.
- Q20. En plus de l'avertissement affiché (§7.5), faut-il des rappels envoyés (notification, e-mail) quand l'étudiant n'a pas fait de séance depuis un certain temps ? Avec quel délai par défaut ?

**Intégration à Mentor**

- Q21. Correspondance des termes : une *question* Leitner est-elle la *carte Leitner* du PRD Mentor, rattachée à une notion ; une *suite* correspond-elle à une leçon, un chapitre ou une formation ?
