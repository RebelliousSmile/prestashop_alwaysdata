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
        Configuration::set(self::CONFIG_HTACCESS_PATH, _PS_ROOT_DIR_ . '/.htaccess');

        return parent::install();
    }

    public function uninstall(): bool
    {
        return parent::uninstall()
            && Configuration::deleteByName(self::CONFIG_LOGS_PATH)
            && Configuration::deleteByName(self::CONFIG_HTACCESS_PATH);
    }

    public function getContent(): void
    {
        Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminScAlwaysdata')
        );
    }
}
