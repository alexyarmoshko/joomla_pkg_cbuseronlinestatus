<?php
/**
 * Online Timeout field for CB User Online Status XML Manifest.
 *
 * @package     YakShaver.Plugin.System.Cbuseronlinestatus
 * @author      Yak Shaver <me@kayakshaver.com>
 * @copyright   (C) 2026 Yak Shaver https://www.kayakshaver.com
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace YakShaver\Plugin\System\Cbuseronlinestatus\Field;

use Joomla\CMS\Form\Field\NumberField;
use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Custom number field that dynamically acts as a readonly display when
 * the timeout source is set to Kunena.
 */
class OnlineTimeoutField extends NumberField
{
    /**
     * Guard to avoid injecting the same script multiple times.
     *
     * @var bool
     */
    private static bool $scriptLoaded = false;

    /**
     * The form field type.
     *
     * @var string
     */
    protected $type = 'OnlineTimeout';

    /**
     * Gets the form field input.
     *
     * @return string
     */
    protected function getInput(): string
    {
        $source = $this->form->getValue('timeout_source', 'params', 'manual');
        $manualValue = (string) $this->value;
        $kunenaTimeout = null;
        $kunenaConfigClass = '\\Kunena\\Forum\\Libraries\\Config\\KunenaConfig';
        $isInstalled = class_exists($kunenaConfigClass);

        if ($isInstalled) {
            $kunenaConfig = $kunenaConfigClass::getInstance();
            if ($kunenaConfig !== null) {
                $kunenaTimeout = (int) $kunenaConfig->sessionTimeOut;
            }
        }

        if ($source === 'kunena') {
            if ($kunenaTimeout !== null) {
                $this->value = $kunenaTimeout;
            }

            // Set field to disabled so it looks greyed out
            $this->disabled = true;

            // Generate HTML using parent class
            $html = parent::getInput();

            // Strip the 'name' attribute so the display value is not submitted,
            // preserving the user's manual value in the DB.
            $html = preg_replace('/name="[^"]+"/', '', $html);

        } else {
            // Manual mode: behave directly like a normal NumberField
            $html = parent::getInput();
        }

        $noteId = $this->id . '_kunena_note';
        $showNote = $source === 'kunena' && !$isInstalled;

        $html .= '<div id="' . htmlspecialchars($noteId, ENT_QUOTES, 'UTF-8') . '" class="small mt-1 text-muted"'
            . ($showNote ? '' : ' style="display:none"')
            . '>'
            . Text::_('PLG_SYSTEM_CBUSERONLINESTATUS_KUNENA_NOT_INSTALLED_NOTE')
            . '</div>';

        $html .= '<span'
            . ' class="cbuseronlinestatus-timeout-toggle"'
            . ' data-timeout-field-id="' . htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-source-field-id="jform_params_timeout_source"'
            . ' data-original-name="' . htmlspecialchars((string) $this->name, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-manual-value="' . htmlspecialchars($manualValue, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-kunena-timeout="' . htmlspecialchars((string) ($kunenaTimeout ?? ''), ENT_QUOTES, 'UTF-8') . '"'
            . ' data-kunena-installed="' . ($isInstalled ? '1' : '0') . '"'
            . ' data-note-id="' . htmlspecialchars($noteId, ENT_QUOTES, 'UTF-8') . '"'
            . ' style="display:none"></span>';

        if (!self::$scriptLoaded) {
            self::$scriptLoaded = true;
            $html .= $this->getToggleScript();
        }

        return $html;
    }

    /**
     * Inline script to toggle disabled/name state when timeout source changes.
     *
     * @return string
     */
    private function getToggleScript(): string
    {
        return <<<HTML
<script>
(function () {
    function initCbUserOnlineStatusTimeoutFields() {
        var markers = document.querySelectorAll('.cbuseronlinestatus-timeout-toggle');

        markers.forEach(function (marker) {
            if (marker.dataset.bound === '1') {
                return;
            }

            var input = document.getElementById(marker.dataset.timeoutFieldId);
            var source = document.getElementById(marker.dataset.sourceFieldId);
            var note = document.getElementById(marker.dataset.noteId);

            if (!input || !source) {
                return;
            }

            marker.dataset.bound = '1';
            input.dataset.cbuserstatusMode = source.value === 'kunena' ? 'kunena' : 'manual';

            function applyState() {
                var isKunena = source.value === 'kunena';
                var kunenaInstalled = marker.dataset.kunenaInstalled === '1';

                if (isKunena) {
                    if (input.dataset.cbuserstatusMode === 'manual') {
                        marker.dataset.manualValue = input.value;
                    }

                    if (kunenaInstalled && marker.dataset.kunenaTimeout !== '') {
                        input.value = marker.dataset.kunenaTimeout;
                    }

                    input.disabled = true;
                    input.removeAttribute('name');
                } else {
                    input.disabled = false;
                    input.setAttribute('name', marker.dataset.originalName);

                    if (marker.dataset.manualValue !== undefined) {
                        input.value = marker.dataset.manualValue;
                    }
                }

                if (note) {
                    note.style.display = isKunena && !kunenaInstalled ? '' : 'none';
                }

                input.dataset.cbuserstatusMode = isKunena ? 'kunena' : 'manual';
            }

            source.addEventListener('change', applyState);
            applyState();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCbUserOnlineStatusTimeoutFields);
    } else {
        initCbUserOnlineStatusTimeoutFields();
    }
})();
</script>
HTML;
    }
}
