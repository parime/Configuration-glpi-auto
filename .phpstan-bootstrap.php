<?php

/**
 * Constantes définies par le cœur GLPI à l'exécution réelle (front-controller), mais absentes du
 * simple `require vendor/autoload.php` utilisé ici comme bootstrap PHPStan (on évite
 * volontairement le vrai bootstrap Kernel, qui exige une connexion DB). Valeurs arbitraires :
 * seule leur existence compte pour l'analyse statique, jamais leur contenu réel. Même convention
 * que le plugin jumeau assetsign-glpi (`.phpstan-bootstrap.php`), qui a le premier prouvé que
 * `src/` est bel et bien analysable sans DB — contrairement à ce que ce plugin supposait jusqu'ici
 * (voir l'historique de phpstan.neon avant ce correctif).
 */
if (!defined('GLPI_DOC_DIR')) {
    define('GLPI_DOC_DIR', '/tmp/glpi-phpstan-doc');
}
if (!defined('GLPI_TMP_DIR')) {
    define('GLPI_TMP_DIR', '/tmp/glpi-phpstan-tmp');
}
if (!defined('GLPI_PLUGIN_DOC_DIR')) {
    define('GLPI_PLUGIN_DOC_DIR', '/tmp/glpi-phpstan-plugin-doc');
}
if (!defined('GLPI_THEMES_DIR')) {
    define('GLPI_THEMES_DIR', '/tmp/glpi-phpstan-themes');
}
if (!defined('GLPI_CACHE_DIR')) {
    define('GLPI_CACHE_DIR', '/tmp/glpi-phpstan-cache');
}

/**
 * Autoload de GLPI lui-même (classes globales CommonDBTM, Session, Toolbox...), nécessaire pour
 * que PHPStan résolve les types du cœur GLPI utilisés par ce plugin. Chemin fixe dans l'image
 * officielle glpi/glpi (utilisée en CI) ; variante /var/www/html/glpi conservée pour d'autres
 * agencements locaux (l'instance de développement partagée de ce plugin, image diouxx/glpi).
 */
foreach (['/var/www/glpi/vendor/autoload.php', '/var/www/html/glpi/vendor/autoload.php'] as $glpiAutoload) {
    if (is_file($glpiAutoload)) {
        require_once $glpiAutoload;
        break;
    }
}
