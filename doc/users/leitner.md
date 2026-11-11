# Module Leitner — guide d'utilisation

Le module Leitner fait mémoriser des connaissances par répétition espacée. Il s'ouvre sur `/leitner`, ou depuis le lien **Module Leitner** du menu du module QCM. Dans cette première version, il n'y a pas de connexion : toute personne qui accède à l'application peut tout faire.

## Créer une banque

1. Dans le menu, ouvrez **Banques de questions**, puis cliquez sur **Créer**.
2. Donnez un **nom** à la banque (obligatoire, différent de celui des autres banques) et, si vous le souhaitez, une **description**.
3. Cliquez sur **Créer** : la banque s'ouvre, prête à recevoir ses questions.

Pour renommer une banque, ouvrez-la et modifiez son nom. Pour la supprimer, utilisez **Supprimer** dans la liste ou dans la banque : la confirmation indique combien de questions seront supprimées avec elle.

Une banque peut servir à plusieurs suites de révision en même temps ; l'entraînement ne la modifie jamais.

## Saisir une question

1. Ouvrez la banque, puis, dans la section **Questions**, cliquez sur **Créer**.
2. Choisissez le **type de question** (voir ci-dessous). Le type ne peut plus être changé ensuite : pour un autre type, créez une nouvelle question.
3. Saisissez l'**énoncé** et, si la question porte sur une image (« Quel est ce monument ? »), ajoutez l'**image** et décrivez-la en quelques mots : cette description est lue par les lecteurs d'écran et elle est obligatoire dès qu'il y a une image.
4. Remplissez la réponse attendue, selon le type.
5. Dans **Compléments**, ajoutez si vous le souhaitez une **explication** (affichée après la réponse), la **difficulté** et un **thème**. Le champ thème propose les thèmes déjà utilisés dans la banque : reprenez-les plutôt que d'en créer des variantes.
6. Cliquez sur **Créer**. La question est aussitôt utilisable.

Pour modifier ou supprimer une question, utilisez **Modifier** ou **Supprimer** sur sa ligne. Les filtres de la liste affichent les questions d'un type, d'une difficulté ou d'un thème.

Quand vous modifiez une question déjà en cours de révision dans une suite, le formulaire demande la **nature de la modification** :

- **mineure** (faute de frappe, reformulation) : la question garde sa boîte ;
- **de fond** (la réponse attendue change) : la question revient en boîte 1 dans toutes les suites, pour être réapprise.

La confirmation de suppression d'une question ou d'une banque indique dans combien de suites elle est utilisée.

### QCM

Saisissez la **bonne réponse** et les **mauvaises réponses** : trois lignes sont proposées, il en faut au moins une. Pour chaque mauvaise réponse, vous pouvez expliquer pourquoi elle est fausse ; l'explication s'affiche quand l'étudiant la choisit. Les réponses sont mélangées à chaque affichage ; désactivez **Mélanger les réponses** si elles se réfèrent les unes aux autres (« aucune des réponses précédentes »).

### Vrai / faux

Saisissez une affirmation dans l'énoncé, puis indiquez si elle est **vraie** ou **fausse**.

### Auto-évaluation

Saisissez la **réponse de référence**. Pendant la révision, l'étudiant réfléchit, affiche la réponse, puis indique lui-même s'il savait. Ce type convient aux questions ouvertes du genre « Vous rappelez-vous comment démontrer le théorème de Pythagore ? ».

### Valeur numérique

Saisissez la **valeur attendue** (point décimal) et, si vous le souhaitez, son **unité**. Choisissez la **tolérance** :

- **Aucune** : seule la valeur exacte est acceptée ;
- **Écart absolu** : par exemple 0,5 accepte de 12 à 13 pour une valeur de 12,5 ;
- **Écart en %** : par exemple 10 accepte de 180 à 220 pour une valeur de 200.

L'étudiant peut répondre avec une virgule ou un point, et ajouter l'unité après le nombre.

## Choisir la difficulté

Chaque question peut être **facile**, **moyenne** ou **difficile**. Quand une suite a besoin de nouvelles questions, elle prend d'abord toutes les questions faciles, puis les moyennes, puis les difficiles. Une question sans difficulté est traitée comme moyenne.

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
