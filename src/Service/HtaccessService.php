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

class HtaccessService
{
    public function readBlockedIps(): array
    {
        try {
            $content = file_get_contents($this->getHtaccessPath());
            if ($content === false) {
                return [];
            }
        } catch (\Throwable $e) {
            return [];
        }

        preg_match_all('/Require not ip\s+(\S+)/', $content, $matches);

        return $matches[1] ?? [];
    }

    public function readBlockedUAs(): array
    {
        try {
            $content = file_get_contents($this->getHtaccessPath());
            if ($content === false) {
                return [];
            }
        } catch (\Throwable $e) {
            return [];
        }

        preg_match_all('/RewriteCond %\{HTTP_USER_AGENT\} "([^"]+)" \[NC\]/', $content, $matches);

        return $matches[1] ?? [];
    }

    public function blockIp(string $ip): void
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException(sprintf('Invalid IP address: %s', $ip));
        }

        try {
            $htaccessPath = $this->getHtaccessPath();
            $current = file_get_contents($htaccessPath);
            if ($current === false) {
                $current = '';
            }
        } catch (\Throwable $e) {
            $current = '';
        }

        $block = "\n# sc_alwaysdata block: {$ip}\n"
            . "<RequireAll>\n"
            . "Require all granted\n"
            . "Require not ip {$ip}\n"
            . "</RequireAll>\n";

        $this->writeAtomic($current . $block);
    }

    public function blockUserAgent(string $ua): void
    {
        $ua = str_replace(['"', '\\'], '', $ua);

        try {
            $htaccessPath = $this->getHtaccessPath();
            $current = file_get_contents($htaccessPath);
            if ($current === false) {
                $current = '';
            }
        } catch (\Throwable $e) {
            $current = '';
        }

        $block = "\n# sc_alwaysdata block UA: {$ua}\n"
            . "RewriteCond %{HTTP_USER_AGENT} \"{$ua}\" [NC]\n"
            . "RewriteRule .* - [F,L]\n";

        $this->writeAtomic($current . $block);
    }

    private function getHtaccessPath(): string
    {
        return (string) \Configuration::get('SC_ALWAYSDATA_HTACCESS_PATH');
    }

    private function writeAtomic(string $content): void
    {
        $htaccessPath = $this->getHtaccessPath();
        $tmp = $htaccessPath . '.tmp';

        try {
            $result = file_put_contents($tmp, $content);
            if ($result === false) {
                throw new \RuntimeException(sprintf('Failed to write temporary file: %s', $tmp));
            }

            if (!rename($tmp, $htaccessPath)) {
                throw new \RuntimeException(sprintf('Failed to rename %s to %s', $tmp, $htaccessPath));
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException(sprintf('Atomic write failed: %s', $e->getMessage()));
        }
    }
}
