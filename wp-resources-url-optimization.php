<?php
/**
 * @package wp-resources-url-optimization
 */
/*
Plugin Name: WP Resources URL Optimization
Plugin URI: https://www.litefeel.com/wp-resources-url-optimization/
Description: WP Resources URL Optimization is a Wordpress plugin optimized browser cache, it will greatly enhance the website page display speed and reduce the pressure of the server to handle static files.Default wp added after the static files the query string to ensure that the static files are modified immediately after the performance to the browser (front-end), this way there is a drawback: the browser will request the server regardless of whether the file is modified,If the file has been modified it will download the new file, if the file has not been modified http status code 304 is returned to inform the browser reads the local cache. The goal of the plugin: do not have to re-initiate the request to the server, and directly read the browser cache when the file has not been modified.
Version: 1.6
Author: litefeel
Author URI: https://www.litefeel.com/
Text Domain: wp-resources-url-optimization

Copyright (c) 2011
Released under the GPL license
http://www.gnu.org/licenses/gpl.txt
*/

if (!class_exists('WP_Resources_URL_Optimization')) {
	include('lite3-wp-plugin-base.php');
	class WP_Resources_URL_Optimization extends LITE3_WP_Plugin_Base {

		var $wwwurl  = '';
		var $wwwpath = '';
		var $abs_wpurl = '';
		var $wwwurl_len = 0;
		
		var $abs_resources_path = '';
		var $resources_path = '';
		var $resources_url = '';
		
		function __construct() {
			parent::__construct('1.5.0', 'wpruo_option');
			add_filter('init', array(&$this,'wpruo_init'));
		}
		public function get_default_options(){
			$options = array();
			$options['only_remove_query_string'] = true;
			return $options;
		}
		
		public function wpruo_init() {
			if(is_admin()) {
				load_plugin_textdomain( 'wpresourcesurloptimization' );
				add_action('admin_menu', array($this, 'add'));
				add_filter( 'plugin_action_links', array($this, 'plugin_action_links'), 10, 2 );
			}else{
				add_filter('script_loader_src', array(&$this, 'optimize_style_script_url'), 1000);
				add_filter('style_loader_src', array(&$this, 'optimize_style_script_url'), 1000);
			}
			
			$wpurl = get_bloginfo('wpurl');
			if($wpurl[strlen($wpurl)-1] != '/') {
				$wpurl .= '/';
			}
			$pos = strpos($wpurl, '/', 8);
			$this->wwwurl_len = $pos + 1;
			$this->wwwurl = substr($wpurl, 0, $this->wwwurl_len);
			$this->abs_wpurl = substr($wpurl, $this->wwwurl_len);
			$this->wwwpath = str_replace('\\', '/', ABSPATH);
			$this->wwwpath = substr($this->wwwpath, 0, strlen($this->wwwpath) - strlen($this->abs_wpurl));
			$this->abs_resources_path = $this->abs_wpurl . 'wp-content/wp-resources-back/';
			$this->resources_path = $this->wwwpath . $this->abs_resources_path;
			$this->resources_url = $this->wwwurl . $this->abs_resources_path;
			
			$this->check_version();
		}
		
		public function optimize_style_script_url($src) {
			//echo "src=$src <br/>";
			if(substr($src, 0, $this->wwwurl_len) != $this->wwwurl) {
				return $src;
			}

			$options = $this->get_options();
			if($options['only_remove_query_string']) {
				return $this->ignore_query_string($src);
			}
			$abs_src = substr($this->ignore_query_string($src), $this->wwwurl_len);
			$source_path = $this->wwwpath . $abs_src;
			//echo "source_path=$source_path <br/>";
			if(!file_exists($source_path))  return $src;
			
			$mtime = filemtime($source_path);
			if(FALSE === $mtime) return $src;
			
			$mtime_path = $this->resources_path . $mtime . '/';
			$to_path =  $mtime_path . $abs_src;
			//echo "to_path = $to_path <br/>";
			if(!file_exists($to_path)) {
				if(substr($to_path, strlen($to_path) - 4) === '.css') {
					if(!$this->csssprit_copy($source_path, $abs_src, $mtime_path)) {
						//echo "false csssprite_copy<br/>";
						return $src;
					}
				}
				if(!$this->file_copy($source_path, $to_path))  return $src;
			}
			//$src = str_replace(get_home_url(null, '/'), 'http://static.litefeel.com/', $src);
			//echo "false file_copy<br/>";
			return $this->resources_url . $mtime . '/' . $abs_src;
		}
		
		public function csssprit_copy(&$source_path, &$abs_source_path, &$to_dir) {
			$content = file_get_contents($source_path);
			$result = preg_match_all('/url\([\'"]?([^\)]+[\'"]?)\)/',  $content, $out, PREG_PATTERN_ORDER);
			if($result === FALSE) return FALSE;
			if($result === 0) return TRUE;
			
			//print_r($out[1]);
			$arr = $out[1];
			$abs_source_dir = dirname($abs_source_path) . '/';
			foreach ($arr as $file) {
				if(strpos($file, 'http://') === 0 || strpos($file, 'https://') === 0 || strpos($file, '/') === 0) {
					continue;
				}
				$file = $this->ignore_query_string($file);
				$abs_from = $this->absolute_path($abs_source_dir . $file);
				if(!$abs_from) continue;
				$from = $this->wwwpath . $abs_from;
				$to = $to_dir . $abs_from;
				//echo "copy css sprite img :$from -> $to<br/>";
				if(!$this->file_copy($from, $to))  return FALSE;
			}
			return TRUE;
		}
		
		public function file_copy(&$from, &$to) {
			if(!file_exists($from)) return TRUE;
			if(!is_file($from)) return TRUE;
			//echo "copy file $from<br/>";
			$to_dir = dirname($to) ;
			if(!file_exists($to_dir)) {
				if(!mkdir($to_dir, 0755, true)) return FALSE;
			}
			return copy($from, $to);
		}
		
		public function img_copy(&$from, &$to) {
			if(!file_exists($from)) return TRUE;
			$to_dir = dirname($to) ;
			if(!file_exists($to_dir)) {
				if(!mkdir($to_dir, 0755, true)) return FALSE;
			}
			// 测试文件是否是同一个,通常修改一个图片文件后文件大小会改变.
			// 不能检测文件修改日期,因为mtime目录里的文件修改日期一定不等于文件修改日期
			if(file_exists($to) && filesize($from) == filesize($to)) {
				return TRUE;
			}
			return copy($from, $to);
		}

		public function absolute_path($path) {
			$list = explode('/', $path);
			$len = count($list);
			for($i = 0; $i < $len; $i++) {
				$del = 0;
				if($list[$i] === '' || $list[$i] === '.') $del = 1;
				else if($list[$i] === '..') $del = 2;
				if($del != 0) {
					if($i < $del - 1) return false;
					$i -= $del;
					$len -= $del;
					array_splice($list, $i + 1, $del);
				}
			}
			$path = implode('/', $list);
			return $path;
		}
		
		public function ignore_query_string($src) {
			$pos = strpos($src, '?');
			if($pos !== FALSE) {
				$src = substr($src, 0, $pos);
			}
			return $src;
		}
		
		public function check_version() {
			if(!file_exists($this->resources_path)) {
				mkdir($this->resources_path, 0755, TRUE);
			}
			$file = $this->resources_path . $this->version;
			if(!file_exists($file)) {
				$this->del_tree($this->resources_path, TRUE);
				file_put_contents($file, $this->version);
			}
		}
		
		public function del_tree($dir, $ignore_this = FALSE) {
			$files = array_diff(scandir($dir), array('.','..'));
			foreach ($files as $file) {
				$tmp = $dir . '/' . $file;
				(is_dir($tmp)) ? $this->del_tree($tmp) : unlink($tmp);
			}
			if($ignore_this) return TRUE;
			return rmdir($dir);
		}

		public function clear_cache() {
			if(file_exists($this->resources_path)) {
				$this->del_tree($this->resources_path, TRUE);
			}
		}

		public function plugin_action_links( $links, $file ) {
			if ( $file != plugin_basename( __FILE__ )) return $links;

			$settings_link = '<a href="options-general.php?page=wp-resources-url-optimization/wp-resources-url-optimization.php">' . __( 'Settings', 'wpresourcesurloptimization' ) . '</a>';
			array_push( $links, $settings_link );
			return $links;
		}

		public function add() {
			if(isset($_POST['wpruo_save'])) {
				$options = $this->get_options();

				// set cache file
				if(!$_POST['only_remove_query_string']) {
					$options['only_remove_query_string'] = (bool)false;
				} else {
					$options['only_remove_query_string'] = (bool)true;
				}
				$this->update_options($options);
			} elseif(isset($_POST['wpruo_reset'])) {
				$this->reset_options();
			} elseif (isset($_POST['wpruo_clear_cache'])) {
				$this->clear_cache();
			}

			add_options_page('WP Resources URL Optimization', 'WP Resources URL Optimization', 10, __FILE__, array($this, 'display'));
		}

		public function display() {
		$options = $this->get_options();
?>

<div class="wrap">
	<div class="icon32" id="icon-options-general"><br /></div>
	<h2><?php _e('WP Resources URL Optimization Options', 'wpresourcesurloptimization'); ?></h2>

	<div id="poststuff" class="has-right-sidebar">
		<div class="inner-sidebar">
			<div id="donate" class="postbox" style="border:2px solid #080;">
				<h3 class="hndle" style="color:#080;cursor:default;"><?php _e('Donation', 'wpresourcesurloptimization'); ?></h3>
				<div class="inside">
					<p><?php _e('If you like this plugin, please donate to support development and maintenance!', 'wpresourcesurloptimization'); ?>
					<br /><br /><strong><a href="https://me.alipay.com/lite3" target="_blank"><?php _e('Donate by alipay', 'wpresourcesurloptimization'); ?></a></strong><style>#donate form{display:none;}</style>
					</p>
				</div>
			</div>

			<div class="postbox">
				<h3 class="hndle" style="cursor:default;"><?php _e('About Author', 'wpresourcesurloptimization'); ?></h3>
				<div class="inside">
					<ul>
						<li><a href="http://www.litefeel.com/" target="_blank"><?php _e('Author Blog', 'wpresourcesurloptimization'); ?></a></li>
						<li><a href="http://www.litefeel.com/plugins/" target="_blank"><?php _e('More Plugins', 'wpresourcesurloptimization'); ?></a></li>
					</ul>
				</div>					
			</div>
		</div>

		<div id="post-body">
			<div id="post-body-content">

<form action="#" method="POST" enctype="multipart/form-data" name="wp-resources-url-optimization_form">
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row"><?php _e('Only remove query string of URL', 'wpresourcesurloptimization'); ?></th>
					<td>
						<label>
							<input name="only_remove_query_string" type="checkbox" <?php if($options['only_remove_query_string']) echo 'checked="checked"'; ?> />
							 <?php _e('Only remove query string of URL, if not, Cache js, css and css sprite file to resource directory.', 'wpresourcesurloptimization'); ?>
						</label>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
		<input class="button-primary" type="submit" name="wpruo_save" value="<?php _e('Update Options', 'wpresourcesurloptimization'); ?>" />
		<input class="button-primary" type="submit" name="wpruo_reset" value="<?php _e('Reset Settings to Defaults', 'wpresourcesurloptimization'); ?>" />
		<input class="button-primary" type="submit" name="wpruo_clear_cache" value="<?php _e('Clear cache directory', 'wpresourcesurloptimization'); ?>" />
		</p>
</form>
			</div>
		</div>
	</div>
</div>

<?php
		}
	}
	$wp_resources_URL_optimization = new WP_Resources_URL_Optimization();
}

?>
