<?php

use GlpiPlugin\Configurationglpiauto\Config;
use GlpiPlugin\Configurationglpiauto\ConfigurationProfile;
use GlpiPlugin\Configurationglpiauto\EntityBuilder;

Session::checkRight(Config::$rightname, READ);

if (isset($_POST['finish'])) {
    Session::checkRight(Config::$rightname, UPDATE);

    $config = Config::getConfig();
    $config->update($_POST + ['id' => $config->getID()]);
    $created = (new EntityBuilder())->build($config);

    if (empty($created)) {
        Session::addMessageAfterRedirect(__('Configuration enregistrée (mode mono-entité : aucune entité à créer).', 'configurationglpiauto'));
    } else {
        Session::addMessageAfterRedirect(sprintf(
            __('Configuration enregistrée et structure créée : %s.', 'configurationglpiauto'),
            implode(' > ', $created)
        ));
    }

    Html::redirect(ConfigurationProfile::getSearchURL());
}

Html::header(__('Assistant de configuration', 'configurationglpiauto'), $_SERVER['PHP_SELF'], 'admin', ConfigurationProfile::class);

$config = Config::getConfig();
$profiles = (new ConfigurationProfile())->find(['is_active' => 1], ['sort_order ASC']);

\Glpi\Application\View\TemplateRenderer::getInstance()->display('@configurationglpiauto/wizard.html.twig', [
    'config'       => $config->fields,
    'profiles'     => $profiles,
    'modes'        => Config::getModes(),
    'max_levels'   => Config::MAX_LEVELS,
    'level_labels' => $config->getLevelLabels(),
    'csrf_token'   => Session::getNewCSRFToken(),
]);

Html::footer();
