<?php

use GlpiPlugin\Configurationglpiauto\ConfigurationProfile;

Session::checkRight(ConfigurationProfile::$rightname, READ);

Html::header(ConfigurationProfile::getTypeName(2), $_SERVER['PHP_SELF'], 'admin', ConfigurationProfile::class);

// Search::show() alone generates no create link (same pitfall documented on remise-glpi).
if (ConfigurationProfile::canCreate()) {
    echo "<div class='mb-3'>";
    echo "<a class='btn btn-primary' href='" . htmlspecialchars(ConfigurationProfile::getFormURL()) . "'>";
    echo "<i class='ti ti-plus'></i> " . __('Ajouter');
    echo "</a>";
    echo "</div>";
}

Search::show(ConfigurationProfile::class);

Html::footer();
