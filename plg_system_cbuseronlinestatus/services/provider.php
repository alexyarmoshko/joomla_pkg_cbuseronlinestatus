<?php
/**
 * DI container service provider for plg_system_cbuseronlinestatus.
 *
 * @package     YakShaver.Plugin.System.Cbuseronlinestatus
 * @subpackage  Services
 * @author      Yak Shaver <me@kayakshaver.com>
 * @copyright   (C) 2026 Yak Shaver https://www.kayakshaver.com
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use YakShaver\Plugin\System\Cbuseronlinestatus\Extension\CbUserOnlineStatus;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $plugin = new CbUserOnlineStatus(
                    $container->get(DispatcherInterface::class),
                    (array) PluginHelper::getPlugin('system', 'cbuseronlinestatus')
                );
                $plugin->setApplication(Factory::getApplication());
                $plugin->setDatabase($container->get(\Joomla\Database\DatabaseInterface::class));

                return $plugin;
            }
        );
    }
};
