<?php
/**
 * System plugin to fix stale online-status indicators in Community Builder.
 *
 * Registers a prepended PHP autoloader that intercepts CB's StatusField and
 * MessageTable classes, replacing them with timeout-aware overrides.
 *
 * @package     YakShaver.Plugin.System.Cbuseronlinestatus
 * @author      Yak Shaver <me@kayakshaver.com>
 * @copyright   (C) 2026 Yak Shaver https://www.kayakshaver.com
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace YakShaver\Plugin\System\Cbuseronlinestatus\Extension;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class CbUserOnlineStatus extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * Tracked files for hash verification.
     */
    private const TRACKED_FILES = [
        'components/com_comprofiler/plugin/user/plug_cbcore/library/Field/StatusField.php',
        'components/com_comprofiler/plugin/user/plug_pms_mypmspro/library/Table/MessageTable.php',
    ];

    /**
     * Auto-load language files for this namespaced service-provider plugin.
     *
     * @var bool
     */
    protected $autoloadLanguage = true;

    /**
     * Configurable timeout in seconds. Default 1800 (30 minutes).
     *
     * @var int
     */
    private static int $onlineTimeout = 1800;

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterInitialise' => 'onAfterInitialise',
        ];
    }

    /**
     * Read the configured timeout and register the prepended autoloader.
     *
     * @return void
     */
    public function onAfterInitialise(): void
    {
        // 1. Read hash state
        $storedHashes = json_decode($this->params->get('upstream_hashes', '{}'), true);
        $verified = (bool) $this->params->get('hashes_verified', 0);

        // 2. If no hashes stored yet, compute and store, warn admin
        if (empty($storedHashes)) {
            $this->computeAndStoreHashes();
            if ($this->getApplication()->isClient('administrator')) {
                $this->getApplication()->enqueueMessage(
                    Text::_('PLG_SYSTEM_CBUSERONLINESTATUS_HASHES_NOT_VERIFIED'),
                    'warning'
                );
            }
            return;
        }

        // 3. If not verified, warn admin, do not register autoloader
        if (!$verified) {
            if ($this->getApplication()->isClient('administrator')) {
                $this->getApplication()->enqueueMessage(
                    Text::_('PLG_SYSTEM_CBUSERONLINESTATUS_HASHES_NOT_VERIFIED'),
                    'warning'
                );
            }
            return;
        }

        // 4. Verified — check for upstream changes
        if (!$this->verifyUpstreamHashes($storedHashes)) {
            return;
        }

        // 5. All good — read timeout
        $source = $this->params->get('timeout_source', 'manual');

        if ($source === 'kunena') {
            $kunenaConfigClass = '\\Kunena\\Forum\\Libraries\\Config\\KunenaConfig';
            if (class_exists($kunenaConfigClass)) {
                $kunenaConfig = $kunenaConfigClass::getInstance();
                if ($kunenaConfig !== null) {
                    self::$onlineTimeout = (int) $kunenaConfig->sessionTimeOut;
                }
            } else {
                self::$onlineTimeout = (int) $this->params->get('online_timeout', 1800);
                if ($this->getApplication()->isClient('administrator')) {
                    $this->getApplication()->enqueueMessage(
                        Text::_('PLG_SYSTEM_CBUSERONLINESTATUS_KUNENA_NOT_FOUND'),
                        'warning'
                    );
                }
            }
        } else {
            self::$onlineTimeout = (int) $this->params->get('online_timeout', 1800);
        }

        // 10b: Decouple module from plugin namespace via application state
        $this->getApplication()->set('cbuserstatus.timeout', self::$onlineTimeout);

        if ($this->getApplication()->isClient('site')) {
            spl_autoload_register([$this, 'overrideAutoloader'], true, true);
        }
    }

    /**
     * Compute hashes for tracked files and save to params.
     */
    private function computeAndStoreHashes(): void
    {
        $hashes = [];
        foreach (self::TRACKED_FILES as $path) {
            $realPath = JPATH_SITE . '/' . $path;
            $hashes[$path] = file_exists($realPath) ? hash_file('sha256', $realPath) : null;
        }
        $this->saveParams(['upstream_hashes' => json_encode($hashes), 'hashes_verified' => 0]);
    }

    /**
     * Verify tracked files against stored hashes.
     */
    private function verifyUpstreamHashes(array $storedHashes): bool
    {
        $changedFiles = [];
        foreach (self::TRACKED_FILES as $path) {
            $realPath = JPATH_SITE . '/' . $path;
            $currentHash = file_exists($realPath) ? hash_file('sha256', $realPath) : null;
            if (!array_key_exists($path, $storedHashes) || $currentHash !== $storedHashes[$path]) {
                $changedFiles[] = $path;
            }
        }

        if (!empty($changedFiles)) {
            $this->computeAndStoreHashes();
            if ($this->getApplication()->isClient('administrator')) {
                $this->getApplication()->enqueueMessage(
                    sprintf(Text::_('PLG_SYSTEM_CBUSERONLINESTATUS_UPSTREAM_CHANGED'), implode(', ', $changedFiles)),
                    'warning'
                );
            }
            return false;
        }

        return true;
    }

    /**
     * Save updated params to the extensions table.
     */
    private function saveParams(array $updates): void
    {
        foreach ($updates as $k => $v) {
            $this->params->set($k, $v);
        }

        $db = $this->getDatabase();
        try {
            $db->lockTable('#__extensions');
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('params') . ' = ' . $db->quote($this->params->toString()))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('cbuseronlinestatus'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'));
            $db->setQuery($query);
            $db->execute();
            $db->unlockTables();

            \Joomla\CMS\Factory::getCache('com_plugins', '')->clean();
        } catch (\Exception $e) {
            $db->unlockTables();
        }
    }

    /**
     * Return the configured online timeout in seconds.
     *
     * @return int
     */
    public static function getOnlineTimeout(): int
    {
        return self::$onlineTimeout;
    }

    /**
     * Prepended autoloader that intercepts specific CB class FQCNs.
     *
     * If the requested class matches a known override and the override file
     * is readable, it is loaded before CB's own autoloader sees the request.
     * If the file does not exist, the method returns silently and CB's own
     * autoloader handles the class as usual.
     *
     * @param  string  $class  Fully qualified class name being loaded.
     *
     * @return void
     */
    public function overrideAutoloader(string $class): void
    {
        // Override files guard with defined('CBLIB') or die(). Bail out
        // early if CB has not bootstrapped yet so that class_exists()
        // probes from other extensions do not terminate the request.
        if (!defined('CBLIB')) {
            return;
        }

        $map = [
            'CB\\Plugin\\Core\\Field\\StatusField' => __DIR__ . '/../Field/StatusField.php',
            'CB\\Plugin\\PMS\\Table\\MessageTable' => __DIR__ . '/../Table/MessageTable.php',
        ];

        if (isset($map[$class]) && is_readable($map[$class])) {
            require $map[$class];
        }
    }
}
