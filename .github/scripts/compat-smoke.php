<?php
// Charge toutes les classes d'un plugin heritant d'une classe Compat Base, sur le vrai coeur GLPI,
// et verifie que chaque propriete redeclaree a la valeur attendue, dans un emplacement propre.
// usage: php smoke.php <glpi src root> <plugin root>
[$_, $glpi, $plugin] = $argv;
require "$glpi/vendor/autoload.php";
if (!defined('GLPI_VERSION')) {
    require "$glpi/src/autoload/constants.php";
}

// Index nom de classe -> fichier, pour tout le code du plugin (src/ et inc/).
$index = [];
foreach (['src', 'inc'] as $d) {
    if (!is_dir("$plugin/$d")) {
        continue;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$plugin/$d")) as $f) {
        if (!$f->isFile() || !str_ends_with($f->getFilename(), '.php')) {
            continue;
        }
        $s = file_get_contents($f->getPathname());
        $ns = preg_match('/^namespace\s+([^;]+);/m', $s, $m) ? $m[1] . '\\' : '';
        if (preg_match_all('/^\s*(?:final\s+|abstract\s+)*(?:class|trait|interface|enum)\s+(\w+)/m', $s, $m)) {
            foreach ($m[1] as $c) {
                $index[$ns . $c] ??= $f->getPathname();
            }
        }
    }
}
spl_autoload_register(function ($c) use ($index) {
    if (isset($index[$c])) {
        require_once $index[$c];
    }
});

$props = [
    'rightname' => 'RIGHTNAME', 'itemtype_1' => 'ITEMTYPE_1', 'items_id_1' => 'ITEMS_ID_1', 'itemtype_2' => 'ITEMTYPE_2',
    'items_id_2' => 'ITEMS_ID_2', 'checkItem_2_Rights' => 'CHECK_ITEM_2_RIGHTS', 'itemtype' => 'ITEMTYPE', 'items_id' => 'ITEMS_ID',
    'dohistory' => true, 'usenotepad' => true, 'userlinkclass' => 'USERLINKCLASS', 'grouplinkclass' => 'GROUPLINKCLASS',
    'supplierlinkclass' => 'SUPPLIERLINKCLASS', 'menu_option' => 'MENU_OPTION',
];
$before = [
    'CommonGLPI::$rightname' => CommonGLPI::$rightname, 'CommonDropdown::$rightname' => CommonDropdown::$rightname,
    'Rule::$rightname' => Rule::$rightname, 'RuleCollection::$rightname' => RuleCollection::$rightname,
];

$checked = $errors = 0;
foreach ($index as $class => $file) {
    $src = file_get_contents($file);
    if (!preg_match('/class\s+' . preg_quote(substr(strrchr('\\' . $class, '\\'), 1)) . '\s+extends\s+\S*Base\b/', $src)) {
        continue;
    }
    $r = new ReflectionClass($class);
    $base = $r->getParentClass();
    foreach ($base->getProperties() as $p) {
        if ($p->getDeclaringClass()->getName() !== $base->getName() || !isset($props[$p->getName()])) {
            continue;
        }
        $want = $props[$p->getName()];
        $expected = is_bool($want) ? $want : constant("$class::$want");
        $actual = $p->isStatic() ? $r->getProperty($p->getName())->getValue() : $r->getDefaultProperties()[$p->getName()];
        $checked++;
        if ($actual !== $expected) {
            $errors++;
            echo "FAIL $class::\${$p->getName()} = " . var_export($actual, true) . ' attendu ' . var_export($expected, true) . "\n";
        }
    }
}
foreach ($before as $k => $v) {
    [$c, $prop] = explode('::$', $k);
    if ($c::$$prop !== $v) {
        $errors++;
        echo "FAIL coeur GLPI modifie : $k = " . var_export($c::$$prop, true) . "\n";
    }
}
printf("PHP %s / GLPI %s / %s : %d proprietes verifiees, %d erreur(s)\n", PHP_VERSION, GLPI_VERSION, basename($plugin), $checked, $errors);
exit($errors ? 1 : 0);
