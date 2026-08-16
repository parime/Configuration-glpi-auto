# Contribuer

## Workflow des branches

- `dev` accumule le travail en cours — poussez-y vos changements.
- `main` est la branche stable, protégée : toute modification doit passer par une Pull Request avec au moins une revue approuvée et une CI verte (voir `.github/workflows/continuous-integration.yml`).
- Ne travaillez pas directement sur `main`.

## Environnement de test

Ce plugin a besoin d'une vraie instance GLPI pour être testé sérieusement — beaucoup de code n'a de sens qu'exécuté contre un vrai `$DB`, de vraies capacités d'actif GLPI 11, un vrai moteur de règles. La stack Docker fournie (`docker-compose.test.yml`) monte GLPI + MariaDB avec ce dépôt monté en lecture seule comme plugin :

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

Toutes ces vérifications tournent automatiquement en CI à chaque push/Pull Request, mais autant les faire passer localement d'abord :

```bash
php -l <fichier modifié>                                    # syntaxe
vendor/bin/phpstan analyse --no-progress                    # analyse statique (tests/Unit uniquement, voir phpstan.neon)
vendor/bin/php-cs-fixer fix --dry-run --diff --allow-risky=yes
vendor/bin/phpunit -c phpunit.xml.dist                      # tests unitaires purs, pas de GLPI requis
```

Les tests d'intégration (`tests/Integration/`, contre une vraie instance) doivent tourner **depuis le conteneur GLPI** — ils démarrent un vrai `Glpi\Kernel\Kernel` :

```bash
docker compose -f docker-compose.test.yml exec -w /var/www/html/glpi/plugins/configurationglpiauto glpi \
  vendor/bin/phpunit -c phpunit.xml
```

- **Analyse statique** : PHPStan est volontairement limité à `tests/Unit` (`phpstan.neon`) — tout le reste du code étend/instancie des classes du cœur GLPI qui n'existent que dans une instance réelle, non stubée pour l'analyse statique ici.
- **Tests** : `tests/Unit` pour la logique pure sans dépendance GLPI, `tests/Integration` pour tout ce qui écrit réellement en base ou dépend du moteur de règles GLPI. Ajoutez un test pour tout comportement non trivial que vous introduisez ou corrigez.

## Vérification fonctionnelle

Au-delà de la suite qualité, tout changement visible dans l'assistant (nouvelle étape, nouveau réglage) doit être vérifié en le soumettant réellement via l'interface (ou Playwright) et en contrôlant l'état créé en base — la suite qualité seule ne détecte pas une régression Twig ou un mauvais mapping de champ. Voir `CHANGELOG.md` pour la profondeur de vérification attendue sur ce projet.

## Messages de commit

Ce dépôt écrit ses messages de commit en français, à l'impératif, et explique le **pourquoi** plutôt que de reformuler le diff. `git log` sur ce dépôt donne le ton à suivre.

## Numéro de version

Toute Pull Request qui change le comportement du plugin doit incrémenter `PLUGIN_CONFIGURATIONGLPIAUTO_VERSION` dans `setup.php` (et la même valeur dans `composer.json`/`configurationglpiauto.xml`) — c'est ce que GLPI affiche sur **Configuration > Plugins**, et c'est lui qui signale à un administrateur qu'une mise à jour est disponible.

## Publier une release

Une fois `main` à jour avec les changements voulus :

1. Vérifiez que `PLUGIN_CONFIGURATIONGLPIAUTO_VERSION` (`setup.php`) correspond bien à `composer.json`/`configurationglpiauto.xml`.
2. Créez et poussez un tag annoté au même format (`vX.Y.Z`, avec le `v`) :
   ```bash
   git tag -a v1.2.3 -m "v1.2.3 : description courte"
   git push origin v1.2.3
   ```
3. `.github/workflows/release.yml` se déclenche automatiquement : il vérifie que le tag correspond à `PLUGIN_CONFIGURATIONGLPIAUTO_VERSION` (échoue sinon), construit l'archive de distribution, l'installe réellement sur une instance GLPI fraîche pour valider sa structure, puis publie la GitHub Release avec l'archive en pièce jointe.

Pensez à ajouter une entrée dans [CHANGELOG.md](CHANGELOG.md) (format [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)) pour toute version publiée.

## Signaler une vulnérabilité

Ne passez pas par une issue publique — voir [SECURITY.md](SECURITY.md) pour la procédure.

## Pour aller plus loin

- **[docs/TUTORIAL.md](docs/TUTORIAL.md)** — tutoriel pas à pas de l'assistant, utile pour comprendre le flux complet avant de le modifier.
- **[ROADMAP.md](ROADMAP.md)** — ce qui est prévu, en cours, ou explicitement écarté (avec la raison).

## Références officielles GLPI

Ce plugin suit les conventions officielles de développement de plugins GLPI 11 :

- **[Tutoriel officiel de création de plugin](https://glpi-developer-documentation.readthedocs.io/en/master/plugins/tutorial.html)** — `plugin_init_<key>()`, hooks, autoloading PSR-4 natif.
- **[Documentation plugins GLPI, page "Create a new plugin"](https://glpi-plugins.readthedocs.io/fr/latest/empty/index.html#create-a-new-plugin)** — conventions de structure côté écosystème `pluginsGLPI` (marketplace).
- **[pluginsGLPI/empty](https://github.com/pluginsGLPI/empty)** — squelette officiel minimal, référence pour `setup.php`/`hook.php`/le manifeste XML.
