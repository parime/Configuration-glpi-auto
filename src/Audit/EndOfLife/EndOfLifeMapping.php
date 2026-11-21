<?php

/**
 * -------------------------------------------------------------------------
 * Configuration GLPI Auto plugin for GLPI
 * Copyright (C) 2026 Vincent GUILLOTTE
 * https://github.com/parime/Configuration-glpi-auto
 * -------------------------------------------------------------------------
 * LICENSE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version. See LICENSE for the full text.
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Configurationglpiauto\Audit\EndOfLife;

/**
 * Issue #467 : correspondance explicite entre un nom GLPI (système d'exploitation ou modèle de
 * matériel) et un produit endoflife.date, une ligne par nom : « Nom dans GLPI = produit » ou
 * « Nom dans GLPI = produit cycle » pour imposer le cycle. Rien n'est deviné en silence : les
 * propositions ne s'ajoutent que sur demande de l'administrateur.
 */
final class EndOfLifeMapping
{
    /** Formes courantes des systèmes : nom simplifié entier => produit endoflife.date. */
    public const SUGGESTIONS = [
        '/^microsoft windows (7|8\.1|10|11)( .*)?$/'                              => 'windows',
        '/^microsoft windows server \d{4}( .*)?$/'                               => 'windows-server',
        '/^debian( gnu\/linux)?( \d{1,2})?$/'                                    => 'debian',
        '/^ubuntu( server)?( \d{2}\.\d{2}(\.\d+)?)?( lts)?$/'                    => 'ubuntu',
        '/^(red hat enterprise linux|rhel)( server| workstation)?( \d{1,2})?$/'  => 'rhel',
        '/^centos( linux)?( \d{1,2})?$/'                                         => 'centos',
        '/^rocky linux( \d{1,2})?$/'                                             => 'rocky-linux',
        '/^almalinux( \d{1,2})?$/'                                               => 'almalinux',
        '/^fedora( linux)?( \d{2})?$/'                                           => 'fedora',
        '/^linux mint( \d{1,2})?$/'                                              => 'linuxmint',
        '/^(macos|mac os x|os x)( \d{2})?$/'                                     => 'macos',
        '/^oracle linux( server)?( \d{1,2})?$/'                                  => 'oracle-linux',
        '/^suse linux enterprise server( \d{2})?$/'                              => 'sles',
        '/^freebsd$/'                                                            => 'freebsd',
        '/^(vmware )?esxi$/'                                                     => 'esxi',
        '/^ios$/'                                                                => 'ios',
        '/^ipados$/'                                                             => 'ipados',
        '/^android( \d{1,2})?$/'                                                 => 'android',
    ];

    /**
     * @return array<string, array{name: string, product: string, cycle: string}> clé = nom GLPI en minuscules
     */
    public static function parse(string $text): array
    {
        $map = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            $position = strrpos($line, '=');
            if ($line === '' || str_starts_with($line, '#') || $position === false) {
                continue;
            }
            $name = trim((string) preg_replace('/\s+/u', ' ', substr($line, 0, $position)));
            $target = preg_split('/\s+/', strtolower(trim(substr($line, $position + 1))), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $product = (string) ($target[0] ?? '');
            if ($name === '' || !EndOfLifeClient::isValidProduct($product)) {
                continue;
            }
            $map[self::key($name)] = [
                'name'    => mb_substr($name, 0, 255),
                'product' => $product,
                'cycle'   => mb_substr((string) ($target[1] ?? ''), 0, 64),
            ];
        }

        return $map;
    }

    /** Texte rangé : lignes valides seulement, une par nom (la dernière l'emporte). */
    public static function normalize(string $text): string
    {
        return implode("\n", array_map(
            static fn (array $entry): string => $entry['name'] . ' = ' . trim($entry['product'] . ' ' . $entry['cycle']),
            array_values(self::parse($text))
        ));
    }

    public static function key(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)));
    }

    /** Produit proposé pour un nom de système, ou chaîne vide. */
    public static function suggest(string $name): string
    {
        $simple = self::key((string) preg_replace('/\([^)]*\)/u', ' ', $name));
        foreach (self::SUGGESTIONS as $pattern => $product) {
            if (preg_match($pattern, $simple) === 1) {
                return $product;
            }
        }

        return '';
    }

    /**
     * Ajoute au texte les propositions des noms qui n'ont pas encore de correspondance.
     *
     * @param list<string> $names
     */
    public static function withSuggestions(string $text, array $names): string
    {
        $map = self::parse($text);
        foreach ($names as $name) {
            $product = self::suggest($name);
            $key = self::key($name);
            if ($product !== '' && !isset($map[$key])) {
                $map[$key] = ['name' => trim($name), 'product' => $product, 'cycle' => ''];
            }
        }

        return implode("\n", array_map(
            static fn (array $entry): string => $entry['name'] . ' = ' . trim($entry['product'] . ' ' . $entry['cycle']),
            array_values($map)
        ));
    }

    /**
     * Produits endoflife.date cités, sans doublon.
     *
     * @return list<string>
     */
    public static function products(string $text): array
    {
        return array_values(array_unique(array_column(self::parse($text), 'product')));
    }
}
