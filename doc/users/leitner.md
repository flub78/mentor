# Module Leitner — guide d'utilisation

Le module Leitner fait mémoriser des connaissances par répétition espacée. Il s'ouvre sur `/leitner`, ou depuis le lien **Module Leitner** du menu du module QCM. Dans cette première version, il n'y a pas de connexion : toute personne qui accède à l'application peut tout faire.

## Banques et questions

Les banques et leurs questions sont communes aux modules Leitner et QCM : le menu **Banques de questions** est décrit dans le [guide des banques](banques.md). Vous y trouverez comment créer une banque, saisir une question de chaque type (QCM, vrai / faux, auto-évaluation, valeur numérique), choisir sa difficulté, la modifier, la déplacer d'une banque à l'autre, et importer ou exporter une banque.

Le module Leitner pose tous les types de questions. Deux points le concernent directement :

- la **difficulté** décide de l'ordre d'arrivée des nouvelles questions dans une suite : d'abord les faciles, puis les moyennes, puis les difficiles ; une question sans difficulté compte comme moyenne ;
- une **modification de fond** d'une question en cours de révision la remet en boîte 1 dans toutes les suites, et la **déplacer** vers une banque qui n'est pas une source de la suite l'en retire.

## Créer une suite

Une suite est ce sur quoi vous révisez : une ou plusieurs banques, et la façon de les réviser.

1. Dans le menu, ouvrez **Suites de révision**, puis cliquez sur **Créer**.
2. Donnez un **nom** à la suite et choisissez ses **banques sources**. Une suite peut réunir plusieurs banques, par exemple une par matière (météorologie, réglementation, mécanique du vol…).
3. Réglez si besoin les **paramètres** :
   - le **nombre de boîtes** (voir ci-dessous) ;
   - le **nombre minimum de questions en boîte 1** : quand la boîte 1 en contient moins, de nouvelles questions sont introduites au début de la séance ;
   - l'**ordre de présentation** des questions : mélangé, boîte 1 d'abord ou boîtes hautes d'abord ;
   - **reposer les erreurs en fin de séance** : une question échouée est reposée jusqu'à être réussie une fois.
4. Cliquez sur **Créer**. Toutes les questions des banques sont « en banque » : elles entreront dans les boîtes au fil des séances.

La liste des suites résume pour chacune le nombre de questions par boîte (B1, B2…), en banque et maîtrisées.

Vous pouvez modifier une suite à tout moment. Si la modification fait perdre ou déplacer des questions déjà introduites (retrait d'une banque, réduction du nombre de boîtes), une confirmation vous dit lesquelles avant d'enregistrer. Supprimer une suite efface sa progression, mais jamais ses banques.

## Choisir le nombre de boîtes

Une question bien répondue monte d'une boîte, et chaque boîte est posée deux fois moins souvent que la précédente. Plus il y a de boîtes, plus la mémorisation est durable, mais plus il faut de temps pour maîtriser une banque. Sous le choix du nombre de boîtes, le formulaire affiche la durée estimée : avec 5 boîtes (valeur par défaut), une question réussie à chaque fois est maîtrisée en 16 séances environ, soit à peu près 3 semaines à raison de 5 séances par semaine.

## Ajouter mes propres questions

1. Ouvrez la suite, puis cliquez sur **Nouvelle question**.
2. Saisissez la question comme dans une banque.
3. Choisissez son **placement** : **directement en boîte 1** (elle sera posée à la prochaine séance) ou **en banque** (elle sera introduite avec les autres nouvelles questions).

Vos questions sont rangées dans une banque personnelle, « <nom de la suite> — mes questions », créée à la première question et ajoutée aux sources de la suite. Elle est conservée si vous supprimez la suite.

## Lire l'état d'une suite

Cliquez sur une suite dans la liste **Suites de révision** pour ouvrir son état :

- **Boîtes** : le nombre de questions dans chaque boîte, sous forme de barres ;
- **Répartition** : les questions encore en banque (jamais posées), par difficulté, celles qui sont dans les boîtes, maîtrisées ou suspendues ; le total vérifie que rien ne manque ;
- **Prochaine séance** : son numéro, les boîtes posées, le nombre de questions et de nouvelles questions ; la date de la dernière séance et le nombre de séances des 7 derniers jours.

Si vous avez fait moins de 3 séances sur les 7 derniers jours, un avertissement **Rythme insuffisant** s'affiche, ici et dans la colonne **Rythme** de la liste des suites. Il ne bloque rien : vous restez libre de votre rythme.

## Comprendre les boîtes et le rythme

Les séances sont numérotées. Chaque séance pose la boîte 1 et une seule boîte plus haute, à tour de rôle : la boîte 2 une séance sur deux, la boîte 3 une sur quatre, la boîte 4 une sur huit, et ainsi de suite. Avec 5 boîtes, les séances 1 à 8 posent les boîtes 1 et 2, 1 et 3, 1 et 2, 1 et 4, 1 et 2, 1 et 3, 1 et 2, puis 1 et 5. La charge reste ainsi à peu près la même d'une séance à l'autre. Le rythme compte en séances, pas en jours : l'idéal est une séance par jour, ou par jour ouvré.

- Une **bonne réponse** fait monter la question d'une boîte ; dans la dernière boîte, elle devient **maîtrisée** et n'est plus posée.
- Une **mauvaise réponse** fait reculer la question d'une boîte ; si la réponse précédente était déjà fausse, elle revient en boîte 1.
- Au début de chaque séance, si la boîte 1 contient moins de questions que le minimum de la suite, de nouvelles questions sont prises dans la banque, les plus faciles d'abord.

## Faire une séance

1. Ouvrez la suite et cliquez sur **Commencer la séance**.
2. La page annonce le numéro de la séance, les boîtes posées et le nombre de questions. Choisissez si besoin l'**ordre de présentation** pour cette séance, puis cliquez sur **Commencer la séance**.
3. Pour chaque question :
   - **QCM** : choisissez une proposition, puis **Valider** ;
   - **Vrai / faux** : cliquez sur **Vrai** ou **Faux** ;
   - **Auto-évaluation** : réfléchissez, cliquez sur **Voir la réponse**, puis dites honnêtement si vous saviez ;
   - **Valeur numérique** : saisissez le nombre (virgule ou point, unité facultative), puis **Valider**.
4. La correction s'affiche : la réponse attendue, l'explication et le mouvement de la question (« Boîte 2 → boîte 3 »). Cliquez sur **Question suivante**.

En haut de la page, vous voyez votre position dans la séance et le pourcentage de bonnes réponses.

- **Passer** laisse une question dans sa boîte ; elle sera reposée à une prochaine séance.
- **Annuler ma réponse**, juste après une réponse, permet de répondre de nouveau (par exemple après une erreur de clic). Une seule annulation est possible, avant de passer à la question suivante.
- Si la suite repose les erreurs, une question échouée revient en fin de séance jusqu'à être réussie ; ces reprises ne changent pas sa boîte.
- **Terminer la séance** arrête la séance à tout moment : les questions non posées restent dans leur boîte.

À la fin, le **bilan** donne le taux de bonnes réponses, les nouvelles questions, les questions maîtrisées, les mouvements entre boîtes, les questions échouées avec leur réponse attendue, et ce que posera la prochaine séance.

### Raccourcis clavier

| Touche | Action |
|---|---|
| 1 à 9 | Choisir une proposition (QCM) |
| V / F | Répondre vrai ou faux |
| Espace | Voir la réponse (auto-évaluation) |
| O / N | Je savais / je ne savais pas |
| Entrée | Valider, ou passer à la question suivante |
| P | Passer la question |
| A | Annuler ma réponse |

## Reprendre une séance interrompue

Chaque réponse est enregistrée dès qu'elle est donnée : fermer la page ou perdre la connexion ne fait rien perdre. En rouvrant la suite, **Reprendre la séance** repart à la première question sans réponse. Vous pouvez aussi **abandonner** la séance : les réponses déjà données sont conservées, et une séance abandonnée sans aucune réponse ne compte pas.
