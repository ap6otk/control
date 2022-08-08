<?php
require_once 'API.class.php';


class User {
	private $_id;
	private $_park;
	private $_isAdmin;
	private $_password;
	public function __construct($id, $path) {		$filename=$path.$id;
		if (!file_exists($filename)) {
			$id=null;
			return;
		}
		$json = file_get_contents($filename);
		$data=json_decode($json);
		$this->_id=$id;
		$this->_park=$data->park;
		$this->_isAdmin=$data->isAdmin=='true';
		$this->_password=$data->password;
   	}



   	public function getId() {   		return $this->_id;   	}

   	public function getPark() {
   		return $this->_park;
   	}

   	public function isAdmin() {
   		return $this->_isAdmin;
   	}

   	public function checkPassword($password, $time) {
   		return $password==md5($this->_password.$time);
   	}

}

class MyAPI extends API
{
	protected $user;
	protected $pathUsers;
	protected $pathParks;
	protected $userParkDir;
	public $fileToDownload;

	public function __construct($request, $origin) {
	   parent::__construct($request);
	   $this->pathUsers="../../users/";
	   $this->pathParks="../../data/parks/";
	}

	public function changeStatus($data, $status = 200) {
		return $this->_response($data, $status);
	}



	public function time() {
		return Array( 'time' => time() );
 	}


	public function bintime() {
		$tz = array (
			'-11:00' => 'Pacific/Midway',
			'-10:00' => 'Pacific/Honolulu',
			'-09:00' => 'US/Alaska',
			'-08:00' => 'America/Los_Angeles',
			'-07:00' => 'US/Arizona',
			'-06:00' => 'US/Central',
			'-05:00' => 'US/Eastern',
			'-04:00' => 'Canada/Atlantic',
			'-04:30' => 'America/Caracas',
			'-04:00' => 'America/Santiago',
			'-03:30' => 'Canada/Newfoundland',
			'-03:00' => 'America/Sao_Paulo',
			'-02:00' => 'America/Noronha',
			'-01:00' => 'Atlantic/Azores',
			'00:00' => 'UTC',
			'01:00' => 'Europe/Amsterdam',
			'02:00' => 'Europe/Athens',
			'03:00' => 'Europe/Minsk',
			'04:00' => 'Europe/Moscow',
			'04:30' => 'Asia/Kabul',
			'05:00' => 'Asia/Karachi',
			'05:30' => 'Asia/Calcutta',
			'06:00' => 'Asia/Yekaterinburg',
			'06:30' => 'Asia/Rangoon',
			'07:00' => 'Asia/Novosibirsk',
			'08:00' => 'Asia/Krasnoyarsk',
			'09:00' => 'Asia/Irkutsk',
			'09:30' => 'Australia/Adelaide',
			'10:00' => 'Asia/Yakutsk',
			'11:00' => 'Asia/Vladivostok',
			'12:00' => 'Asia/Magadan',
			'13:00' => 'Pacific/Tongatapu'
		);
		$arg = $this->args[0];
		$stz = $tz[$arg] ;
		if (!is_null($stz)) {
			date_default_timezone_set($stz);
		}
		$d = getdate();
		if ($d["wday"]==0) $d["wday"]=7;
		$this->binaryArray = array( $d["hours"], $d["minutes"], $d["seconds"], $d["wday"], $d["mday"], $d["mon"], $d["year"]%100 );
		return $d;
 	} 	private function checkOrCreateDir($dir) {		if (!file_exists($dir)) mkdir($dir, 0777, TRUE);
 	}

 	protected function authorise() {
	 	if (!array_key_exists('id', $this->request)) throw new Exception('No User ID provided', 401);
	 	$id=$this->request['id'];

	 	if (!array_key_exists('pass', $this->request)) throw new Exception('No password provided', 401);
	 	$password=$this->request['pass'];
	 	$time=substr($password,32);
	 	$password=substr($password,0,32);
		$curTime=time();
		if (abs($time-$curTime)>60) throw new Exception ('Time mark expired',401);

	 	$this->user= new User($id,$this->pathUsers);
	 	if ($this->user->getId()==null) throw new Exception('User ID is not found', 401);
		if (!$this->user->checkPassword($password,$time)) throw new Exception('Invalid password or user Id', 401);
		$this->userParkDir=$this->pathParks.$this->user->getPark();
		$this->checkOrCreateDir($this->userParkDir); 	}


	public function debug() {		phpinfo();		//print_r($this);	}


	public function md5time() {
		$str=$this->args[0];
	 	if (array_key_exists('time', $this->request)) {
			$time=$this->request['time'];
		} else {
			$time=time();
		}
		return Array( $str => md5($str.$time).$time);
	}

	public function md5($args) {		$str=$args[0];		return Array( "$str" => md5($str));
	}

	public function test($args) {		$this->authorise($args);
		return Array("authorised"=>"true");
		
		$fullFileName='../../data/test.txt';

   		if (!file_exists($fullFileName)) throw new Exception($fullFileName.' does not exists',404);
   		$this->fileToDownload=$fullFileName;
	}

	private function getCurrent($create, $passive=FALSE) {
		$curFileName=$this->userParkDir.'/current.txt';   		if (!file_exists($curFileName)) {  			if (!$create) throw new Exception($fullFileName.' does not exists',404);
   			file_put_contents($curFileName,"1");
	   	}
		$cur=file_get_contents($curFileName);
		if ($passive) $cur=($cur=="0")?"1":"0";
		return $cur;
	}

	private function getFile($filepath,$filename) {
		$passive=array_key_exists('passive',$this->request);
		$cur=$this->getCurrent(FALSE, $passive);

		$fullFileName=$this->userParkDir.'/'.$cur.$filepath.$filename;

   		if (!file_exists($fullFileName)) throw new Exception($fullFileName.' does not exists',404);
   		$this->fileToDownload=$fullFileName;
	}

	private function postFile($filepath,$filename) {		if (!$this->user->isAdmin()) throw new Exception ('Admin user rights required', 403);

		$cur=$this->getCurrent(TRUE, TRUE);
	   	$fullPath=$this->userParkDir.'/'.$cur.$filepath;
   		$this->checkOrCreateDir($fullPath);
		$fullFilename=$fullPath.$filename;
		if (!isset($_FILES["filedata"])) {
			throw new Exception('No file data');
	  	}
	  	$tempname=$_FILES['filedata']['tmp_name'];
		if (!is_uploaded_file($tempname)) {
		  throw new Exception('Read error');
		}
		if (!@move_uploaded_file($tempname, $fullFilename)) {
			throw new Exception( 'Error: moving file failed. ');
		}
		$this->changeStatus("",203);
	}


	private function simpleGetFile($filepath,$filename) {
		$fullFileName=$this->userParkDir.'/'.$filepath.$filename;

   		if (!file_exists($fullFileName)) throw new Exception($fullFileName.' does not exists',404);
   		$this->fileToDownload=$fullFileName;
	}

	private function simplePostFile($filepath,$filename) {
		if (!$this->user->isAdmin()) throw new Exception ('Admin user rights required', 403);

	   	$fullPath=$this->userParkDir.'/'.$filepath;
   		$this->checkOrCreateDir($fullPath);
		$fullFilename=$fullPath.$filename;
		if (!isset($_FILES["filedata"])) {
			throw new Exception('No file data');
	  	}
	  	$tempname=$_FILES['filedata']['tmp_name'];
		if (!is_uploaded_file($tempname)) {
		  throw new Exception('Read error');
		}
		if (!@move_uploaded_file($tempname, $fullFilename)) {
			throw new Exception( 'Error: moving file failed. ');
		}
		$this->changeStatus("",203);
	}


	private function commonRequest($filepath, $filename) {		if ($this->method == 'GET') {
			$this->getFile($filepath,$filename);
		   	return null;
	   } else if ($this->method == 'POST') {
			if (!$this->user->isAdmin()) throw new Exception ('Admin user rights required', 403);
	 		$this->postFile($filepath,$filename);
		  	return Array("result"=>$filepath.$filename." was created");
	   } else {
			throw new Exception ("Only accepts GET/PUT requests",405);
	   }
  	}

	private function simpleRequest($filepath, $filename) {
		if ($this->method == 'GET') {
			$this->simpleGetFile($filepath,$filename);
		   	return null;
	   } else if ($this->method == 'POST') {
	 		$this->simplePostFile($filepath,$filename);
		  	return Array("result"=>$filepath.$filename." was created");
	   } else {
			throw new Exception ("Only accepts GET/PUT requests",405);
	   }
  	}

	/*public function textmsg() {
		$this->authorise();
		$fullFileName=$this->userParkDir.'/message.txt';
		if ($this->method == 'GET') {
	   		if (file_exists($fullFileName)) 
				$str=file_get_contents($fullFileName);
			else
				$str="";
			return Array("message"=>$str);
	   	} else if ($this->method == 'POST') {
			if (!$this->user->isAdmin()) throw new Exception ('Admin user rights required', 403);
			$str="";
		 	if (array_key_exists('text', $this->request)) $str=$this->request['text'];
			file_put_contents($fullFileName,$str);
			return Array("message"=>$str);
	   } else {
			throw new Exception ("Only accepts GET/PUT requests",405);
	   }
  	}*/

	public function datainfo () {		$this->authorise();
		return $this->commonRequest('/','data.nfo');	}

	public function iskrasys () {
		$this->authorise();
		return $this->commonRequest('/','iskrasys.lzw');
	}

	public function group($args) {
		$this->authorise();
		$group=$args[0];
		$filename=$args[1];
		return $this->commonRequest('/'.$group.'/',$filename);
	}

	public function sounds($args) {
		$this->authorise();
		$filename=$args[0];
		return $this->commonRequest('/Sounds/',$filename);
	}

	public function video($args) {
		$this->authorise();
		$filename=$args[0];
		return $this->commonRequest('/Video/',$filename);
	}

	public function images($args) {
		$this->authorise();
		return $this->commonRequest('/','images.itr');
	}

	public function calendar($args) {
		$this->authorise();
		$filename=$args[0];
		return $this->commonRequest('/Calendar/',$filename);
	}

	public function imglist($args) {
		$this->authorise();
		return $this->simpleRequest('/','imglist.sld');
	}

	public function textmsg () {
		$this->authorise();
		return $this->simpleRequest('/','message.txt');
	}


	public function info($args) {
		return phpinfo();
	}


	public function current() {
		$this->authorise();
		if (!$this->user->isAdmin()) throw new Exception ('Admin user rights required', 403);
		if ($this->method == 'GET') {
			$cur=$this->getCurrent(FALSE, FALSE);

		  	return Array("current"=>$cur);
		} else if ($this->method == 'POST') {
		 	if (!array_key_exists('current', $this->request)) throw new Exception('No new Current value provided', 401);
		 	$newCur=$this->request['current'];
		 	if (($newCur!='0') && ($newCur!="1")) throw new Exception('Invalid new Current value', 401);

			$curFileName=$this->userParkDir.'/current.txt';
   			file_put_contents($curFileName,$newCur);
			$cur=$this->getCurrent(FALSE, FALSE);
		  	return Array("current"=>$cur);
	   } else {
			throw new Exception ("Only accepts GET/PUT requests",405);
	   }
	}

 }
?>