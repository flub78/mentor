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
