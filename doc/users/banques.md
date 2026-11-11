# Banques de questions — guide d'utilisation

Les banques de questions sont communes aux modules **QCM** et **Leitner** : une question saisie une fois sert à la fois aux études et examens QCM et aux suites de révision Leitner. Le menu **Banques de questions** est le même dans les deux modules (`/qcm/banks` et `/leitner/banks`). Dans cette première version, il n'y a pas de connexion : toute personne qui accède à l'application peut tout faire.

Toute question vit dans une banque. La **banque par défaut** recueille les questions créées sans banque choisie, par exemple depuis un questionnaire QCM.

## Créer une banque

1. Ouvrez **Banques de questions**, puis cliquez sur **Créer**.
2. Donnez un **nom** à la banque (obligatoire, différent de celui des autres banques) et, si vous le souhaitez, une **description**.
3. Cliquez sur **Créer** : la banque s'ouvre, prête à recevoir ses questions.

Pour renommer une banque, ouvrez-la et modifiez son nom. Pour la supprimer, utilisez **Supprimer** : la confirmation indique combien de questions seront supprimées et quels questionnaires QCM et suites Leitner les utilisent. La banque par défaut peut être renommée mais pas supprimée.

## Saisir une question

1. Ouvrez la banque, puis, dans la section **Questions**, cliquez sur **Créer**.
2. Choisissez le **type de question** (voir ci-dessous). Le type ne peut plus être changé ensuite : pour un autre type, créez une nouvelle question.
3. Saisissez l'**énoncé**. Si la question porte sur une image (« Quel est ce monument ? »), ajoutez l'**image** et décrivez-la en quelques mots : cette description est lue par les lecteurs d'écran et elle est obligatoire dès qu'il y a une image. Sans image, rien n'est affiché à sa place.
4. Remplissez la réponse attendue, selon le type.
5. Dans **Compléments**, ajoutez si vous le souhaitez une **explication** (affichée après la réponse), la **difficulté** et un **thème**. Le champ thème propose les thèmes déjà utilisés dans la banque : reprenez-les plutôt que d'en créer des variantes (« Météo » et « Météorologie » seraient deux thèmes différents). Dans le module QCM, le thème s'appelle **module**.
6. Cliquez sur **Créer**. La question est aussitôt utilisable.

Les filtres de la liste affichent les questions d'un type, d'une difficulté ou d'un thème.

### QCM

Saisissez la **bonne réponse** et les **mauvaises réponses** : trois lignes sont proposées, il en faut au moins une. Pour chaque mauvaise réponse, vous pouvez expliquer pourquoi elle est fausse ; l'explication s'affiche quand elle est choisie. Les réponses sont mélangées à chaque affichage ; désactivez **Mélanger les réponses** si elles se réfèrent les unes aux autres (« aucune des réponses précédentes »).

### Vrai / faux

Saisissez une affirmation dans l'énoncé, puis indiquez si elle est **vraie** ou **fausse**. Le module QCM la pose comme un QCM à deux réponses, « Vrai » et « Faux ».

### Auto-évaluation

Saisissez la **réponse de référence**. L'étudiant réfléchit, affiche la réponse, puis indique lui-même s'il savait. Ce type convient aux questions ouvertes du genre « Vous rappelez-vous comment démontrer le théorème de Pythagore ? ». Il n'est posé que par le module Leitner.

### Valeur numérique

Saisissez la **valeur attendue** (point décimal) et, si vous le souhaitez, son **unité**. Choisissez la **tolérance** :

- **Aucune** : seule la valeur exacte est acceptée ;
- **Écart absolu** : par exemple 0,5 accepte de 12 à 13 pour une valeur de 12,5 ;
- **Écart en %** : par exemple 10 accepte de 180 à 220 pour une valeur de 200.

L'étudiant peut répondre avec une virgule ou un point, et ajouter l'unité après le nombre. Ce type n'est posé que par le module Leitner.

## Quels modules posent quelles questions

| Type | Module QCM | Module Leitner |
|---|---|---|
| QCM | oui | oui |
| Vrai / faux | oui, comme QCM à deux réponses | oui |
| Auto-évaluation, valeur numérique | non | oui |

Quand le module QCM utilise une banque qui contient d'autres types, il les écarte et l'annonce : « 3 questions n'étaient pas de type QCM ou vrai / faux et n'ont pas été utilisées. »

## Choisir la difficulté

Chaque question peut être **facile**, **moyenne** ou **difficile**. Quand une suite Leitner a besoin de nouvelles questions, elle prend d'abord toutes les questions faciles, puis les moyennes, puis les difficiles. Une question sans difficulté est traitée comme moyenne.

## Modifier ou supprimer une question

Utilisez **Modifier** ou **Supprimer** sur la ligne de la question. Une question modifiée l'est partout où elle sert : questionnaires QCM et suites Leitner.

Quand la question est en cours de révision dans une suite Leitner, le formulaire demande la **nature de la modification** :

- **mineure** (faute de frappe, reformulation) : rien ne change pour les suites ;
- **de fond** (la réponse attendue change) : la question revient en boîte 1 dans toutes les suites, pour être réapprise.

La confirmation de suppression indique les questionnaires et les suites qui utilisent la question.

## Ranger les questions de la banque par défaut

Les questions créées depuis un questionnaire QCM, ou importées avec un questionnaire, vont dans la banque choisie à ce moment-là, ou à défaut dans la **banque par défaut**. Pour les ranger dans une banque thématique, déplacez-les (voir ci-dessous).

## Déplacer ou copier des questions

1. Ouvrez la banque, cochez les questions, puis choisissez **Déplacer vers…** ou **Copier vers…** dans les actions groupées.
2. Choisissez la **banque d'arrivée**, puis confirmez.

- Un **déplacement** garde la question et ses utilisations : un questionnaire QCM continue de la poser. Une suite Leitner qui n'a pas la banque d'arrivée pour source perd la question et sa progression ; la fenêtre l'annonce dès que vous choisissez la banque.
- Une **copie** est une nouvelle question indépendante, image comprise, qui n'est utilisée nulle part.

## Exporter une banque

Ouvrez la banque, puis **Exporter** :

- **CSV (questions seules)** : à ouvrir dans un tableur ; sans les images ;
- **ZIP (questions, illustrations et paramètres)** : pour transmettre la banque complète.

Pour n'exporter que certaines questions, cochez-les puis choisissez **Exporter la sélection**. Un fichier exporté se réimporte sans modification.

## Importer des questions

- **Nouvelle banque** : dans la liste des banques, **Importer une banque**.
- **Banque existante** : ouvrez-la, puis **Importer des questions**.

1. Choisissez le fichier CSV ou ZIP, puis cliquez sur **Suivant**.
2. Lisez le **rapport** : nombre de questions valides par type, lignes en erreur (non importées) avec leur numéro, la colonne en cause et les valeurs admises, remarques sur les lignes importées, doublons probables, thèmes nouveaux, images manquantes.
3. Pour une nouvelle banque, vérifiez son nom ; choisissez le sort des doublons s'il y en a.
4. Cliquez sur **Importer**.

Une ligne en erreur n'empêche pas l'import des autres. Les fichiers exportés par les anciennes versions du module QCM s'importent tels quels : leurs questions sont des QCM, leur colonne `module` devient le thème.

## Préparer un fichier dans un tableur

1. Téléchargez un fichier exemple (aussi proposés dans la fenêtre d'import) : [banque-exemple.csv](../../public/exemples/banques/banque-exemple.csv) (une question de chaque type), ou un exemple par type : [qcm.csv](../../public/exemples/banques/qcm.csv), [vrai-faux.csv](../../public/exemples/banques/vrai-faux.csv), [auto-evaluation.csv](../../public/exemples/banques/auto-evaluation.csv), [valeur-numerique.csv](../../public/exemples/banques/valeur-numerique.csv).
2. Gardez la première ligne (les noms de colonnes) et remplacez les suivantes par vos questions, une par ligne.
3. Enregistrez au format **CSV UTF-8** (séparateur point-virgule ou virgule).
4. Pour joindre des images, créez une archive ZIP contenant le CSV nommé `questions.csv` et un répertoire `images/` avec les fichiers cités dans la colonne `illustration` (voir [banque-exemple.zip](../../public/exemples/banques/banque-exemple.zip)).

## Format des fichiers d'échange

**CSV** — une ligne d'en-tête, puis une ligne par question. Encodage UTF-8, séparateur `;` ou `,`. L'en-tête tolère majuscules, accents et espaces (« Bonne réponse » vaut `bonne_reponse`) ; l'ordre des colonnes est libre ; une colonne inconnue est signalée et ignorée.

**Colonnes communes**

| Colonne | Obligatoire | Contenu |
|---|---|---|
| `type` | non | `qcm`, `vrai_faux`, `auto_evaluation` ou `valeur_numerique` (majuscules, accents, espaces et tirets tolérés). Absente ou vide : `qcm`. |
| `enonce` | oui | Énoncé de la question. |
| `explication` | non | Explication affichée après la réponse. |
| `theme` | non | Thème. La colonne `module` des fichiers QCM est acceptée à sa place. |
| `difficulte` | non | `1`, `2`, `3`, ou `facile`, `moyen`, `difficile`. Autre valeur : question importée sans difficulté, signalée. |
| `illustration` | non | Nom du fichier image, rangé dans `images/` de l'archive ZIP. |
| `texte_alternatif` | avec une illustration | Description de l'image. Absente, elle est remplacée par « Illustration : » suivi du début de l'énoncé, et le rapport le signale. |

**Colonnes propres à chaque type**

| Type | Colonnes |
|---|---|
| `qcm` | `bonne_reponse` (obligatoire) ; `mauvaise_reponse_1`, `mauvaise_reponse_2`… (au moins la 1re) ; `explication_1`, `explication_2`… (pourquoi la mauvaise réponse de même numéro est fausse) ; `melange` (`oui` par défaut). |
| `vrai_faux` | `reponse` : `vrai` ou `faux` (obligatoire). |
| `auto_evaluation` | `reponse` : réponse de référence (obligatoire). |
| `valeur_numerique` | `valeur` (obligatoire) ; `type_tolerance` : `aucune` (par défaut), `absolue` ou `pourcentage` ; `tolerance` : nombre ≥ 0, obligatoire sauf tolérance `aucune` ; `unite`. |

Une colonne qui ne concerne pas le type de la ligne est ignorée pour cette ligne, et signalée si elle est remplie. Les types énumération, correspondance, réponse courte et question ouverte sont prévus par le format mais pas encore disponibles : une ligne de ces types est refusée avec ce motif.

**Valeurs** — oui / non : `oui`, `non`, `vrai`, `faux`, `1`, `0`. Nombres : virgule ou point décimal, espaces entre les milliers ignorés (`4 806`, `1,225`). L'export écrit le point-virgule, la virgule décimale et seulement les colonnes utiles.

**ZIP** — à la racine : `questions.csv`, le répertoire `images/` et le fichier de paramètres `banque.json` : `{"format_version": 1, "nom": "…", "description": "…"}`. Une image doit être un fichier image lisible (JPEG, PNG, GIF, WebP…) d'au plus 2 Mo ; sinon la question est importée sans image et le rapport le signale. Les fichiers de questionnaire et d'examen du module QCM s'importent aussi dans une banque : seules leurs questions sont reprises.

Les imports et exports se font au niveau des banques : ni les suites Leitner, ni la progression des étudiants, ni les résultats d'examen n'y figurent.
