# Module QCM — guide d'utilisation

Le module QCM s'ouvre sur la page d'accueil de Mentor (`/qcm`). Dans cette première version, il n'y a pas de connexion : toute personne qui accède à l'application peut tout faire.

## Banques et questions

Les banques et leurs questions sont communes aux modules QCM et Leitner : le menu **Banques de questions** est décrit dans le [guide des banques](banques.md) (créer une banque, saisir une question, la déplacer, importer et exporter une banque, format des fichiers).

Le module QCM pose les questions de type **QCM** et **vrai / faux** ; un vrai / faux est présenté comme un QCM à deux réponses, « Vrai » et « Faux », toujours dans cet ordre. Les autres types (auto-évaluation, valeur numérique…) servent au module Leitner : quand une banque en contient, le module QCM les écarte et l'annonce, par exemple « 3 questions n'étaient pas de type QCM ou vrai / faux et n'ont pas été utilisées ». Le **module** d'une question QCM est son thème dans la banque.

## Lancer une session d'étude

1. Ouvrez **Étudier** dans le menu.
2. Cochez une ou plusieurs banques ; une banque sans question QCM ou vrai / faux est grisée, et le nombre de questions d'autres types, non utilisées, est indiqué. Les modules de ces banques s'affichent, avec le nombre de questions disponibles ; les modules de même nom sont regroupés, majuscules comprises (« Météo » et « météo » ne font qu'un).
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

Un questionnaire est une liste ordonnée de questions prises dans les banques.

1. Dans le menu, ouvrez **Questionnaires**, puis cliquez sur **Créer**.
2. Donnez un **titre** et, si vous le souhaitez, une **version** (elle figurera sur les certificats de résultat ; changez-la quand vous modifiez un questionnaire déjà utilisé) et une description.
3. Cliquez sur **Créer** : le questionnaire s'ouvre, prêt à recevoir ses questions.

Pour changer l'ordre des questions, cliquez sur l'icône de réordonnancement au-dessus de la liste, puis faites glisser les questions.

**Dupliquer** crée une variante qui utilise les mêmes questions. Supprimer un questionnaire ne supprime pas ses questions : elles restent dans leurs banques. Un questionnaire utilisé par un examen ne peut pas être supprimé ; le message nomme les examens concernés.

## Ajouter des questions

Dans le questionnaire, au-dessus de la liste des questions :

- **Nouvelle question** : saisissez une question QCM ou vrai / faux. Choisissez la **banque** où la ranger ; à défaut, elle va dans la banque par défaut, d'où vous pourrez la déplacer (voir le [guide des banques](banques.md)). Le message de confirmation dit dans quelle banque elle est rangée.
- **Ajouter depuis une banque** : choisissez la banque, éventuellement un module, puis cochez les questions. Les questions déjà présentes ne sont pas proposées ; les questions d'autres types sont écartées et annoncées.
- **Tirer au hasard** : voir ci-dessous.

La colonne **Banque** indique d'où vient chaque question. Une question se modifie dans sa banque (**Modifier dans la banque**) : la modification vaut pour tous les questionnaires qui l'utilisent, et pour les suites Leitner. **Retirer** l'enlève du questionnaire sans la supprimer de sa banque.

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


## Choisir les renseignements demandés aux candidats

Le nom et le prénom sont toujours demandés. Pour chacun des autres renseignements — adresse e-mail, date de naissance, organisation (club, entreprise), identifiant libre — choisissez **Non demandé**, **Facultatif** ou **Obligatoire**. Pour l'identifiant libre, donnez son libellé (par exemple « Numéro de licence »).

## Prévisualiser un examen

Dans la liste des examens ou dans un examen, cliquez sur **Prévisualiser** : l'écran du candidat s'ouvre dans un nouvel onglet, avec une identité fictive déjà remplie. Vous pouvez répondre, marquer des questions, terminer et voir le certificat (numéro « APERÇU ») et la correction. **Rien n'est enregistré** : ni passage, ni résultat.

## Faire passer un examen sur ce poste

1. Dans la liste des examens ou dans un examen, cliquez sur **Faire passer** : l'examen s'ouvre dans un nouvel onglet, sans le menu de l'application. Laissez le candidat s'installer.
2. Le candidat lit l'accueil (consignes, nombre de questions, durée, taux de réussite minimal), clique sur **Commencer**, puis remplit les renseignements demandés. Un champ obligatoire vide ou mal saisi (adresse e-mail, date) est signalé et l'examen ne démarre pas.
3. Il répond aux questions dans l'ordre qu'il veut : **Précédente**, **Suivante**, ou un numéro dans la **liste des questions** (fond foncé : répondue ; ★ : marquée). **Marquer** pose un repère pour y revenir. Il peut changer d'avis : la dernière réponse compte.
4. Si l'examen a une durée, le temps restant est affiché, avec un avertissement à 5 minutes et à 1 minute. À la fin du temps, l'examen se termine seul avec les réponses déjà données.
5. **Terminer l'examen…** rappelle les questions non répondues (elles comptent comme fausses) et marquées ; **Confirmer et terminer** affiche le certificat, puis la correction détaillée si l'examen la prévoit (filtres : fausses, non répondues, marquées).
6. Pour le candidat suivant, cliquez sur **Nouveau passage (candidat suivant)**.

Si l'onglet est rechargé ou fermé par erreur, rouvrez l'examen depuis le même navigateur : le passage reprend avec les mêmes réponses, et le chronomètre a continué de tourner. Si le nombre maximal de passages est atteint pour ce candidat (reconnu à son e-mail s'il est demandé, sinon à ses nom et prénom), l'examen ne démarre pas et un message l'indique.

Pour qu'un examen abandonné en cours soit terminé à l'heure même si personne ne rouvre la page, le planificateur de Laravel doit tourner (`php artisan schedule:work`, ou `schedule:run` dans le cron) ; sinon, il est terminé à la prochaine ouverture.

## Imprimer ou enregistrer son certificat

Le certificat présente l'examen, le questionnaire et sa version, l'identité saisie, la date du passage, le taux par module, le taux global, le taux minimal, la mention **Réussi** ou **Non réussi** et un **numéro de certificat** qui permet de le retrouver. Il ne contient pas les questions.

Cliquez sur **Imprimer ou enregistrer en PDF** : la boîte d'impression du navigateur s'ouvre, avec le certificat seul sur une page A4. Pour un PDF, choisissez « Enregistrer au format PDF » comme imprimante.

## Consulter les résultats

Chaque passage terminé enregistre un **résultat**. Ouvrez **Résultats** dans le menu pour tous les examens, ou ouvrez un examen : ses résultats sont listés sous le formulaire.

- **Rechercher** : tapez un nom, un prénom (dans n'importe quel ordre), une adresse e-mail, une organisation, un identifiant libre ou un numéro de certificat.
- **Filtrer** (icône entonnoir) : par examen, par période de passage, par mention (réussi / non réussi).
- **Voir** un résultat : identité, date, examen, questionnaire et version, taux par module, taux global, taux minimal, mention et numéro de certificat.

Les résultats restent consultables après une modification ou une suppression des questions, du questionnaire ou de l'examen : ils gardent les titres et la version du moment du passage.

Pour supprimer un résultat, utilisez **Supprimer** dans la liste ou dans le résultat, puis confirmez. Son certificat ne pourra plus être réédité.

## Exporter les résultats

Dans **Résultats** (ou dans les résultats d'un examen), appliquez si besoin une recherche et des filtres, puis cliquez sur **Exporter (CSV)**. Le fichier contient les résultats affichés, dans le même ordre, à ouvrir dans un tableur :

- une ligne par résultat ;
- une colonne par renseignement d'identité et par module rencontrés (les modules de même nom sont regroupés, majuscules comprises) ; la case est vide si le module n'a pas été posé à ce candidat ;
- la date du passage, l'examen, le questionnaire et sa version, le taux global, le taux minimal, la réussite (Oui / Non) et le numéro de certificat.

Les taux sont des pourcentages écrits avec une virgule (par exemple `66,67`), sans le signe %.

## Exporter un questionnaire ou un examen

- **Questionnaire** : ouvrez-le, puis **Exporter** → **CSV (questions seules)** ou **ZIP (questions, illustrations et paramètres)**. Le ZIP garde le titre, la description et la version.
- **Examen** : **Exporter (ZIP)**, dans la liste des examens ou dans l'examen. L'archive contient le questionnaire de l'examen et tous les paramètres de l'examen (durée, taux de réussite, options, renseignements demandés).

Le fichier est autonome : les questions prises dans des banques y sont écrites en entier.

## Importer un questionnaire ou un examen

1. Dans **Questionnaires**, cliquez sur **Importer** (ou, dans **Examens**, sur **Importer un examen**).
2. Choisissez le fichier, puis cliquez sur **Suivant** :
   - un ZIP d'examen crée un questionnaire **et** un examen qui l'utilise ;
   - un ZIP de questionnaire, un ZIP de banque ou un CSV seul crée seulement un questionnaire (titré d'après le fichier s'il n'a pas de paramètres).
3. Lisez le rapport : en plus des contrôles de l'import de banque (voir le [guide des banques](banques.md)), il signale les paramètres d'examen invalides (taux hors de 0 à 100, durée négative, renseignement inconnu…), remplacés par la valeur par défaut, et les questions d'autres types que QCM et vrai / faux, qui ne sont pas reprises.
4. Choisissez la **banque** où ranger les questions importées (la banque par défaut est proposée), vérifiez les titres, choisissez le sort des doublons s'il y en a, puis cliquez sur **Importer**. Le questionnaire importé s'ouvre.

L'import crée toujours un nouveau questionnaire (et un nouvel examen) et de nouvelles questions dans la banque choisie : il ne modifie jamais un questionnaire existant. Exemples : [questionnaire-exemple.zip](../../public/exemples/qcm/questionnaire-exemple.zip), [examen-exemple.zip](../../public/exemples/qcm/examen-exemple.zip).

## Format des fichiers de questionnaire et d'examen

Les questions suivent le format des banques, décrit dans le [guide des banques](banques.md) (« Format des fichiers d'échange »). Les anciens fichiers du module QCM restent lisibles.

**ZIP** — à la racine : `questions.csv`, le répertoire `images/`, et au plus un fichier de paramètres :

- `banque.json` : `{"format_version": 1, "nom": "…", "description": "…"}` ;
- `questionnaire.json` : `{"format_version": 1, "titre": "…", "description": "…", "version": "…"}` ;
- `examen.json` : `{"format_version": 1, "questionnaire": {…comme questionnaire.json, sans format_version…}, "examen": {…}}`.

Paramètres de l'objet `examen` (tous facultatifs ; une valeur absente ou invalide prend la valeur par défaut, signalée au rapport) :

| Clé | Valeurs | Par défaut |
|---|---|---|
| `titre` | texte | titre du questionnaire |
| `consignes` | texte | aucune |
| `duree_minutes` | entier ≥ 1, ou `null` | `null` (pas de chronomètre) |
| `taux_reussite` | entier de 0 à 100 | 75 |
| `melange_questions` | `true` / `false` | `true` |
| `correction_detaillee` | `true` / `false` | `true` |
| `passages_max` | entier ≥ 1, ou `null` | `null` (illimité) |
| `identification` | objet : `email`, `date_naissance`, `organisation`, `identifiant` → `non_demande`, `facultatif` ou `obligatoire` | tout `non_demande` |
| `libelle_identifiant` | texte, obligatoire si `identifiant` est demandé | « Identifiant » |

Une image doit être un fichier image lisible (JPEG, PNG, GIF, WebP…) d'au plus 2 Mo ; sinon la question est importée sans image et le rapport le signale. Un fichier exporté se réimporte sans modification.
