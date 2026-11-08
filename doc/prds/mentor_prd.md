# PRD — Mentor, assistant pédagogique

| | |
|---|---|
| **Version** | 0.6 (brouillon) |
| **Date** | 8 novembre 2026 |
| **Auteur** | Frédéric |
| **Statut** | À valider — voir §10 « Questions ouvertes » |

---

## 1. Vision

Mentor est une plateforme de formation qui accompagne un étudiant tout au long d'un programme : elle lui construit un plan adapté à ses disponibilités, déroule les leçons, lui permet de prendre des notes et d'interroger un agent IA pendant l'étude, fait mémoriser les notions par répétition espacée (Leitner), l'évalue en fin de chapitre et en examen blanc, et rend compte de sa progression.

Premier cas d'application envisagé : la formation théorique des élèves pilotes (planeur, avion, ULM), où la banque de questions et les examens blancs existent déjà en projet. Le modèle doit cependant rester générique (toute formation structurée en chapitres / leçons).

### 1.1 Objectifs

- O1 — Réduire le temps d'accompagnement individuel du formateur sur la théorie.
- O2 — Donner à l'étudiant une vision claire de ce qu'il a vu, assimilé et doit revoir.
- O3 — Améliorer le taux de réussite à l'examen final grâce aux examens blancs et à la mémorisation espacée.
- O4 — Garantir la traçabilité : supports obligatoires consultés, séances, résultats.

### 1.2 Hors périmètre (v1)

- Paiement / facturation des formations.
- Délivrance de diplômes ou d'attestations officielles.
- Visioconférence / cours en direct.
- Création de contenu vidéo par la plateforme (les vidéos sont importées ou liées).

### 1.3 Principes pédagogiques

Ces principes s'appliquent à tous les cas d'utilisation et à tout ce que produit l'agent IA.

- **P1 — Du général au particulier.** À chaque niveau (formation, chapitre, leçon, partie de leçon, notion), l'agent IA présente d'abord une **synthèse** : de quoi il s'agit, à quoi ça sert, comment les éléments s'articulent, ce qui va suivre. Il n'entre dans les détails qu'ensuite. Cela vaut aussi pour ses réponses aux questions : la réponse courte et le principe d'abord, puis le développement.
- **P2 — Les bases avant la suite.** Chaque notion déclare ses **prérequis**. Avant d'aborder une notion, le système vérifie que ses prérequis sont acquis. S'ils ne le sont pas, l'agent les reconstruit d'abord (rappel, explication, cartes de révision) avant d'aller plus loin.
- **P3 — Varier les explications.** Si une explication ne passe pas, l'agent change d'angle plutôt que de répéter (voir UC-07).
- **P4 — Informer, ne pas contraindre.** Le système indique à l'étudiant ce que le programme suppose (supports obligatoires, prérequis) et où il en est, mais ne l'empêche pas d'avancer plus vite s'il le souhaite. Les écarts sont signalés, jamais sanctionnés.

### 1.4 Architecture modulaire

Mentor est construit comme un ensemble de **modules indépendants qui s'utilisent les uns les autres**. Chaque module peut être utilisé seul ou intégré dans Mentor, qui les assemble en un parcours de formation complet. Un module est livré et utilisable dès qu'il est prêt, sans attendre les autres : le module QCM, en particulier, doit pouvoir servir seul dès sa première version.

| Module | Rôle | Utilise | PRD | Cas d'utilisation du présent PRD |
|---|---|---|---|---|
| **QCM** | Gérer des banques de questions ; créer, faire passer et corriger des examens à partir de ces banques. | — | [qcm_prd.md](qcm_prd.md) | UC-11, 12, 13, 19, 20, 21 (import QCM) |
| **Leitner** | Étude par répétition espacée avec des questions / réponses. Utilise les questions QCM et des questions textuelles (réponse libre). | QCM | `doc/prds/leitner_prd.md` *(à rédiger)* | UC-10, 10b, 10c |
| **Présentation** | Créer et afficher des présentations, équivalent PowerPoint. Pour commencer, les pages sont des pages Markdown ; l'usage d'une bibliothèque existante (reveal.js, Marp…) reste possible (Q7). | — | `doc/prds/presentation_prd.md` *(à rédiger)* | UC-18 (présentations) |
| **Déroulement de support** | Dérouler des présentations, des PDF, des vidéos, en enregistrant les temps de lecture. | Présentation | `doc/prds/deroulement_prd.md` *(à rédiger)* | UC-04, 04b |
| **Coach IA** | Connaît les autres modules ; définit et adapte un programme adapté à l'étudiant ; répond aux questions, rédige synthèses et bilans. | Tous | `doc/prds/coach_prd.md` *(à rédiger)* | UC-02, 03, 04c, 07, 09, 15 ; exigences IA-* (§6) |
| **Mentor** (assemblage) | Formations, inscriptions, notes, tableaux de bord, suivi formateur ; orchestre les modules. | Tous | ce document | UC-01, 05, 06, 08, 14, 16, 17 |

**Règles d'architecture**

- Chaque module a son **propre PRD**, qui décrit son usage autonome. Le présent document reste le PRD d'ensemble : il décrit le parcours intégré et renvoie aux PRD des modules pour le détail.
- Un module ne dépend que des **interfaces publiques** des modules qu'il utilise (API, formats d'échange), jamais de leur stockage interne.
- Les dépendances vont dans un seul sens (pas de cycle) : QCM et Présentation ne dépendent d'aucun autre module ; Leitner dépend de QCM ; Déroulement dépend de Présentation ; le Coach IA et Mentor s'appuient sur tous les autres.
- Utilisé seul, un module fournit ce dont il a besoin pour fonctionner (organisation de ses contenus) ; intégré à Mentor, il se rattache aux formations, notions et inscriptions de Mentor *(mécanisme à définir, Q28)*.

### 1.5 Socle technique : application dérivée de Laraskel

Mentor est une **application dérivée de [Laraskel](../../../laraskel)** (`../laraskel`), le template d'application Laravel du projet. Conformément au [guide de l'application dérivée](../../../laraskel/doc/development/guide-application-derivee.md) de Laraskel :

- Mentor est un **projet Laravel neuf** qui consomme les briques du socle sous forme de **packages Composer** (`flub78/laraskel-core`, `flub78/laraskel-users`…), et non une copie de Laraskel. Pendant la phase de co-évolution, les briques sont déclarées en *path repository* (`../laraskel/packages/*`) ; les deux dépôts sont côte à côte.
- Mentor étend le socle **par composition** (ex. `UserResource extends BaseUserResource`), jamais en modifiant le code des briques ; les mises à jour du socle se propagent par `composer update`.
- Mentor reprend la stack et les conventions de Laraskel : Laravel, Filament pour le panel d'administration et la conception des contenus, Tailwind CSS + daisyUI pour le front, tests et contrôles qualité (`composer qa`).
- Les services généraux ne sont pas redéveloppés dans Mentor : authentification, comptes et rôles (brique `laraskel-users`), sauvegarde et restauration, export, gestion des médias, interface LLM sont pris dans le socle dès qu'ils y sont disponibles. Un besoin général découvert dans Mentor est remonté dans Laraskel plutôt que codé localement.
- Les rôles de Mentor (§2 : étudiant, formateur, concepteur, administrateur) sont définis au-dessus du mécanisme de rôles de `laraskel-users`.

---

## 2. Acteurs

| Acteur | Description |
|---|---|
| **Étudiant** | Suit une ou plusieurs formations. |
| **Formateur** | Suit ses étudiants, consulte les bilans, peut annoter et valider. |
| **Concepteur pédagogique** | Crée les formations, leçons, supports, QCM, examens. (Peut être la même personne que le formateur.) |
| **Administrateur** | Gère les comptes, inscriptions, paramètres. |
| **Agent IA** (acteur système) | Élabore le plan, répond aux questions, rédige synthèses et bilans. |
| **Planificateur** (acteur système) | Déclenche bilans périodiques et rappels de révision. |

---

## 3. Glossaire et modèle de contenu

```
Formation
 └── Chapitre (ou Module)
      └── Leçon
           ├── Partie de leçon (séquence)
           ├── Supports (obligatoire | facultatif)
           │     ├── Présentation (diapositives)
           │     ├── Vidéo
           │     ├── Animation JavaScript
           │     ├── Document (PDF, HTML)
           │     └── Test interactif (QCM ou page HTML+JS)
           └── Notions (avec prérequis) → cartes Leitner
 ├── Évaluations de fin de chapitre
 └── Examens blancs (globaux)
```

- **Notion** : unité de connaissance élémentaire (ex. « gradient adiabatique sec »), rattachée à une leçon. Les notions forment un **graphe de prérequis** : une notion peut dépendre de notions d'autres leçons ou chapitres.
- **Notion acquise** : pour un étudiant, une notion dont les cartes sont en boîte ≥ seuil, ou validée par une évaluation *(seuil à définir, Q13)*.
- **Synthèse (overview)** : présentation d'ensemble d'un niveau (formation, chapitre, leçon, partie, notion), affichée avant le détail.
- **Question QCM** : énoncé, image(s) optionnelle(s), une réponse exacte, N réponses fausses, une explication par réponse fausse (et une explication générale de la bonne réponse), rattachement à une ou plusieurs notions / chapitres, niveau de difficulté.
- **Carte Leitner** : une notion à mémoriser, placée dans une boîte 1 à N. Deux types :
  - **carte QCM** : réutilise une question de la banque QCM, correction automatique ;
  - **carte question / réponse** : une question au recto, une réponse de référence au verso. L'étudiant saisit sa réponse en texte libre et l'agent IA juge si elle est correcte sur le fond, sans exiger la formulation exacte.
- **Origine d'une carte** : *étudiant* (créée pendant l'étude, privée), *concepteur* (rattachée à la formation), *IA* (générée pour compléter une banque insuffisante).
- **Séance** : période d'activité de l'étudiant (début, fin, contenus consultés, actions).
- **Statut d'une leçon pour un étudiant** : *non abordée* → *abordée* → *assimilée* ↔ *à revoir*.
- **Support personnel** : document ajouté par l'étudiant, visible par lui seul (sauf partage — voir questions ouvertes).

---

## 4. Cas d'utilisation — vue d'ensemble

| ID | Cas d'utilisation | Acteur principal | Priorité |
|---|---|---|---|
| **A. Inscription et planification** |||
| UC-01 | Inscrire un étudiant à une formation | Admin / Formateur | MVP |
| UC-02 | Élaborer le plan de formation personnalisé | Étudiant + Agent IA | MVP |
| UC-03 | Ajuster le plan en cours de formation | Étudiant + Agent IA | v1 |
| **B. Apprentissage** |||
| UC-04 | Suivre une leçon (dérouler une présentation / un support) | Étudiant | MVP |
| UC-04b | Suivre la prise de connaissance des supports | Étudiant / Formateur | Lot « Suivi de lecture » |
| UC-04c | Ouvrir et clore une séance (rappel et synthèse) | Étudiant + Agent IA | MVP |
| UC-05 | Prendre des notes pendant une leçon | Étudiant | MVP |
| UC-06 | Consulter ses notes | Étudiant | MVP |
| UC-07 | Poser une question à l'agent IA | Étudiant | MVP |
| UC-08 | Ajouter ses propres supports | Étudiant | v1 |
| UC-09 | Demander une synthèse / un résumé | Étudiant | v1 |
| **C. Mémorisation et évaluation** |||
| UC-10 | Réviser par la méthode Leitner | Étudiant | MVP |
| UC-10b | Créer une carte Leitner pendant l'étude | Étudiant | MVP |
| UC-10c | Compléter le jeu de cartes d'une leçon | Concepteur / Agent IA | MVP |
| UC-11 | Passer une évaluation de fin de chapitre | Étudiant | MVP |
| UC-12 | Passer un examen blanc | Étudiant | MVP |
| UC-13 | Consulter la correction détaillée | Étudiant | MVP |
| **D. Suivi** |||
| UC-14 | Consulter son tableau de bord | Étudiant | MVP |
| UC-15 | Recevoir un bilan de progression périodique | Étudiant / Formateur | v1 |
| UC-16 | Suivre ses étudiants | Formateur | v1 |
| **E. Conception** |||
| UC-17 | Créer / structurer une formation | Concepteur | MVP |
| UC-18 | Gérer les supports pédagogiques | Concepteur | MVP |
| UC-19 | Gérer la banque de QCM | Concepteur | MVP |
| UC-20 | Composer une évaluation ou un examen blanc | Concepteur | MVP |
| UC-21 | Importer des contenus existants | Concepteur | v1 |

---

## 5. Cas d'utilisation détaillés

### A. Inscription et planification

#### UC-01 — Inscrire un étudiant à une formation

- **Acteur** : Administrateur ou formateur.
- **Préconditions** : la formation est publiée.
- **Scénario nominal**
  1. L'acteur sélectionne une formation et choisit « Inscrire ».
  2. Il saisit ou sélectionne l'étudiant (création de compte si nécessaire).
  3. Il indique optionnellement une date cible (ex. date d'examen) et un formateur référent.
  4. Le système crée l'inscription, initialise la progression (toutes leçons *non abordées*) et le jeu de cartes Leitner (toutes en boîte 1, non activées).
  5. L'étudiant reçoit une invitation ; à sa première connexion, UC-02 est lancé.
- **Variantes**
  - 2a. Auto-inscription de l'étudiant si la formation l'autorise.
  - 2b. Inscription en lot (import CSV).
- **Règles** : un étudiant peut suivre plusieurs formations en parallèle ; les modules communs entre formations (ex. météo, réglementation) partagent la progression *(à confirmer, Q4)*.

#### UC-02 — Élaborer le plan de formation personnalisé

- **Acteurs** : Étudiant, Agent IA.
- **Préconditions** : inscription active, plan non encore établi.
- **Scénario nominal**
  1. L'agent IA ouvre un entretien guidé et recueille :
     - objectif et date cible (examen, échéance) ;
     - disponibilités (jours, créneaux, durée par séance, volume hebdomadaire) ;
     - niveau initial et acquis déclarés ;
     - préférences d'apprentissage (vidéo, lecture, exercices, séances courtes ou longues) ;
     - contraintes particulières (périodes indisponibles).
  2. Optionnellement, le système propose un **test de positionnement** (QCM rapide par chapitre) pour mesurer le niveau réel.
  3. L'agent IA génère un plan : séquence de leçons datées, révisions Leitner intégrées, évaluations de chapitre, examens blancs, jalons.
  4. L'étudiant consulte le plan, demande des modifications en langage naturel, puis le valide.
  5. Le système enregistre le plan et planifie les rappels et le premier bilan (UC-15).
- **Variantes**
  - 2a. Si le test de positionnement montre une maîtrise d'un chapitre, l'agent propose de le marquer *assimilé* ou de le placer en révision légère.
  - 3a. Si la date cible est irréaliste vu les disponibilités, l'agent le signale et propose des alternatives (plus de temps hebdomadaire, date repoussée, chapitres facultatifs écartés).
- **Règles** : le plan respecte l'ordre des prérequis défini par le concepteur ; le formateur peut consulter et amender le plan *(validation formateur obligatoire ? Q6)*.

#### UC-03 — Ajuster le plan en cours de formation

- **Déclencheurs** : demande de l'étudiant, retard détecté, résultats d'évaluation, bilan périodique.
- **Scénario** : l'agent IA propose un plan révisé avec les écarts expliqués ; l'étudiant accepte ou refuse. L'historique des versions du plan est conservé.

### B. Apprentissage

#### UC-04 — Suivre une leçon

- **Acteur** : Étudiant.
- **Préconditions** : leçon accessible (prérequis satisfaits ou contournement autorisé).
- **Scénario nominal**
  1. L'étudiant ouvre la leçon du jour depuis son plan ou le sommaire.
  1a. **Vérification des prérequis (P2)** : le système contrôle que les notions prérequises de la leçon sont acquises. Pour celles qui ne le sont pas, l'agent propose un rattrapage court avant d'entrer dans la leçon : rappel ciblé, quelques cartes, ou renvoi vers la leçon d'origine.
  1b. **Synthèse (P1)** : l'agent présente la synthèse de la leçon : objectifs, plan, lien avec ce qui a déjà été vu et avec la suite. À l'ouverture du premier élément d'un chapitre ou de la formation, la synthèse de ce niveau est présentée d'abord.
  2. Le système affiche la liste des supports en distinguant **obligatoires** et **facultatifs**, avec leur état (non consulté / consulté).
  3. L'étudiant déroule un support :
     - présentation : navigation diapositive par diapositive ;
     - vidéo : lecteur intégré ;
     - animation JS : exécution dans un cadre isolé ;
     - test interactif : exécution et remontée du score.
  4. Un panneau latéral permanent donne accès aux **notes** (UC-05) et à l'**agent IA** (UC-07).
  5. Le système enregistre la consultation (début, fin, progression dans le support).
  6. Lorsque tous les supports obligatoires sont consultés, la leçon passe à *abordée* et les cartes Leitner associées sont activées.
- **Variantes**
  - 3a. L'étudiant interrompt : la position est mémorisée et la reprise se fait au même endroit.
  - 3b. Une partie de leçon peut être déroulée isolément (révision ciblée).
- **Règles**
  - Dans le parcours par défaut, les supports sont affichés **par les visionneuses intégrées de Mentor** (PDF, HTML, vidéo, présentation, animation). C'est ce qui permet le suivi décrit en UC-04b. La possibilité de consulter les supports en dehors de Mentor reste à trancher (Q24).
  - Le bouton « Continuer » est toujours actif (P4).
  - En MVP, le critère « support consulté » est simple : support ouvert et parcouru jusqu'au bout. Le suivi fin relève de UC-04b.

#### UC-04b — Suivre la prise de connaissance des supports

- **Acteurs** : Étudiant (principal), Formateur.
- **Objectif** : indiquer à l'étudiant, et au formateur s'il y a accès, quels supports obligatoires ont été réellement consultés et compris, sans jamais bloquer la progression (P4).
- **Principe** : le système distingue deux niveaux.
  - L'**exposition**, mesurée automatiquement : ce qui a été affiché, combien de temps, quelles parties ont été vues.
  - La **prise en compte**, vérifiée par quelques questions de contrôle. C'est la seule preuve de compréhension, et elle reste facultative.
- **Mesures par type de support**

| Support | Affichage dans Mentor | Ce qui est mesuré |
|---|---|---|
| PDF | Rendu page par page (PDF.js) dans une zone défilante | Pages réellement affichées, temps par page, comparé à un temps de lecture plausible calculé d'après le nombre de mots |
| Document HTML | Zone défilante | Sections vues au défilement, temps passé |
| Vidéo | Lecteur HTML5 ou API officielle (YouTube, Vimeo, PeerTube) | Segments réellement lus (et pas seulement la position finale : un saut à la fin ne compte pas comme vu), vitesse de lecture |
| Présentation | Visionneuse à diapositives émettant un événement à chaque changement | Diapositives vues, temps par diapositive, diapositives passées en moins d'une seconde |
| Animation JS / test interactif | iframe isolée | Progression déclarée par le support lui-même via un contrat d'intégration (voir UC-18) |
| Lien externe | Ouvert hors de Mentor | Rien de mesurable : déclaration « J'ai pris connaissance » par l'étudiant |

- **États affichés pour un support**
  - *non ouvert* ;
  - *survolé* : tout a été affiché, mais trop vite par rapport au temps plausible ;
  - *consulté* : couverture et temps cohérents ;
  - *vérifié* : questions de contrôle réussies.
- **Scénario nominal**
  1. L'étudiant ouvre un support obligatoire ; la visionneuse enregistre la couverture et le temps actif.
  2. À la fin du support, le système propose deux ou trois **questions de contrôle** tirées des notions du support (cartes ou QCM). L'étudiant peut les passer.
  3. Le système calcule l'état du support et l'affiche dans la leçon et dans le tableau de bord.
  4. Si un support obligatoire reste *non ouvert* ou *survolé*, l'agent IA le rappelle au moment opportun (avant l'évaluation de chapitre, dans le bilan périodique), sans bloquer.
- **Variantes**
  - 2a. Questions de contrôle échouées : le support reste *consulté*, l'agent renvoie vers le passage concerné et les cartes associées entrent dans le circuit Leitner en boîte 1.
  - 1a. Support consulté hors de Mentor (si autorisé, Q24) : l'étudiant peut le déclarer lu ; le support passe à *consulté (déclaré)*, et peut ensuite devenir *vérifié* par les questions de contrôle.
- **Côté formateur** (UC-16) : vue par étudiant et par support des états ci-dessus, et repérage des supports souvent *survolés* ou des questions de contrôle souvent échouées, ce qui signale un support à améliorer. Le niveau de détail visible par le formateur est à trancher (Q25).
- **Règles**
  - Les compteurs de temps sont suspendus quand l'onglet n'est pas visible (API Page Visibility) et après une période d'inactivité.
  - Les seuils (temps plausible, pourcentage de vidéo vue) sont configurables par formation.
  - Le système ne prétend jamais savoir si l'étudiant a « lu » : seul l'état *vérifié* atteste d'une compréhension.
  - Ce suivi est une collecte de données de comportement : il est annoncé à l'étudiant dès l'inscription (RGPD).

#### UC-04c — Ouvrir et clore une séance

- **Acteurs** : Étudiant, Agent IA.
- **Objectif** : donner à chaque séance un début et une fin structurés, dans l'esprit de P1 : on resitue avant d'avancer, on récapitule avant de partir.

**Ouverture de séance**

- **Déclencheur** : l'étudiant se connecte ou reprend une formation après la fin de la séance précédente.
- **Scénario nominal**
  1. L'agent présente un **rappel de progression** court : où en est l'étudiant dans son plan (avance, retard), pourcentage de leçons abordées / assimilées / à revoir, cartes Leitner dues aujourd'hui, prochaine échéance.
  2. Il présente une **synthèse des notions abordées lors de la séance précédente** : notions vues, points restés fragiles (questions de contrôle ou cartes échouées, questions posées à l'agent), notes prises.
  3. Il propose la suite : reprendre la leçon en cours là où elle s'est arrêtée, faire d'abord les révisions dues, ou traiter un point fragile.
  4. L'étudiant choisit ; la séance démarre.
- **Variantes**
  - 1a. Première séance de la formation : pas de rappel, l'agent présente la synthèse de la formation (P1) et le plan (UC-02).
  - 2a. Longue interruption (au-delà d'un seuil configurable) : la synthèse remonte plus loin que la seule séance précédente, et l'agent peut proposer quelques cartes d'échauffement *(Q27)*.
  - 4a. L'étudiant peut passer le rappel d'un clic (P4).

**Clôture de séance**

- **Déclencheur** : l'étudiant clique sur « Terminer la séance », ou la séance est close automatiquement après une période d'inactivité *(Q26)*.
- **Scénario nominal**
  1. L'agent présente une **synthèse de la séance** : supports consultés, notions abordées, résultats (cartes, questions de contrôle, évaluations), questions posées et réponses clés, notes prises.
  2. Il signale les points à reprendre et ce qui est prévu à la prochaine séance selon le plan.
  3. L'étudiant peut compléter ou corriger la synthèse, ou en tirer des cartes Leitner (UC-10b).
  4. La synthèse est enregistrée et consultable avec les notes (UC-06) ; elle sert de base à l'ouverture de la séance suivante.
- **Variante**
  - 1a. Séance close automatiquement par inactivité : la synthèse est générée en arrière-plan et présentée à l'ouverture suivante, juste avant le rappel.
- **Règles**
  - Rappel et synthèse sont courts (lisibles en moins d'une minute), avec possibilité de déplier le détail.
  - Ils s'appuient uniquement sur les données de l'étudiant (consultations, résultats, notes, échanges avec l'agent) ; l'agent n'invente pas de progression.
  - Les synthèses de séance alimentent le bilan périodique (UC-15).

#### UC-05 — Prendre des notes pendant une leçon

- **Scénario** : l'étudiant saisit une note dans le panneau latéral ; la note est automatiquement rattachée au contexte (formation, leçon, support, diapositive ou horodatage vidéo). Format texte riche / Markdown, images collées acceptées.
- **Variante** : l'étudiant transforme une note ou une réponse de l'agent en carte Leitner personnelle (→ UC-10b).
- **Règle** : depuis le même panneau, l'étudiant choisit « Note » ou « Carte Leitner » ; les deux restent attachées au même contexte.

#### UC-06 — Consulter ses notes

- **Scénario** : accessible à tout moment, hors leçon. Vues par formation, par chapitre, par date ; recherche plein texte ; clic sur une note → retour au point exact du support.
- **Variantes** : export (Markdown / PDF) ; demande à l'agent d'une synthèse des notes (UC-09).

#### UC-07 — Poser une question à l'agent IA

- **Acteurs** : Étudiant, Agent IA.
- **Préconditions** : l'étudiant est dans une formation (de préférence dans une leçon).
- **Scénario nominal**
  1. L'étudiant pose une question ; le contexte courant (leçon, support, diapositive) est transmis à l'agent.
  2. L'agent vérifie que la question relève du **périmètre de la formation**.
  3. Il construit sa réponse en s'appuyant en priorité sur les supports de la formation (et les supports personnels de l'étudiant), en citant les passages utilisés.
  3b. Il structure sa réponse selon P1 : réponse courte et principe général d'abord, détails ensuite.
  3c. S'il détecte que la difficulté vient d'un prérequis non acquis (P2), il revient d'abord sur ce prérequis avant de répondre sur la notion demandée, en le disant explicitement à l'étudiant.
  4. Il adapte la forme de l'explication : si l'étudiant indique ne pas comprendre, l'agent change d'approche (analogie, schéma, exemple concret, décomposition pas à pas, contre-exemple).
  5. Il peut proposer : relire une partie précise du cours, visionner une vidéo (de la formation ou complémentaire), faire quelques cartes de révision sur la notion.
  6. L'échange est enregistré et consultable avec les notes.
- **Variantes**
  - 2a. Question hors sujet → rejet poli avec rappel du périmètre ; la question n'est pas traitée.
  - 2b. Question liée à une autre formation suivie par l'étudiant → *(acceptée ou rejetée ? Q10)*.
  - 3a. Réponse non trouvée dans les supports → l'agent le signale explicitement avant de répondre sur ses connaissances générales, ou propose de transmettre la question au formateur.
- **Règles**
  - L'agent ne donne pas les réponses d'une évaluation en cours (désactivé ou restreint pendant UC-11 / UC-12).
  - Les questions fréquentes et les rejets sont journalisés pour aider le concepteur à améliorer les supports.

#### UC-08 — Ajouter ses propres supports

- **Scénario** : l'étudiant téléverse un document, une image ou un lien et le rattache à un chapitre ou une leçon. Le support devient utilisable par l'agent IA pour ses réponses et synthèses.
- **Règles** : supports personnels privés par défaut ; quotas de taille ; formats acceptés à préciser.

#### UC-09 — Demander une synthèse, un résumé, une fiche

- **Scénario** : l'étudiant choisit une portée (partie de leçon, leçon, chapitre, ses notes, un support) et un format (résumé court, fiche de révision, carte mentale, liste de points clés, glossaire). L'agent génère le document, qui est enregistré dans l'espace de l'étudiant et peut être édité.

### C. Mémorisation et évaluation

#### UC-10 — Réviser par la méthode Leitner

- **Préconditions** : au moins une carte activée.
- **Scénario nominal**
  1. Le système calcule les cartes dues aujourd'hui selon leur boîte.
  2. L'étudiant lance une session ; les cartes sont présentées une à une.
  3. Pour chaque carte :
     - **carte QCM** : l'étudiant choisit une réponse, la correction est automatique et l'explication de la réponse choisie s'affiche ;
     - **carte question / réponse** : l'étudiant saisit sa réponse en texte libre ; l'agent IA la compare à la réponse de référence et rend un verdict (*correcte* / *incorrecte*, avec éventuellement *partiellement correcte*) accompagné d'une courte justification ; le verso est ensuite affiché.
  4. Bonne réponse → boîte suivante ; mauvaise réponse → retour en boîte 1.
  5. En fin de session : bilan (nombre de cartes, réussite, cartes difficiles) et proposition de revoir la leçon concernée.
- **Variantes**
  - 3a. L'étudiant conteste le verdict de l'IA : il peut le corriger lui-même *(dernier mot à l'étudiant ? Q17)* ; la contestation est journalisée pour améliorer la réponse de référence.
  - 3b. Mode rapide sans saisie : l'étudiant retourne la carte et s'auto-évalue (utile sur téléphone, ou si l'IA est indisponible).
- **Règles — correction par l'IA**
  - L'IA juge le fond (notion, valeurs, unités), pas l'orthographe ni la formulation.
  - Le concepteur peut ajouter à la réponse de référence des **critères attendus** (éléments obligatoires, tolérance numérique, réponses acceptables) qui guident le jugement.
  - Une réponse *partiellement correcte* est traitée comme *(à décider, Q18)*.
- **Paramètres proposés** (configurables par formation) : 5 boîtes, intervalles 1 / 2 / 4 / 8 / 16 jours ; une carte sortie de la boîte 5 est considérée maîtrisée.
- **Règles** : la proportion de cartes en boîtes hautes alimente le statut *assimilé* de la leçon ; des échecs répétés repassent la leçon en *à revoir*.

#### UC-10b — Créer une carte Leitner pendant l'étude

- **Acteur** : Étudiant.
- **Scénario nominal**
  1. Pendant une leçon (ou depuis ses notes), l'étudiant choisit « Nouvelle carte ».
  2. Il choisit le type : question / réponse, ou QCM (bonne réponse, mauvaises réponses et explications facultatives).
  3. La carte est rattachée automatiquement au contexte (leçon, support, position).
  4. Elle entre en boîte 1 et apparaît dans ses prochaines révisions.
- **Variantes**
  - 2a. L'étudiant demande à l'agent IA de rédiger la carte à partir d'une sélection de texte, d'une note ou d'une réponse de l'agent ; il relit et valide.
  - 2b. L'agent IA signale une carte ambiguë (réponse de référence vague, plusieurs réponses possibles) et propose une reformulation.
- **Règles** : les cartes de l'étudiant sont privées *(proposables au concepteur pour la banque commune ? Q19)*.

#### UC-10c — Compléter le jeu de cartes d'une leçon

- **Acteurs** : Concepteur, Agent IA.
- **Déclencheur** : la leçon a moins de cartes que le **seuil minimal** défini par le concepteur (ex. N cartes par leçon ou par objectif pédagogique), en comptant les cartes de la banque et celles de l'étudiant.
- **Scénario nominal (concepteur)** : le concepteur voit les leçons sous-dotées dans son tableau de bord et crée les cartes manquantes (UC-19).
- **Scénario alternatif (IA)**
  1. À l'activation des cartes d'une leçon (UC-04, étape 6), le système détecte un manque.
  2. L'agent IA génère des cartes à partir des supports obligatoires de la leçon, en couvrant en priorité les objectifs pédagogiques sans carte.
  3. Les cartes générées sont marquées *origine IA* et liées au passage du support dont elles sont tirées.
  4. Selon la configuration *(Q20)*, elles sont mises directement en circulation pour l'étudiant, ou placées en file de validation pour le concepteur.
- **Règles**
  - L'IA ne crée pas de doublon d'une carte existante (même notion, même question).
  - Une carte IA validée par le concepteur rejoint la banque de la formation.
  - Le taux de contestation et d'échec d'une carte IA est suivi ; au-delà d'un seuil, la carte est retirée et signalée.

#### UC-11 — Passer une évaluation de fin de chapitre

- **Préconditions** : leçons du chapitre *abordées* (ou déblocage manuel).
- **Scénario nominal**
  1. Le système propose l'évaluation lorsque le chapitre est terminé dans le plan.
  2. Il tire les questions (fixes ou aléatoires selon la configuration), en mélangeant l'ordre des réponses.
  3. L'étudiant répond ; temps limité optionnel.
  4. Correction immédiate (UC-13) ; score et seuil de réussite.
  5. Mise à jour : leçons réussies → contribution au statut *assimilé* ; notions échouées → cartes Leitner remises en boîte 1 et leçons marquées *à revoir*.
- **Variantes** : nombre de tentatives limité ou non ; délai entre tentatives.

#### UC-12 — Passer un examen blanc

- **Scénario** : identique à UC-11 sur l'ensemble du programme, en **conditions d'examen** : durée, nombre de questions et barème calqués sur l'examen réel ; agent IA et notes désactivés ; correction affichée seulement à la fin. Résultat ventilé par chapitre / module.
- **Règles** : tirage de questions jamais vues récemment en priorité ; un examen blanc ne réutilise pas les mêmes questions que le précédent si la banque le permet.

#### UC-13 — Consulter la correction détaillée

- **Scénario** : pour chaque question : réponse donnée, bonne réponse, **explication de la bonne réponse**, **explication spécifique de la réponse fausse choisie**, lien vers la leçon / diapositive concernée, bouton « demander une autre explication » (→ UC-07), bouton « ajouter à mes cartes ».

### D. Suivi

#### UC-14 — Consulter son tableau de bord

Indicateurs affichés pour chaque formation :
- **% de leçons abordées**, **% assimilées**, **% à revoir** (global et par chapitre) ;
- supports obligatoires consultés / total ;
- avancement par rapport au plan (en avance / à jour / en retard, en jours) ;
- état Leitner : cartes par boîte, cartes dues aujourd'hui ;
- historique des scores (évaluations, examens blancs) avec tendance ;
- temps passé (par semaine, par chapitre) ;
- prochaines échéances.

#### UC-15 — Recevoir un bilan de progression périodique

- **Déclencheur** : planificateur, à intervalle défini (ex. hebdomadaire) ou à un jalon (fin de chapitre, examen blanc).
- **Scénario** : l'agent IA rédige un bilan à partir des données (progression vs plan, points forts, notions fragiles, recommandations, ajustement du plan proposé → UC-03). Le bilan est envoyé à l'étudiant (et au formateur référent) et archivé.

#### UC-16 — Suivre ses étudiants (formateur)

- **Scénario** : liste des étudiants avec indicateurs clés et alertes (inactivité, retard, échecs répétés, supports obligatoires non ouverts ou survolés — UC-04b) ; accès au tableau de bord individuel, aux bilans, aux questions posées à l'agent *(confidentialité, Q12)* ; possibilité d'ajouter un commentaire ou de valider manuellement une leçon.

### E. Conception

#### UC-17 — Créer / structurer une formation

- **Scénario** : créer la formation, ses chapitres et leçons, ordonner, définir prérequis, durée estimée, objectifs pédagogiques par leçon, seuils de réussite, paramètres Leitner, mode d'accès (libre / séquentiel). Publication versionnée.
- **Notions et prérequis** : pour chaque leçon, le concepteur déclare les notions enseignées et leurs prérequis. L'agent IA peut proposer un graphe initial à partir des supports, que le concepteur corrige et valide. Le système refuse les cycles et signale les notions sans carte ni question.
- **Synthèses** : le concepteur peut rédiger la synthèse de chaque niveau. À défaut, l'agent IA la génère à partir des supports *(validation requise ? Q21)*.
- **Règles** : un chapitre (module) peut être partagé entre plusieurs formations ; l'ordre des leçons et le plan généré (UC-02) doivent respecter le graphe de prérequis.

#### UC-18 — Gérer les supports pédagogiques

- **Scénario** : ajouter un support à une leçon, choisir son type et son statut (obligatoire / facultatif), éventuellement l'associer à une partie de leçon.
- **Types** : présentation (format source à définir, Q7), vidéo (fichier ou lien externe), animation JS (paquet HTML/JS exécuté en bac à sable), document PDF/HTML, test interactif QCM ou page HTML+JS (avec une API de remontée de score).
- **Contrat d'intégration des animations et tests** : un support HTML+JS communique avec Mentor par `postMessage`, au moyen d'une petite bibliothèque fournie (ex. `mentor.progress(0.5)`, `mentor.complete()`, `mentor.score(8, 10)`). Un support qui n'implémente pas le contrat est traité comme un lien externe (déclaration de l'étudiant).
- **Questions de contrôle** : le concepteur peut attacher deux ou trois questions à chaque support obligatoire ; à défaut, elles sont tirées des notions du support (UC-04b).
- **Règles** : tout support doit déclarer sa licence / origine (contenus libres de droits ou produits en interne).

#### UC-19 — Gérer la banque de QCM

- **Scénario** : créer / éditer une question : énoncé, image(s), une bonne réponse, N mauvaises réponses chacune avec son explication, explication générale, notions et chapitres rattachés, difficulté, source.
- **Variantes** : génération assistée par l'agent IA (brouillons de questions à partir d'un support, à relire et valider) ; détection de doublons ; statistiques par question (taux de réussite, réponse fausse la plus choisie) pour repérer les questions mal formulées.
- **Règle** : une question QCM générée par l'IA n'est jamais publiée dans les évaluations ni les examens blancs sans validation humaine. Les cartes Leitner générées pour combler un manque suivent la règle de UC-10c.
- **Cartes question / réponse** : le concepteur les gère dans la même banque (question, réponse de référence, critères attendus, leçon rattachée) et fixe le seuil minimal de cartes par leçon.

#### UC-20 — Composer une évaluation ou un examen blanc

- **Scénario** : définir la portée (chapitre ou programme), le mode (liste fixe ou tirage aléatoire par règles : X questions du module A, Y du module B, répartition de difficulté), durée, barème, seuil, nombre de tentatives.

#### UC-21 — Importer des contenus existants

- **Scénario** : import de QCM depuis CSV / JSON / Google Forms, import de présentations et documents existants. Rapport d'import avec erreurs.

---

## 6. Agent IA — exigences transverses

| Réf. | Exigence |
|---|---|
| IA-1 | Les réponses s'appuient d'abord sur les supports de la formation (recherche documentaire / RAG) et citent leurs sources. |
| IA-2 | Filtrage du hors-sujet par rapport au périmètre de la formation ; message de rejet explicite. |
| IA-3 | Mémoire pédagogique par étudiant : explications déjà essayées, notions fragiles, préférences — pour varier les approches. |
| IA-4 | Désactivation ou mode restreint pendant les évaluations. |
| IA-5 | Choix du fournisseur LLM découplé (couche d'abstraction), possibilité d'un modèle local. |
| IA-6 | Plafond de coût / de requêtes par étudiant et par période. |
| IA-7 | Journalisation des échanges pour amélioration des contenus, dans le respect du RGPD. |
| IA-8 | Correction des réponses libres aux cartes Leitner : jugement sur le fond par rapport à la réponse de référence et aux critères attendus, verdict justifié en une ou deux phrases, temps de réponse < 3 s. |
| IA-9 | Génération de cartes Leitner à partir des supports, avec lien vers le passage source et sans doublon. |
| IA-10 | Toute production de l'agent (leçon, réponse, bilan, synthèse demandée) commence par une vue d'ensemble avant le détail (P1). |
| IA-12 | Rédaction des rappels d'ouverture et synthèses de clôture de séance à partir des seules données de l'étudiant, en format court avec détail dépliable (UC-04c). |
| IA-11 | Avant d'expliquer une notion, l'agent consulte l'état d'acquisition de ses prérequis chez l'étudiant et reconstruit d'abord ceux qui manquent (P2). |

---

## 7. Exigences non fonctionnelles

- **Plateforme** : application web responsive (ordinateur, tablette, téléphone pour les révisions Leitner).
- **Hors ligne** : à préciser (Q14) — au minimum, révision Leitner possible hors connexion serait utile.
- **Sécurité** : animations et tests HTML+JS exécutés en iframe isolée (sandbox, CSP).
- **RGPD** : données personnelles minimales, export et suppression sur demande, hébergement en UE.
- **Performances** : ouverture d'une leçon < 2 s ; réponse de l'agent IA en streaming.
- **Visionneuses intégrées** : PDF, HTML, vidéo et présentations sont affichés dans Mentor dans le parcours par défaut, ce qui conditionne le suivi de UC-04b.
- **Traçabilité** : journal horodaté des consultations de supports obligatoires et des évaluations, non modifiable par l'étudiant.
- **Accessibilité** : sous-titres vidéo, navigation clavier, contrastes.
- **Langue** : français en v1, i18n prévue.

---

## 8. Modèle de données (esquisse)

```
User(id, nom, email, rôle)
Formation(id, titre, version, paramètres_leitner, statut)
Chapitre(id, titre, ordre)            Formation_Chapitre(formation_id, chapitre_id, ordre)
Lecon(id, chapitre_id, titre, ordre, objectifs, durée_estimée, prérequis)
Partie(id, lecon_id, titre, ordre)
Notion(id, lecon_id, titre, description)
NotionPrerequis(notion_id, prerequis_id)          -- graphe orienté sans cycle
Synthese(id, niveau[formation|chapitre|lecon|partie|notion], cible_id, contenu, origine[concepteur|ia], validée)
AcquisitionNotion(inscription_id, notion_id, statut, maj_le)
Support(id, lecon_id, partie_id?, type, obligatoire, url/fichier, licence, propriétaire_id?)
Question(id, énoncé, images, explication, difficulté, source, statut_validation)
Reponse(id, question_id, texte, correcte, explication)
Question_Notion(question_id, notion_id)
Evaluation(id, portée, type[chapitre|examen_blanc], règles_tirage, durée, seuil)
Inscription(id, user_id, formation_id, date_cible, formateur_id, statut)
Plan(id, inscription_id, version, contenu, validé_le)
Seance(id, inscription_id, début, fin, clôture[manuelle|inactivité], synthèse_clôture, rappel_ouverture)
Consultation(id, seance_id, support_id, progression, terminé_le)
SuiviSupport(inscription_id, support_id, couverture[segments vus], temps_actif, état[non_ouvert|survolé|consulté|vérifié],
             déclaré_par_étudiant, maj_le)
ProgressionLecon(inscription_id, lecon_id, statut, maj_le)
CarteModele(id, notion_id, type[qcm|question_reponse], question_id?, recto, verso, critères_attendus,
            origine[étudiant|concepteur|ia], auteur_id?, source_support_id?, statut_validation)
Carte(id, inscription_id, carte_modele_id, boîte, prochaine_échéance)
RevisionCarte(id, carte_id, date, réponse_saisie, verdict, justification_ia, contestée)
Tentative(id, inscription_id, evaluation_id, score, début, fin)
ReponseDonnee(tentative_id, question_id, reponse_id, correcte)
Note(id, user_id, contexte[lecon, support, position], contenu)
EchangeIA(id, user_id, contexte, question, réponse, rejetée)
Bilan(id, inscription_id, période, contenu)
```

---

## 9. Découpage en lots

Les lots ci-dessous décrivent le parcours intégré. Chaque module (§1.4) a en outre son propre découpage dans son PRD et peut être livré seul avant le MVP intégré ; le **module QCM** est le premier visé, utilisable de façon autonome dès sa disponibilité.

| Lot | Contenu | Critère de sortie |
|---|---|---|
| **MVP** | Visionneuses intégrées (prérequis du lot Suivi de lecture) ; UC-01, 04, 04c, 05, 06, 10, 11, 12, 13, 14, 17, 18, 19, 20 ; UC-02 en version simple ; UC-07 sur supports de la formation | Un étudiant pilote suit un module complet (ex. Météorologie planeur) de bout en bout |
| **v1** | UC-03, 08, 09, 15, 16, 21 ; IA-3 mémoire pédagogique | Une promotion complète suivie, bilans automatiques actifs |
| **Suivi de lecture** | UC-04b : mesures par type de support, états gradués, questions de contrôle, vue formateur, contrat d'intégration des animations | L'étudiant et le formateur voient, pour chaque support obligatoire, s'il est non ouvert, survolé, consulté ou vérifié |
| **v2** | Hors ligne, statistiques avancées de questions, multi-structures | À définir |

---

## 10. Questions ouvertes

**Périmètre et utilisateurs**
- Q1. Usage interne (un club, quelques dizaines d'élèves) ou produit multi-structures (plusieurs clubs / organismes, multi-tenant) ?
- Q2. Mentor est-il autonome ou intégré à un existant (GVV, comptes déjà existants, authentification commune) ?
- Q3. Le formateur a-t-il un rôle actif (validation, commentaires) ou Mentor est-il essentiellement en autoformation ?
- Q4. Les modules communs entre formations (ex. météo, réglementation pour avion / planeur / ULM) partagent-ils la progression et les cartes Leitner d'un étudiant inscrit aux deux ?

**Planification**
- Q5. Le plan est-il calendaire (dates précises, rappels) ou seulement une séquence avec rythme cible ?
- Q6. Le plan généré par l'IA doit-il être validé par un formateur ?

**Contenus**
- Q7. Format source des présentations : Markdown/HTML (reveal.js, Marp…), import PowerPoint/PDF, ou les deux ?
- Q8. Seuils du suivi de lecture (UC-04b) : temps de lecture plausible, pourcentage de vidéo vue, durée minimale par diapositive — valeurs par défaut à fixer.
- Q9. Vidéos hébergées sur la plateforme ou liens externes (YouTube, PeerTube) ? La vidéo « complémentaire » proposée par l'IA peut-elle venir d'Internet ?
- Q24. Les supports (PDF, vidéos, présentations…) sont-ils **consultables uniquement dans Mentor**, ou aussi téléchargeables / visibles ailleurs ? Le premier choix rend le suivi complet mais gêne la lecture hors ligne ou sur papier ; le second oblige à s'appuyer sur la déclaration de l'étudiant et les questions de contrôle.
- Q25. Le formateur voit-il le détail du suivi de lecture (temps, pages, segments) ou seulement l'état final de chaque support ?

**Agent IA**
- Q10. Une question portant sur une autre formation suivie par l'étudiant : acceptée ou rejetée ? Quelle tolérance pour les questions connexes (ex. aérodynamique en cours de météo) ?
- Q11. Fournisseur LLM : API commerciale, modèle local, budget mensuel cible ?
- Q12. Le formateur peut-il lire les questions posées à l'agent et les notes de l'étudiant ?

**Évaluation et mémorisation**
- Q13. Paramètres Leitner (5 boîtes, 1/2/4/8/16 j) et définition de « leçon assimilée » (ex. évaluation de chapitre ≥ 75 % **et** 80 % des cartes en boîte ≥ 3) : à valider ou à ajuster ?
- Q14. Besoin d'un usage hors ligne (révisions sur téléphone sans réseau) ?
- Q17. Si l'étudiant conteste le verdict de l'IA sur une carte question / réponse, a-t-il le dernier mot ?
- Q18. Une réponse « partiellement correcte » : la carte avance, reste dans sa boîte, ou recule en boîte 1 ?
- Q19. Les cartes créées par un étudiant restent-elles privées, ou peut-il les proposer au concepteur pour enrichir la banque commune ?
- Q20. Les cartes générées par l'IA pour combler un manque sont-elles mises en circulation immédiatement, ou seulement après validation du concepteur ?

**Principes pédagogiques**
- Q21. Les synthèses générées par l'IA (quand le concepteur n'en a pas écrit) doivent-elles être validées avant affichage, ou sont-elles générées à la volée pour chaque étudiant ?
- Q22. Un prérequis non acquis est-il **bloquant** (la leçon reste fermée tant que le rattrapage n'est pas fait) ou **indicatif** (l'étudiant peut passer outre, et l'agent le signale) ?
- Q23. Granularité des notions : faut-il une notion par objectif pédagogique (quelques-unes par leçon), ou un découpage plus fin ? Cela fixe le volume de travail de saisie du graphe de prérequis.

**Séances**
- Q26. Fin de séance : uniquement sur action de l'étudiant, ou aussi automatique après inactivité ? Si oui, quel délai (ex. 30 min) ?
- Q27. À l'ouverture d'une séance, l'agent doit-il systématiquement proposer quelques cartes d'échauffement, ou seulement après une longue interruption ?

**Architecture modulaire**
- Q28. Comment un module utilisé seul se rattache-t-il ensuite à Mentor ? Piste cohérente avec Laraskel (§1.5) : chaque module est un package Composer, utilisable dans Mentor ou dans toute autre application dérivée de Laraskel, les comptes venant alors de `laraskel-users`. À confirmer.
- Q29. Ordre de livraison des modules après QCM : Leitner, puis Présentation et Déroulement, puis Coach IA ?

**Technique et licence**
- ~~Q15. Stack imposée ou ouverte ?~~ **Tranché** : application dérivée de Laraskel (Laravel + Filament + Tailwind), voir §1.5.
- Q16. Projet open source (comme GVV) ? Quelle licence pour le code et pour les contenus ?
