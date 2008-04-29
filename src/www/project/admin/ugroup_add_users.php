<?php
//
// CodeX: Breaking Down the Barriers to Source Code Sharing inside Xerox
// Copyright (c) Xerox Corporation, CodeX, 2001-2004. All Rights Reserved
// http://codex.xerox.com
//
// 
//
// Originally written by Nicolas Terray 2008, CodeX Team, Xerox
//

require_once('pre.php');
require_once('www/project/admin/project_admin_utils.php');

$Language->loadLanguageMsg('project/project');

$request =& HTTPRequest::instance();

$group_id = $request->getValidated('group_id', 'GroupId', 0);

//Only project admin
session_require(array('group'=>$group_id,'admin_flags'=>'A'));


function display_user_result_table($res) {
    $nb_cols = 4;
    if (db_numrows($res)) {
        echo '<table><tr>';
        $i = 0;
        while($data = db_fetch_array($res)) {
            if ($i++ % $nb_cols == 0) {
                echo '</tr><tr>';
            }
            $action     = 'add';
            $icon       = '/ic/group_add.png';
            $background = 'eee';
            if ($data['is_on']) {
                $action     = 'remove';
                $icon       = '/ic/group_delete.png';
                $background = 'dcf7c4';
            }
            echo '<td width="20%">';
            echo '<div style="border:1px solid #CCC; background: #'. $background .'; padding:10px 5px; position:relative">';
            //echo '<div style="float:left;padding-right:3px;"><input type="checkbox" name="user_id" value="'. $data['user_id'] .'" /></div>';
            echo '<div style="">';
            echo '<div style="float:right;"><input type="image" src="'. util_get_dir_image_theme() . $icon .'" name="user['. $data['user_id'] .']" value="'. $action .'" /></div>';
            echo '<div style=""><a href="/users/'. $data['user_name'] .'/">'. user_get_name_display_from_id($data['user_id']) .'</a></div>';
            echo '<div style="color:#666">'. $data['email'] .'</div>';
            echo '</div>';
            echo '</div>';
            echo '</td>';
        }
        echo '</tr></table>';
    } else {
        echo 'No user match';
        echo db_error();
    }
}

$ugroup_id = $request->getValidated('ugroup_id', 'uint', 0);
if ($ugroup_id) {
    $res = ugroup_db_get_ugroup($ugroup_id);
    if ($res) {
        $hp = CodeX_HTMLPurifier::instance();
        
        //define capitals
        $sql = "SELECT DISTINCT UPPER(LEFT(user.email,1)) as capital
            FROM user
            WHERE status in ('A', 'R')
            UNION
            SELECT DISTINCT UPPER(LEFT(user.realname,1)) as capital
            FROM user
            WHERE status in ('A', 'R')
            UNION
            SELECT DISTINCT UPPER(LEFT(user.user_name,1)) as capital
            FROM user
            WHERE status in ('A', 'R')
            ORDER BY capital";
        $res = db_query($sql);
        $allowed_begin_values = array();
        while($data = db_fetch_array($res)) {
            $allowed_begin_values[] = $data['capital'];
        }

        $valid_order_by = new Valid_WhiteList('order_by', array('name', 'email'));
        $valid_order_by->required();
        
        $valid_asc = new Valid_WhiteList('', array('asc', 'desc'));
        $valid_asc->required();
        
        $valid_begin = new Valid_WhiteList('', $allowed_begin_values);
        $valid_begin->required();
        
        $offset           = $request->getValidated('offset', 'uint', 0);
        $number_per_page  = $request->exist('number_per_page') ? $request->getValidated('number_per_page', 'uint', 0) : 20;
        $order_by         = $request->getValidated('order_by', $valid_order_by, 'name');
        $asc              = $request->getValidated('asc', $valid_asc, 'asc');
        $search           = $request->getValidated('search', 'string', '');
        $begin            = $request->getValidated('begin', $valid_begin, '');
        
        $user = $request->get('user');
        if ($user && is_array($user)) {
            list($user_id, $action) = each($user);
            $user_id = (int)$user_id;
            if ($user_id) {
                switch($action) {
                case 'add':
                    ugroup_add_user_to_ugroup($group_id, $ugroup_id, $user_id);
                    break;
                case 'remove':
                    ugroup_remove_user_from_ugroup($group_id, $ugroup_id, $user_id);
                    break;
                default:
                    break;
                }
                $GLOBALS['Response']->redirect('?group_id='. (int)$group_id .
                    '&ugroup_id='. (int)$ugroup_id .
                    '&offset='. (int)$offset .
                    '&number_per_page='. (int)$number_per_page .
                    '&order_by='. urlencode($order_by) .
                    '&asc='. urlencode($asc) .
                    '&search='. urlencode($search) .
                    '&begin='. urlencode($begin)
                );
            }
        }
        //Display the page
        $ugroup_name = db_result($res, 0, 'name');
        project_admin_header(array(
            'title'=> $Language->getText('project_admin_editugroup','edit_ug'),
            'group'=>$group_id,
            'help' => 'UserGroups.html#UGroupCreation')
        );
        echo '<P><h2>'. 'Add users to '. $ugroup_name .'</h2>';
        
        //fetch existing members
        /*$sql = "SELECT user_id FROM ugroup_user WHERE ugroup_id = ". db_ei($ugroup_id);
        $members = array();
        if ($res = db_query($sql)) {
            while($data = db_fetch_array($res)) {
                $members[] = $data['user_id'];
            }
        }
        */
        
        //Display the form
        $selected = 'selected="selected"';
        echo '<form action="" method="GET">';
        echo '<table><tr valign="top"><td>';
        echo '<input type="hidden" name="group_id" value="'. (int)$group_id .'" />';
        echo '<input type="hidden" name="ugroup_id" value="'. (int)$ugroup_id .'" />';
        echo '<input type="hidden" name="offset" value="'. (int)$offset .'" />';

        //Filter
        echo '<fieldset><legend>'. 'Filter' .'</legend>';
        echo '<div>';
        //contains
        echo 'Search users whose name or email contains ';
        echo '<input type="text" name="search" value="'.  $hp->purify($search, CODEX_PURIFIER_CONVERT_HTML) .'" class="textfield_medium" /> ';
        //begin
        echo 'or begins with ';
        echo '<select name="begin">';
        echo '<option value="" '. (in_array($begin, $allowed_begin_values) ? $selected : '') .'></option>';
        foreach($allowed_begin_values as $b) {
            echo '<option value="'. $b .'" '. ($b == $begin ? $selected : '') .'>'. $b .'</option>';
        }
        echo '</select>';
        echo '</div>';
        echo '</fieldset>';
        
        //Display
        echo '<fieldset><legend>'. 'Display' .'</legend>';
        echo '<div>';
        echo 'Show ';
        //number per page
        echo '<select name="number_per_page">';
        echo '<option '. ($number_per_page == 20 ? $selected : '') .'>20</option>';
        echo '<option '. ($number_per_page == 40 ? $selected : '') .'>40</option>';
        echo '<option '. ($number_per_page == 80 ? $selected : '') .'>80</option>';
        if (!in_array($number_per_page, array(20, 40, 80))) {
            echo '<option '. $selected .'>'. (int)$number_per_page .'</option>';
        }
        echo '</select> ';
        echo 'users sorted by ';
        //Order by
        echo '<select name="order_by">';
        echo '<option value="name"  '. ($order_by == 'name'  ? $selected : '') .'>'. 'name' .'</option>';
        echo '<option value="email" '. ($order_by == 'email' ? $selected : '') .'>'. 'email' .'</option>';
        echo '</select> ';
        echo 'with ';
        //asc
        echo '<select name="asc">';
        echo '<option value="asc"  '. ($asc == 'asc'  ? $selected : '') .'>'. 'ascending order' .'</option>';
        echo '<option value="desc" '. ($asc == 'desc' ? $selected : '') .'>'. 'descending order' .'</option>';
        echo '</select> ';
        
        
        echo '</div>';
        echo '</fieldset>';
        echo '<div style="text-align:center"><input type="submit" value="Ok" /></div>';
        $sql = "SELECT SQL_CALC_FOUND_ROWS user.user_id, user_name, email, IF(R.user_id = user.user_id, 1, 0) AS is_on
                FROM user NATURAL LEFT JOIN (SELECT user_id FROM ugroup_user WHERE ugroup_id=". db_ei($ugroup_id) .") AS R
                WHERE status in ('A', 'R') ";
        if ($search || $begin) {
            $sql .= ' AND ( ';
            if ($search) {
                $sql .= " user.realname LIKE '%". db_es($search) ."%' OR user.user_name LIKE '%". db_es($search) ."%' OR user.email LIKE '%". db_es($search) ."%' ";
                if ($begin) {
                    $sql .= " OR ";
                }
            }
            if ($begin) {
                $sql .= " user.realname LIKE '". db_es($begin) ."%' OR user.user_name LIKE '". db_es($begin) ."' OR user.email LIKE '". db_es($begin) ."%' ";
            }
            $sql .= " ) ";
        }
        $sql .= "ORDER BY ". ($order_by == 'name' ? (user_get_preference("username_display") > 1 ? 'realname' : 'user_name') : 'email') ." ". $asc ."
                LIMIT ". db_ei($offset) .", ". db_ei($number_per_page);
        $res = db_query($sql);
        $res2 = db_query('SELECT FOUND_ROWS() as nb');
        $num_total_rows = db_result($res2, 0, 'nb');
        display_user_result_table($res);
        
        //Jump to page
        $nb_of_pages = ceil($num_total_rows / $number_per_page);
        $current_page = round($offset / $number_per_page);
        echo '<div style="font-family:Verdana">Page: ';
        $width = 10;
        for ($i = 0 ; $i < $nb_of_pages ; ++$i) {
            if ($i == 0 || $i == $nb_of_pages - 1 || ($current_page - $width / 2 <= $i && $i <= $width / 2 + $current_page)) {
                echo '<a href="?'.
                    'group_id='. (int)$group_id .
                    '&amp;ugroup_id='. (int)$ugroup_id .
                    '&amp;offset='. (int)($i * $number_per_page) .
                    '&amp;number_per_page='. (int)$number_per_page .
                    '&amp;order_by='. urlencode($order_by) .
                    '&amp;asc='. urlencode($asc) .
                    '&amp;search='. urlencode($search) .
                    '&amp;begin='. urlencode($begin) .
                    '">';
                if ($i == $current_page) {
                    echo '<b>'. ($i + 1) .'</b>';
                } else {
                    echo $i + 1;
                }
                echo '</a>&nbsp;';
            } else if ($current_page - $width / 2 - 1 == $i || $current_page + $width / 2 + 1 == $i) {
                echo '...&nbsp;';
            }
        }
        echo '</div>';
        
        echo '</td><td>';
        $sql_members = "SELECT user_id FROM ugroup_user WHERE ugroup_id = ". db_ei($ugroup_id);
        $res_members = db_query($sql_members);
        if (db_numrows($res_members)>0) {
            echo '<h3>'. 'Members' .'</h3>';
            echo '<table>';
            $i = 0;
            $hp = CodeX_HTMLPurifier::instance();
            while ($data = db_fetch_array($res_members)) {
                echo '<tr class="'. html_get_alt_row_color(++$i) .'">';
                echo '<td>'. user_get_name_display_from_id($data['user_id']) .'</td>';
                echo '<td>';
                echo '<input type="image" src="'. util_get_dir_image_theme() .'/ic/group_delete.png" onclick="return confirm(\''.  $hp->purify(addslashes('Remove '. user_get_name_display_from_id($data['user_id']) .' from '. $ugroup_name .'?'), CODEX_PURIFIER_CONVERT_HTML)  .'\');" name="user_id" value="'. $data['user_id'] .'" />';
                echo '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        echo '</td></tr></table>';
        
        echo '</form>';
        echo '<p><a href="/project/admin/editugroup.php?group_id='. $group_id .'&amp;ugroup_id='. $ugroup_id .'&amp;func=edit">&laquo; Go back to the ugroup edition</a></p>';
        $GLOBALS['HTML']->footer(array());
    } else {
        $GLOBALS['Response']->addFeedback('error', $Language->getText('project_admin_editugroup','ug_not_found',array($ugroup_id,db_error())));
        $GLOBALS['Response']->redirect('/project/admin/ugroup.php?group_id='. $group_id);
    }
} else {
    $GLOBALS['Response']->addFeedback('error', $GLOBALS['Language']->getText('global', 'missing_parameters'));
    $GLOBALS['Response']->redirect('/project/admin/ugroup.php?group_id='. $group_id);
}

?>
