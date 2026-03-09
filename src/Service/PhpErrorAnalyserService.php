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

class PhpErrorAnalyserService
{
    private const MAX_ENTRIES = 100;

    private const SEVERITY = [
        'fatal' => 5,
        'error' => 4,
        'warning' => 3,
        'notice' => 2,
        'deprecated' => 1,
    ];

    private const LOG_PATTERN = '/\[(?P<date>[^\]]+)\]\s+PHP\s+(?P<level>Fatal error|Parse error|Warning|Notice|Deprecated|Strict Standards):\s+(?P<message>.+?)\s+in\s+(?P<file>[^\s:]+)(?::(?P<line>\d+))?/i';

    private const LEVEL_MAP = [
        'fatal error' => 'fatal',
        'parse error' => 'fatal',
        'warning' => 'warning',
        'notice' => 'notice',
        'deprecated' => 'deprecated',
        'strict standards' => 'notice',
    ];

    public function analyse(array $logSources): array
    {
        /** @var array<string, array{level: string, file: string, line: int|null, message: string, count: int}> $entries */
        $entries = [];

        foreach ($logSources as $source) {
            if (!isset($source['source'], $source['lines'], $source['error'])) {
                continue;
            }

            if ((string) $source['source'] !== 'php') {
                continue;
            }

            if ($source['error'] !== null) {
                continue;
            }

            $lines = (array) $source['lines'];
            if (empty($lines)) {
                continue;
            }

            foreach ($lines as $line) {
                if (!is_string($line) || $line === '') {
                    continue;
                }

                if (!preg_match(self::LOG_PATTERN, $line, $matches)) {
                    continue;
                }

                $rawLevel = strtolower($matches['level']);
                $level = self::LEVEL_MAP[$rawLevel] ?? 'error';
                $file = $matches['file'];
                $parsedLine = isset($matches['line']) && $matches['line'] !== '' ? (int) $matches['line'] : null;
                $message = $matches['message'];

                $hash = md5($level . $file . (string) $parsedLine . $message);

                if (isset($entries[$hash])) {
                    ++$entries[$hash]['count'];
                } else {
                    $entries[$hash] = [
                        'level' => $level,
                        'file' => $file,
                        'line' => $parsedLine,
                        'message' => $message,
                        'count' => 1,
                    ];
                }
            }
        }

        if (empty($entries)) {
            return [];
        }

        $result = array_values($entries);

        usort($result, function (array $a, array $b): int {
            $severityA = self::SEVERITY[$a['level']] ?? 0;
            $severityB = self::SEVERITY[$b['level']] ?? 0;

            if ($severityB !== $severityA) {
                return $severityB - $severityA;
            }

            return $b['count'] - $a['count'];
        });

        return array_slice($result, 0, self::MAX_ENTRIES);
    }
}
