<?php
/*
Plugin Name: WP-MultiTarget-Uploads-Sync-Tool
Plugin URI: http://www.rainmoe.com/
Description: A WordPress plugin which able to sync attachments to multiple targets, such as FTP, Dropbox and etc.
Author: 小邪.Evlos
Version: 1.0.0
Author URI: http://www.rainmoe.com/
*/

MUST::ins();

class MUST {

	private static $name = 'MUST';
	private static $ins;
	private static $addons = array(
		'ftp' => 'MUST_ftp',
	);

	private function __construct() {
		$this->wpInit();
		$this->createOpt();
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
	public static function singleOpt($changed = array()) {
		$opt = array(
			'name' => '',
			'type' => '',
			'conn' => '',
			'enable' => 0
		);
		return self::arrayRewrite($opt, $changed);
	}

	function getOpt() {
		$opt = get_option(self::$name.'_option', '');
		return empty($opt) ? array() : json_decode($opt, true);
	}
	function refreshOpt() {
		//Notice
	}
	function putOpt($changed) {
		$res = update_option(self::$name.'_option', json_encode($changed));
		$this->refreshOpt();
		return $res;
	}
	function getMT() {
		return get_option(self::$name.'_mtarget');
	}
	function putMT($data) {
		return update_option(self::$name.'_mtarget', $data);
	}
	function getPM($pid, $key) {
		return get_post_meta($pid, self::$name.'_'.$key, true);
	}
	function putPM($pid, $key, $data) {
		update_post_meta($pid, self::$name.'_'.$key, $data);
	}
		
	function createOpt() {
		if (!$this->opt = get_option(self::$name.'_option')) {
			update_option(self::$name.'_option', '');
		}
	}
	function wpInit() {
		add_action('admin_menu', array($this, 'adminMenu'));
	}
	function addStyle() {
		wp_enqueue_style('/wp-admin/css/colors-classic.css');
	}
	
	static function readAttachments() {
		global $wpdb;
		return array_reverse($wpdb->get_results("
			SELECT * FROM {$wpdb->posts}
			WHERE post_status = 'inherit' AND post_type = 'attachment'
		"));
	}
	/*
	 * $res refer to
	 * 
	 * Array
		(
			[0] => wp-content/uploads/2012/06/goodwp.com-15509.jpg
			[1] => 2012
			[2] => 06
			[3] => goodwp.com-15509.jpg
			[4] => C:\path\to\wordpress\wp-content\uploads
		)
	 * 
	 */
	static function splitUrl($guid) {
		$regex = '/wp-content\/uploads\/([0-9]+)\/([0-9]+)\/(.+)$/i';
		preg_match($regex, $guid, $res);
		$dir = wp_upload_dir();
		$res[] = $dir['basedir'];
		return $res;
	}
	public static function linko($pid) {
		global $wpdb;
		return $wpdb->get_var("
			SELECT guid FROM {$wpdb->posts}
			WHERE post_status = 'inherit' AND post_type = 'attachment' AND ID = {$pid}
		");
	}
	function link($pid) {
		$MT = $this->getMT();
		$url = getPM($pid, 'link_'.$MT);
		if (!empty($url)) return $url;
		else {
			return self::linko($pid);
		}
	}
	
	function upload($chkRemote = false) {
		$opt = $this->getOpt();
		foreach ($opt as $key => $val) {
			if ($val['enable']) {
				$conn = $val['conn'];
				$attach = self::readAttachments();
				foreach ($attach as $val) {
					$isReady = false;
					//Check ifUploaded
					$nurl = $this->getPM($val->ID, 'link_'.$key);
					//if (empty($nurl))
						$isReady = true;
					//Check remote ifUploaded
					if ($chkRemote) {
						
					}
					//Upload
					if ($isReady) {
						$nurl = MUST_ftp::upload(self::splitUrl($val->guid), $conn);
						if ($nurl) $this->putPM($val->ID, 'link_'.$key, $nurl);
					}
				}
			}
		}
	}

	function adminMenu() {
		$page = add_menu_page('WP-MUST', 'WP-MUST', 'administrator', __FILE__, array($this, 'pageList'));
		add_action('admin_print_styles-'.$page, array($this, 'addStyle'));
		$page = add_submenu_page(__FILE__, 'WP-MUST', 'WP-MUST', 'administrator', __FILE__, array($this, 'pageList'));
		$page = add_submenu_page(__FILE__, 'WP-MUST Setting', 'WP-MUST Setting', 'administrator', 'MUSTpageSetting', array($this, 'pageSetting'));
		add_action('admin_print_styles-'.$page, array($this, 'addStyle'));
	}
	function pageList() {
		if (isset($_POST['do'])&&$_POST['do']=='upload') {
			$this->upload();
		}
		$opt = $this->getOpt();
		?>
		<div style="margin: 4px 15px 0 0;">
		<h2>WP-MultiTarget-Uploads-Sync-Tool</h2>
		<div>
			<form action="" method="POST" style="display:inline;">
				<input type="hidden" name="do" value="upload" />
				<input type="submit" value="Upload All" />
			</form>
			<form action="" method="POST" style="display:inline;">
				<input type="hidden" name="do" value="update" />
				<input type="submit" value="Update Posts" />
			</form>
		</div>
		<br />
		<table class="widefat">
			<?php
			$data = self::readAttachments();
			
			echo '<thead><th>ID</th><th>User</th><th>Date</th><th>Title</th><th>Mime</th><th>Guid</th>';
			foreach ($opt as $key_ => $val_) echo '<th>'.$val_['name'].'</th>';
			echo '</thead>';
			foreach ($data as $val) {
				$res = self::splitUrl($val->guid);
				echo '<tr><td>'.$val->ID.'</td><td>'.get_user_meta($val->post_author, 'nickname', true).'</td>
				<td>'.$val->post_date_gmt.'</td><td>'.$val->post_title.'</td><td>'.$val->post_mime_type.'</td>
				<td><a target="_blank" href="'.$val->guid.'">'.$res[3].'</a></td>';
				foreach ($opt as $key_ => $val_) {
					echo '<td>';
					$url = $this->getPM($val->ID, 'link_'.$key_);
					if (!$url) echo '<span style="color:grey;">None</span>';
					else echo '<a target="_blank" href="'.$url.'" style="color:green;">OPEN</a> - <a target="_blank" href="#" style="color:green;">UPLOAD</a>';
					echo '</td>';
				}
				echo '</tr>';
			}
			?>
		</table>
		</div>
		<?php
	}
	function pageSetting() {
		$opt = $this->getOpt();
		$MT = $this->getMT();
		if (isset($_POST['do'])) {
			//print_r($_POST);
			if ($_POST['do'] == 'add') {
				$opt[] = self::singleOpt(array('type' => $_POST['add_type']));
				self::putOpt($opt);
			}
			elseif ($_POST['do'] == 'modify') {
				$count = 0;
				while (isset($_POST[$count.'_name'])) {
					$tmp = array();
					foreach ($_POST as $key => $val) {
						$tmp[str_replace($count.'_', '', $key)] = $val;
					}
					$singleSet = MUST_ftp::set($tmp);
					$opt_[$count] = self::singleOpt(array(
						'name' => $_POST[$count.'_name'],
						'type' => $_POST[$count.'_type'],
						'conn' => $singleSet,
						'enable' => (isset($_POST[$count.'_enable']) ? 1 : 0),
					));
					$count++;
				}
				$opt = $opt_;
				self::putOpt($opt);
			}
			elseif ($_POST['do'] == 'mtarget') {
				self::putMT($_POST['mtarget']);
				$MT = $_POST['mtarget'];
			}
		}
		?>
		<style type="text/css">
		.widefat tr td .widefat {
			border-color: #EEE;
		}
		.widefat tr td .widefat thead tr th {
			background-image: none;
			background: #fafafa;
		}
		.widefat tr td .widefat td, .widefat tr td .widefat th {
			border-bottom-color: #eee;
		}
		input[disabled="disabled"] {
			background: #eee;
		}
		.widefat .child input {
			width: 120px;
		}
		</style>
		<div style="margin: 4px 15px 0 0;">
		<h2>WP-MultiTarget-Uploads-Sync-Tool Setting</h2>
		<div>
			<form action="" method="POST" style="display:inline;">
				<select name="add_type"><?php foreach (self::$addons as $key => $val) : ?>
					<option value="<?php echo $key; ?>"><?php echo strtoupper($key); ?></option>
				<?php endforeach; ?></select>
				<input type="hidden" name="do" value="add" />
				<input type="submit" value="Add" />
			</form>
			<?php if (!empty($opt)): ?>
				<form action="" method="POST" style="display:inline;">
					<select name="mtarget"><?php foreach ($opt as $key_ => $val_): ?>
						<option value="<?php echo $key_; ?>"<?php echo $MT == $key_ ? ' selected' : ''; ?>><?php echo $val_['name']; ?></option>
					<?php endforeach; ?></select>
					<input type="hidden" name="do" value="mtarget" />
					<input type="submit" value="Main Target" />
				</form>
			<?php endif; ?>
		</div>
		<br />
		<div>
			<?php if (!empty($opt)): ?>
				<form action="" method="POST">
					<table class="widefat">
						<thead><th>Enable</th></th><th>ID</th><th>Name</th><th>Type</th><th>Connection</th></thead>
						<?php foreach ($opt as $key_ => $val_): ?>
							<tr id="target-<?php echo $key_; ?>">
								<td><input type="checkbox" name="<?php echo $key_; ?>_enable"<?php echo $val_['enable'] ? ' checked' : '';?> /></td>
								<td><?php echo $key_; ?></td>
								<td><input type="text" name="<?php echo $key_; ?>_name" value="<?php echo $val_['name']; ?>" /></td>
								<td><?php echo strtoupper($val_['type']); ?></td>
								<td><table class="widefat child">
									<thead>
									<?php foreach (MUST_ftp::$set as $key => $val): ?>
										<th><?php echo strtoupper($key); ?></th>
									<?php endforeach; ?>
									</thead>
									<tr>
									<?php foreach (MUST_ftp::set($val_['conn']) as $key => $val): ?>
										<td><input type="text" name="<?php echo $key_; ?>_<?php echo $key; ?>" value="<?php echo $val; ?>" /></td>
									<?php endforeach; ?>
									</tr>
									</table>
									<input type="hidden" name="<?php echo $key_; ?>_type" value="<?php echo $val_['type']; ?>" />
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
					<input type="hidden" name="do" value="modify" />
					<br />
					<input type="submit" value="Save" />
				</form>
			<?php endif; ?>
		</div>
		</div>
		<?php
	}

}

class MUST_ftp {

	private static $name = 'ftp';
	public static $set = array(
		'host' => '',
		'username' => '',
		'password' => '',
		'folder' => '',
		'folder_url' => '',
		'port' => 21,
	);

	public static function set($data = array()) {
		return MUST::arrayRewrite(self::$set, $data);
	}
	
	public static function upload($res, $conn) {

		$send_file = $res[4].'/'.$res[1].'/'.$res[2].'/'.$res[3];
		$remote_file = $res[3];

		$ftp_server = $conn['host'];
		$ftp_user_name = $conn['username'];
		$ftp_user_pass = $conn['password'];
		$ftp_dst_dir = $conn['folder'];
		
		$dir_level = array($res[1], $res[2]);
		
		require_once plugin_dir_path(__FILE__).'/lib/ftp_do.php';

		$final = MUST_ftp_do::ins($ftp_server, $ftp_user_name, $ftp_user_pass, $ftp_dst_dir, $dir_level)
		->send($remote_file, $send_file, FTP_BINARY, $dir_level);
		
		$nurl = $conn['folder_url'].$res[1].'/'.$res[2].'/'.$res[3];
		
		if ($final) return $nurl; else return false;

	}
	
}

//Made by Evlos >w< ||
