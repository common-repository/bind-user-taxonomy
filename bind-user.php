<?php
/*
Plugin Name: Bind user to taxonomy
Plugin URI: 
Description: Adds a control panel which the admin can use to restrict posts by selected users to a selected taxonomy. Restricted users won't view the taxonomy selection panel in edit screens.
Version: 0.3
Author: lucdecri, Choan C. Gálvez
*/

/*  
    Copyright 2006  Choan C. Gálvez  (email: choan.galvez@gmail.com)
    
    Heavy modified by lucdecri

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/* 
    Changelog:
    - 0.2a (2008-03-23)
      - Tentatively updated to work on WP2.3. Untested, not ready for production. Really.
    - 0.2b (2008-03-24)
      - Fixed JS Errors
      - Fixed field names
      - It works!
   - 0.3 (2012-08-22)
      - bind multiple terms to user
      - hide denied term in list
      - choice taxonomy to bind
      - Fixed some bug 
*/


function butc_categorySavePre($in) {
	$cat = butc_getCategory();
	if ($cat) {
		return array($cat);
	}
	return $in;
}

// ritorna le tassonomie assegnate all'utente corrente
function butc_getCategory() {
	global $user_ID;
	$opts = get_option("binduser_terms");
	$keys = array_keys($opts);
	$sid = (string)$user_ID;
	if (in_array($sid, $keys)) {
		return $opts[$sid];
	}
	return false;
}

function butc_removeCategorySelection($page) {
	return preg_replace('#<fieldset id="categorydiv".*?</fieldset>#sim', '', $page);
}

function butc_adminHead($in) {
	global $user_level;
	get_currentuserinfo();
	$cat = butc_getCategory();
	if ($cat && $user_level < 10) {
		if(
			preg_match('#/wp-admin/post\.php#', $_SERVER['REQUEST_URI'])
			|| preg_match('#/wp-admin/post-new\.php#', $_SERVER['REQUEST_URI'])
		) {
			ob_start(butc_removeCategorySelection);
		}
	}
	return $in;
}

function butc_menu() {
	add_submenu_page('users.php','Bind User to category',
                     'Bind User',
                     'edit_plugins', 'butc', "butc_form");
 }

function butc_form() {
	global $wpdb;
	
	if (isset($_POST['info_update'])) {
		
		$updated = butc_saveForm($_POST);
		if ($updated) {
			echo '<div class="updated"><p><strong>' . __('Binding successful.', 'bindusertocat') .'</strong></p></div>';
		} else {
			echo '<div class="error"><p><strong>' . __('Error while saving binding.', 'bindusertocat') .'</strong></p></div>';
		}
	}
	$binding_taxonomy = get_option('binduser_taxonomy');
		
	echo '<div class="wrap"><form method="post" action="">';
	echo '<h2>Bind user to taxonomy settings</h2>';
	echo '<label for="bind_taxonomy">Taxonomy to bind </label>';
	$taxs = get_taxonomies('','objects');
	echo butc_select('bind_taxonomy', $taxs, 'name','label', $binding_taxonomy);
	echo '<br />';
	$userids = $wpdb->get_col("SELECT ID FROM $wpdb->users;");
	$users = array();
	foreach ($userids as $userid) {
		$tmp_user = new WP_User($userid);
		if ($tmp_user->wp_user_level > 7) continue;
		$users[$userid] = $tmp_user;
	}

  $wp23 = butc_wp23orbetter();

  if ($wp23) {
  	$cats = $wpdb->get_results("SELECT * FROM $wpdb->terms JOIN $wpdb->term_taxonomy USING (term_id) WHERE taxonomy='$binding_taxonomy' ORDER BY name");
  }
  else {
    $cats = $wpdb->get_results("SELECT * FROM $wpdb->categories ORDER BY cat_name");
  }

	$opts = get_option("binduser_terms");

	$t = "<tr><td>%s</td><td>%s</td></tr>";

	echo "<table id='bindusertocat'>";

  $field = $wp23 ? 'term_id' : 'cat_ID';
  $name = $wp23 ? 'name' : 'cat_name';

	foreach ($opts as $k => $v) {
		foreach ($v as $n => $s)
			printf($t, butc_select('user[]', $users, 'ID', 'user_login', $k), butc_select('cat[]', $cats, $field, $name, $s));
	}

	printf($t, butc_select('user[]', $users, 'ID', 'user_login'), butc_select('cat[]', $cats, $field, $name));

	echo "</table>";

	echo '<div class="submit"><input type="submit" name="info_update" value="' . __('Update settings', 'bindusertocat') . '" /></div></form></div>';
}

function butc_select($n, $a = array(), $v, $t, $s = '') {
	$h = '<select name="' . $n . '">';
	$h .= '<option value=""' . ($s === "" ? ' selected="selected"' : '') . '> -- </option>';
	foreach ($a as $it) {
		$h .= '<option value="' . $it->$v . '"' . ($it->$v == $s ? ' selected="selected"' : '') . '>' . $it->$t . '</option>';
	}
	$h .= '</select>';
	return $h;
}

function butc_saveForm() {
	$len = count($_POST["user"]);
	$opts=array();
	for ($i = 0; $i < $len; $i++) {
		echo $_POST["user"][$i].'-'.$_POST["cat"][$i].'<br />';
		if ($_POST["user"][$i] && $_POST["cat"][$i]) {
			$opts[$_POST["user"][$i]][] = (int)$_POST["cat"][$i];
			
		}
	}
	update_option("binduser_terms", $opts);
	update_option("binduser_taxonomy",$_POST["bind_taxonomy"]);
	return true;
}

function butc_script() {
	if (!isset($_GET['page']) || !$_GET['page'] == "bind-user.php") return;
	echo "<script type='text/javascript'>\n";
	readfile(dirname(__FILE__) . "/bind-user.js");
	echo "\n</script>";
}

function butc_wp23orbetter() {
	static $ret = null;
	if (isset($ret)) {
		return $ret;
	}
	$version = get_bloginfo('version');
	$parts = explode('.', $version);
	if ((int)$parts[0] > 2) {
		$ret = true;
		return $ret;
	}
	if ((int)$parts[0] == 2) {
		$ret = ((int)$parts[1] >= 3);
		return $ret;
	}
	$ret = false;
	return $ret;
}

function butc_hideposts($posts){
    //l'amministratore può vedere sempre tutto
    if (current_user_can('administrator')) return $posts;  
    $binding_taxonomy = get_option('binduser_taxonomy');
    $bind = butc_getCategory();
    if ($bind==false) return array();
    $visible = array();
    foreach($posts as $post_id => $post) {
	$terms=get_the_terms( $post->ID, $binding_taxonomy);
	if ($terms==false) {
		// non supporta quella taxonomia o non ha binding, lo visualizzo
		$visible[] =$post;
	} else {
		// support la taxonomia, lo filtro
		$tax=array_keys($terms);
		foreach($bind as $k => $v) 
			if (in_array($v,$tax)) $visible[] = $post;
	}
    }
    return $visible;

}

function butc_hidetaxonomy($cache,$taxonomies,$args) {

	// l'amministratore può vedere tutte le categorie
	if (current_user_can('administrator')) return $cache;
        $binding_taxonomy = get_option('binduser_taxonomy');
	// non è la categoria su cui faccio il binding
	if ($taxonomies[0]!=$binding_taxonomy) return $cache;
	
	// filtro
	$bind = butc_getCategory();
	if ($bind==false) return array();
	$new_cache=array();
	foreach($cache as $k=>$v) 
		if (in_array($v->term_id,$bind)) $new_cache[]=$v;
	return $new_cache;
}


add_option("binduser_terms", array(), "", false);
add_option("binduser_taxonomy", 'cat', "", false);

add_action('admin_menu', "butc_menu");
add_filter("category_save_pre", "butc_categorySavePre");
add_action("admin_head", "butc_adminHead");
add_action("admin_head", "butc_script");
add_filter("the_posts","butc_hideposts");
add_filter('get_terms', 'butc_hidetaxonomy',1,3);

