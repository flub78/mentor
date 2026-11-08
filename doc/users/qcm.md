# Module QCM — guide d'utilisation

Le module QCM s'ouvre sur la page d'accueil de Mentor (`/qcm`). Dans cette première version, il n'y a pas de connexion : toute personne qui accède à l'application peut tout faire.

## Créer une banque

1. Dans le menu, ouvrez **Banques de questions**, puis cliquez sur **Créer**.
2. Donnez un **nom** à la banque (obligatoire, différent de celui des autres banques) et, si vous le souhaitez, une **description**.
3. Cliquez sur **Créer** : la banque s'ouvre, prête à recevoir ses questions.

Pour renommer une banque, ouvrez-la et modifiez son nom. Pour la supprimer, utilisez **Supprimer** dans la liste ou dans la banque : la confirmation indique combien de questions seront supprimées avec elle.

## Saisir une question

1. Ouvrez la banque, puis, dans la section **Questions**, cliquez sur **Créer**.
2. Remplissez :
   - l'**énoncé** ;
   - éventuellement une **image** (schéma, carte, photo) sur laquelle porte la question ;
   - la **bonne réponse** ;
   - les **mauvaises réponses** : trois lignes sont proposées. Ajoutez-en avec **Ajouter une mauvaise réponse**, ou supprimez celles dont vous n'avez pas besoin ; il en faut au moins une. Pour chacune, vous pouvez expliquer pourquoi elle est fausse ;
   - dans **Compléments**, l'**explication** de la bonne réponse et le **module** (facultatifs).
3. Cliquez sur **Créer**. La question est aussitôt utilisable.

Pour modifier ou supprimer une question, utilisez **Modifier** ou **Supprimer** sur sa ligne. Pour n'afficher que les questions d'un module, utilisez le filtre **Module** de la liste.

## Réutiliser un nom de module

Le module est un texte libre qui regroupe les questions par thème (par exemple « Météorologie », « Réglementation »). En saisissant le module, le champ propose les noms déjà utilisés dans la banque : choisissez-en un plutôt que d'en taper une variante (« Météo » et « Météorologie » seraient deux modules différents).

## Lancer une session d'étude

1. Ouvrez **Étudier** dans le menu, ou cliquez sur **Étudier** sur la ligne d'une banque (elle est alors déjà cochée).
2. Cochez une ou plusieurs banques. Les modules de ces banques s'affichent, avec le nombre de questions disponibles ; les modules de même nom sont regroupés, majuscules comprises (« Météo » et « météo » ne font qu'un).
3. Pour chaque module, laissez **Toutes** coché, ou décochez-le et indiquez le nombre de questions voulu (0 pour ignorer le module). Le nombre total de questions tirées s'affiche sous la liste.
4. Cliquez sur **Commencer**. Les questions sont tirées au hasard et les réponses sont mélangées.
5. Pour chaque question, choisissez une réponse puis cliquez sur **Valider**. Vous voyez aussitôt si elle est bonne, la bonne réponse, l'explication de votre mauvaise réponse et l'explication générale quand elles existent. Cliquez ensuite sur **Question suivante**.

En haut de la page, le pourcentage de bonnes réponses depuis le début de la session et le nombre de questions restantes sont mis à jour à chaque réponse. **Terminer la session** arrête la session et affiche le bilan des questions déjà répondues.

La session n'est pas enregistrée : recharger la page ou la quitter la perd.

## Lire le bilan

À la fin de la session, le bilan affiche :

- le pourcentage global de bonnes réponses ;
- un tableau par module : bonnes réponses sur questions répondues, et taux ;
- la liste des questions échouées, avec leur bonne réponse.

**Nouvelle session** revient au choix des banques, en gardant vos réglages.

## Composer un questionnaire

Un questionnaire est une liste ordonnée de questions. Certaines viennent d'une banque, d'autres, les **questions propres**, n'existent que dans le questionnaire.

1. Dans le menu, ouvrez **Questionnaires**, puis cliquez sur **Créer**.
2. Donnez un **titre** et, si vous le souhaitez, une **version** (elle figurera sur les certificats de résultat ; changez-la quand vous modifiez un questionnaire déjà utilisé) et une description.
3. Cliquez sur **Créer** : le questionnaire s'ouvre, prêt à recevoir ses questions.

Pour changer l'ordre des questions, cliquez sur l'icône de réordonnancement au-dessus de la liste, puis faites glisser les questions.

**Dupliquer** crée une variante : les questions propres sont copiées, les questions de banque restent partagées. Un questionnaire utilisé par un examen ne peut pas être supprimé ; le message nomme les examens concernés.

## Ajouter des questions propres ou prises dans une banque

Dans le questionnaire, au-dessus de la liste des questions :

- **Nouvelle question** : saisissez une question propre, avec le même formulaire que dans une banque. Elle n'apparaît dans aucune banque ni dans l'étude libre.
- **Ajouter depuis une banque** : choisissez la banque, éventuellement un module, puis cochez les questions. Les questions déjà présentes ne sont pas proposées.
- **Tirer au hasard** : voir ci-dessous.

La colonne **Origine** indique si une question est propre ou d'où elle vient. Une question de banque se modifie dans sa banque (**Modifier dans la banque**) : la modification vaut pour tous les questionnaires qui l'utilisent. **Retirer** l'enlève du questionnaire sans la supprimer de sa banque ; **Supprimer** efface une question propre.

## Générer un questionnaire par tirage

Pour préparer un examen blanc, vous pouvez faire tirer les questions au hasard :

- **Générer par tirage**, dans la liste des questionnaires, crée un nouveau questionnaire ;
- **Tirer au hasard**, dans un questionnaire, ajoute des questions tirées à celles qui y sont déjà.

1. Choisissez une ou plusieurs banques : leurs modules s'affichent, avec le nombre de questions disponibles (les modules de même nom sont regroupés, majuscules comprises).
2. Pour chaque module, indiquez le nombre de questions à tirer, ou cochez **Toutes**.
3. Validez. Si un module n'a pas assez de questions, elles sont toutes tirées et le message le signale. Une question déjà présente n'est jamais tirée une seconde fois.

Le tirage n'a lieu qu'à ce moment : ensuite, le questionnaire est une liste fixe, la même pour tous les candidats, que vous pouvez retoucher.

## Définir un examen

Un examen fait passer **toutes** les questions d'un questionnaire.

1. Dans le menu, ouvrez **Examens**, puis **Créer** — ou, depuis un questionnaire, **Créer un examen**.
2. **Général** : titre, consignes affichées au candidat, questionnaire. Le nombre de questions posées, au total et par module, s'affiche.
3. **Réussite et durée** : taux de réussite minimal (75 % par défaut) ; durée maximale en minutes, ou vide pour un passage sans chronomètre.
4. **Passage** : correction détaillée à la fin du passage ou non, ordre des questions mélangé ou non, nombre maximal de passages par candidat (vide : illimité).
5. **Identification du candidat** : voir ci-dessous.

Le passage des examens par les candidats arrivera dans une prochaine version.

## Choisir les renseignements demandés aux candidats

Le nom et le prénom sont toujours demandés. Pour chacun des autres renseignements — adresse e-mail, date de naissance, organisation (club, entreprise), identifiant libre — choisissez **Non demandé**, **Facultatif** ou **Obligatoire**. Pour l'identifiant libre, donnez son libellé (par exemple « Numéro de licence »).

## Exporter une banque

Ouvrez la banque : au-dessus de la liste des questions, **Exporter** propose deux formats :

- **CSV (questions seules)** : un fichier à ouvrir dans un tableur, sans les images ;
- **ZIP (questions, illustrations et paramètres)** : le CSV, les images et le nom de la banque ; c'est le format à utiliser pour sauvegarder une banque ou la transmettre à une autre instance de Mentor.

Pour n'exporter que certaines questions, cochez-les dans la liste, puis utilisez **Exporter la sélection (CSV)** ou **(ZIP)** dans les actions groupées.

## Importer des questions

- **Dans une nouvelle banque** : dans **Banques de questions**, cliquez sur **Importer une banque**.
- **Dans une banque existante** : ouvrez la banque, puis cliquez sur **Importer des questions**.

1. Choisissez le fichier (CSV, ou ZIP pour importer aussi les images), puis cliquez sur **Suivant**. Si le fichier est inutilisable (encodage, colonnes manquantes, archive endommagée), un message dit ce qui ne va pas et rien n'est importé.
2. Lisez le **rapport** : nombre de questions valides, lignes en erreur et leur motif, doublons probables (même énoncé, aux majuscules et à la ponctuation finale près), images inutilisables, modules qui n'existent pas encore dans la banque (vérifiez les fautes de frappe).
3. Pour une nouvelle banque, vérifiez son **nom** (repris du fichier) ; s'il y a des doublons, choisissez de les importer ou non.
4. Cliquez sur **Importer**. Les lignes en erreur ne sont pas importées ; les autres le sont toutes. Pour annuler, fermez la fenêtre : rien n'est importé.

## Préparer un fichier dans un tableur

1. Téléchargez le fichier exemple [banque-exemple.csv](../../public/exemples/qcm/banque-exemple.csv) (aussi proposé dans la fenêtre d'import) et ouvrez-le dans votre tableur.
2. Gardez la première ligne (les noms de colonnes), remplacez les lignes suivantes par vos questions, une par ligne.
3. Enregistrez au format **CSV UTF-8** (séparateur point-virgule ou virgule).
4. Pour joindre des images, créez une archive ZIP contenant le CSV nommé `questions.csv` et un répertoire `images/` avec les fichiers cités dans la colonne `illustration` (voir [banque-exemple.zip](../../public/exemples/qcm/banque-exemple.zip)).

## Format des fichiers d'échange

Banques, questionnaires et examens s'échangent dans le même format.

**CSV** — une ligne d'en-tête, puis une ligne par question. Encodage UTF-8, séparateur `;` ou `,`. L'en-tête tolère majuscules, accents et espaces (« Bonne réponse » vaut `bonne_reponse`) ; une colonne inconnue est signalée et ignorée.

| Colonne | Obligatoire | Contenu |
|---|---|---|
| `enonce` | oui | Énoncé de la question |
| `bonne_reponse` | oui | Texte de la bonne réponse |
| `mauvaise_reponse_1`, `mauvaise_reponse_2`… | au moins la 1re | Textes des mauvaises réponses, autant que nécessaire |
| `explication_1`, `explication_2`… | non | Pourquoi la mauvaise réponse de même numéro est fausse |
| `explication` | non | Explication générale de la bonne réponse |
| `module` | non | Module (thème) de la question |
| `illustration` | non | Nom du fichier image, rangé dans `images/` de l'archive ZIP |

**ZIP** — à la racine : `questions.csv`, le répertoire `images/`, et au plus un fichier de paramètres :

- `banque.json` : `{"format_version": 1, "nom": "…", "description": "…"}` ;
- `questionnaire.json` et `examen.json` : voir « Importer un questionnaire ou un examen ».

Une image doit être un fichier image lisible (JPEG, PNG, GIF, WebP…) d'au plus 2 Mo ; sinon la question est importée sans image et le rapport le signale. Un fichier exporté se réimporte sans modification.
