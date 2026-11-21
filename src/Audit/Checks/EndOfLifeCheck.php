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

namespace GlpiPlugin\Configurationglpiauto\Audit\Checks;

use Computer;
use ComputerModel;
use GlpiPlugin\Configurationglpiauto\Audit\AuditCheckInterface;
use GlpiPlugin\Configurationglpiauto\Audit\AuditFinding;
use GlpiPlugin\Configurationglpiauto\Audit\EndOfLife\CycleMatcher;
use GlpiPlugin\Configurationglpiauto\Audit\EndOfLife\EndOfLifeCache;
use GlpiPlugin\Configurationglpiauto\Audit\EndOfLife\EndOfLifeClient;
use GlpiPlugin\Configurationglpiauto\Audit\EndOfLife\EndOfLifeMapping;
use GlpiPlugin\Configurationglpiauto\Audit\ParkAudit;
use GlpiPlugin\Configurationglpiauto\Config;
use Item_OperatingSystem;
use Monitor;
use MonitorModel;
use NetworkEquipment;
use NetworkEquipmentModel;
use OperatingSystem;
use OperatingSystemVersion;
use Peripheral;
use PeripheralModel;
use Phone;
use PhoneModel;
use Printer;
use PrinterModel;

/**
 * Issue #467 : systèmes d'exploitation et modèles de matériel hors support, ou bientôt hors
 * support, d'après les dates endoflife.date. Facultatif (désactivé par défaut) ; correspondance
 * explicite nom GLPI => produit ; le contrôle ne lit que le cache, jamais le réseau. Lecture seule :
 * rien n'est modifié sur le matériel, les tickets ne sont ouverts que sur clic.
 *
 * @phpstan-import-type Cycle from CycleMatcher
 * @phpstan-type Source array{itemtype: string, row: array<string, mixed>, kind: string, name: string, version: string}
 * @phpstan-type Found array{itemtype: string, row: array<string, mixed>, kind: string, name: string, version: string, product: string, cycle: string, eol: string, status: string}
 */
final class EndOfLifeCheck implements AuditCheckInterface
{
    public const TICKET_PREFIX = '[Audit] Fin de support';

    public const KIND_OS = 'os';
    public const KIND_MODEL = 'model';

    /** Modèle de chaque type de matériel du parc. */
    public const MODELS = [
        Computer::class         => ComputerModel::class,
        Monitor::class          => MonitorModel::class,
        NetworkEquipment::class => NetworkEquipmentModel::class,
        Peripheral::class       => PeripheralModel::class,
        Phone::class            => PhoneModel::class,
        Printer::class          => PrinterModel::class,
    ];

    public function getKey(): string
    {
        return 'end_of_life';
    }

    public function getDomain(): string
    {
        return __('Parc', 'configurationglpiauto');
    }

    public static function enabled(): bool
    {
        return !empty(Config::getConfig()->fields['audit_eol_enabled']);
    }

    public static function days(): int
    {
        return (int) (Config::getConfig()->fields['audit_eol_days'] ?? 180);
    }

    public static function mappingText(): string
    {
        return (string) (Config::getConfig()->fields['audit_eol_mapping'] ?? '');
    }

    /**
     * Systèmes et modèles du matériel en service.
     *
     * @return list<Source>
     */
    public static function sources(): array
    {
        global $DB;

        $sources = [];
        $ios = Item_OperatingSystem::getTable();
        $os = OperatingSystem::getTable();
        $osv = OperatingSystemVersion::getTable();
        foreach (self::MODELS as $itemtype => $modelClass) {
            $table = $itemtype::getTable();
            $rows = $DB->request([
                'SELECT'     => ["$table.id", "$table.name", "$table.entities_id", "$os.name AS os_name", "$osv.name AS os_version"],
                'FROM'       => $ios,
                'INNER JOIN' => [
                    $table => ['FKEY' => [$ios => 'items_id', $table => 'id']],
                    $os    => ['FKEY' => [$ios => 'operatingsystems_id', $os => 'id']],
                ],
                'LEFT JOIN'  => [$osv => ['FKEY' => [$ios => 'operatingsystemversions_id', $osv => 'id']]],
                'WHERE'      => array_merge(ParkAudit::inServiceCriteria($itemtype), ["$ios.itemtype" => $itemtype, "$ios.is_deleted" => 0]),
            ]);
            foreach ($rows as $row) {
                $sources[] = ['itemtype' => $itemtype, 'row' => $row, 'kind' => self::KIND_OS, 'name' => (string) $row['os_name'], 'version' => (string) ($row['os_version'] ?? '')];
            }
            $models = $modelClass::getTable();
            $rows = $DB->request([
                'SELECT'     => ["$table.id", "$table.name", "$table.entities_id", "$models.name AS model_name"],
                'FROM'       => $table,
                'INNER JOIN' => [$models => ['FKEY' => [$table => $modelClass::getForeignKeyField(), $models => 'id']]],
                'WHERE'      => ParkAudit::inServiceCriteria($itemtype),
            ]);
            foreach ($rows as $row) {
                $sources[] = ['itemtype' => $itemtype, 'row' => $row, 'kind' => self::KIND_MODEL, 'name' => (string) $row['model_name'], 'version' => ''];
            }
        }

        return $sources;
    }

    /**
     * Classement pur : éléments hors support ou bientôt, et ce qui empêche de conclure.
     *
     * @param list<Source> $sources
     * @param array<string, array{name: string, product: string, cycle: string}> $mapping
     * @param array<string, array{cycles: list<Cycle>, date: string}> $cache
     * @return array{found: list<Found>, missing: list<string>, unmatched: list<string>, stale: list<string>}
     */
    public static function classify(array $sources, array $mapping, array $cache, string $today, int $warningDays): array
    {
        $found = [];
        $missing = [];
        $unmatched = [];
        $stale = [];
        $staleLimit = date('Y-m-d', (int) strtotime(substr($today, 0, 10) . ' -' . EndOfLifeCache::STALE_DAYS . ' days'));
        foreach ($sources as $source) {
            $target = $mapping[EndOfLifeMapping::key($source['name'])] ?? null;
            if ($target === null) {
                continue;
            }
            $product = $target['product'];
            if (!isset($cache[$product])) {
                $missing[$product] = $product;
                continue;
            }
            if (substr($cache[$product]['date'], 0, 10) < $staleLimit) {
                $stale[$product] = $product;
            }
            $cycle = CycleMatcher::match($source['name'], $source['version'], $cache[$product]['cycles'], $target['cycle']);
            if ($cycle === null) {
                $label = trim($source['name'] . ' ' . $source['version']);
                $unmatched[$label] = $label;
                continue;
            }
            $status = CycleMatcher::status($cycle, $today, $warningDays);
            if (in_array($status['status'], [CycleMatcher::STATUS_EOL, CycleMatcher::STATUS_SOON], true)) {
                $found[] = $source + ['product' => $product, 'cycle' => $cycle['cycle'], 'eol' => $cycle['eol'], 'status' => $status['status']];
            }
        }
        // Hors support d'abord (sans date en tête), puis fin la plus proche.
        $rank = static fn (array $f): string => ($f['status'] === CycleMatcher::STATUS_EOL ? '0' : '1')
            . ($f['eol'] === EndOfLifeClient::EOL_WITHOUT_DATE ? '0000-00-00' : $f['eol']);
        usort($found, static fn (array $a, array $b): int => strcmp($rank($a), $rank($b)));

        return ['found' => $found, 'missing' => array_values($missing), 'unmatched' => array_values($unmatched), 'stale' => array_values($stale)];
    }

    /**
     * @return array{found: list<Found>, missing: list<string>, unmatched: list<string>, stale: list<string>}
     */
    public static function evaluate(): array
    {
        $mapping = EndOfLifeMapping::parse(self::mappingText());
        if (!self::enabled() || $mapping === []) {
            return ['found' => [], 'missing' => [], 'unmatched' => [], 'stale' => []];
        }

        return self::classify(self::sources(), $mapping, EndOfLifeCache::all(), date('Y-m-d'), self::days());
    }

    /**
     * Noms de systèmes du parc, pour les propositions de correspondance.
     *
     * @return list<string>
     */
    public static function osNames(): array
    {
        $names = [];
        foreach (self::sources() as $source) {
            if ($source['kind'] === self::KIND_OS && $source['name'] !== '') {
                $names[EndOfLifeMapping::key($source['name'])] = $source['name'];
            }
        }

        return array_values($names);
    }

    /**
     * @param Found $item
     */
    private static function describe(array $item): string
    {
        $eol = $item['eol'] === EndOfLifeClient::EOL_WITHOUT_DATE
            ? __('date inconnue', 'configurationglpiauto')
            : ParkAudit::date($item['eol']);

        return sprintf(
            __('%1$s : %2$s (cycle %3$s), fin de support le %4$s', 'configurationglpiauto'),
            ParkAudit::label($item['itemtype'], $item['row']),
            trim($item['name'] . ' ' . $item['version']),
            $item['cycle'],
            $eol
        );
    }

    public function analyze(): array
    {
        if (!self::enabled()) {
            return [];
        }
        if (EndOfLifeMapping::parse(self::mappingText()) === []) {
            return [new AuditFinding(
                checkKey: $this->getKey(),
                domain: $this->getDomain(),
                severity: AuditFinding::SEVERITY_INFO,
                title: __('Fin de support : aucune correspondance avec endoflife.date', 'configurationglpiauto'),
                description: __('Le contrôle est activé, mais aucun système ni modèle n\'est relié à un produit endoflife.date.', 'configurationglpiauto'),
                recommendation: __('Plus bas, dans « Fin de support », cliquez sur « Proposer les correspondances » ou saisissez une ligne par nom, par exemple « Microsoft Windows 10 Professionnel = windows ».', 'configurationglpiauto')
            )];
        }
        $result = self::evaluate();
        $findings = [];
        if ($result['found'] !== []) {
            $eol = count(array_filter($result['found'], static fn (array $i): bool => $i['status'] === CycleMatcher::STATUS_EOL));
            $findings[] = new AuditFinding(
                checkKey: $this->getKey(),
                domain: $this->getDomain(),
                severity: $eol > 0 ? AuditFinding::SEVERITY_WARNING : AuditFinding::SEVERITY_INFO,
                title: sprintf(
                    __('Fin de support : %1$d élément(s) hors support, %2$d bientôt hors support (dans les %3$d jours)', 'configurationglpiauto'),
                    $eol,
                    count($result['found']) - $eol,
                    self::days()
                ),
                description: ParkAudit::summarize(array_map(self::describe(...), $result['found'])),
                recommendation: __(
                    'Un système ou un matériel hors support ne reçoit plus de correctifs de sécurité : mettre à jour, remplacer, ou accepter et documenter le risque. « Créer les tickets » ouvre un ticket regroupant ces matériels, lié à chacun. Rien n\'est modifié sur le matériel.',
                    'configurationglpiauto'
                ),
                fixable: true,
                fixLabel: __('Créer les tickets', 'configurationglpiauto')
            );
        }
        $gaps = [];
        if ($result['missing'] !== []) {
            $gaps[] = sprintf(__('dates non téléchargées : %s', 'configurationglpiauto'), implode(', ', $result['missing']));
        }
        if ($result['unmatched'] !== []) {
            $gaps[] = sprintf(__('cycle non reconnu : %s', 'configurationglpiauto'), ParkAudit::summarize($result['unmatched']));
        }
        if ($result['stale'] !== []) {
            $gaps[] = sprintf(__('dates de plus de %1$d jours : %2$s', 'configurationglpiauto'), EndOfLifeCache::STALE_DAYS, implode(', ', $result['stale']));
        }
        if ($gaps !== []) {
            $findings[] = new AuditFinding(
                checkKey: $this->getKey(),
                domain: $this->getDomain(),
                severity: AuditFinding::SEVERITY_INFO,
                title: __('Fin de support : correspondances à compléter', 'configurationglpiauto'),
                description: implode(' ; ', $gaps),
                recommendation: __('Plus bas, dans « Fin de support » : « Télécharger les dates » (ou importez un fichier en mode hors ligne), et précisez le cycle d\'un nom non reconnu, par exemple « Debian 12 = debian 12 ».', 'configurationglpiauto')
            );
        }

        return $findings;
    }

    public function canFix(): bool
    {
        return true;
    }

    public function fix(): void
    {
        $toLink = [];
        $lines = [];
        foreach (self::evaluate()['found'] as $item) {
            $id = (int) $item['row']['id'];
            $key = $item['itemtype'] . ':' . $id;
            if (isset($toLink[$key]) || ParkAudit::openTicketFor($item['itemtype'], $id, self::TICKET_PREFIX) !== null) {
                continue;
            }
            $toLink[$key] = ['itemtype' => $item['itemtype'], 'items_id' => $id];
            $lines[] = self::describe($item);
        }
        if ($toLink === []) {
            ParkAudit::reportCreated(0);
            return;
        }
        $html = ParkAudit::ticketContent(
            __('Ces matériels en service utilisent un système ou un modèle hors support, ou bientôt hors support (dates endoflife.date) :', 'configurationglpiauto'),
            $lines,
            __('Pour chacun : mettre à jour, remplacer, ou accepter et documenter le risque. Ce ticket a été ouvert par le mode Audit de Configuration GLPI Auto : aucune donnée du matériel n\'a été modifiée.', 'configurationglpiauto')
        );
        ParkAudit::reportCreated(ParkAudit::openTicket(self::TICKET_PREFIX . ' (' . date('Y-m-d') . ')', $html, array_values($toLink), 0) > 0 ? 1 : 0);
    }
}
