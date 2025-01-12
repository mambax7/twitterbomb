<?php
/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/

/**
 * XOOPS global search
 *
 * See the enclosed file license.txt for licensing information.
 * If you did not receive this file, get it at http://www.fsf.org/copyleft/gpl.html
 *
 * @copyright       The XOOPS Project http://sourceforge.net/projects/xoops/
 * @license         http://www.fsf.org/copyleft/gpl.html GNU General Public License (GPL)
 * @package         core
 * @since           2.0.0
 * @author          Kazumi Ono (AKA onokazu)
 * @author          Taiwen Jiang <phppp@users.sourceforge.net>
 * @version         $Id: search.php 5334 2010-09-18 23:55:54Z kris_fr $
 * @todo            Modularize; Both search algorithms and interface will be redesigned
 */
include('header.php');

xoops_loadLanguage('search');

$config_handler    = xoops_getHandler('config');
$xoopsConfigSearch = $config_handler->getConfigsByCat(XOOPS_CONF_SEARCH);

if (1 != $xoopsConfigSearch['enable_search']) {
    header('Location: ' . XOOPS_URL . '/modules/twitterbomb/index.php');
    exit();
}
$action = 'search';
if (!empty($_GET['action'])) {
    $action = trim(strip_tags($_GET['action']));
} else if (!empty($_POST['action'])) {
    $action = trim(strip_tags($_POST['action']));
}
$query = '';
if (!empty($_GET['query'])) {
    $query = trim(strip_tags($_GET['query']));
} else if (!empty($_POST['query'])) {
    $query = trim(strip_tags($_POST['query']));
}
$andor = 'OR';
if (!empty($_GET['andor'])) {
    $andor = trim(strip_tags($_GET['andor']));
} else if (!empty($_POST['andor'])) {
    $andor = trim(strip_tags($_POST['andor']));
}
$mid = $uid = $start = 0;
if (!empty($_GET['mid'])) {
    $mid = (int)$_GET['mid'];
} else if (!empty($_POST['mid'])) {
    $mid = (int)$_POST['mid'];
}
if (!empty($_GET['uid'])) {
    $uid = (int)$_GET['uid'];
} else if (!empty($_POST['uid'])) {
    $uid = (int)$_POST['uid'];
}
if (!empty($_GET['start'])) {
    $start = (int)$_GET['start'];
} else if (!empty($_POST['start'])) {
    $start = (int)$_POST['start'];
}

$queries = [];

if ('results' == $action) {
    if ('' == $query) {
        redirect_header('search.php', 1, _SR_PLZENTER);
        exit();
    }
} else if ('showall' == $action) {
    if ('' == $query || empty($mid)) {
        redirect_header('search.php', 1, _SR_PLZENTER);
        exit();
    }
} else if ('showallbyuser' == $action) {
    if (empty($mid) || empty($uid)) {
        redirect_header('search.php', 1, _SR_PLZENTER);
        exit();
    }
}

$groups            = is_object($xoopsUser) ? $xoopsUser->getGroups() : XOOPS_GROUP_ANONYMOUS;
$gperm_handler     = xoops_getHandler('groupperm');
$available_modules = $gperm_handler->getItemIds('module_read', $groups);
if ('search' == $action) {
    include $GLOBALS['xoops']->path('header.php');
    include $GLOBALS['xoops']->path('include/searchform.php');
    $search_form->display();
    include $GLOBALS['xoops']->path('footer.php');
    exit();
}
if ('OR' != $andor && 'exact' != $andor && 'AND' != $andor) {
    $andor = 'AND';
}

$myts = MyTextSanitizer::getInstance();
if ('showallbyuser' != $action) {
    if ('exact' != $andor) {
        $ignored_queries = []; // holds kewords that are shorter than allowed minmum length
        $temp_queries = preg_split('/[\s,]+/', $query);
        foreach ($temp_queries as $q) {
            $q = trim($q);
            if (strlen($q) >= $xoopsConfigSearch['keyword_min']) {
                $queries[] = $myts->addSlashes($q);
            } else {
                $ignored_queries[] = $myts->addSlashes($q);
            }
        }
        if (0 == count($queries)) {
            redirect_header('search.php', 2, sprintf(_SR_KEYTOOSHORT, $xoopsConfigSearch['keyword_min']));
            exit();
        }
    } else {
        $query = trim($query);
        if (strlen($query) < $xoopsConfigSearch['keyword_min']) {
            redirect_header('search.php', 2, sprintf(_SR_KEYTOOSHORT, $xoopsConfigSearch['keyword_min']));
            exit();
        }
        $queries = [$myts->addSlashes($query)];
    }
}
switch ($action) {
    case 'results':
        $module_handler = xoops_getHandler('module');
        $criteria       = new \CriteriaCompo(new \Criteria('hassearch', 1));
        $criteria->add(new \Criteria('isactive', 1));
        $criteria->add(new \Criteria('mid', '(' . implode(',', $available_modules) . ')', 'IN'));
        $modules = $module_handler->getObjects($criteria, true);
        $mids = $_REQUEST['mids'] ?? [];
        if (empty($mids) || ! is_array($mids)) {
            unset($mids);
            $mids = array_keys($modules);
        }
        $xoopsOption['xoops_pagetitle'] = _SR_SEARCHRESULTS . ': ' . implode(' ', $queries);
        include $GLOBALS['xoops']->path('header.php');
        $nomatch = true;
        echo '<h3>' . _SR_SEARCHRESULTS . "</h3>\n";
        echo _SR_KEYWORDS . ':';
        if ('exact' != $andor) {
            foreach ($queries as $q) {
                echo ' <strong>' . htmlspecialchars(stripslashes($q)) . '</strong>';
            }
            if (!empty($ignored_queries)) {
                echo '<br />';
                printf(_SR_IGNOREDWORDS, $xoopsConfigSearch['keyword_min']);
                foreach ($ignored_queries as $q) {
                    echo ' <strong>' . htmlspecialchars(stripslashes($q)) . '</strong>';
                }
            }
        } else {
            echo ' "<strong>' . htmlspecialchars(stripslashes($queries[0])) . '</strong>"';
        }
        echo '<br />';
        foreach ($mids as $mid) {
            $mid = (int)$mid;
            if (in_array($mid, $available_modules)) {
                $module = $modules[$mid];
                $results = $module->search($queries, $andor, 5, 0);
                $count = count($results);
                if (is_array($results) && $count > 0) {
                    $nomatch = false;
                    echo '<h4>' . $module->getVar('name') . '</h4>';
                    for($i = 0; $i < $count; $i++) {
                        if (isset($results[$i]['image']) && '' != $results[$i]['image']) {
                            echo "<img src='modules/" . $module->getVar('dirname') . '/' . $results[$i]['image'] . "' alt='" . $module->getVar('name') . "' />&nbsp;";
                        } else {
                            echo "<img style='width:26px; height:26px;' src='images/icons/posticon2.gif' alt='" . $module->getVar('name') . "' />&nbsp;";
                        }
                        if (!preg_match('/^http[s]*:\/\//i', $results[$i]['link'])) {
                            $results[$i]['link'] = 'modules/' . $module->getVar('dirname') . '/' . $results[$i]['link'];
                        }
                        echo "<strong><a href='" . $results[$i]['link'] . "' title=''>" . $myts->htmlSpecialChars($results[$i]['title']) . "</a></strong><br />\n";
                        echo "<span class='x-small'>";
                        $results[$i]['uid'] = @(int)$results[$i]['uid'];
                        if (!empty($results[$i]['uid'])) {
                            $uname = XoopsUser::getUnameFromId($results[$i]['uid']);
                            echo "&nbsp;&nbsp;<a href='" . XOOPS_URL . '/userinfo.php?uid=' . $results[$i]['uid'] . "' title=''>" . $uname . "</a>\n";
                        }
                        echo !empty($results[$i]['time']) ? ' (' . formatTimestamp((int)$results[$i]['time']) . ')' : '';
                        echo "</span><br />\n";
                    }
                    if ($count >= 5) {
                        $search_url = XOOPS_URL . '/search.php?query=' . urlencode(stripslashes(implode(' ', $queries)));
                        $search_url .= "&mid={$mid}&action=showall&andor={$andor}";
                        echo '<p><a href="' . htmlspecialchars($search_url) . '" title="' . _SR_SHOWALLR . '">' . _SR_SHOWALLR . '</a></p>';
                    }
                }
            }
            unset($results);
            unset($module);
        }
        if ($nomatch) {
            echo '<p>' . _SR_NOMATCH . '</p>';
        }
        include $GLOBALS['xoops']->path('include/searchform.php');
        $search_form->display();
        break;

    case 'showall':
    case 'showallbyuser':
        include $GLOBALS['xoops']->path('header.php');
        $module_handler = xoops_getHandler('module');
        $module         = $module_handler->get($mid);
        $results        = $module->search($queries, $andor, 20, $start, $uid);
        $count = count($results);
        if (is_array($results) && $count > 0) {
            $next_results = $module->search($queries, $andor, 1, $start + 20, $uid);
            $next_count   = count($next_results);
            $has_next = false;
            if (is_array($next_results) && 1 == $next_count) {
                $has_next = true;
            }
            echo '<h4>' . _SR_SEARCHRESULTS . "</h4>\n";
            if ('showall' == $action) {
                echo _SR_KEYWORDS . ':';
                if ('exact' != $andor) {
                    foreach ($queries as $q) {
                        echo ' <strong>' . htmlspecialchars(stripslashes($q)) . '</strong>';
                    }
                } else {
                    echo ' "<strong>' . htmlspecialchars(stripslashes($queries[0])) . '</strong>"';
                }
                echo '<br />';
            }
            printf(_SR_SHOWING, $start + 1, $start + $count);
            echo '<h5>' . $module->getVar('name') . '</h5>';
            for ($i = 0; $i < $count; $i ++) {
                if (isset($results[$i]['image']) && '' != $results[$i]['image']) {
                    echo "<img src='modules/" . $module->getVar('dirname', 'n') . '/' . $results[$i]['image'] . "' alt='" . $module->getVar('name') . "' />&nbsp;";
                } else {
                    echo "<img style='width:26px; height:26px;' src='images/icons/posticon2.gif' alt='" . $module->getVar('name') . "' />&nbsp;";
                }
                if (!preg_match('/^http[s]*:\/\//i', $results[$i]['link'])) {
                    $results[$i]['link'] = 'modules/' . $module->getVar('dirname') . '/' . $results[$i]['link'];
                }
                echo "<strong><a href='" . $results[$i]['link'] . "'>" . $myts->htmlSpecialChars($results[$i]['title']) . "</a></strong><br />\n";
                echo "<span class='x-small'>";
                $results[$i]['uid'] = @(int)$results[$i]['uid'];
                if (!empty($results[$i]['uid'])) {
                    $uname = XoopsUser::getUnameFromId($results[$i]['uid']);
                    echo "&nbsp;&nbsp;<a href='" . XOOPS_URL . '/userinfo.php?uid=' . $results[$i]['uid'] . "'>" . $uname . "</a>\n";
                }
                echo !empty($results[$i]['time']) ? ' (' . formatTimestamp((int)$results[$i]['time']) . ')' : '';
                echo "</span><br />\n";
            }
            echo '<table><tr>';
            $search_url = XOOPS_URL . '/search.php?query=' . urlencode(stripslashes(implode(' ', $queries)));
            $search_url .= "&mid={$mid}&action={$action}&andor={$andor}";
            if ('showallbyuser' == $action) {
                $search_url .= "&uid={$uid}";
            }
            if ($start > 0) {
                $prev = $start - 20;
                echo '<td align="left">';
                $search_url_prev = $search_url . "&start={$prev}";
                echo '<a href="' . htmlspecialchars($search_url_prev) . '">' . _SR_PREVIOUS . '</a></td>';
            }
            echo '<td>&nbsp;&nbsp;</td>';
            if (false != $has_next) {
                $next = $start + 20;
                $search_url_next = $search_url . "&start={$next}";
                echo '<td align="right"><a href="' . htmlspecialchars($search_url_next) . '">' . _SR_NEXT . '</a></td>';
            }
            echo '</tr></table>';
        } else {
            echo '<p>' . _SR_NOMATCH . '</p>';
        }
        include $GLOBALS['xoops']->path('include/searchform.php');
        $search_form->display();
        break;
}
include $GLOBALS['xoops']->path('footer.php');
?>
