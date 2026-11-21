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

use GlpiPlugin\Configurationglpiauto\Compat\Http;

/**
 * Issue #467 : client de l'API publique endoflife.date (cycles d'un produit et leur fin de
 * support). Seule cette adresse est appelée, jamais une autre : pas d'adresse configurable.
 * Le transport est injectable pour les tests (aucun appel réseau dans la CI).
 */
final class EndOfLifeClient
{
    public const URL = 'https://endoflife.date/api/v1/products/%s/';

    /** Fin de support atteinte sans date connue (« isEol » sans « eolFrom »). */
    public const EOL_WITHOUT_DATE = 'yes';

    public const MAX_CYCLES = 300;

    /** @var callable(string): string */
    private $transport;

    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport ?? static fn (string $url): string => Http::get($url, [
            'headers' => ['Accept' => 'application/json'],
            'timeout' => 10,
        ]);
    }

    /** Identifiant de produit endoflife.date (« windows », « ubuntu », « iphone »). */
    public static function isValidProduct(string $product): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9.+_-]{0,63}$/', $product) === 1;
    }

    public static function productUrl(string $product): string
    {
        return sprintf(self::URL, rawurlencode($product));
    }

    /** Page publique du produit, pour vérifier une correspondance. */
    public static function productPage(string $product): string
    {
        return 'https://endoflife.date/' . rawurlencode($product);
    }

    /**
     * @return list<array{cycle: string, codename: string, eol: string, latest: string}>
     * @throws \RuntimeException produit invalide, site injoignable ou réponse inattendue
     */
    public function cycles(string $product): array
    {
        if (!self::isValidProduct($product)) {
            throw new \RuntimeException('invalid product');
        }
        $data = json_decode(($this->transport)(self::productUrl($product)), true);
        if (!is_array($data)) {
            throw new \RuntimeException('unexpected response');
        }

        return self::parse($data);
    }

    /**
     * Lit une réponse de l'API v1 (`result.releases`) ou de l'ancienne API (liste de cycles) :
     * c'est aussi le format d'un fichier téléchargé à la main pour le mode hors ligne.
     *
     * @param array<mixed> $data
     * @return list<array{cycle: string, codename: string, eol: string, latest: string}>
     * @throws \RuntimeException format inconnu
     */
    public static function parse(array $data): array
    {
        $isV1 = isset($data['result']) && is_array($data['result']);
        $releases = $isV1 ? ($data['result']['releases'] ?? null) : $data;
        if (!is_array($releases) || !array_is_list($releases) || $releases === []) {
            throw new \RuntimeException('unexpected format');
        }
        $cycles = [];
        foreach ($releases as $release) {
            if (!is_array($release) || count($cycles) >= self::MAX_CYCLES) {
                continue;
            }
            $name = self::text($release[$isV1 ? 'name' : 'cycle'] ?? null);
            if ($name === '') {
                continue;
            }
            if ($isV1) {
                $eol = self::date($release['eolFrom'] ?? null);
                if ($eol === '' && ($release['isEol'] ?? false) === true) {
                    $eol = self::EOL_WITHOUT_DATE;
                }
                $latest = is_array($release['latest'] ?? null) ? self::text($release['latest']['name'] ?? null) : '';
            } else {
                $value = $release['eol'] ?? null;
                $eol = $value === true ? self::EOL_WITHOUT_DATE : self::date($value);
                $latest = self::text($release['latest'] ?? null);
            }
            $cycles[] = [
                'cycle'    => mb_substr($name, 0, 64),
                'codename' => mb_substr(self::text($release['codename'] ?? null), 0, 64),
                'eol'      => $eol,
                'latest'   => mb_substr($latest, 0, 64),
            ];
        }
        if ($cycles === []) {
            throw new \RuntimeException('unexpected format');
        }

        return $cycles;
    }

    private static function text(mixed $value): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return '';
        }

        return trim((string) preg_replace('/[\x00-\x1f\x7f]/u', '', (string) $value));
    }

    private static function date(mixed $value): string
    {
        if (!is_string($value) || preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m) !== 1) {
            return '';
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $m[1] . '-' . $m[2] . '-' . $m[3] : '';
    }
}
