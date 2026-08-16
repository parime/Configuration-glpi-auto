# Contribuer
*Contributing*

## Workflow des branches
*Branch workflow*

- `dev` accumule le travail en cours — poussez-y vos changements.
- *`dev` accumulates work in progress — push your changes there.*
- `main` est la branche stable, protégée : toute modification doit passer par une Pull Request avec au moins une revue approuvée et une CI verte (voir `.github/workflows/continuous-integration.yml`).
- *`main` is the stable, protected branch: any change must go through a Pull Request with at least one approved review and a green CI (see `.github/workflows/continuous-integration.yml`).*
- Ne travaillez pas directement sur `main`.
- *Do not work directly on `main`.*

## Environnement de test
*Test environment*

Ce plugin a besoin d'une vraie instance GLPI pour être testé sérieusement — beaucoup de code n'a de sens qu'exécuté contre un vrai `$DB`, de vraies capacités d'actif GLPI 11, un vrai moteur de règles. La stack Docker fournie (`docker-compose.test.yml`) monte GLPI + MariaDB avec ce dépôt monté en lecture seule comme plugin :

*This plugin needs a real GLPI instance to be tested seriously — a lot of the code only makes sense when run against a real `$DB`, real GLPI 11 asset capabilities, a real rules engine. The provided Docker stack (`docker-compose.test.yml`) spins up GLPI + MariaDB with this repository mounted read-only as a plugin:*

```bash
composer install                         # dépendances dev incluses (phpunit, phpstan, php-cs-fixer)
docker compose -f docker-compose.test.yml up -d
docker compose -f docker-compose.test.yml exec glpi \
  php bin/console database:configure --allow-superuser -n --db-host=db --db-name=glpi --db-user=glpi --db-password=glpi
docker compose -f docker-compose.test.yml exec glpi \
  php bin/console database:install --allow-superuser -n --default-language=fr_FR
docker compose -f docker-compose.test.yml exec glpi \
  php bin/console plugin:install configurationglpiauto --allow-superuser -n
docker compose -f docker-compose.test.yml exec glpi \
  php bin/console plugin:activate configurationglpiauto --allow-superuser -n
```

## Avant de pousser
*Before pushing*

Toutes ces vérifications tournent automatiquement en CI à chaque push/Pull Request, mais autant les faire passer localement d'abord :

*All these checks run automatically in CI on every push/Pull Request, but it's worth running them locally first:*

```bash
php -l <fichier modifié>                                    # syntaxe
vendor/bin/phpstan analyse --no-progress                    # analyse statique (tests/Unit uniquement, voir phpstan.neon)
vendor/bin/php-cs-fixer fix --dry-run --diff --allow-risky=yes
vendor/bin/phpunit -c phpunit.xml.dist                      # tests unitaires purs, pas de GLPI requis
```

Les tests d'intégration (`tests/Integration/`, contre une vraie instance) doivent tourner **depuis le conteneur GLPI** — ils démarrent un vrai `Glpi\Kernel\Kernel` :

*Integration tests (`tests/Integration/`, against a real instance) must run **from the GLPI container** — they boot a real `Glpi\Kernel\Kernel`:*

```bash
docker compose -f docker-compose.test.yml exec -w /var/www/html/glpi/plugins/configurationglpiauto glpi \
  vendor/bin/phpunit -c phpunit.xml
```

- **Analyse statique** : PHPStan est volontairement limité à `tests/Unit` (`phpstan.neon`) — tout le reste du code étend/instancie des classes du cœur GLPI qui n'existent que dans une instance réelle, non stubée pour l'analyse statique ici.
- ***Static analysis**: PHPStan is deliberately limited to `tests/Unit` (`phpstan.neon`) — the rest of the code extends/instantiates GLPI core classes that only exist in a real instance, not stubbed for static analysis here.*
- **Tests** : `tests/Unit` pour la logique pure sans dépendance GLPI, `tests/Integration` pour tout ce qui écrit réellement en base ou dépend du moteur de règles GLPI. Ajoutez un test pour tout comportement non trivial que vous introduisez ou corrigez.
- ***Tests**: `tests/Unit` for pure logic with no GLPI dependency, `tests/Integration` for anything that actually writes to the database or depends on GLPI's rules engine. Add a test for any non-trivial behavior you introduce or fix.*

## Vérification fonctionnelle
*Functional verification*

Au-delà de la suite qualité, tout changement visible dans l'assistant (nouvelle étape, nouveau réglage) doit être vérifié en le soumettant réellement via l'interface (ou Playwright) et en contrôlant l'état créé en base — la suite qualité seule ne détecte pas une régression Twig ou un mauvais mapping de champ. Voir `CHANGELOG.md` pour la profondeur de vérification attendue sur ce projet.

*Beyond the quality suite, any change visible in the wizard (new step, new setting) must be verified by actually submitting it through the interface (or Playwright) and checking the resulting state in the database — the quality suite alone won't catch a Twig regression or a wrong field mapping. See `CHANGELOG.md` for the depth of verification expected on this project.*

## Messages de commit
*Commit messages*

Ce dépôt écrit ses messages de commit en français, à l'impératif, et explique le **pourquoi** plutôt que de reformuler le diff. `git log` sur ce dépôt donne le ton à suivre.

*This repository writes its commit messages in French, in the imperative mood, and explains the **why** rather than restating the diff. `git log` on this repository sets the tone to follow.*

## Numéro de version
*Version number*

Toute Pull Request qui change le comportement du plugin doit incrémenter `PLUGIN_CONFIGURATIONGLPIAUTO_VERSION` dans `setup.php` (et la même valeur dans `composer.json`/`configurationglpiauto.xml`) — c'est ce que GLPI affiche sur **Configuration > Plugins**, et c'est lui qui signale à un administrateur qu'une mise à jour est disponible.

*Any Pull Request that changes the plugin's behavior must bump `PLUGIN_CONFIGURATIONGLPIAUTO_VERSION` in `setup.php` (and the same value in `composer.json`/`configurationglpiauto.xml`) — this is what GLPI displays under **Configuration > Plugins**, and it's what signals to an administrator that an update is available.*

## Publier une release
*Publishing a release*

Une fois `main` à jour avec les changements voulus :

*Once `main` is up to date with the intended changes:*

1. Vérifiez que `PLUGIN_CONFIGURATIONGLPIAUTO_VERSION` (`setup.php`) correspond bien à `composer.json`/`configurationglpiauto.xml`.
1. *Check that `PLUGIN_CONFIGURATIONGLPIAUTO_VERSION` (`setup.php`) actually matches `composer.json`/`configurationglpiauto.xml`.*
2. Créez et poussez un tag annoté au même format (`vX.Y.Z`, avec le `v`) :
2. *Create and push an annotated tag in the same format (`vX.Y.Z`, with the `v`):*
   ```bash
   git tag -a v1.2.3 -m "v1.2.3 : description courte"
   git push origin v1.2.3
   ```
3. `.github/workflows/release.yml` se déclenche automatiquement : il vérifie que le tag correspond à `PLUGIN_CONFIGURATIONGLPIAUTO_VERSION` (échoue sinon), construit l'archive de distribution, l'installe réellement sur une instance GLPI fraîche pour valider sa structure, puis publie la GitHub Release avec l'archive en pièce jointe.
3. *`.github/workflows/release.yml` triggers automatically: it checks that the tag matches `PLUGIN_CONFIGURATIONGLPIAUTO_VERSION` (fails otherwise), builds the distribution archive, actually installs it on a fresh GLPI instance to validate its structure, then publishes the GitHub Release with the archive attached.*

Pensez à ajouter une entrée dans [CHANGELOG.md](CHANGELOG.md) (format [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)) pour toute version publiée.

*Remember to add an entry to [CHANGELOG.md](CHANGELOG.md) (in [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) format) for every published version.*

## Signaler une vulnérabilité
*Reporting a vulnerability*

Ne passez pas par une issue publique — voir [SECURITY.md](SECURITY.md) pour la procédure.

*Do not go through a public issue — see [SECURITY.md](SECURITY.md) for the procedure.*

## Pour aller plus loin
*Going further*

- **[docs/TUTORIAL.md](docs/TUTORIAL.md)** — tutoriel pas à pas de l'assistant, utile pour comprendre le flux complet avant de le modifier.
- ***[docs/TUTORIAL.md](docs/TUTORIAL.md)** — step-by-step wizard tutorial, useful for understanding the full flow before changing it.*
- **[ROADMAP.md](ROADMAP.md)** — ce qui est prévu, en cours, ou explicitement écarté (avec la raison).
- ***[ROADMAP.md](ROADMAP.md)** — what's planned, in progress, or explicitly ruled out (with the reason).*

## Références officielles GLPI
*Official GLPI references*

Ce plugin suit les conventions officielles de développement de plugins GLPI 11 :

*This plugin follows the official GLPI 11 plugin development conventions:*

- **[Tutoriel officiel de création de plugin](https://glpi-developer-documentation.readthedocs.io/en/master/plugins/tutorial.html)** — `plugin_init_<key>()`, hooks, autoloading PSR-4 natif.
- ***[Official plugin creation tutorial](https://glpi-developer-documentation.readthedocs.io/en/master/plugins/tutorial.html)** — `plugin_init_<key>()`, hooks, native PSR-4 autoloading.*
- **[Documentation plugins GLPI, page "Create a new plugin"](https://glpi-plugins.readthedocs.io/fr/latest/empty/index.html#create-a-new-plugin)** — conventions de structure côté écosystème `pluginsGLPI` (marketplace).
- ***[GLPI plugins documentation, "Create a new plugin" page](https://glpi-plugins.readthedocs.io/fr/latest/empty/index.html#create-a-new-plugin)** — structure conventions on the `pluginsGLPI` ecosystem (marketplace) side.*
- **[pluginsGLPI/empty](https://github.com/pluginsGLPI/empty)** — squelette officiel minimal, référence pour `setup.php`/`hook.php`/le manifeste XML.
- ***[pluginsGLPI/empty](https://github.com/pluginsGLPI/empty)** — official minimal skeleton, reference for `setup.php`/`hook.php`/the XML manifest.*
