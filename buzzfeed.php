<?php

/*
Plugin Name: Socialvoom Buzz Feed
Plugin URI: http://socialvoom.com/developer/
Description: Displays the Socialvoom Buzz Feed within your posts. This plugin grabs your post category and uses that to determine what feed results to show. For best results, use multiple categories throughout your blog. You are able to position the feed above or below your posts from within the Buzz Feed menu located just under the Settings menu.

Version: 1.0.3
Author: Socialvoom
Author URI: http://socialvoom.com/
*/

class svbf {

	function svbf() {
		$this->cookie = '';
	}

	function install() {
		global $wpdb;

		$charset_collate = '';
		if ( version_compare(mysql_get_server_info(), '4.1.0', '>=') ) {
			if (!empty($wpdb->charset)) {
				$charset_collate .= sprintf(' DEFAULT CHARACTER SET %s', $wpdb->charset);
			}
			if (!empty($wpdb->collate)) {
				$charset_collate .= ' COLLATE $wpdb->collate';
			}
		}
		$sql_post = 'CREATE TABLE `%s_post` (
					 `post_id` INT(11) NOT NULL PRIMARY KEY,
					 `avg_api` DOUBLE(3,2) NOT NULL,
					 `count` INT(6) NOT NULL
					 )%s';
		$sql_api = 'CREATE TABLE `%s_api` (
					 `post_id` INT(11) NOT NULL,
					 `api` INT(1) NOT NULL,
					 `ip_address` CHAR(15) NOT NULL,
					 `cookie` CHAR(32) NOT NULL,
					 `user_id` INT(11),
					 INDEX  (`post_id`)
					 )%s';
		$wpdb->query(sprintf($sql_post, $wpdb->svbf, $charset_collate));
		$wpdb->query(sprintf($sql_api, $wpdb->svbf, $charset_collate));

		update_option('svbf_max_apis_ip', '25');
	}

	function avg_api($post_id) {
		global $wpdb;
		$starting_avg = '3.00';

		$post = $wpdb->get_row(
			sprintf('SELECT avg_api from %s_post WHERE post_id = \'%s\'',
				$wpdb->svbf,
				$wpdb->escape($post_id)
		));

		if (isset($post->avg_api) and !empty($post->avg_api)) {
			return $post->avg_api;
		}

		$wpdb->query(
			sprintf('INSERT INTO %s_post (post_id, avg_api, count) VALUES (\'%s\',\'%s\',\'1\')',
					$wpdb->svbf,
					$wpdb->escape($post_id),
					$starting_avg
		));

		return $starting_avg;
	}

	function get_api($post_id, $user_id, $ip_address, $cookie) {
		global $wpdb;

		$post = $wpdb->get_row(
			sprintf('SELECT api from %s_api WHERE post_id = \'%s\' AND ((user_id = \'%s\' AND user_id != 0) OR cookie = \'%s\')',
				$wpdb->svbf,
				$wpdb->escape($post_id),
				$wpdb->escape($user_id),
				$wpdb->escape($cookie)));

		if (isset($post->api)) {
			return (int)$post->api;
		}

		return 0;
	}

	function delete_post($post_id) {
		global $wpdb;

		$wpdb->query(
			sprintf('DELETE FROM %s_post WHERE post_id = \'%s\'',
				$wpdb->svbf,
				$wpdb->escape($post_id)
		));
	}

	function display_svapi($post_id, $user_id, $ip_address, $cookie) {
		$simple_svapi = (int)$svapi = $this->avg_api($post_id);
		$user_api = (int)$this->get_api($post_id, $user_id, $ip_address, $cookie);
		$status_info = $status = '';
		$hide_more_info = get_option('svbf_hide_more_info');
		$hide_more_info = empty($hide_more_info) ? false : $hide_more_info;

		if ($user_api > 0) {
			$status_info = '';
			$status = ' apid';
		} ?>

<script language = 'javascript'>
var smSearchPhrase = '<?php 
$category = get_the_category(); 
if($category[0]){
echo ''.$category[0]->cat_name.'';
}
?>';
var smTitle = 'More "<?php 
$category = get_the_category(); 
if($category[0]){
echo ''.$category[0]->cat_name.'';
}
?>" buzz.';
var smItemsPerPage = 7;
var smShowUserImages = true;
var smFontSize = 11;
var smWidgetHeight = 500;
</script>

<? $html = '<script type="text/javascript" language="javascript" src="http://apipublic.socialvoom.com/buzz.js"></script>';

		return $html;
	}

	function to_many_ip_apis($post_id, $ip_address) {
		global $wpdb;

		$max_apis_ip = get_option('svbf_max_apis_ip');
		$max_apis_ip = (int)$max_apis_ip > 0 ? $max_apis_ip : '25';

		$select = $wpdb->get_row(
			sprintf('SELECT count(ip_address) AS count FROM %s_api
				WHERE post_id = \'%s\' AND ip_address = \'%s\'',
				$wpdb->svbf,
				$wpdb->escape($post_id),
				$wpdb->escape($ip_address)
		));

		return $select->count > $max_apis_ip;
	}

	function set_api($post_id, $api, $ip_address, $user_id, $cookie) {
		global $wpdb;

		if (is_numeric($api) and is_numeric($post_id) and !$this->to_many_ip_apis($post_id, $ip_address)) {
			$api = (int)$api;
			if (!($api >= 0 and $api <= 5)) {
				return false;
			}
		} else {
			return false;
		}

		$wpdb->query(
			sprintf('DELETE FROM %s_api WHERE post_id = \'%s\' AND ((user_id = \'%s\' AND user_id != 0) OR cookie = \'%s\')',
					$wpdb->svbf,
					$wpdb->escape($post_id),
					$wpdb->escape($user_id),
					$wpdb->escape($cookie)
		));
		if ($api > 0) {
			$wpdb->query(
				sprintf('INSERT INTO %s_api
						 (post_id, api, ip_address, user_id, cookie)
						 VALUES (\'%s\', \'%s\', \'%s\', \'%s\', \'%s\')',
						$wpdb->svbf,
						$wpdb->escape($post_id),
						$wpdb->escape($api),
						$wpdb->escape($ip_address),
						$wpdb->escape($user_id),
						$wpdb->escape($cookie)
			));
		}
		$apis = $wpdb->get_results(
			sprintf('SELECT api from %s_api WHERE post_id = \'%s\'',
					$wpdb->svbf,
					$wpdb->escape($post_id)
		));

		$count = $api_total = 0;

		foreach ($apis as $api) {
			$api_total += (int)$api->api;
			$count++;
		}

		// Add initial 3-star api
		$count++;
		$api_total = $api_total + 3;

		$avg_api = (double)$api_total / (double)$count;
		$avg_api = $avg_api > 0 ? round($avg_api, 2) : 3;
		$avg_api = (double)$avg_api;

		$wpdb->query(
			sprintf('UPDATE %s_post SET avg_api = \'%s\', count = \'%s\' WHERE post_id = \'%s\'',
					$wpdb->svbf,
					$wpdb->escape($avg_api),
					$wpdb->escape($count),
					$wpdb->escape($post_id)
		));

		// post id, apir's user id, user's api (0-5), sum of all apis, total number of apis
		do_action('svbf_api', $post_id, $user_id, $api, $total_api, $count);
	}
}

function svbf_init() {
	global $wpdb, $svbf;

	$svbf = new svbf;
	$wpdb->svbf = sprintf('%ssvbf', $wpdb->prefix);

	if (!isset($_COOKIE['wordpress_svbf'])) {
		$svbf->cookie = md5(sprintf('svbf%s%s', time(), $_SERVER['REMOTE_ADDR']));
		setcookie('wordpress_svbf', $svbf->cookie, time()+60*60*24*360, COOKIEPATH);
	} else {
		$svbf->cookie = $_COOKIE['wordpress_svbf'];
	}

	if (isset($_GET['activate']) and $_GET['activate'] == 'true') {
		$tables = $wpdb->get_col('SHOW TABLES');
		if (!in_array($wpdb->svbf.'_post', $tables)) {
			$svbf->install();
		}
	}
}
add_action('init', 'svbf_init');

function svbf_the_content($content = '') {
	global $wpdb, $post, $userdata, $svbf;

	if(!is_feed() and !is_trackback() and !is_page()) {
		$display_svapi = $svbf->display_svapi($post->ID, $userdata->ID, $_SERVER['REMOTE_ADDR'], $_COOKIE['wordpress_svbf']);

		$top_or_bottom = get_option('svbf_top_or_bottom');
		$top_or_bottom = empty($top_or_bottom) ? 'bottom' : $top_or_bottom;
		
		if($top_or_bottom == 'top') {
			return sprintf('%s%s', $display_svapi, $content);
		}
		else {
			return sprintf('%s%s', $content, $display_svapi);
		}		
	}
	return $content;
}
add_action('the_content', 'svbf_the_content');

function svbf_delete_post($post_id) {
	global $svbf;
	$svbf->delete_post($post_id);
}
add_action('delete_post', 'svbf_delete_post');

function svbf_request_handler() {
	global $wpdb, $userdata, $svbf, $wp_version;

	if (!empty($_GET['ak_action'])) {
		switch($_GET['ak_action']) {
			case 'svbf_api':
				if (isset($_POST['post_id']) and isset($_POST['api'])) {
					$svbf->set_api($_POST['post_id'], $_POST['api'], $_SERVER['REMOTE_ADDR'], $userdata->ID, $_COOKIE

['wordpress_svbf']);
				}
				die();
				break;
			case 'svbf_api_avg':
				if (isset($_POST['post_id'])) {
					echo $svbf->avg_api($_POST['post_id']);
				}
				die();
				break;
			case 'svbf_css':
				header('Content-type: text/css');
?>
div.svbf { padding-top: 10px; }
<?php
				die();
				break;
?>


<?php
				die();
				break;
		}
	}
}
add_action('init', 'svbf_request_handler', 10);

function svbf_admin_menu() {
	add_menu_page('Socialvoom Buzz Feed Admin', 'Buzz Feed', 'manage_links', basename(__FILE__), 'svbf_admin');
	add_options_page('Socialvoom Buzz Feed Options', 'Buzz Feed', 'manage_options', basename(__FILE__), 'svbf_options');
}
add_action('admin_menu','svbf_admin_menu');

function svbf_admin() {
	global $wpdb;
	// Gets current page and does basic validation
	$paged = (isset($_GET['svbf_paged']) and is_numeric($_GET['svbf_paged']) and (int)$_GET['svbf_paged'] > 0) ? (int)$_GET

['svbf_paged'] : 1;
	$limit = 50;
	$offset = ($paged - 1) * $limit;

	$post_results = $wpdb->get_results(
		sprintf('SELECT * from %s_post ORDER BY post_id DESC LIMIT %s OFFSET %s',
				$wpdb->svbf,
				$wpdb->escape($limit),
				$wpdb->escape($offset)
	));
	$post_count = $wpdb->get_row(
		sprintf('SELECT count(post_id) as num from %s_post',
				$wpdb->svbf,
				$wpdb->escape($post_id)
	));

	$has_next = $post_count->num > ($offset + $limit);
	$has_previous = $paged > 1;
?>

<?php
}


function svbf_options() {
	$max_apis_ip = get_option('svbf_max_apis_ip');
	$max_apis_ip = $max_apis_ip == '' ? 25 : $max_apis_ip;
	$hide_more_info = get_option('svbf_hide_more_info');
	$hide_more_info = empty($hide_more_info) ? false : $hide_more_info;
	$top_or_bottom = get_option('svbf_top_or_bottom');
	$top_or_bottom = empty($top_or_bottom) ? 'bottom' : $top_or_bottom;

	$caption = 'Save Options';

	if (isset($_POST['action'])) {
		if (isset($_POST['max_apis_ip']) and is_numeric($_POST['max_apis_ip'])) {
			$max_apis_ip = (int)$_POST['max_apis_ip'] > 0 ? $_POST['max_apis_ip'] : $max_apis_ip;
			update_option('svbf_max_apis_ip', $max_apis_ip);
		}
		$hide_more_info = isset($_POST['hide_more_info']);
		update_option('svbf_hide_more_info', $hide_more_info);
		
		if(!empty($_POST['top_or_bottom']) && in_array($_POST['top_or_bottom'], array('top','bottom'))) {
			$top_or_bottom = $_POST['top_or_bottom'];
		}
		else {
			$top_or_bottom = 'bottom';
		}
		update_option('svbf_top_or_bottom', $top_or_bottom);
	}
	$hide_more_info = $hide_more_info ? 'checked="checked" ' : '';
	
	if($top_or_bottom == 'top') {
		$top = ' selected=selected';
		$bottom = '';
	}
	else {
		$top = '';
		$bottom = ' selected=selected';
	}
?>
<div class="wrap">
	<h2>Socialvoom Buzz Feed Options</h2>
	<form action="" method="post">
		<fieldset class="options">
			<legend>General Options</legend>
			<table class="editform" width="100%" cellspacing="2" cellpadding="5">

				<tr>
					<th width="33%" scope="row">Buzz Feed on top or bottom of post:</th>
					<td width="67%"><select name="top_or_bottom"><option value="bottom"<?php echo $bottom; ?>>Bottom</option><option 

value="top"<?php echo $top; ?>>Top</option></select>
				</tr>
			</table>
			<div class="submit"><input type="submit" name="action" value="<?php echo $caption; ?>" /></div>
		</fieldset>
	</form>
</div>
<?php
}

function svbf_head() {
	printf('
		<link rel="stylesheet" type="text/css" href="%s/index.php?ak_action=svbf_css" />
	', get_bloginfo('wpurl'));
?>
<!--[if lt IE 7]>
<style type="text/css">
div.svbf div { float:left; line-height: 20px; }
}
</style>
<![endif]-->
<?php
}
add_action('wp_head', 'svbf_head');

function svbf_foot() {
	printf('
		<script type="text/javascript" src="%s/index.php?ak_action=svbf_js"></script>
	', get_bloginfo('wpurl'));
}
add_action('wp_footer', 'svbf_foot');


if (!function_exists('wp_prototype_before_jquery')) {
	function wp_prototype_before_jquery( $js_array ) {
		if ( false === $jquery = array_search( 'jquery', $js_array ) )
			return $js_array;
	
		if ( false === $prototype = array_search( 'prototype', $js_array ) )
			return $js_array;
	
		if ( $prototype < $jquery )
			return $js_array;
	
		unset($js_array[$prototype]);
	
		array_splice( $js_array, $jquery, 0, 'prototype' );
	
		return $js_array;
	}
	
	add_filter( 'print_scripts_array', 'wp_prototype_before_jquery' );
}
wp_enqueue_script('jquery');

?>