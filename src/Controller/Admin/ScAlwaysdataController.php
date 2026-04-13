<?php
/**
 * SC Alwaysdata - PrestaShop 8 Module
 *
 * @author    Scriptami
 * @copyright Scriptami
 * @license   Academic Free License version 3.0
 */

declare(strict_types=1);

namespace ScAlwaysdata\Controller\Admin;

use Configuration;
use Db;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use ScAlwaysdata\Entity\DailyStat;
use ScAlwaysdata\Service\CrawlerAnalyserService;
use ScAlwaysdata\Service\HtaccessService;
use ScAlwaysdata\Service\LogReaderService;
use ScAlwaysdata\Service\PhpErrorAnalyserService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ScAlwaysdataController extends FrameworkBundleAdminController
{
    private LogReaderService $logReaderService;
    private CrawlerAnalyserService $crawlerAnalyserService;
    private PhpErrorAnalyserService $phpErrorAnalyserService;
    private HtaccessService $htaccessService;

    public function __construct(
        LogReaderService $logReaderService,
        CrawlerAnalyserService $crawlerAnalyserService,
        PhpErrorAnalyserService $phpErrorAnalyserService,
        HtaccessService $htaccessService
    ) {
        $this->logReaderService = $logReaderService;
        $this->crawlerAnalyserService = $crawlerAnalyserService;
        $this->phpErrorAnalyserService = $phpErrorAnalyserService;
        $this->htaccessService = $htaccessService;
    }

    /**
     * Index action: settings form + analysis trigger
     *
     * @AdminSecurity(
     *     "is_granted('read', request.get('_legacy_controller'))",
     *     message="You do not have permission to access this.",
     *     redirectRoute="admin_dashboard"
     * )
     */
    public function indexAction(Request $request): Response
    {
        $this->ensureStatsTable();

        if ($request->isMethod('POST')) {
            $logsPath = trim((string) $request->request->get('logs_path', ''));
            $htaccessPath = trim((string) $request->request->get('htaccess_path', ''));

            if ($logsPath === '') {
                $this->addFlash(
                    'error',
                    $this->trans('Le chemin des logs ne peut pas être vide.', 'Modules.Scalwaysdata.Admin')
                );
            } else {
                Configuration::set('SC_ALWAYSDATA_LOGS_PATH', $logsPath);
                Configuration::set('SC_ALWAYSDATA_HTACCESS_PATH', $htaccessPath);
                $this->addFlash(
                    'success',
                    $this->trans('Configuration sauvegardée.', 'Modules.Scalwaysdata.Admin')
                );
            }
        }

        $logsPath = (string) Configuration::get('SC_ALWAYSDATA_LOGS_PATH');
        $htaccessPath = (string) Configuration::get('SC_ALWAYSDATA_HTACCESS_PATH');
        $logsPathHint = ($_SERVER['HOME'] ?? '') . '/admin/logs/';

        return $this->render(
            '@Modules/sc_alwaysdata/views/templates/admin/index.html.twig',
            [
                'layoutTitle' => $this->trans('Alwaysdata Log Viewer', 'Modules.Scalwaysdata.Admin'),
                'enableSidebar' => true,
                'help_link' => false,
                'logsPath' => $logsPath,
                'htaccessPath' => $htaccessPath,
                'logsPathHint' => $logsPathHint,
            ]
        );
    }

    /**
     * Analyse action: run log analysis
     *
     * @AdminSecurity(
     *     "is_granted('read', request.get('_legacy_controller'))",
     *     message="You do not have permission to access this.",
     *     redirectRoute="admin_dashboard"
     * )
     */
    public function analyseAction(Request $request): JsonResponse
    {
        try {
            $sources = $this->logReaderService->readTodayLogs();

            $crawlers = $this->crawlerAnalyserService->analyse($sources);
            $blockedIps = $this->htaccessService->readBlockedIps();
            $blockedUAs = $this->htaccessService->readBlockedUAs();
            $phpErrors = $this->phpErrorAnalyserService->analyse($sources);

            foreach ($crawlers as &$entry) {
                $entry['already_blocked'] = in_array($entry['ip'], $blockedIps, true);
            }
            unset($entry);

            $sourcesInfo = array_map(function (array $source): array {
                return [
                    'source' => $source['source'],
                    'truncated' => $source['truncated'] ?? false,
                    'error' => $source['error'],
                ];
            }, $sources);

            return new JsonResponse([
                'crawlers' => $crawlers,
                'php_errors' => $phpErrors,
                'sources' => $sourcesInfo,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Apply block action: write .htaccess block rules
     *
     * @AdminSecurity(
     *     "is_granted('update', request.get('_legacy_controller'))",
     *     message="You do not have permission to modify this.",
     *     redirectRoute="admin_dashboard"
     * )
     */
    private function ensureStatsTable(): void
    {
        $tableExists = (bool) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = \'' . _DB_PREFIX_ . 'sc_alwaysdata_stats_daily\''
        );

        if (!$tableExists) {
            Db::getInstance()->execute(DailyStat::getCreateTableSql());
        }
    }

    public function applyBlockAction(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('sc_alwaysdata_apply_block', $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
        }

        $type = (string) $request->request->get('type', '');
        $value = (string) $request->request->get('value', '');

        try {
            if ($type === 'ip') {
                $this->htaccessService->blockIp($value);
            } elseif ($type === 'ua') {
                $this->htaccessService->blockUserAgent($value);
            } else {
                return new JsonResponse(['success' => false, 'message' => 'Unknown block type: ' . $type]);
            }
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }

        return new JsonResponse(['success' => true, 'message' => 'Blocked successfully']);
    }
}
