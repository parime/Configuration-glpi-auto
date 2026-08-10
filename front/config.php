<?php

use GlpiPlugin\Configurationglpiauto\Config;

Session::checkRight(Config::$rightname, READ);

if (isset($_POST['update'])) {
    Session::checkRight(Config::$rightname, UPDATE);

    $config = Config::getConfig();
    $config->update($_POST + ['id' => $config->getID()]);
    Session::addMessageAfterRedirect(__('Configuration enregistrée.', 'configurationglpiauto'));
    Html::back();
}

Html::header(Config::getTypeName(), $_SERVER['PHP_SELF'], 'admin', Config::class);

$config = Config::getConfig();

\Glpi\Application\View\TemplateRenderer::getInstance()->display('@configurationglpiauto/config_form.html.twig', [
    'config'      => $config->fields,
    'modes'       => Config::getModes(),
    'max_levels'  => Config::MAX_LEVELS,
    'level_labels' => $config->getLevelLabels(),
    'csrf_token'  => Session::getNewCSRFToken(),
]);

Html::footer();
