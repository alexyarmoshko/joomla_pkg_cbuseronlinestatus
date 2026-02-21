<?php
/**
 * Dispatcher for mod_cbuseronlinestatus.
 *
 * Calls the helper and selects the correct sublayout based on the module mode.
 *
 * @package     Joomla.Site
 * @subpackage  mod_cbuseronlinestatus
 *
 * @copyright   (C) 2026 Yak Shaver https://www.kayakshaver.com
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Module\Cbuseronlinestatus\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Dispatcher class for mod_cbuseronlinestatus.
 *
 * @since  1.0.0
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Returns the layout data, calling the helper for layout variables
     * and determining the sublayout from the mode parameter.
     *
     * @return  array
     *
     * @since   1.0.0
     */
    protected function getLayoutData(): array
    {
        $data = parent::getLayoutData();

        /** @var \Joomla\Module\Cbuseronlinestatus\Site\Helper\CbUserOnlineStatusHelper $helper */
        $helper = $this->getHelperFactory()->getHelper('CbUserOnlineStatusHelper');

        $layoutVars = $helper->getLayoutVariables($data['params']);

        return array_merge($data, $layoutVars);
    }
}
