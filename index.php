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

	public function getOpt() {
		$opt = get_option(self::$name.'_option', '');
		return empty($opt) ? array() : json_decode($opt, true);
	}
	public function refreshOpt() {
		//Notice
	}
	public function putOpt($changed) {
		$res = update_option(self::$name.'_option', json_encode($changed));
		$this->refreshOpt();
		return $res;
	}
	public function getMT() {
		return get_option(self::$name.'_mtarget');
	}
	public function putMT($data) {
		return update_option(self::$name.'_mtarget', $data);
	}
	public function getPM($pid, $key) {
		return get_post_meta($pid, $key, true);
	}
	public function putPM($pid, $key, $data) {
		update_post_meta($pid, $key, $data);
	}
	
	public function singlePS($changed = array()) {
		$opt = $this->getOpt();
		foreach ($opt as $key_ => $val_) {
			$ps[$key_] = 0;
		}
		return self::arrayRewrite($ps, $changed);
	}
	public function writePS($pid, $data = array()) {
		return $this->putPM($pid, self::$name.'_poststatus', json_encode($this->singlePS($data)));
	}
	public function readPS($pid) {
		$ps = $this->getPM($pid, self::$name.'_poststatus');
		if (empty($ps)) {
			$this->writePS($pid);
			return $this->singlePS();
		}
		else {
			return json_decode($ps);
		}
	}
	public static function success($pid, $tid) {
		
	}
	
	public function createOpt() {
		if (!$this->opt = get_option(self::$name.'_option')) {
			update_option(self::$name.'_option', '');
		}
	}
	public function wpInit() {
		add_action('admin_menu', array($this, 'adminMenu'));
	}
	public function addStyle() {
		wp_enqueue_style('/wp-admin/css/colors-classic.css');
	}
	
	public static function readAttachments() {
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
		)
	 * 
	 */
	public static function splitUrl($guid) {
		$regex = '/wp-content\/uploads\/([0-9\/]+)\/(.+)$/i';
		preg_match($regex, $guid, $res);
		return $res;
	}
	
	public function upload($chkRemote = false) {
		$opt = $this->getOpt();
		foreach ($opt as $key => $val) {
			$conn = $val['conn'];
			$attach = self::readAttachments();
			foreach ($attach as $val) {
				$isReady = false;
				//Check ifUploaded
				$ps = self::readPS($val->ID);
				if (!$ps[$key]) $isReady = true;
				//Check remote ifUploaded
				if ($chkRemote) {
					
				}
				//Upload
				if ($isReady) MUST_ftp::upload(self::splitUrl($val->guid));
			}
		}
	}

	public function adminMenu() {
		$page = add_menu_page('WP-MUST', 'WP-MUST', 'administrator', __FILE__, array($this, 'pageList'));
		add_action('admin_print_styles-'.$page, array($this, 'addStyle'));
		$page = add_submenu_page(__FILE__, 'WP-MUST', 'WP-MUST', 'administrator', __FILE__, array($this, 'pageList'));
		$page = add_submenu_page(__FILE__, 'WP-MUST Setting', 'WP-MUST Setting', 'administrator', 'MUSTpageSetting', array($this, 'pageSetting'));
		add_action('admin_print_styles-'.$page, array($this, 'addStyle'));
	}
	public function pageList() {
		if (isset($_POST['do'])&&$_POST['do']=='upload') {
			$this->upload();
		}
		$opt = $this->getOpt();
		?>
		<div style="margin: 4px 15px 0 0;">
		<h2>WP-MultiTarget-Uploads-Sync-Tool</h2>
		<table class="widefat">
			<?php
			$data = self::readAttachments();
			
			echo '<thead><th>ID</th><th>User</th><th>Date</th><th>Title</th><th>Mime</th><th>Guid</th><th>Status</th></thead>';
			foreach ($data as $val) {
				echo '<tr><td>'.$val->ID.'</td><td>'.get_user_meta($val->post_author, 'nickname', true).'</td>
				<td>'.$val->post_date_gmt.'</td><td>'.$val->post_title.'</td><td>'.$val->post_mime_type.'</td>
				<td><a target="_blank" href="'.$val->guid.'">'.$val->guid.'</a></td><td>';
				$ps = $this->readPS($val->ID);
				$count = 0;
				foreach ($ps as $key_ => $val_) {
					if ($count++ != 0) echo ', ';
					echo '<span style="'.(!$val_ ? 'color:red;' : 'color:green;').'">'.$opt[$key_]['name'].'</span>';
				}
				echo '</td></tr>';
			}
			?>
		</table>
		<br />
		<div>
			<form action="" method="POST">
				<input type="hidden" name="do" value="upload" />
				<input type="submit" value="Upload" />
			</form>
		</div>
		<br /><br />
		Notice: <span style="color:#888;">Red means not uploaded, Green means uploaded.</span>
		</div>
		<?php
	}
	public function pageSetting() {
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
			width: 100px;
		}
		</style>
		<div style="margin: 4px 15px 0 0;">
		<h2>WP-MultiTarget-Uploads-Sync-Tool Setting</h2>
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
		<br /><br />
		<div>
			<form action="" method="POST">
				<select name="add_type"><?php foreach (self::$addons as $key => $val) : ?>
					<option value="<?php echo $key; ?>"><?php echo strtoupper($key); ?></option>
				<?php endforeach; ?></select>
				<input type="hidden" name="do" value="add" />
				<input type="submit" value="Add" />
			</form>
		</div>
		<br />
		<div>
			<?php if (!empty($opt)): ?>
				<form action="" method="POST">
					<select name="mtarget"><?php foreach ($opt as $key_ => $val_): ?>
						<option value="<?php echo $key_; ?>"<?php echo $MT == $key_ ? ' selected' : ''; ?>><?php echo $val_['name']; ?></option>
					<?php endforeach; ?></select>
					<input type="hidden" name="do" value="mtarget" />
					<input type="submit" value="MainTarget" />
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
	
}
