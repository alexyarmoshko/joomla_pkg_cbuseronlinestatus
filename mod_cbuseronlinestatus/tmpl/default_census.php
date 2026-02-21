<?php
/**
 * Census sublayout for mod_cbuseronlinestatus (mode 7).
 *
 * @var   int       $totalUsers     Total registered user count
 * @var   \CBuser   $latestUser     Latest registered CBuser instance
 * @var   int       $onlineUsers    Online user count
 * @var   int       $usersToday     Users registered today
 * @var   int       $usersWeek      Users registered this week
 * @var   int       $usersMonth     Users registered this month
 * @var   int       $usersYear      Users registered this year
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
    echo \modCBOnlineHelper::getPlugins($params, 'beforeCensus');
} ?>
<ul class="m-0 unstyled list-unstyled cbOnlineCensus">
    <?php if ($pluginsEnabled) {
        echo \modCBOnlineHelper::getPlugins($params, 'beforeList');
    } ?>
    <li class="cbCensusTotal">
        <?php
        $icon = '<span class="' . htmlspecialchars($templateClass) . '">'
            . '<span class="cbModuleCensusTotalIcon fa fa-users" title="' . htmlspecialchars(CBTxt::T('TOTAL_USERS', 'Total Users')) . '"></span>'
            . '</span>';

        if ($label == 3) {
            echo CBTxt::T('ICON_TOTAL_USERS_COUNT_FORMAT', '[icon] Total Users: [count_format]', array('[icon]' => $icon, '[count]' => $totalUsers, '[count_format]' => number_format((float) $totalUsers, 0, '.', $separator)));
        } elseif ($label == 2) {
            echo CBTxt::T('ICON_COUNT_FORMAT', '[icon] [count_format]', array('[icon]' => $icon, '[count]' => $totalUsers, '[count_format]' => number_format((float) $totalUsers, 0, '.', $separator)));
        } else {
            echo CBTxt::T('TOTAL_USERS_COUNT_FORMAT', 'Total Users: [count_format]', array('[count]' => $totalUsers, '[count_format]' => number_format((float) $totalUsers, 0, '.', $separator)));
        }
        ?>
    </li>
    <?php if ($latestUser) { ?>
        <li class="cbCensusLatest">
            <?php
            $icon = '<span class="' . htmlspecialchars($templateClass) . '">'
                . '<span class="cbModuleCensusLatestIcon fa fa-user" title="' . htmlspecialchars(CBTxt::T('LATEST_USER', 'Latest User')) . '"></span>'
                . '</span>';

            if ($label == 3) {
                echo CBTxt::T('ICON_LATEST_USER_FORMATNAME', '[icon] Latest User: [formatname]', array('[icon]' => $icon, '[formatname]' => $latestUser->getField('formatname', null, 'html', 'none', 'list', 0, true)));
            } elseif ($label == 2) {
                echo CBTxt::T('ICON_FORMATNAME', '[icon] [formatname]', array('[icon]' => $icon, '[formatname]' => $latestUser->getField('formatname', null, 'html', 'none', 'list', 0, true)));
            } else {
                echo CBTxt::T('LATEST_USER_FORMATNAME', 'Latest User: [formatname]', array('[formatname]' => $latestUser->getField('formatname', null, 'html', 'none', 'list', 0, true)));
            }
            ?>
        </li>
    <?php } ?>
    <li class="cbCensusOnline">
        <?php
        $icon = '<span class="' . htmlspecialchars($templateClass) . '">'
            . '<span class="cbModuleCensusOnlineIcon fa fa-circle" title="' . htmlspecialchars(CBTxt::T('ONLINE_USERS', 'Online Users')) . '"></span>'
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
    <li class="cbCensusToday">
        <?php
        $icon = '<span class="' . htmlspecialchars($templateClass) . '">'
            . '<span class="cbModuleCensusTodayIcon fa fa-calendar" title="' . htmlspecialchars(CBTxt::T('USERS_TODAY', 'Users Today')) . '"></span>'
            . '</span>';

        if ($label == 3) {
            echo CBTxt::T('ICON_USERS_TODAY_COUNT_FORMAT', '[icon] Users Today: [count_format]', array('[icon]' => $icon, '[count]' => $usersToday, '[count_format]' => number_format((float) $usersToday, 0, '.', $separator)));
        } elseif ($label == 2) {
            echo CBTxt::T('ICON_COUNT_FORMAT', '[icon] [count_format]', array('[icon]' => $icon, '[count]' => $usersToday, '[count_format]' => number_format((float) $usersToday, 0, '.', $separator)));
        } else {
            echo CBTxt::T('USERS_TODAY_COUNT_FORMAT', 'Users Today: [count_format]', array('[count]' => $usersToday, '[count_format]' => number_format((float) $usersToday, 0, '.', $separator)));
        }
        ?>
    </li>
    <li class="cbCensusWeek">
        <?php
        $icon = '<span class="' . htmlspecialchars($templateClass) . '">'
            . '<span class="cbModuleCensusWeekIcon fa fa-calendar" title="' . htmlspecialchars(CBTxt::T('USERS_THIS_WEEK', 'Users this Week')) . '"></span>'
            . '</span>';

        if ($label == 3) {
            echo CBTxt::T('ICON_USERS_THIS_WEEK_COUNT_FORMAT', '[icon] Users this Week: [count_format]', array('[icon]' => $icon, '[count]' => $usersWeek, '[count_format]' => number_format((float) $usersWeek, 0, '.', $separator)));
        } elseif ($label == 2) {
            echo CBTxt::T('ICON_COUNT_FORMAT', '[icon] [count_format]', array('[icon]' => $icon, '[count]' => $usersWeek, '[count_format]' => number_format((float) $usersWeek, 0, '.', $separator)));
        } else {
            echo CBTxt::T('USERS_THIS_WEEK_COUNT_FORMAT', 'Users this Week: [count_format]', array('[count]' => $usersWeek, '[count_format]' => number_format((float) $usersWeek, 0, '.', $separator)));
        }
        ?>
    </li>
    <li class="cbCensusMonth">
        <?php
        $icon = '<span class="' . htmlspecialchars($templateClass) . '">'
            . '<span class="cbModuleCensusMonthIcon fa fa-calendar" title="' . htmlspecialchars(CBTxt::T('USERS_THIS_MONTH', 'Users this Month')) . '"></span>'
            . '</span>';

        if ($label == 3) {
            echo CBTxt::T('ICON_USERS_THIS_MONTH_COUNT_FORMAT', '[icon] Users this Month: [count_format]', array('[icon]' => $icon, '[count]' => $usersMonth, '[count_format]' => number_format((float) $usersMonth, 0, '.', $separator)));
        } elseif ($label == 2) {
            echo CBTxt::T('ICON_COUNT_FORMAT', '[icon] [count_format]', array('[icon]' => $icon, '[count]' => $usersMonth, '[count_format]' => number_format((float) $usersMonth, 0, '.', $separator)));
        } else {
            echo CBTxt::T('USERS_THIS_MONTH_COUNT_FORMAT', 'Users this Month: [count_format]', array('[count]' => $usersMonth, '[count_format]' => number_format((float) $usersMonth, 0, '.', $separator)));
        }
        ?>
    </li>
    <li class="cbCensusYear">
        <?php
        $icon = '<span class="' . htmlspecialchars($templateClass) . '">'
            . '<span class="cbModuleCensusYearIcon fa fa-calendar" title="' . htmlspecialchars(CBTxt::T('USERS_THIS_YEAR', 'Users this Year')) . '"></span>'
            . '</span>';

        if ($label == 3) {
            echo CBTxt::T('ICON_USERS_THIS_YEAR_COUNT_FORMAT', '[icon] Users this Year: [count_format]', array('[icon]' => $icon, '[count]' => $usersYear, '[count_format]' => number_format((float) $usersYear, 0, '.', $separator)));
        } elseif ($label == 2) {
            echo CBTxt::T('ICON_COUNT_FORMAT', '[icon] [count_format]', array('[icon]' => $icon, '[count]' => $usersYear, '[count_format]' => number_format((float) $usersYear, 0, '.', $separator)));
        } else {
            echo CBTxt::T('USERS_THIS_YEAR_COUNT_FORMAT', 'Users this Year: [count_format]', array('[count]' => $usersYear, '[count_format]' => number_format((float) $usersYear, 0, '.', $separator)));
        }
        ?>
    </li>
    <?php if ($pluginsEnabled) {
        echo \modCBOnlineHelper::getPlugins($params, 'afterCensus');
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