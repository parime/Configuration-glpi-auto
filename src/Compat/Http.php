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

namespace GlpiPlugin\Configurationglpiauto\Compat;

/**
 * Outgoing HTTP GET through GLPI core's own client, on GLPI 11 (Toolbox::getGuzzleClient()) and
 * GLPI 12 (Glpi\Toolbox\HttpClient, which replaced it) alike — both honour GLPI's proxy settings.
 */
final class Http
{
    /**
     * @param array{query?: array<string, scalar>, headers?: array<string, string>, timeout?: int, follow_redirects?: bool, resolve?: array{host: string, port: int, ip: string}} $options
     *        `follow_redirects` defaults to true; set it to false where a redirect could reopen an
     *        SSRF the caller already guards against (see ajax/geocode.php). `resolve` pins the
     *        connection to an address the caller already validated, so a second DNS lookup by the
     *        client (DNS rebinding) cannot swap it for another one in between.
     * @return string The response body.
     * @throws \Throwable On any transport failure or non-2xx response.
     */
    public static function get(string $url, array $options = []): string
    {
        $query = $options['query'] ?? [];
        $headers = $options['headers'] ?? [];
        $timeout = $options['timeout'] ?? 5;
        $followRedirects = $options['follow_redirects'] ?? true;
        $resolve = $options['resolve'] ?? null;

        if (class_exists(\Glpi\Toolbox\HttpClient::class)) {
            return (new \Glpi\Toolbox\HttpClient())->get($url, [
                'query' => $query,
                'headers' => $headers,
                'timeout' => $timeout,
                'max_redirects' => $followRedirects ? 5 : 0,
            ] + ($resolve !== null ? ['resolve' => [$resolve['host'] => $resolve['ip']]] : []))->getContent();
        }

        return (string) \Toolbox::getGuzzleClient()->request('GET', $url, [
            'query' => $query,
            'headers' => $headers,
            'timeout' => $timeout,
            'allow_redirects' => $followRedirects,
            'http_errors' => true,
        ] + ($resolve !== null ? ['curl' => [CURLOPT_RESOLVE => [sprintf('%s:%d:%s', $resolve['host'], $resolve['port'], $resolve['ip'])]]] : []))->getBody();
    }
}
