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

class LogReaderService
{
    private const MAX_FILE_SIZE_BYTES = 5_242_880; // 5 MB
    private const MAX_LINES = 5000;
    private const LOG_DIRS = ['apache', 'http', 'php', 'sites'];

    public function readTodayLogs(): array
    {
        set_time_limit(60);

        $basePath = \Configuration::get('SC_ALWAYSDATA_LOGS_PATH');

        if (empty($basePath) || !is_readable($basePath)) {
            return [
                [
                    'source' => 'base',
                    'lines' => [],
                    'truncated' => false,
                    'error' => 'Log base path is not configured or not readable',
                ],
            ];
        }

        $results = [];
        foreach (self::LOG_DIRS as $dir) {
            $results[] = $this->readSource($basePath, $dir);
        }

        return $results;
    }

    private function readSource(string $basePath, string $dir): array
    {
        try {
            $dirPath = $basePath . $dir . '/';

            if (!is_readable($dirPath)) {
                return [
                    'source' => $dir,
                    'lines' => [],
                    'truncated' => false,
                    'error' => 'Directory not readable: ' . $dirPath,
                ];
            }

            $today = date('Y-m-d');
            $todayFiles = glob($dirPath . '*' . $today . '*');

            if (!empty($todayFiles)) {
                $file = $todayFiles[0];
            } else {
                $allFiles = glob($dirPath . '*');
                if (empty($allFiles)) {
                    return [
                        'source' => $dir,
                        'lines' => [],
                        'truncated' => false,
                        'error' => 'No log file found for today',
                    ];
                }

                usort($allFiles, function (string $a, string $b): int {
                    return filemtime($b) - filemtime($a);
                });

                $file = $allFiles[0];
            }

            $truncated = false;

            if (filesize($file) > self::MAX_FILE_SIZE_BYTES) {
                return [
                    'source' => $dir,
                    'lines' => [],
                    'truncated' => true,
                    'error' => null,
                ];
            }

            $lines = $this->tailFile($file, self::MAX_LINES);

            return [
                'source' => $dir,
                'lines' => $lines,
                'truncated' => $truncated,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'source' => $dir,
                'lines' => [],
                'truncated' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function tailFile(string $filePath, int $maxLines): array
    {
        $fh = fopen($filePath, 'rb');
        if ($fh === false) {
            return [];
        }

        fseek($fh, 0, SEEK_END);
        $fileSize = ftell($fh);
        $chunkSize = 8192;
        $buffer = '';
        $pos = $fileSize;
        $lines = [];

        while (count($lines) <= $maxLines && $pos > 0) {
            $readSize = min($chunkSize, $pos);
            $pos -= $readSize;
            fseek($fh, $pos);
            $buffer = fread($fh, $readSize) . $buffer;
            $lines = explode("\n", $buffer);
        }

        fclose($fh);

        $lines = array_filter($lines, fn ($l) => $l !== '');

        return array_slice(array_values($lines), -$maxLines);
    }
}
