<?php
/**
 * SC Alwaysdata - PrestaShop 8 Module
 *
 * @author    Scriptami
 * @copyright Scriptami
 * @license   Academic Free License version 3.0
 */

declare(strict_types=1);

namespace ScAlwaysdata\Service;

class CrawlerAnalyserService
{
    private const KNOWN_BOTS = [
        'AhrefsBot',
        'SemrushBot',
        'DotBot',
        'MJ12bot',
        'GPTBot',
        'ClaudeBot',
        'CCBot',
        'DataForSeoBot',
        'PetalBot',
        'BLEXBot',
        'YandexBot',
        'Baiduspider',
        'Sogou',
        '360Spider',
    ];

    private const HIGH_FREQ_THRESHOLD = 200;

    private const MAX_RESULTS = 50;

    private const LOG_PATTERN = '/^(\S+)\s+\S+\s+\S+\s+\[.*?\]\s+"[^"]*"\s+\d+\s+\S+(?:\s+"[^"]*"\s+"([^"]*)")?/';

    public function analyse(array $logSources): array
    {
        /** @var array<string, array{uas: array<string, bool>, count: int}> $ipData */
        $ipData = [];

        foreach ($logSources as $source) {
            if (!isset($source['source'], $source['lines'], $source['error'])) {
                continue;
            }

            $sourceName = (string) $source['source'];
            if ($sourceName !== 'apache' && $sourceName !== 'http') {
                continue;
            }

            if ($source['error'] !== null) {
                continue;
            }

            foreach ((array) $source['lines'] as $line) {
                if (!is_string($line) || $line === '') {
                    continue;
                }

                if (!preg_match(self::LOG_PATTERN, $line, $matches)) {
                    continue;
                }

                $ip = $matches[1];
                $ua = isset($matches[2]) ? $matches[2] : '';

                if (!isset($ipData[$ip])) {
                    $ipData[$ip] = ['uas' => [], 'count' => 0];
                }

                ++$ipData[$ip]['count'];

                if ($ua !== '') {
                    $ipData[$ip]['uas'][$ua] = true;
                }
            }
        }

        $candidates = [];

        foreach ($ipData as $ip => $data) {
            $reason = null;

            foreach (array_keys($data['uas']) as $ua) {
                foreach (self::KNOWN_BOTS as $bot) {
                    if (stripos($ua, $bot) !== false) {
                        $reason = 'known_bot';
                        break 2;
                    }
                }
            }

            if ($reason === null && $data['count'] >= self::HIGH_FREQ_THRESHOLD) {
                $reason = 'high_frequency';
            }

            if ($reason === null) {
                continue;
            }

            $candidates[] = [
                'ip' => $ip,
                'user_agents' => array_keys($data['uas']),
                'request_count' => $data['count'],
                'reason' => $reason,
            ];
        }

        usort($candidates, function (array $a, array $b): int {
            return $b['request_count'] - $a['request_count'];
        });

        return array_slice($candidates, 0, self::MAX_RESULTS);
    }
}
