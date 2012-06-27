<?php
/*
Plugin Name: WP-MultiTarget-Uploads-Sync-Tool
Plugin URI: http://www.rainmoe.com/
Description: A WordPress plugin which able to sync attachments to multiple targets, such as FTP, Dropbox and etc.
Author: 小邪.Evlos
Version: 2.0.0
Author URI: http://www.rainmoe.com/
*/

function MUSTtar() {
	return array(
		'Ftp' => array(
			'Info' => array(
				'Username', 'Password'
			),
		),
	);
}
function MUSTaddStyle() {
	wp_enqueue_style('/wp-admin/css/colors-classic.css');
}
function MUSTshowTargets() {
	return 'No Targets';
}

add_action('admin_menu','MUSTadminMenu');
function MUSTadminMenu() {
	$page = add_menu_page('WP-MUST','WP-MUST','administrator',__FILE__,'MUSTpageA');
	add_action('admin_print_styles-'.$page, 'MUSTaddStyle');
	$page = add_submenu_page(__FILE__, 'WP-MUST', 'WP-MUST', 'administrator', __FILE__, 'MUSTpageA');
	$page = add_submenu_page(__FILE__, 'WP-MUST Setting', 'WP-MUST Setting', 'administrator', 'MUSTpageB', 'MUSTpageB');
	add_action('admin_print_styles-'.$page, 'MUSTaddStyle');
}
function MUSTpageA() {
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
			<td><a target="_blank" href="'.$val->guid.'">'.$val->guid.'</a></td><td>'.MUSTshowTargets($val->ID).'</td></tr>';
		}
		?>
	</table>
	</div>
	<?php
}
function MUSTpageB() {
	$targets = MUSTtar();
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
						<?php foreach ($targets['Ftp']['Info'] as $val): ?>
							<th><?php echo $val; ?></th>
						<?php endforeach; ?>
						</thead>
						<tr>
						<?php foreach ($targets['Ftp']['Info'] as $val): ?>
							<td><input type="text" name="<?php echo $val; ?>-<?php echo $count; ?>" /></td>
						<?php endforeach; ?>
						</tr>
					</table></td>
				</tr>
			</table>
		</form>
	</div>
	<div>
		<form>
			<select name="type"><?php foreach ($targets as $key => $val) : ?>
				<option value="<?php echo $key; ?>"><?php echo $key; ?></option>
			<?php endforeach; ?></select>
			<input type="submit" />
		</form>
	</div>
	</div>
	<?php
}
