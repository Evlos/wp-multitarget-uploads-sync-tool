<?php

class MUST_ftp_do {
	
	private static $host;
	private static $username;
	private static $password;
	private static $folder;
	private static $conn;
	private static $result;
	private static $dir_level;
	private static $port; # Fixed
	
	private static $ins;

	private static $errors;

	function error($msg) {
		self::$errors = true;
		echo '* '.$msg." ...\r\n";
	}
	
	public static function ins($host, $username, $password, $folder, $dir_level, $port = 21) {
		self::$errors = false;
		if (is_null(self::$ins))
			self::$ins = new self($host, $username, $password, $folder, $dir_level, $port);
		return self::$ins;
	}

	function MUST_ftp_do($host, $username, $password, $folder, $dir_level, $port) {
		if ($host!="" && $username!="" && $password!="" && $folder!="" && $dir_level!="") {
			$this->host = $host;
			$this->username = $username;
			$this->password = $password;
			$this->folder = $folder;
			$this->dir_level = $dir_level;
			$this->port = $port;
		}
		else {
			$this->error('Connection information error.');
			return false;
		}
		if (!$this->connect() || !$this->setdir()) {
			$this->error('Unable to connect server or unable to setDir.');
			return false;
		}
		else return !self::$errors;
	}

	function connect() {
		$this->conn = @ftp_connect($this->host, $this->port);
		$this->result = @ftp_login($this->conn, $this->username, $this->password);
		
		@ftp_pasv($this->conn, true);
		if (@ftp_get_option($this->conn, FTP_TIMEOUT_SEC) < FS_TIMEOUT)
			@ftp_set_option($this->conn, FTP_TIMEOUT_SEC, FS_TIMEOUT);
				
		if ((!$this->conn) || (!$this->result)) {
			$this->error('Unable to connect server.');
			return false;
		}
		else return true;
	}

	function createdir() {
		//echo "Dir: ".ftp_pwd($this->conn);
		foreach ($this->dir_level as $val) {
			@ftp_mkdir($this->conn, $val);
			if (!@ftp_chdir($this->conn, $val)) {
				$this->error('Unable to chdir deeply.');
				return false;
			}
			//echo "Dir: ".ftp_pwd($this->conn);
		}
		return true;
	}

	function setdir() {
		//echo "Dir: ".ftp_pwd($this->conn);
		if (!@ftp_chdir($this->conn, $this->folder)||!$this->createdir()) {
			$this->error('Unable to chdir.');
			return false;
		}
		else return true;
	}
	
	function send($remote_file, $file, $mode=FTP_BINARY, $dir_level) {
		if ($this->dir_level!=$dir_level) {
			$this->setdir();
			$this->dir_level=$dir_level;
		}
		
		//$f = fopen($file, 'r');
		//fseek($f, 0);
		//$z = ftp_fput($this->conn, $remote_file, $f, $mode);
		//fclose($f);
		
		if (@ftp_put($this->conn, $remote_file, $file, $mode)) return true;
		//if ($z) return true;
		else {
			$this->error('Unable to send file.');
			return false;
		}
	}
	
	function del($remote_file, $dir_level) {
		//Dev
	}

}
