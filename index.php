<?php
/*
Plugin Name: WP-MultiTarget-Uploads-Sync-Tool
Plugin URI: http://www.rainmoe.com/
Description: A WordPress plugin which able to sync attachments to multiple targets, such as FTP, Dropbox and etc.
Author: 小邪.Evlos
Version: 2.0.0
Author URI: http://www.rainmoe.com/
*/

MUST::ins();

class MUST {

private static $name = 'MUST';
private static $ins;
private static $addons = array(
	'ftp' => 'MUST_ftp',
);
private $opt;

private function __construct() {
	$this->wpInit();
	$this->updateOpt();
}
public static function ins() {
	if (is_null(self::$ins))
		self::$ins = new self();
	return self::$ins;
}

public static function arrayRewrite($default, $changed) {
	foreach ($default as $key => $val) {
		if (isset($changed[$key])) $default[$key] = $changed[$key];
	}
	return $default;
}
public static function zip($data) {
	return json_encode($data);
}
public static function unzip($data) {
	return json_decode($data, true);
}
public static function singleOpt($data = array()) {
	$opt = array(
		'name' => '',
		'type' => '',
		'conn' => '',
	);
	return self::arrayRewrite($opt, $changed);
}

public function getOpt() {
	$opt = get_option(self::$name.'_option');
	return empty($opt) ? array() : $opt;
}
public function putOpt($data) {
	return update_option(self::$name.'_option', $data);
}

public function updateOpt() {
	if (!$this->opt = get_option(self::$name.'_option')) {
		$this->opt = '';
		update_option(self::$name.'_option', '');
	}
}
public function wpInit() {
	add_action('admin_menu', array($this, 'adminMenu'));
}
public function addStyle() {
	wp_enqueue_style('/wp-admin/css/colors-classic.css');
}
public function showTar($id) {
	return 'No Targets';
}

public function adminMenu() {
	$page = add_menu_page('WP-MUST', 'WP-MUST', 'administrator', __FILE__, array($this, 'pageA'));
	add_action('admin_print_styles-'.$page, array($this, 'addStyle'));
	$page = add_submenu_page(__FILE__, 'WP-MUST', 'WP-MUST', 'administrator', __FILE__, array($this, 'pageA'));
	$page = add_submenu_page(__FILE__, 'WP-MUST Setting', 'WP-MUST Setting', 'administrator', 'MUSTpageB', array($this, 'pageB'));
	add_action('admin_print_styles-'.$page, array($this, 'addStyle'));
}
public function pageA() {
	?>
	<div style="margin: 4px 15px 0 0;">
	<h2>WP-MultiTarget-Uploads-Sync-Tool</h2>
	<table class="widefat">
		<?php
		global $wpdb;
		$data = array_reverse($wpdb->get_results("
			SELECT * FROM {$wpdb->posts}
			WHERE post_status = 'inherit' AND post_type = 'attachment'
		"));
		
		echo '<thead><th>ID</th><th>User</th><th>Date</th><th>Title</th><th>Mime</th><th>Guid</th><th>Control</th></thead>';
		foreach ($data as $val) {
			echo '<tr><td>'.$val->ID.'</td><td>'.get_user_meta($val->post_author, 'nickname', true).'</td>
			<td>'.$val->post_date_gmt.'</td><td>'.$val->post_title.'</td><td>'.$val->post_mime_type.'</td>
			<td><a target="_blank" href="'.$val->guid.'">'.$val->guid.'</a></td><td>'.$this->showTar($val->ID).'</td></tr>';
		}
		?>
	</table>
	</div>
	<?php
}
public function pageB() {
	if (isset($_POST['add_type'])) {
		$opt = $this->getOpt();
		$opt[] = self::singleOpt();
		print_r($opt);
	}
	$count = 1;
	?>
	<div style="margin: 4px 15px 0 0;">
	<h2>WP-MultiTarget-Uploads-Sync-Tool Setting</h2>
	<div>
		<form>
			<table class="widefat">
				<thead><th>ID</th><th>Name</th><th>Type</th><th>Connection</th></thead>
				
					<tr id="target-<?php $count; ?>">
						<td><?php echo $count; ?></td><td><input type="text" name="name-<?php echo $count; ?>" /></td>
						<td>Ftp</td>
						<td><table class="widefat">
							<thead>
							<?php foreach (MUST_ftp::$set as $key => $val): ?>
								<th><?php echo $key; ?></th>
							<?php endforeach; ?>
							</thead>
							<tr>
							<?php foreach (MUST_ftp::$set as $key => $val): ?>
								<td><input type="text" name="<?php echo $key; ?>-<?php echo $count; ?>" /></td>
							<?php endforeach; ?>
							</tr>
						</table></td>
					</tr>
				
			</table>
		</form>
	</div>
	<br /><br />
	<div>
		<form action="" method="POST">
			Add New Target:
			<select name="add_type"><?php foreach (self::$addons as $key => $val) : ?>
				<option value="<?php echo $key; ?>"><?php echo $key; ?></option>
			<?php endforeach; ?></select>
			<input type="submit" />
		</form>
	</div>
	</div>
	<?php
}

}

class MUST_ftp {

public static $set = array(
	'Host' => '',
	'Username' => '',
	'Password' => '',
	'Folder' => ''
);

public static function set($data = array()) {
	return MUST::arrayRewrite(self::$set, $data);
}

}
