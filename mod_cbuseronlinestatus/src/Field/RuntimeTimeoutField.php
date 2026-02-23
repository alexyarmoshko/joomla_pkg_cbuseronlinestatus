<?php
/**
 * Runtime Timeout field for mod_cbuseronlinestatus XML Manifest.
 *
 * @package     Joomla.Site
 * @subpackage  mod_cbuseronlinestatus
 *
 * @copyright   (C) 2026 Yak Shaver https://www.kayakshaver.com
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Module\Cbuseronlinestatus\Site\Field;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\NumberField;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Displays the effective runtime timeout as a disabled number field.
 *
 * Uses the system plugin-provided application state value when available;
 * otherwise falls back to the module's own configured timeout.
 *
 * @since  1.0.0
 */
class RuntimeTimeoutField extends NumberField
{
    /**
     * The form field type.
     *
     * @var string
     *
     * @since  1.0.0
     */
    protected $type = 'RuntimeTimeout';

    /**
     * Gets the form field input.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    protected function getInput(): string
    {
        $fallback = (int) $this->form->getValue('online_timeout', 'params', 1800);
        $runtime = (int) Factory::getApplication()->get('cbuserstatus.timeout', 0);

        $this->value = $runtime > 0 ? $runtime : $fallback;
        $this->disabled = true;

        $html = parent::getInput();

        // Remove the name so this display-only value is never submitted.
        $html = preg_replace('/name="[^"]+"/', '', $html);

        return $html;
    }
}
