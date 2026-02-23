<?php
/**
 * Upstream hashes field for CB User Online Status XML Manifest.
 *
 * @package     YakShaver.Plugin.System.Cbuseronlinestatus
 * @author      Yak Shaver <me@kayakshaver.com>
 * @copyright   (C) 2026 Yak Shaver https://www.kayakshaver.com
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace YakShaver\Plugin\System\Cbuseronlinestatus\Field;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Custom form field to display a readonly table of tracked file SHA256 hashes.
 */
class UpstreamHashesField extends FormField
{
    /**
     * The form field type.
     *
     * @var string
     */
    protected $type = 'UpstreamHashes';

    /**
     * Gets the form field input.
     *
     * @return string
     */
    protected function getInput(): string
    {
        $hashesJson = $this->form->getValue('upstream_hashes', 'params', '{}');
        $hashes = json_decode((string) $hashesJson, true);

        if (empty($hashes)) {
            return '<p>' . Text::_('PLG_SYSTEM_CBUSERONLINESTATUS_HASHES_PENDING') . '</p>';
        }

        $html = '<table class="table table-striped table-bordered mb-0">';
        $html .= '<thead><tr><th>File</th><th>SHA256 Hash</th></tr></thead>';
        $html .= '<tbody>';
        foreach ($hashes as $file => $hash) {
            $html .= '<tr>';
            $html .= '<td><code>' . htmlspecialchars((string) $file, ENT_QUOTES, 'UTF-8') . '</code></td>';
            $html .= '<td><code>' . htmlspecialchars((string) $hash, ENT_QUOTES, 'UTF-8') . '</code></td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }
}
