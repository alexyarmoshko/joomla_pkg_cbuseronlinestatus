<?php
/**
 * Default layout for mod_cbuseronlinestatus (modes 1 & 9: Online Users / Connections).
 *
 * @var   \CBuser[] $cbUsers        Array of CBuser instances
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

/** @var Joomla\Registry\Registry $params */
/** @var string $sublayout */

$pluginsEnabled = (int) $params->get('cb_plugins', 0) && class_exists('modCBOnlineHelper', false);
$beforeLinksPlugins = '';
$afterUsersPlugins = '';

if ($pluginsEnabled) {
    // Cache hook output so hooks are not executed twice (condition + render).
    $beforeLinksPlugins = (string) \modCBOnlineHelper::getPlugins($params, 'beforeLinks');
    $afterUsersPlugins = (string) \modCBOnlineHelper::getPlugins($params, 'afterUsers');
}

// Delegate to sublayouts for statistics (mode 6) and census (mode 7)
if (!empty($sublayout)) {
    require __DIR__ . '/default_' . basename($sublayout) . '.php';
    return;
}

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
    echo \modCBOnlineHelper::getPlugins($params, 'beforeUsers');
} ?>
<?php if ($beforeLinksPlugins || $cbUsers || $afterUsersPlugins) { ?>
    <ul class="m-0 unstyled list-unstyled cbOnlineUsers">
        <?php echo $beforeLinksPlugins; ?>
        <?php foreach ($cbUsers as $cbUser) { ?>
            <li class="cbOnlineUser">
                <?php
                if ($params->get('usertext')) {
                    echo $cbUser->replaceUserVars($params->get('usertext'));
                } else {
                    echo $cbUser->getField('formatname', null, 'html', 'none', 'list', 0, true);
                }
                ?>
            </li>
        <?php } ?>
        <?php echo $afterUsersPlugins; ?>
    </ul>
<?php } ?>
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
