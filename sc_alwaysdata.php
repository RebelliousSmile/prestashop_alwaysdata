<?php
/**
 * SC Alwaysdata - PrestaShop 8 Module
 *
 * @author    Scriptami
 * @copyright Scriptami
 * @license   Academic Free License version 3.0
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

class sc_alwaysdata extends Module
{
    public const VERSION = '1.0.0';

    public const CONFIG_LOGS_PATH = 'SC_ALWAYSDATA_LOGS_PATH';
    public const CONFIG_HTACCESS_PATH = 'SC_ALWAYSDATA_HTACCESS_PATH';
    public const CONFIG_CRON_TOKEN = 'SC_ALWAYSDATA_CRON_TOKEN';

    public function __construct()
    {
        $this->name = 'sc_alwaysdata';
        $this->version = self::VERSION;
        $this->author = 'Scriptami';
        $this->tab = 'administration';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => '8.99.99',
        ];

        parent::__construct();

        $this->displayName = $this->trans('Alwaysdata Log Viewer', [], 'Modules.Scalwaysdata.Admin');
        $this->description = $this->trans(
            'Analyse les logs Alwaysdata et gère le fichier .htaccess.',
            [],
            'Modules.Scalwaysdata.Admin'
        );
    }

    public function install(): bool
    {
        return parent::install()
            && $this->createStatsTable()
            && $this->initCronToken();
    }

    public function uninstall(): bool
    {
        return parent::uninstall()
            && Configuration::deleteByName(self::CONFIG_LOGS_PATH)
            && Configuration::deleteByName(self::CONFIG_HTACCESS_PATH)
            && Configuration::deleteByName(self::CONFIG_CRON_TOKEN)
            && $this->dropStatsTable();
    }

    public function getContent(): void
    {
        Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminScAlwaysdata')
        );
    }

    private function createStatsTable(): bool
    {
        require_once __DIR__ . '/src/Entity/DailyStat.php';

        return Db::getInstance()->execute(\ScAlwaysdata\Entity\DailyStat::getCreateTableSql());
    }

    private function dropStatsTable(): bool
    {
        return Db::getInstance()->execute(
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'sc_alwaysdata_stats_daily`'
        );
    }

    private function initCronToken(): bool
    {
        if (!Configuration::get(self::CONFIG_CRON_TOKEN)) {
            return Configuration::set(self::CONFIG_CRON_TOKEN, Tools::passwdGen(32));
        }

        return true;
    }
}
