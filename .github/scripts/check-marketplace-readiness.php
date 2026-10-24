<?php

/**
 * Exigences de revue du Marketplace GLPI (glpi-developer-documentation, "Publishing your plugin") :
 *
 * 1. Pas de SQL brut : $DB->doQuery()/query() n'est admis que dans le code d'installation, de mise
 *    a jour ou de desinstallation (fonction dont le nom contient install, migrat, upgrade, schema ou
 *    table). Partout ailleurs, utiliser $DB->request(), insert(), update(), delete(). Une exception
 *    se justifie dans .github/marketplace-allowlist.txt ("raw-sql:<fichier>:<fonction> : raison").
 * 2. Twig des que possible : le nombre de chaines HTML ecrites en PHP ne doit jamais augmenter.
 *    La reference par fichier est dans .github/twig-baseline.txt. Quand un ecran passe en Twig, le
 *    compte baisse et la CI demande de regenerer la reference (--update-baseline), pour que le
 *    gain soit acquis et ne puisse pas etre reperdu.
 *
 * Le controle des droits dans front/ et ajax/ est verifie par check-entrypoints-auth.sh.
 *
 * Usage : php .github/scripts/check-marketplace-readiness.php [--update-baseline]
 */

declare(strict_types=1);

const BASELINE  = '.github/twig-baseline.txt';
const ALLOWLIST = '.github/marketplace-allowlist.txt';
const RAW_SQL_METHODS = ['doquery', 'query', 'queryordie', 'doqueryordie', 'domultiplequeries'];
const INSTALL_FUNCTION = '/install|migrat|upgrade|schema|table/i';
const HTML_TAG = '/<\/?(div|span|table|tr|td|th|thead|tbody|form|input|select|option|textarea|button|label|'
    . 'a|p|h[1-6]|ul|ol|li|br|hr|i|b|em|strong|small|img|fieldset|legend|section|nav|pre|code)[\s>\/]/i';

function phpFiles(): array
{
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('.', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $path = substr(str_replace('\\', '/', $file->getPathname()), 2);
        if (
            $file->getExtension() !== 'php'
            || preg_match('#^(\.|(vendor|node_modules|tests|lib|build|dist)/)#', $path)
        ) {
            continue;
        }
        $files[] = $path;
    }
    sort($files);
    return $files;
}

/**
 * Parcourt les jetons d'un fichier : appels de SQL brut (avec la fonction nommee englobante) et
 * nombre de chaines contenant une balise HTML.
 *
 * @return array{raw: list<array{line: int, function: string}>, html: int}
 */
function analyse(string $path): array
{
    $tokens = token_get_all((string) file_get_contents($path));
    $raw = [];
    $html = 0;
    $depth = 0;
    $stack = [];
    $pending = null;
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $t = $tokens[$i];
        $id = is_array($t) ? $t[0] : null;
        $text = is_array($t) ? $t[1] : $t;
        if ($id === T_FUNCTION) {
            $j = $i + 1;
            while ($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT], true)) {
                $j++;
            }
            if ($j < $count && $tokens[$j] === '&') {
                $j++;
                while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    $j++;
                }
            }
            $pending = (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) ? $tokens[$j][1] : null;
            continue;
        }
        if ($text === ';' && $pending !== null) {
            $pending = null;
        }
        if ($text === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
            $depth++;
            if ($pending !== null && $text === '{') {
                $stack[] = [$pending, $depth];
                $pending = null;
            }
            continue;
        }
        if ($text === '}') {
            if ($stack && end($stack)[1] === $depth) {
                array_pop($stack);
            }
            $depth--;
            continue;
        }
        if ($id === T_CONSTANT_ENCAPSED_STRING || $id === T_ENCAPSED_AND_WHITESPACE || $id === T_INLINE_HTML) {
            if (preg_match(HTML_TAG, $text)) {
                $html++;
            }
            continue;
        }
        if (($id === T_OBJECT_OPERATOR || $id === T_NULLSAFE_OBJECT_OPERATOR) && isset($tokens[$i + 2])) {
            $name = $tokens[$i + 1];
            if (
                is_array($name) && $name[0] === T_STRING
                && in_array(strtolower($name[1]), RAW_SQL_METHODS, true)
                && $tokens[$i + 2] === '('
            ) {
                $raw[] = ['line' => $name[2], 'function' => $stack ? $stack[0][0] : '(global)'];
            }
        }
    }
    return ['raw' => $raw, 'html' => $html];
}

function readPairs(string $file, string $separator): array
{
    $pairs = [];
    if (!is_file($file)) {
        return $pairs;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, $separator)) {
            continue;
        }
        [$key, $value] = array_map('trim', explode($separator, $line, 2));
        $pairs[$key] = $value;
    }
    return $pairs;
}

$update = in_array('--update-baseline', $argv, true);
$allow = readPairs(ALLOWLIST, ' : ');
$baseline = readPairs(BASELINE, ' ');
$status = 0;
$current = [];
foreach (phpFiles() as $path) {
    $result = analyse($path);
    foreach ($result['raw'] as $call) {
        $key = 'raw-sql:' . $path . ':' . $call['function'];
        if (preg_match(INSTALL_FUNCTION, $call['function'])) {
            continue;
        }
        if (($allow[$key] ?? '') !== '') {
            continue;
        }
        echo "::error file=$path,line={$call['line']}::SQL brut hors installation (fonction {$call['function']}) :"
            . " utiliser \$DB->request()/insert()/update()/delete(), ou justifier \"$key : raison\" dans "
            . ALLOWLIST . "\n";
        $status = 1;
    }
    if ($result['html'] > 0) {
        $current[$path] = $result['html'];
    }
}

if ($update) {
    $out = "# Chaines HTML ecrites en PHP, par fichier (genere par check-marketplace-readiness.php).\n"
        . "# Ces nombres ne peuvent que baisser : les nouveaux ecrans s'ecrivent en Twig (templates/).\n";
    foreach ($current as $path => $n) {
        $out .= "$path $n\n";
    }
    file_put_contents(BASELINE, $out);
    echo 'Reference Twig mise a jour : ' . array_sum($current) . " chaines HTML dans " . count($current)
        . " fichiers PHP.\n";
    exit($status);
}

$lowered = [];
foreach ($current as $path => $n) {
    $ref = (int) ($baseline[$path] ?? 0);
    if ($n > $ref) {
        echo "::error file=$path::$n chaines HTML ecrites en PHP (reference $ref) : ecrire le nouvel affichage"
            . " dans un gabarit Twig (templates/) plutot qu'en PHP\n";
        $status = 1;
    } elseif ($n < $ref) {
        $lowered[] = $path;
    }
}
foreach ($baseline as $path => $ref) {
    if (!isset($current[$path]) && (int) $ref > 0) {
        $lowered[] = $path;
    }
}
if ($lowered) {
    echo '::error::Moins de HTML en PHP dans ' . implode(', ', $lowered) . ' : bravo, executer'
        . ' "php .github/scripts/check-marketplace-readiness.php --update-baseline" pour acquerir ce gain.' . "\n";
    $status = 1;
}
echo 'HTML ecrit en PHP : ' . array_sum($current) . ' chaines (reference ' . array_sum(array_map('intval', $baseline))
    . ").\n";
exit($status);
