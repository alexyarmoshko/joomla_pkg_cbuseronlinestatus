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
        // Only intercept on the site (frontend) application
        if (!$this->getApplication()->isClient('site')) {
            return;
        }

        self::$onlineTimeout = (int) $this->params->get('online_timeout', 1800);

        spl_autoload_register([$this, 'overrideAutoloader'], true, true);
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
        $map = [
            'CB\\Plugin\\Core\\Field\\StatusField' => __DIR__ . '/../Field/StatusField.php',
            'CB\\Plugin\\PMS\\Table\\MessageTable' => __DIR__ . '/../Table/MessageTable.php',
        ];

        if (isset($map[$class]) && is_readable($map[$class])) {
            require $map[$class];
        }
    }
}
