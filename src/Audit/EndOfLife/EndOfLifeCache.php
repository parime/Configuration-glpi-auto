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
 * Issue #467 : dates endoflife.date gardées en base, par produit. Le contrôle d'audit ne lit que
 * ce cache (jamais le réseau) ; le téléchargement n'a lieu que sur demande, et jamais en mode
 * hors ligne, où un fichier JSON récupéré ailleurs peut être importé à la place.
 *
 * @phpstan-import-type Cycle from CycleMatcher
 */
final class EndOfLifeCache
{
    public const TABLE = 'glpi_plugin_configurationglpiauto_eolcaches';

    /** Âge (jours) au-delà duquel le contrôle conseille d'actualiser les dates. */
    public const STALE_DAYS = 30;

    /**
     * @return array<string, array{cycles: list<Cycle>, date: string}>
     */
    public static function all(): array
    {
        global $DB;

        $entries = [];
        if (!$DB->tableExists(self::TABLE)) {
            return $entries;
        }
        foreach ($DB->request(['FROM' => self::TABLE]) as $row) {
            $cycles = json_decode((string) $row['cycles'], true);
            if (is_array($cycles)) {
                /** @var list<Cycle> $cycles */
                $entries[(string) $row['product']] = ['cycles' => $cycles, 'date' => (string) $row['date_mod']];
            }
        }

        return $entries;
    }

    /**
     * @param list<Cycle> $cycles
     */
    public static function store(string $product, array $cycles): void
    {
        global $DB;

        $fields = ['cycles' => (string) json_encode($cycles), 'date_mod' => date('Y-m-d H:i:s')];
        $DB->updateOrInsert(self::TABLE, $fields, ['product' => $product]);
    }

    /**
     * Télécharge les produits donnés. Renvoie les produits en échec (introuvables ou injoignables) :
     * le cache précédent de ces produits est gardé.
     *
     * @param list<string> $products
     * @return list<string>
     */
    public static function refresh(array $products, ?EndOfLifeClient $client = null): array
    {
        $client ??= new EndOfLifeClient();
        $failed = [];
        foreach ($products as $product) {
            try {
                self::store($product, $client->cycles($product));
            } catch (\Throwable) {
                $failed[] = $product;
            }
        }

        return $failed;
    }

    /**
     * Mode hors ligne : contenu d'un fichier JSON endoflife.date (API v1 ou ancienne API).
     *
     * @return int nombre de cycles importés
     * @throws \RuntimeException produit invalide ou fichier illisible
     */
    public static function import(string $product, string $json): int
    {
        if (!EndOfLifeClient::isValidProduct($product)) {
            throw new \RuntimeException('invalid product');
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException('invalid json');
        }
        $cycles = EndOfLifeClient::parse($data);
        self::store($product, $cycles);

        return count($cycles);
    }
}
