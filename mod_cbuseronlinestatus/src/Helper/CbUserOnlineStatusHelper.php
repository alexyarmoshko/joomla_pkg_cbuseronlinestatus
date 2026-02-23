<?php
/**
 * Helper for mod_cbuseronlinestatus.
 *
 * Provides session-based queries for four CB module modes with configurable
 * online-timeout filtering and shared_session compatibility.
 *
 * @package     Joomla.Site
 * @subpackage  mod_cbuseronlinestatus
 *
 * @copyright   (C) 2026 Yak Shaver https://www.kayakshaver.com
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Module\Cbuseronlinestatus\Site\Helper;

use CBLib\Application\Application;
use CBLib\Registry\GetterInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Helper class for the Yak Shaver CB User Online Status module.
 *
 * @since  1.0.0
 */
class CbUserOnlineStatusHelper
{
    use DatabaseAwareTrait;

    /**
     * Returns all template variables needed by the active layout.
     *
     * @param   Registry  $params  Module parameters.
     *
     * @return  array
     *
     * @since   1.0.0
     */
    public function getLayoutVariables(Registry $params): array
    {
        // Bootstrap CB if not yet loaded
        if (!defined('_VALID_CB') && file_exists(JPATH_ADMINISTRATOR . '/components/com_comprofiler/plugin.foundation.php')) {
            include_once JPATH_ADMINISTRATOR . '/components/com_comprofiler/plugin.foundation.php';
            cbimport('cb.html');
            cbimport('language.front');
        }

        global $_CB_framework, $_CB_database;

        $mode = (int) $params->get('mode', 1);
        $timeout = $this->getOnlineTimeout($params);
        $limit = (int) $params->get('limit', 30);
        $label = (int) $params->get('label', 1);
        $separator = $params->get('separator', ',');

        $cbUser = \CBuser::getMyInstance();
        $user = $cbUser->getUserData();
        $templateClass = 'cb_template cb_template_' . selectTemplate('dir');

        // Load CB template CSS if requested
        if ((int) $params->get('maincbtpl', 1)) {
            outputCbTemplate();
        }

        // Load CB plugins if requested (needed for hook compatibility)
        if ((int) $params->get('cb_plugins', 0)) {
            global $_PLUGINS;
            $_PLUGINS->loadPluginGroup('user');

            // Include the original module helper for plugin hook support
            $helperFile = JPATH_SITE . '/modules/mod_comprofileronline/helper.php';
            if (file_exists($helperFile)) {
                require_once $helperFile;
            }
        }

        // Build exclusion list
        $exclude = array_filter(array_map('intval', explode(',', (string) $params->get('exclude', ''))));
        if ((int) $params->get('exclude_self', 0)) {
            $exclude[] = $user->get('id', 0, GetterInterface::INT);
        }

        // Pre/post text with CB substitutions
        $preText = $params->get('pretext') ? $cbUser->replaceUserVars($params->get('pretext')) : null;
        $postText = $params->get('posttext') ? $cbUser->replaceUserVars($params->get('posttext')) : null;

        $data = [
            'templateClass' => $templateClass,
            'preText' => $preText,
            'postText' => $postText,
            'params' => $params,
        ];

        switch ($mode) {
            case 9: // Online Connections
                $currentUserId = $user->get('id', 0, GetterInterface::INT);
                $userIds = [];
                if ($currentUserId) {
                    $userIds = $this->getOnlineConnectionIds($params, $timeout, $exclude, $limit, $currentUserId);
                }
                $data['cbUsers'] = $this->buildCbUsers($userIds);
                $data['sublayout'] = '';
                break;

            case 6: // Online Statistics
                $stats = $this->getOnlineStatistics($params, $timeout, $exclude);
                $data['onlineUsers'] = $stats['onlineUsers'];
                $data['offlineUsers'] = $stats['offlineUsers'];
                $data['guestUsers'] = $stats['guestUsers'];
                $data['label'] = $label;
                $data['separator'] = $separator;
                $data['sublayout'] = 'statistics';
                break;

            case 7: // User Census
                $census = $this->getCensusData($params, $timeout, $exclude);
                $data['totalUsers'] = $census['totalUsers'];
                $data['latestUser'] = $census['latestUser'];
                $data['onlineUsers'] = $census['onlineUsers'];
                $data['usersToday'] = $census['usersToday'];
                $data['usersWeek'] = $census['usersWeek'];
                $data['usersMonth'] = $census['usersMonth'];
                $data['usersYear'] = $census['usersYear'];
                $data['label'] = $label;
                $data['separator'] = $separator;
                $data['sublayout'] = 'census';
                break;

            default: // Mode 1: Online Users
                $userIds = $this->getOnlineUserIds($params, $timeout, $exclude, $limit);
                $data['cbUsers'] = $this->buildCbUsers($userIds);
                $data['sublayout'] = '';
                break;
        }

        return $data;
    }

    /**
     * Returns the effective online timeout in seconds.
     *
     * Priority: system plugin (via application state) > module parameter > 1800 default.
     *
     * @param   Registry  $params  Module parameters.
     *
     * @return  int
     *
     * @since   1.0.0
     */
    public function getOnlineTimeout(Registry $params): int
    {
        $timeout = \Joomla\CMS\Factory::getApplication()->get('cbuserstatus.timeout', 0);
        if ($timeout > 0) {
            return $timeout;
        }

        return (int) $params->get('online_timeout', 1800);
    }

    /**
     * Mode 1 — Online Users: returns user IDs with active sessions.
     *
     * @param   Registry  $params   Module parameters.
     * @param   int       $timeout  Timeout in seconds.
     * @param   int[]     $exclude  User IDs to exclude.
     * @param   int       $limit    Maximum results.
     *
     * @return  int[]
     */
    private function getOnlineUserIds(Registry $params, int $timeout, array $exclude, int $limit): array
    {
        global $_CB_framework, $_CB_database;

        $timeout = (int) $timeout;
        $clientIdClause = $_CB_framework->getCfg('shared_session') ? ' IS NULL' : ' = 0';

        $query = 'SELECT DISTINCT ' . $_CB_database->NameQuote('userid')
            . "\n FROM " . $_CB_database->NameQuote('#__session')
            . "\n WHERE " . $_CB_database->NameQuote('client_id') . $clientIdClause
            . "\n AND " . $_CB_database->NameQuote('guest') . " = 0"
            . "\n AND (UNIX_TIMESTAMP() - " . $_CB_database->NameQuote('time') . " <= {$timeout})"
            . ($exclude ? "\n AND " . $_CB_database->NameQuote('userid') . " NOT IN " . $_CB_database->safeArrayOfIntegers($exclude) : '')
            . "\n ORDER BY " . $_CB_database->NameQuote('time') . " DESC";
        $_CB_database->setQuery($query, 0, $limit);

        return $_CB_database->loadResultArray() ?: [];
    }

    /**
     * Mode 9 — Online Connections: returns connected user IDs with active sessions.
     *
     * @param   Registry  $params         Module parameters.
     * @param   int       $timeout        Timeout in seconds.
     * @param   int[]     $exclude        User IDs to exclude.
     * @param   int       $limit          Maximum results.
     * @param   int       $currentUserId  Current user's ID.
     *
     * @return  int[]
     */
    private function getOnlineConnectionIds(Registry $params, int $timeout, array $exclude, int $limit, int $currentUserId): array
    {
        global $_CB_framework, $_CB_database;

        $timeout = (int) $timeout;
        $clientIdClause = $_CB_framework->getCfg('shared_session') ? ' IS NULL' : ' = 0';

        $query = 'SELECT DISTINCT s.' . $_CB_database->NameQuote('userid')
            . "\n FROM " . $_CB_database->NameQuote('#__session') . " AS s"
            . "\n INNER JOIN " . $_CB_database->NameQuote('#__comprofiler_members') . " AS m"
            . ' ON m.' . $_CB_database->NameQuote('referenceid') . ' = ' . (int) $currentUserId
            . ' AND m.' . $_CB_database->NameQuote('memberid') . ' = s.' . $_CB_database->NameQuote('userid')
            . ' AND m.' . $_CB_database->NameQuote('accepted') . ' = 1'
            . ' AND m.' . $_CB_database->NameQuote('pending') . ' = 0'
            . "\n WHERE s." . $_CB_database->NameQuote('client_id') . $clientIdClause
            . "\n AND s." . $_CB_database->NameQuote('guest') . " = 0"
            . "\n AND (UNIX_TIMESTAMP() - s." . $_CB_database->NameQuote('time') . " <= {$timeout})"
            . ($exclude ? "\n AND s." . $_CB_database->NameQuote('userid') . " NOT IN " . $_CB_database->safeArrayOfIntegers($exclude) : '')
            . "\n ORDER BY s." . $_CB_database->NameQuote('time') . " DESC";
        $_CB_database->setQuery($query, 0, $limit);

        return $_CB_database->loadResultArray() ?: [];
    }

    /**
     * Mode 6 — Online Statistics: returns online, offline, and guest counts.
     *
     * @param   Registry  $params   Module parameters.
     * @param   int       $timeout  Timeout in seconds.
     * @param   int[]     $exclude  User IDs to exclude.
     *
     * @return  array{onlineUsers: int, offlineUsers: int, guestUsers: int}
     */
    private function getOnlineStatistics(Registry $params, int $timeout, array $exclude): array
    {
        global $_CB_framework, $_CB_database;

        $timeout = (int) $timeout;
        $clientIdClause = $_CB_framework->getCfg('shared_session') ? ' IS NULL' : ' = 0';

        // Online users count (sessions within timeout)
        $query = 'SELECT COUNT(DISTINCT ' . $_CB_database->NameQuote('userid') . ')'
            . "\n FROM " . $_CB_database->NameQuote('#__session')
            . "\n WHERE " . $_CB_database->NameQuote('client_id') . $clientIdClause
            . "\n AND " . $_CB_database->NameQuote('guest') . " = 0"
            . "\n AND (UNIX_TIMESTAMP() - " . $_CB_database->NameQuote('time') . " <= {$timeout})"
            . ($exclude ? "\n AND " . $_CB_database->NameQuote('userid') . " NOT IN " . $_CB_database->safeArrayOfIntegers($exclude) : '');
        $_CB_database->setQuery($query);
        $onlineUsers = (int) $_CB_database->loadResult();

        // Offline users count (registered users with no active session within timeout)
        $query = 'SELECT COUNT(*)'
            . "\n FROM " . $_CB_database->NameQuote('#__comprofiler') . " AS c"
            . "\n INNER JOIN " . $_CB_database->NameQuote('#__users') . " AS u"
            . ' ON u.' . $_CB_database->NameQuote('id') . ' = c.' . $_CB_database->NameQuote('id')
            . "\n LEFT JOIN " . $_CB_database->NameQuote('#__session') . " AS s"
            . ' ON s.' . $_CB_database->NameQuote('userid') . ' = u.' . $_CB_database->NameQuote('id')
            . ' AND s.' . $_CB_database->NameQuote('client_id') . ($_CB_framework->getCfg('shared_session') ? ' IS NULL' : ' = 0')
            . " AND s." . $_CB_database->NameQuote('guest') . " = 0"
            . " AND (UNIX_TIMESTAMP() - s." . $_CB_database->NameQuote('time') . " <= {$timeout})"
            . "\n WHERE c." . $_CB_database->NameQuote('approved') . " = 1"
            . "\n AND c." . $_CB_database->NameQuote('confirmed') . " = 1"
            . "\n AND u." . $_CB_database->NameQuote('block') . " = 0"
            . "\n AND s." . $_CB_database->NameQuote('session_id') . " IS NULL"
            . ($exclude ? "\n AND u." . $_CB_database->NameQuote('id') . " NOT IN " . $_CB_database->safeArrayOfIntegers($exclude) : '');
        $_CB_database->setQuery($query);
        $offlineUsers = (int) $_CB_database->loadResult();

        // Guest users count
        $query = 'SELECT COUNT(*)'
            . "\n FROM " . $_CB_database->NameQuote('#__session')
            . "\n WHERE " . $_CB_database->NameQuote('client_id') . $clientIdClause
            . "\n AND " . $_CB_database->NameQuote('guest') . " = 1"
            . "\n AND (UNIX_TIMESTAMP() - " . $_CB_database->NameQuote('time') . " <= {$timeout})";
        $_CB_database->setQuery($query);
        $guestUsers = (int) $_CB_database->loadResult();

        return [
            'onlineUsers' => $onlineUsers,
            'offlineUsers' => $offlineUsers,
            'guestUsers' => $guestUsers,
        ];
    }

    /**
     * Mode 7 — User Census: returns total users, latest user, online count,
     * and registration counts by period.
     *
     * @param   Registry  $params   Module parameters.
     * @param   int       $timeout  Timeout in seconds.
     * @param   int[]     $exclude  User IDs to exclude.
     *
     * @return  array
     */
    private function getCensusData(Registry $params, int $timeout, array $exclude): array
    {
        global $_CB_framework, $_CB_database;

        $timeout = (int) $timeout;
        $clientIdClause = $_CB_framework->getCfg('shared_session') ? ' IS NULL' : ' = 0';

        $excludeClause = $exclude
            ? "\n AND u." . $_CB_database->NameQuote('id') . " NOT IN " . $_CB_database->safeArrayOfIntegers($exclude)
            : '';

        // Total registered users
        $query = 'SELECT COUNT(*)'
            . "\n FROM " . $_CB_database->NameQuote('#__comprofiler') . " AS c"
            . "\n INNER JOIN " . $_CB_database->NameQuote('#__users') . " AS u"
            . ' ON u.' . $_CB_database->NameQuote('id') . ' = c.' . $_CB_database->NameQuote('id')
            . "\n WHERE c." . $_CB_database->NameQuote('approved') . " = 1"
            . "\n AND c." . $_CB_database->NameQuote('confirmed') . " = 1"
            . "\n AND u." . $_CB_database->NameQuote('block') . " = 0"
            . $excludeClause;
        $_CB_database->setQuery($query);
        $totalUsers = (int) $_CB_database->loadResult();

        // Latest registered user
        $query = 'SELECT u.' . $_CB_database->NameQuote('id')
            . "\n FROM " . $_CB_database->NameQuote('#__comprofiler') . " AS c"
            . "\n INNER JOIN " . $_CB_database->NameQuote('#__users') . " AS u"
            . ' ON u.' . $_CB_database->NameQuote('id') . ' = c.' . $_CB_database->NameQuote('id')
            . "\n WHERE c." . $_CB_database->NameQuote('approved') . " = 1"
            . "\n AND c." . $_CB_database->NameQuote('confirmed') . " = 1"
            . "\n AND u." . $_CB_database->NameQuote('block') . " = 0"
            . $excludeClause
            . "\n ORDER BY u." . $_CB_database->NameQuote('registerDate') . " DESC";
        $_CB_database->setQuery($query, 0, 1);
        $latestUserId = $_CB_database->loadResult();
        $latestUser = \CBuser::getInstance((int) $latestUserId);

        // Online users count (with timeout)
        $excludeSessionClause = $exclude
            ? "\n AND " . $_CB_database->NameQuote('userid') . " NOT IN " . $_CB_database->safeArrayOfIntegers($exclude)
            : '';
        $query = 'SELECT COUNT(DISTINCT ' . $_CB_database->NameQuote('userid') . ')'
            . "\n FROM " . $_CB_database->NameQuote('#__session')
            . "\n WHERE " . $_CB_database->NameQuote('client_id') . $clientIdClause
            . "\n AND " . $_CB_database->NameQuote('guest') . " = 0"
            . "\n AND (UNIX_TIMESTAMP() - " . $_CB_database->NameQuote('time') . " <= {$timeout})"
            . $excludeSessionClause;
        $_CB_database->setQuery($query);
        $onlineUsers = (int) $_CB_database->loadResult();

        // Registration date-based counts (unchanged from original module)
        $thisDay = Application::Date('today', 'UTC')->format('Y-m-d');
        $nextDay = Application::Date('tomorrow', 'UTC')->format('Y-m-d');
        $thisWeek = Application::Date('Monday this week', 'UTC')->format('Y-m-d');
        $nextWeek = Application::Date('Monday next week', 'UTC')->format('Y-m-d');
        $thisMonth = Application::Date('last day of last month', 'UTC')->format('Y-m-d');
        $nextMonth = Application::Date('last day of this month', 'UTC')->format('Y-m-d');
        $thisYear = Application::Date('last day of December last year', 'UTC')->format('Y-m-d');
        $nextYear = Application::Date('last day of December this year', 'UTC')->format('Y-m-d');

        $registeredBase = 'SELECT COUNT(*)'
            . "\n FROM " . $_CB_database->NameQuote('#__comprofiler') . " AS c"
            . "\n INNER JOIN " . $_CB_database->NameQuote('#__users') . " AS u"
            . ' ON u.' . $_CB_database->NameQuote('id') . ' = c.' . $_CB_database->NameQuote('id')
            . "\n WHERE c." . $_CB_database->NameQuote('approved') . " = 1"
            . "\n AND c." . $_CB_database->NameQuote('confirmed') . " = 1"
            . "\n AND u." . $_CB_database->NameQuote('block') . " = 0";

        $_CB_database->setQuery($registeredBase
            . "\n AND u." . $_CB_database->NameQuote('registerDate') . " BETWEEN " . $_CB_database->Quote($thisDay) . " AND " . $_CB_database->Quote($nextDay)
            . $excludeClause);
        $usersToday = (int) $_CB_database->loadResult();

        $_CB_database->setQuery($registeredBase
            . "\n AND u." . $_CB_database->NameQuote('registerDate') . " BETWEEN " . $_CB_database->Quote($thisWeek) . " AND " . $_CB_database->Quote($nextWeek)
            . $excludeClause);
        $usersWeek = (int) $_CB_database->loadResult();

        $_CB_database->setQuery($registeredBase
            . "\n AND u." . $_CB_database->NameQuote('registerDate') . " BETWEEN " . $_CB_database->Quote($thisMonth) . " AND " . $_CB_database->Quote($nextMonth)
            . $excludeClause);
        $usersMonth = (int) $_CB_database->loadResult();

        $_CB_database->setQuery($registeredBase
            . "\n AND u." . $_CB_database->NameQuote('registerDate') . " BETWEEN " . $_CB_database->Quote($thisYear) . " AND " . $_CB_database->Quote($nextYear)
            . $excludeClause);
        $usersYear = (int) $_CB_database->loadResult();

        return [
            'totalUsers' => $totalUsers,
            'latestUser' => $latestUser,
            'onlineUsers' => $onlineUsers,
            'usersToday' => $usersToday,
            'usersWeek' => $usersWeek,
            'usersMonth' => $usersMonth,
            'usersYear' => $usersYear,
        ];
    }

    /**
     * Converts an array of user IDs to CBuser instances.
     *
     * @param   int[]  $userIds  User IDs.
     *
     * @return  \CBuser[]
     */
    private function buildCbUsers(array $userIds): array
    {
        if (!$userIds) {
            return [];
        }

        \CBuser::advanceNoticeOfUsersNeeded($userIds);

        $cbUsers = [];
        foreach ($userIds as $userId) {
            $cbUser = \CBuser::getInstance((int) $userId);
            if ($cbUser !== null) {
                $cbUsers[] = $cbUser;
            }
        }

        return $cbUsers;
    }
}
