<?php
/*
Plugin Name: WP-MultiTarget-Uploads-Sync-Tool
Plugin URI: http://rainmoe.com/
Description: A WordPress plugin which able to sync attachments to multiple FTP targets.
Author: evlos
Version: 1.0.3
Author URI: http://rainmoe.com/
*/

MUST::ins();

class MUST {

	static $name = 'MUST';
	static $version = '1.0.3';
	static $ins;
	static $addons = array(
		'ftp' => 'MUST_ftp',
	);

	private function __construct() {
		$this->wpInit();
		//$this->createOpt();
	}
	function ins() {
		if (is_null(self::$ins))
			self::$ins = new self();
		return self::$ins;
	}

	function arrayRewrite($default, $changed) {
		foreach ($default as $key => $val) {
			if (isset($changed[$key])) $default[$key] = $changed[$key];
		}
		return $default;
	}
	function zip($data) {
		return json_encode($data);
	}
	function unzip($data) {
		return json_decode($data, true);
	}

	function singleOpt($changed = array()) {
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
		// Notice
	}
	function putOpt($changed) {
		$res = update_option(self::$name.'_option', json_encode($changed));
		self::refreshOpt();
		return $res;
	}
	function getMT() {
		$res = get_option(self::$name.'_mtarget', '');
		if (self::isNoLocal()&&($res=='-1'||empty($res))) {
			// IMP
			return $res;
		}
		else return $res;
	}
	function putMT($data) {
		return update_option(self::$name.'_mtarget', $data);
	}
	function getPM($pid, $key) {
		return get_post_meta($pid, self::$name.'_'.$key, true);
	}
	function putPM($pid, $key, $data) {
		return update_post_meta($pid, self::$name.'_'.$key, $data);
	}	
	function createOpt() {
		if (!get_option(self::$name.'_option')) {
			update_option(self::$name.'_option', '');
		}
		// Useless
	}
	function readOpt($name) {
		return get_option(self::$name.'_option_'.$name, '');
	}
	function setOpt($name, $data) {
		return update_option(self::$name.'_option_'.$name, $data);
	}

	function isNoLocal() {
		return self::readOpt('nolocal') == 'yes' ? true : false;
	}
	function setNoLocal() {
		return self::setOpt('nolocal', 'yes');
	}

	function wpInit() {
		add_action('admin_menu', array($this, 'adminMenu'));
		//add_action('wp_enqueue_scripts', array($this, 'addScripts'));
		//add_action('wp_head', array($this, 'addText2Header'));

		if (!self::isNoLocal()) add_filter('the_content', array($this, 'addAfterTheContent'));
		add_action('save_post', array($this, 'addWhenSavingPost'));
		//add_filter('content_save_pre', array($this, 'addOnEditorSaving')); // uploadA() will replace this

		if (self::isNoLocal()) self::putMT(0);
	}
	function addScripts() {
		wp_enqueue_script('jquery');
	}
	function addStyle() {
		wp_enqueue_style('/wp-admin/css/colors-classic.css');
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
				//$(this).attr("src", $(this).attr("src").replace("'.self::urlDefault().'", "'.self::urlCurrent().'"));
				//$(this).css("display", "block");
			});
		});
		</script>';
	}
	
	function readAttachments() {
		global $wpdb;
		return array_reverse($wpdb->get_results("
			SELECT * FROM {$wpdb->posts}
			WHERE post_status = 'inherit' AND post_type = 'attachment'
		"));
	}
	function readChildAttachments($pid) {
		global $wpdb;
		return array_reverse($wpdb->get_results("
			SELECT * FROM {$wpdb->posts}
			WHERE post_status = 'inherit' AND post_type = 'attachment' AND post_parent = {$pid}
		"));
	}
	function readArticles($aid = -1) {
		global $wpdb;
		if ($aid == -1)
			return array_reverse($wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE post_type = 'post'"));
		else
			return array_reverse($wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE post_type = 'post' AND ID = {$aid}"));
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
	function splitUrl($guid) {
		$regex = '/wp-content\/uploads\/([0-9]+)\/([0-9]+)\/(.+)$/i';
		preg_match($regex, $guid, $res);
		$dir = wp_upload_dir();
		$res[] = $dir['basedir'];
		return $res;
	}
	function linko($pid) {
		global $wpdb;
		return $wpdb->get_var("
			SELECT guid FROM {$wpdb->posts}
			WHERE post_status = 'inherit' AND post_type = 'attachment' AND ID = {$pid}
		");
	}
	function link($pid) {
		$MT = self::getMT();
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
	function isReplaced() {
		$opt = self::getOpt();
		if (self::isNoLocal()&&!empty($opt)) return true;
		if (empty($opt)) return false;
		$MT = self::getMT();
		if (empty($MT)||$MT == '-1') return false;
		return true;
	}
	function urlDefault() {
		return site_url().'/wp-content/uploads/';
	}
	function urlCurrent() {
		$MT = self::getMT();
		$opt = self::getOpt();
		return $opt[$MT]['conn']['folder_url'];
	}
	function addWhenSavingPost($aid) {
		if (self::isNoLocal()) self::uploadA(false, false, $aid);
		else self::upload();
	}
	function addAfterTheContent($content) {
		if (self::isReplaced())
			return str_replace(self::urlDefault(), self::urlCurrent(), $content);
		else
			return $content;
	}
	function addOnEditorSaving($content) {
		if (self::isReplaced())
			return str_replace(self::urlDefault(), self::urlCurrent(), $content);
		else
			return $content;
	}
	
	function upload($echo = false, $check = false) {
		set_time_limit(600);
		$opt = self::getOpt();
		foreach ($opt as $key => $val) {
			if ($val['enable']) {
				$conn = $val['conn'];
				$attach = self::readAttachments();
				foreach ($attach as $val) {
					$isReady = true;

					//Check remote ifUploaded
					if ($check)
						if (function_exists('curl_init')) {
							$rurl = self::getPM($val->ID, 'link_'.$key);
							if ($rurl!=''&&self::isRemoteFileExists($rurl)) {
								$isReady = false;
							}
						}
						else {
							if ($echo) echo '* cUrl is not installed, file will be uploaded without exists check ...'."\r\n";
						}

					//Check ifUploaded
					$tmp = self::getPM($val->ID, 'link_'.$key);
					if (empty($tmp)) $isReady = true;

					//Upload
					if ($isReady) {
						$nurl = MUST_ftp::upload(self::splitUrl($val->guid), $conn);
						if ($nurl) self::putPM($val->ID, 'link_'.$key, $nurl);
						if ($echo) echo '* '.$val->guid.' uploaded to '.$nurl.' ...'."\r\n";
					}
					else {
						if ($echo) echo '* '.$val->guid.' remote existed ...'."\r\n";
					}
				}
			}
		}
	}

	function adminMenu() {
		$page = add_menu_page('WP-MUST', 'WP-MUST', 'administrator', __FILE__, array($this, 'pageReadMe'));
		add_action('admin_print_styles-'.$page, array($this, 'addStyle'));
		$page = add_submenu_page(__FILE__, 'WP-MUST', 'WP-MUST', 'administrator', __FILE__, array($this, 'pageReadMe'));
		if (self::isNoLocal())
			$page = add_submenu_page(__FILE__, 'WP-MUST InArticles', 'WP-MUST InArticles', 'administrator', 'MUSTpageInArticles', array($this, 'pageInArticles'));
		else
			$page = add_submenu_page(__FILE__, 'WP-MUST InMedia', 'WP-MUST InMedia', 'administrator', 'MUSTpageInMedia', array($this, 'pageList'));
		$page = add_submenu_page(__FILE__, 'WP-MUST Setting', 'WP-MUST Setting', 'administrator', 'MUSTpageSetting', array($this, 'pageSetting'));
		add_action('admin_print_styles-'.$page, array($this, 'addStyle'));
	}
	function pageList() {
		$opt = self::getOpt();
		?>
		<div style="margin: 4px 15px 0 0;">
		<!-- div -->
			<h2>WP-MultiTarget-Uploads-Sync-Tool Attachments in Media Library</h2>
			<div>
				<form action="" method="POST" style="display:inline;">
					<input type="hidden" name="do" value="upload" />
					<input type="submit" value="Upload All" />
				</form>
				 - 
				<form action="" method="POST" style="display:inline;">
					<input type="hidden" name="do" value="check"/>
					<input type="submit" value="Check All" />
				</form>
			</div>
			<?php
			if (isset($_POST['do'])) {
				if ($_POST['do']=='upload') {
					echo '<br /><textarea style="width: 100%; height: 120px;">';
					self::upload(true);
					echo '</textarea><br />';
				}
				else if ($_POST['do']=='check') {
					self::upload(true, true);
				}
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
						$url = self::getPM($val->ID, 'link_'.$key_);
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
		$opt = self::getOpt();
		$MT = self::getMT();
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
					if (self::isNoLocal()) $enable = 0;
					else $enable = isset($_POST[$count.'_enable']) ? 1 : 0;
					$opt_[$count] = self::singleOpt(array(
						'name' => $_POST[$count.'_name'],
						'type' => $_POST[$count.'_type'],
						'conn' => $singleSet,
						'enable' => $enable,
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
			elseif ($do == 'nolocalforsure') {
				self::setNoLocal();
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
				<?php if (!self::isNoLocal()||empty($opt)): ?>
				<form action="" method="POST" style="display:inline;">
					New Target:
					<select name="add_type"><?php foreach (self::$addons as $key => $val) : ?>
						<option value="<?php echo $key; ?>"><?php echo strtoupper($key); ?></option>
					<?php endforeach; ?></select>
					<input type="hidden" name="do" value="add" />
					<input type="submit" value="Add" />
				</form>
				<?php endif; ?>

				<?php if (!self::isNoLocal()&&!empty($opt)) : ?>
					<form action="" method="POST" style="display:inline;">
						 - Current Target:
						<select name="mtarget"><?php $choosed = false; foreach ($opt as $key_ => $val_): ?>
							<?php if ($MT == $key_) $choosed = true; ?>
							<option value="<?php echo $key_; ?>"<?php echo $MT == $key_ ? ' selected' : ''; ?>><?php echo $val_['name']; ?></option>
						<?php endforeach; ?><?php if (!self::isNoLocal()) echo $choosed ? '' : '<option value="-1" selected>-</option>'; ?></select>
						<input type="hidden" name="do" value="mtarget" />
						<input type="submit" value="Set" />
					</form>

					<form action="" method="POST" style="display:inline;">
						<input type="hidden" name="do" value="clearmtarget" />
						- <input type="submit" value="Stop url replacement" />
					</form>
				<?php endif; ?>

				<?php if (self::isNoLocal()) : ?>
					No Local Mode: 
					<span style="color:green;">ENABLED</span>
				<?php else: ?>
					 - No Local Mode: 
					<?php if (isset($_POST['do'])&&$_POST['do']=='nolocal') : ?>
						<form action="" method="POST" style="display:inline;">
							<input type="hidden" name="do" value="nolocalforsure" />
							<input type="submit" value="I am Sure" /> (Are you sure? There is no turning back.)
						</form>
					<?php else : ?>
						<form action="" method="POST" style="display:inline;">
							<input type="hidden" name="do" value="nolocal" />
							<input type="submit" value="Enable" />
						</form>
					<?php endif; ?>
				<?php endif; ?>
				
				<?php if (self::isNoLocal() && $MT == '-1') : ?>
				<span style="color:red;">- Warning: No main target! One must be choosen!</span>
				<?php endif; ?>
			</div>
			<br />
			<div>
				<?php if (!empty($opt)): ?>
					<form action="" method="POST">
						<table class="widefat">
							<thead><th>Enable</th></th><th>ID</th><th>Name</th><th>Type</th><th>Connection</th></thead>
							<?php $count = 0; foreach ($opt as $key_ => $val_): ?>
								<?php if (self::isNoLocal()&&$count++==1) break; ?>
								<tr id="target-<?php echo $key_; ?>">
									<td>
										<?php if (self::isNoLocal()) : ?>
											<input type="checkbox" name="<?php echo $key_; ?>_none" disabled=disabled />
										<?php else : ?>
											<input type="checkbox" name="<?php echo $key_; ?>_enable"<?php echo $val_['enable'] ? ' checked' : '';?> />
										<?php endif; ?>
									</td>
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
			Default URL: <?php echo self::urlDefault(); ?>
			<br />
			Current URL: <?php echo self::isReplaced() ? self::urlCurrent() : 'disabled.'; ?>
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
				<p>This is a WordPress Multiple Targets Sync Tool, which means you are able to add multiple targets with FTP supported (Currently), and sync attachments to these targets. 
					<br />Also, it is possible to use the url of attachments in one of these targets to show on fontend.</p>
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
				<p>这是一个 WordPress 多目标（图床）附件同步工具。你可以添加多个图床，当然目前仅支持 FTP 图床。设置完成后附件就能够被同步到这些图床，并从某一图床调用显示。</p>
				<p>步骤:</p>
				<ul>
					<li>1. 先新建一个 target，即新建同步目标。</li>
					<li>2. 然后在出现的空白表单内填入连接信息，如果是 ftp 则是此 ftp 的连接信息。</li>
					<li>3. Enable 这个 target，即启用这个目标。启用后的目标才会允许被同步，点 Save 保存。</li>
					<li>4. 在 Current Target 处找到这个新建的 target 目标，点击 Set。设置为当前的目标，那么所有原附件地址将被替换为其在此目标的上的地址。</li>
					<li>5. 到文件列表处点 Upload All 即可。以后传的附件会自动被同步。</li>
				</ul>
			</div>
			<div>
				<h3>FTP example:</h3>
				<ul>
					<li>Host: example.com</li>
					<li>Username: example_username</li>
					<li>Password: example_passweord</li>
					<li>Folder: public_html/example/</li>
					<li>Folder URL: http://example.com/example/</li>
					<li>Port: 21</li>
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
				<p>It should be completed on later version.</p>
			</div>
		<!-- div -->
		</div>
		<?php
	}

	/*function getKeywords() {
		return self::readOpt('keywords');
	}
	function setKeywords() {
		return self::setOpt('keywords', 'yes');
	}*/
	function resetAIID($theid) {
		global $wpdb;
		return $wpdb->get_var("
			ALTER TABLE {$wpdb->posts} AUTO_INCREMENT = {$theid}
		");
	}
	function uploadA($echo = true, $check = false, $aid = -1) {
		set_time_limit(600);
		$data = self::readArticles($aid);
		$opt = self::getOpt();
		$conn = $opt[0]['conn'];
		foreach ($data as $val) {
			preg_match_all("/".str_replace('/', '\/', self::urlDefault())."[^\"]+|".str_replace('/', '\/', self::urlCurrent())."[^\"]+/i", $val->post_content, $res);
			foreach ($res[0] as $img) $final[md5(str_replace(self::urlDefault(), self::urlCurrent(), $img))] = str_replace(self::urlCurrent(), self::urlDefault(), $img);
			foreach (array_unique($final) as $key => $img) {
				$isReady = true;
				$needClear = false;
				$ourl = self::getPM($val->ID, 'link_'.$key, $nurl);

				// Check remote ifUploaded
				if ($check) {
					if (function_exists('curl_init')) {
						if ($ourl != ''&&self::isRemoteFileExists($ourl)) {
							echo '* '.$img.' remote file existed ...'."\r\n";
							$isReady = false;
							$needClear = true;
						}
					}
					else {
						if ($echo) echo '* cUrl is not installed, file will be uploaded without exists check ...'."\r\n";
					}
				}
				if ($ourl != '') {
					echo '* '.$img.' remote record existed ...'."\r\n";
					$isReady = false;
				}

				if ($isReady) {
					$nurl = MUST_ftp::upload(self::splitUrl($img), $conn);
					if ($nurl) self::putPM($val->ID, 'link_'.$key, $nurl);
					if ($echo) echo '* '.$img.' uploaded to '.$nurl.' ...'."\r\n";
					$needClear = true;
				}

				if ($needClear) {
					$post_tmp = array();
					$post_tmp['ID'] = $val->ID;
					$post_tmp['post_content'] = str_replace($img, str_replace(self::urlDefault(), self::urlCurrent(), $img), $val->post_content);
					wp_update_post($post_tmp);

					$posts = self::readChildAttachments($val->ID);
					foreach ($posts as $post) {
						if ($post->guid == $img) wp_delete_attachment($post->ID, true);
					}
					self::resetAIID($val->ID + 1);
				}
			}
		}
	}
	function pageInArticles() {
		$opt = self::getOpt();
		?>
		<div style="margin: 4px 15px 0 0;">
		<!-- div -->
			<h2>WP-MultiTarget-Uploads-Sync-Tool Attachments in Articles</h2>
			<div>
				<form action="" method="POST" style="display:inline;">
					<input type="hidden" name="do" value="upload"/>
					<input type="submit" value="Upload All" />
				</form>
				 - 
				<form action="" method="POST" style="display:inline;">
					<input type="hidden" name="do" value="check"/>
					<input type="submit" value="Check All" />
				</form>
			</div>
			<?php
			if (isset($_POST['do'])) {
				echo '<br /><textarea style="width: 100%; height: 120px;">';
				if ($_POST['do']=='upload') {
					self::uploadA();
				}
				else if ($_POST['do']=='check') {
					self::uploadA(true, true);
				}
				else if ($_POST['do']=='uploadone') {
					$aid = $_POST['aid'];
					self::uploadA(true, true, $aid);
				}
				echo '</textarea><br />';
			}
			?>
			<br />
			<table class="widefat">
				<?php
				$data = self::readArticles();
				
				echo '<thead><th>ID</th><th>User</th><th>Date</th><th>Title</th><th>Images</th><th>Control</th></thead>';
				//print_r($data);
				foreach ($data as $val) {
					$tmp = $val->post_content;
					preg_match_all("/".str_replace('/', '\/', self::urlDefault())."[^\"]+|".str_replace('/', '\/', self::urlCurrent())."[^\"]+/i", $tmp, $res);
					//print_r($res);
					if (!empty($res[0])) {
						$final = array();
						foreach ($res[0] as $img) $final[md5(str_replace(self::urlDefault(), self::urlCurrent(), $img))] = $img;
						//print_r($final);
						echo '<tr><td>'.$val->ID.'</td><td>'.get_user_meta($val->post_author, 'nickname', true).'</td>
						<td>'.$val->post_date_gmt.'</td><td>'.$val->post_title.'</td><td><table class="widefat child">
						<thead><th>CurrentURL</th></thead>';
						foreach (array_unique($final) as $key => $img) {
							$nurl = self::getPM($val->ID, 'link_'.$key); //<td>'.$img.'</td>
							echo '<tr><td>'.(empty($nurl) ? $img : '<a target="_blank" href="'.$nurl.'">'.$nurl.'</a>').'</td></tr>';
						}
						echo '</table></td><td>
						<form action="" method="POST" style="display:inline;">
						<input type="hidden" name="do" value="uploadone"/>
						<input type="hidden" name="aid" value="'.$val->ID.'"/>
						<input type="submit" value="Upload This" />
						</form>
						</td></tr>';
					}
				}
				?>
			</table>
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

// Made by Evlos >w< ||
