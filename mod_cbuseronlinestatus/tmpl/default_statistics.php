<?php
/**
 * Statistics sublayout for mod_cbuseronlinestatus (mode 6).
 *
 * @var   int       $onlineUsers    Online user count
 * @var   int       $offlineUsers   Offline user count
 * @var   int       $guestUsers     Guest count
 * @var   int       $label          Label style (1=text, 2=icon, 3=both)
 * @var   string    $separator      Number format thousands separator
 * @var   string    $templateClass  CB template CSS class
 * @var   string    $preText        Pre-text HTML
 * @var   string    $postText       Post-text HTML
 * @var   Registry  $params         Module parameters
 *
 * @package     Joomla.Site
 * @subpackage  mod_cbuseronlinestatus
 *
 * @copyright   (C) 2026 Yak Shaver https://www.kayakshaver.com
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

use CBLib\Language\CBTxt;

$pluginsEnabled = (int) $params->get('cb_plugins', 0) && class_exists('modCBOnlineHelper', false);

?>
<?php if ($pluginsEnabled) {
    echo \modCBOnlineHelper::getPlugins($params, 'start');
} ?>
<?php if ($preText) { ?>
    <div class="pretext">
        <p>
            <?php echo $preText; ?>
        </p>
    </div>
<?php } ?>
<?php if ($pluginsEnabled) {
    echo \modCBOnlineHelper::getPlugins($params, 'beforeStatistics');
} ?>
<ul class="m-0 unstyled list-unstyled cbOnlineStatistics">
    <?php if ($pluginsEnabled) {
        echo \modCBOnlineHelper::getPlugins($params, 'beforeList');
    } ?>
    <li class="cbStatisticsOnline">
        <?php
        $icon = '<span class="' . htmlspecialchars($templateClass) . '">'
            . '<span class="cbModuleStatisticsOnlineIcon fa fa-circle" title="' . htmlspecialchars(CBTxt::T('ONLINE_USERS', 'Online Users')) . '"></span>'
            . '</span>';

        if ($label == 3) {
            echo CBTxt::T('ICON_ONLINE_USERS_COUNT_FORMAT', '[icon] Online Users: [count_format]', array('[icon]' => $icon, '[count]' => $onlineUsers, '[count_format]' => number_format((float) $onlineUsers, 0, '.', $separator)));
        } elseif ($label == 2) {
            echo CBTxt::T('ICON_COUNT_FORMAT', '[icon] [count_format]', array('[icon]' => $icon, '[count]' => $onlineUsers, '[count_format]' => number_format((float) $onlineUsers, 0, '.', $separator)));
        } else {
            echo CBTxt::T('ONLINE_USERS_COUNT_FORMAT', 'Online Users: [count_format]', array('[count]' => $onlineUsers, '[count_format]' => number_format((float) $onlineUsers, 0, '.', $separator)));
        }
        ?>
    </li>
    <li class="cbStatisticsOffline">
        <?php
        $icon = '<span class="' . htmlspecialchars($templateClass) . '">'
            . '<span class="cbModuleStatisticsOfflineIcon fa fa-circle-o" title="' . htmlspecialchars(CBTxt::T('OFFLINE_USERS', 'Offline Users')) . '"></span>'
            . '</span>';

        if ($label == 3) {
            echo CBTxt::T('ICON_OFFLINE_USERS_COUNT_FORMAT', '[icon] Offline Users: [count_format]', array('[icon]' => $icon, '[count]' => $offlineUsers, '[count_format]' => number_format((float) $offlineUsers, 0, '.', $separator)));
        } elseif ($label == 2) {
            echo CBTxt::T('ICON_COUNT_FORMAT', '[icon] [count_format]', array('[icon]' => $icon, '[count]' => $offlineUsers, '[count_format]' => number_format((float) $offlineUsers, 0, '.', $separator)));
        } else {
            echo CBTxt::T('OFFLINE_USERS_COUNT_FORMAT', 'Offline Users: [count_format]', array('[count]' => $offlineUsers, '[count_format]' => number_format((float) $offlineUsers, 0, '.', $separator)));
        }
        ?>
    </li>
    <li class="cbStatisticsGuest">
        <?php
        $icon = '<span class="' . htmlspecialchars($templateClass) . '">'
            . '<span class="cbModuleStatisticsGuestIcon fa fa-eye" title="' . htmlspecialchars(CBTxt::T('GUESTS', 'Guests')) . '"></span>'
            . '</span>';

        if ($label == 3) {
            echo CBTxt::T('ICON_GUESTS_COUNT_FORMAT', '[icon] Guests: [count_format]', array('[icon]' => $icon, '[count]' => $guestUsers, '[count_format]' => number_format((float) $guestUsers, 0, '.', $separator)));
        } elseif ($label == 2) {
            echo CBTxt::T('ICON_COUNT_FORMAT', '[icon] [count_format]', array('[icon]' => $icon, '[count]' => $guestUsers, '[count_format]' => number_format((float) $guestUsers, 0, '.', $separator)));
        } else {
            echo CBTxt::T('GUESTS_COUNT_FORMAT', 'Guests: [count_format]', array('[count]' => $guestUsers, '[count_format]' => number_format((float) $guestUsers, 0, '.', $separator)));
        }
        ?>
    </li>
    <?php if ($pluginsEnabled) {
        echo \modCBOnlineHelper::getPlugins($params, 'afterStatistics');
    } ?>
</ul>
<?php if ($pluginsEnabled) {
    echo \modCBOnlineHelper::getPlugins($params, 'almostEnd');
} ?>
<?php if ($postText) { ?>
    <div class="posttext">
        <p>
            <?php echo $postText; ?>
        </p>
    </div>
<?php } ?>
<?php if ($pluginsEnabled) {
    echo \modCBOnlineHelper::getPlugins($params, 'end');
} ?>