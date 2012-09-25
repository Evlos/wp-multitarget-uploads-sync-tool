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
	private static $version = '1.0.0';
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
		//add_action('wp_enqueue_scripts', array($this, 'addScripts'));
		//add_action('wp_head', array($this, 'addText2Header'));

		if ($this->getMT()!=''&&$this->urlCurrent()!='')
			add_filter('the_content', array($this, 'addAfterTheContent'));

		add_action('add_attachment', array($this, 'addAfterAttachmentUploadedCompleted'));
	}
	function addScripts() {
		wp_enqueue_script('jquery');
	}
	function addStyle() {
		wp_enqueue_style('/wp-admin/css/colors-classic.css');
	}
	function addAfterTheContent($content) {
		return str_replace($this->urlDefault(), $this->urlCurrent(), $content);
	}
	function addAfterAttachmentUploadedCompleted() {
		$this->upload();
	}
	function addText2Header() {
		//FIX later
		echo '
		<script type="text/javascript">
		jQuery(function($){
			$.one(function(){
					$(".entry-content img").bind("load", function(){
						$(this).hide().attr("src", "");
						self.loaded = true;
					});
			});
		});
		jQuery(document).ready(function($){
			$(".entry-content img").each(function(){
				//$(this).attr("src", $(this).attr("src").replace("'.$this->urlDefault().'", "'.$this->urlCurrent().'"));
				//$(this).css("display", "block");
			});
		});
		</script>';
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

	function isRemoteFileExists($url) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_exec($ch);
		$retcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		return ($retcode==200);
	}
	function urlDefault() {
		return site_url().'/wp-content/uploads/';
	}
	function urlCurrent() {
		$MT = $this->getMT();
		if ($MT=='') return '';
		$opt = $this->getOpt();
		return $opt[$MT]['conn']['folder_url'];
		//print_r($opt[$MT]);
	}
	
	function upload($echo = false) {
		set_time_limit(600);
		$opt = $this->getOpt();
		foreach ($opt as $key => $val) {
			if ($val['enable']) {
				$conn = $val['conn'];
				$attach = self::readAttachments();
				foreach ($attach as $val) {
					$isReady = true;
					//Check remote ifUploaded
					if (function_exists('curl_init')) {
						$rurl = $this->getPM($val->ID, 'link_'.$key);
						if ($rurl!=''&&$this->isRemoteFileExists($rurl)) {
							$isReady = false;
						}
					}
					else {
						if ($echo) echo '* cUrl is not installed, file will be uploaded without exists check ...'."\r\n";
					}
					//Check ifUploaded
						//$nurl = $this->getPM($val->ID, 'link_'.$key);
						//if (empty($nurl)) $isReady = true;
					//Upload
					if ($isReady) {
						$nurl = MUST_ftp::upload(self::splitUrl($val->guid), $conn);
						if ($nurl) $this->putPM($val->ID, 'link_'.$key, $nurl);
						if ($echo) echo '* '.$val->guid.' uploaded to '.$nurl.' ...'."\r\n";
					}
					else {
						if ($echo) echo '* '.$val->guid.' existed in remote ...'."\r\n";
					}
				}
			}
		}
	}

	function adminMenu() {
		$page = add_menu_page('WP-MUST', 'WP-MUST', 'administrator', __FILE__, array($this, 'pageReadMe'));
		add_action('admin_print_styles-'.$page, array($this, 'addStyle'));
		$page = add_submenu_page(__FILE__, 'WP-MUST', 'WP-MUST', 'administrator', __FILE__, array($this, 'pageReadMe'));
		$page = add_submenu_page(__FILE__, 'WP-MUST InMedia', 'WP-MUST InMedia', 'administrator', 'MUSTpageInMedia', array($this, 'pageList'));
		$page = add_submenu_page(__FILE__, 'WP-MUST InFolder', 'WP-MUST InFolder', 'administrator', 'MUSTpageInFolder', array($this, 'pageInFolder'));
		$page = add_submenu_page(__FILE__, 'WP-MUST Setting', 'WP-MUST Setting', 'administrator', 'MUSTpageSetting', array($this, 'pageSetting'));
		add_action('admin_print_styles-'.$page, array($this, 'addStyle'));
	}
	function pageList() {
		$opt = $this->getOpt();
		?>
		<div style="margin: 4px 15px 0 0;">
		<!-- div -->
			<h2>WP-MultiTarget-Uploads-Sync-Tool</h2>
			<div>
				<form action="" method="POST" style="display:inline;">
					<input type="hidden" name="do" value="upload" />
					<input type="submit" value="Upload All" />
				</form>
				<!--<form action="" method="POST" style="display:inline;">
					<input type="hidden" name="do" value="update" />
					<input type="submit" value="Update Posts" />
				</form>-->
			</div>
			<?php
			if (isset($_POST['do'])&&$_POST['do']=='upload') {
				echo '<br /><textarea style="width: 100%; height: 120px;">';
				$this->upload(true);
				echo '</textarea><br />';
			}
			?>
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
						else echo '<a target="_blank" href="'.$url.'" style="color:green;">OPEN</a>';
						// - <a target="_blank" href="#" style="color:green;">UPLOAD</a>
						echo '</td>';
					}
					echo '</tr>';
				}
				?>
			</table>
		<!-- div -->
		</div>
		<?php
	}
	function pageSetting() {
		$opt = $this->getOpt();
		$MT = $this->getMT();
		if (isset($_POST['do'])) {
			$do = $_POST['do'];
			//print_r($_POST);
			if ($do == 'add') {
				$opt[] = self::singleOpt(array('type' => $_POST['add_type']));
				self::putOpt($opt);
			}
			elseif ($do == 'modify') {
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
			elseif ($do == 'mtarget') {
				self::putMT($_POST['mtarget']);
				$MT = $_POST['mtarget'];
			}
			elseif ($do == 'clearmtarget') {
				self::putMT('-1');
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
		input[type="submit"] {
			padding: 3px 12px;
			text-transform: uppercase;
		}
		.inputlong {
			width: 600px;
		}
		</style>
		<div style="margin: 4px 15px 0 0;">
		<!-- div -->
			<h2>WP-MultiTarget-Uploads-Sync-Tool Setting</h2>
			<div>
				<form action="" method="POST" style="display:inline;">
					New Target:
					<select name="add_type"><?php foreach (self::$addons as $key => $val) : ?>
						<option value="<?php echo $key; ?>"><?php echo strtoupper($key); ?></option>
					<?php endforeach; ?></select>
					<input type="hidden" name="do" value="add" />
					<input type="submit" value="Add" />
				</form>
				<?php if (!empty($opt)): ?>
					<form action="" method="POST" style="display:inline;">
						 - Current Target:
						<select name="mtarget"><?php $choosed = false; foreach ($opt as $key_ => $val_): ?>
							<?php if ($MT == $key_) $choosed = true; ?>
							<option value="<?php echo $key_; ?>"<?php echo $MT == $key_ ? ' selected' : ''; ?>><?php echo $val_['name']; ?></option>
						<?php endforeach; ?><?php echo $choosed ? '' : '<option value="-1" selected>-</option>'; ?></select>
						<input type="hidden" name="do" value="mtarget" />
						<input type="submit" value="Set" />
					</form>
				<?php endif; ?>
				<form action="" method="POST" style="display:inline;">
					<input type="hidden" name="do" value="clearmtarget" />
					- <input type="submit" value="Stop url replacement" />
				</form>
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
			<br />
			Default URL: <?php echo $this->urlDefault(); ?>
			<br />
			Current URL: <?php $j = $this->urlCurrent(); echo $j=='' ? 'url will not be replaced.' : $j; ?>
		<!-- div -->
		</div>
		<?php
	}

	function pageReadMe() {
		?>
		<div style="margin: 4px 15px 0 0;">
		<!-- div -->
			<h2>WP-MultiTarget-Uploads-Sync-Tool ReadMe</h2>
			<div>
				<h3>Current version v<?php echo self::$version; ?></h3>
			</div>
			<div>
				<h3>For English Users:</h3>
				<p>This is a WordPress Multiple Targets Sync Tool, which means you are able to add multiple targets with FTP supported (Currently), and sync attachments to these targets. <br />Also, it is possible to use the url of attachments in these targets to show on fontend.</p>
				<p>Steps:</p>
				<ul>
					<li>1. Create an new target.</li>
					<li>2. Fill in connection information in the form displayed afterwards.</li>
					<li>3. Enable this new target to make it syncable, and save it.</li>
					<li>4. (Optional) Find this target at current target option, choose it and set. Which means all url of attachments on fontend will be replaced with new url of attachments in this choosed target.</li>
					<li>5. Switch to WP-MUST InMedia/InFolder, and press upload all. After this, it not necessary to press it again when upload new attachments.</li>
				</ul>
			</div>
			<div>
				<h3>For Chinese Users:</h3>
				<p>这是一个 WordPress 多目标（图床）附件同步工具。你可以添加多个图床，当然目前仅支持 FTP 图床。设置完成后附件就能够被同步到这些图床，并从这些图床调用显示。</p>
				<p>步骤:</p>
				<ul>
					<li>1. 先新建一个 target，即新建同步目标。</li>
					<li>2. 然后在出现的空白表单内填入连接信息，如果是 ftp 则是此 ftp 的连接信息。</li>
					<li>3. Enable 这个 target，即启用这个目标。启用后的目标才会允许被同步，点 Save 保存。</li>
					<li>4. 在 Current Target 处找到这个新建的 target 目标，点击 Set。设置为当前的目标，那么所有原附件地址将被替换为其在此目标的上的地址。</li>
					<li>5. 到文件列表处点 Upload All 即可。以后传的附件会自动被同步。</li>
				</ul>
			</div>
		<!-- div -->
		</div>
		<?php
	}

	function pageInFolder() {
		?>
		<div style="margin: 4px 15px 0 0;">
		<!-- div -->
			<h2>WP-MultiTarget-Uploads-Sync-Tool Files in Uploads Folder</h2>
			<div>
				<h3>I am working on it.</h3>
				<p>It should be completed on version 1.0.2</p>
			</div>
		<!-- div -->
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
