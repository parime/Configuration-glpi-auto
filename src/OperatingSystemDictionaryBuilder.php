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

namespace GlpiPlugin\Configurationglpiauto;

use RuleDictionnaryOperatingSystem;
use RuleDictionnaryOperatingSystemArchitecture;

/**
 * Issue #315 : dictionnaires des systèmes d'exploitation et de leurs architectures.
 *
 * L'inventaire enregistre comme système d'exploitation le *nom complet* remonté par l'agent
 * (`Glpi\Inventory\Asset\OperatingSystem`, champ full_name), dans la langue du poste et avec le
 * numéro de version mineure : « Microsoft Windows 11 Professionnel » et « Microsoft Windows 11
 * Pro » font deux systèmes, « Ubuntu 22.04.3 LTS » et « Ubuntu 22.04.4 LTS » aussi. Le
 * dictionnaire ramène les éditions Windows à leur nom anglais (celui des contrats et de la
 * documentation Microsoft) et les distributions à leur version majeure ; la version complète
 * reste dans la version du système.
 *
 * Architecture : « 64 bits » (Windows en français), « 64-bit » (en anglais), « x86_64 » et
 * « amd64 » (Linux) deviennent « x86_64 » ; idem pour x86 et ARM64.
 *
 * Expressions ancrées au début ET à la fin pour Windows : « Pro » ne doit pas avaler « Pro for
 * Workstations » ou « Pro Education », qui ont leur propre règle.
 */
class OperatingSystemDictionaryBuilder
{
    /**
     * @var array<string, array{0: string, 1: string}> libellé de la règle => [expression, nom attribué]
     */
    private const OS_RULES = [
        'Windows Pro for Workstations' => ['/^microsoft windows (1[01]) (?:professionnel pour les stations de travail|pro for workstations)$/', 'Microsoft Windows #0 Pro for Workstations'],
        'Windows Pro Education' => ['/^microsoft windows (1[01]) (?:professionnel (?:e|é|É)ducation|pro education)$/', 'Microsoft Windows #0 Pro Education'],
        'Windows Pro' => ['/^microsoft windows (1[01]) (?:professionnel|pro)( n)?$/', 'Microsoft Windows #0 Pro#1'],
        'Windows Home Single Language' => ['/^microsoft windows (1[01]) (?:famille unilingue|home single language)$/', 'Microsoft Windows #0 Home Single Language'],
        'Windows Home' => ['/^microsoft windows (1[01]) (?:famille|home)( n)?$/', 'Microsoft Windows #0 Home#1'],
        'Windows Enterprise LTSC' => ['/^microsoft windows (1[01]) (?:entreprise|enterprise) (ltsc|ltsb) (\d{4})$/', 'Microsoft Windows #0 Enterprise #1 #2'],
        'Windows Enterprise' => ['/^microsoft windows (1[01]) (?:entreprise|enterprise)( n)?$/', 'Microsoft Windows #0 Enterprise#1'],
        'Windows Education' => ['/^microsoft windows (1[01]) (?:(?:e|é|É)ducation)( n)?$/', 'Microsoft Windows #0 Education#1'],
        'Ubuntu (version majeure)' => ['/^(ubuntu \d{2}\.\d{2})(?:\.\d+)?( lts)?\b/', '#0#1'],
        'Red Hat Enterprise Linux (version majeure)' => ['/^(red hat enterprise linux)(?: server| workstation)? (?:release )?(\d+)\b/', '#0 #1'],
        'Rocky Linux / AlmaLinux / CentOS (version majeure)' => ['/^(rocky linux|almalinux|centos linux|centos stream|oracle linux server) (?:release )?(\d+)\b/', '#0 #1'],
        'macOS (version majeure)' => ['/^(macos) (\d+)\b/', '#0 #1'],
    ];

    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const ARCH_RULES = [
        'Architecture x86_64' => ['/^(64[- ]?bits?|x86[_-]64|amd64|x64|em64t)$/', 'x86_64'],
        'Architecture x86' => ['/^(32[- ]?bits?|i[3-6]86|x86)$/', 'x86'],
        'Architecture ARM64' => ['/^(arm64|aarch64|arm 64[- ]?bits?|arm64-based pc|arm 64 bits)$/', 'ARM64'],
    ];

    /**
     * @return int Number of dictionary rules created/reused.
     */
    public function build(Config $config): int
    {
        if (empty($config->fields['os_dictionary_enabled'])) {
            return 0;
        }

        foreach ([[RuleDictionnaryOperatingSystem::class, self::OS_RULES], [RuleDictionnaryOperatingSystemArchitecture::class, self::ARCH_RULES]] as [$subType, $rules]) {
            foreach ($rules as $label => [$regex, $name]) {
                DictionaryRules::ensure(
                    $subType,
                    sprintf('Système d\'exploitation — normalisation « %s »', $label),
                    $name,
                    [],
                    $regex,
                    str_contains($name, '#') ? 'regex_result' : 'assign'
                );
            }
        }

        return count(self::OS_RULES) + count(self::ARCH_RULES);
    }
}
