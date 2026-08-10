<?php

use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\EntityBuilder;

Session::checkRight(Config::$rightname, READ);

if (isset($_POST['update'])) {
    Session::checkRight(Config::$rightname, UPDATE);

    $config = Config::getConfig();
    $config->update($_POST + ['id' => $config->getID()]);
    Session::addMessageAfterRedirect(__('Configuration enregistrée.', 'configurationglpiauto'));
    Html::back();
}

if (isset($_POST['apply'])) {
    Session::checkRight(Config::$rightname, UPDATE);

    $config = Config::getConfig();
    $config->update($_POST + ['id' => $config->getID()]);
    $created = (new EntityBuilder())->build($config);

    if (empty($created)) {
        Session::addMessageAfterRedirect(__('Mode mono-entité : aucune entité à créer.', 'configurationglpiauto'));
    } else {
        Session::addMessageAfterRedirect(sprintf(
            __('Structure appliquée : %s. Renommez/dupliquez ensuite depuis Administration > Entités.', 'configurationglpiauto'),
            EntityBuilder::describe($created)
        ));
    }
    Html::back();
}

Html::header(Config::getTypeName(), $_SERVER['PHP_SELF'], 'admin', Config::class);

$config = Config::getConfig();

\Glpi\Application\View\TemplateRenderer::getInstance()->display('@configurationglpiauto/config_form.html.twig', [
    'config'          => $config->fields,
    'modes'           => Config::getModes(),
    'max_levels'      => Config::MAX_LEVELS,
    'level_labels'    => $config->getLevelLabels(),
    'top_level_names' => $config->getTopLevelNames(),
    'csrf_token'      => Session::getNewCSRFToken(),
]);

Html::footer();
