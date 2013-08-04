<?php
class Spider{
	private $url="www.baidu.com";
	private $depth = 1;
	private $refer = "www.baidu.com";
	public function __get($val_name) {
		if(isset($this->$val_name)){
			return ($this->$val_name);
		}else {
			return (NULL);
		}
	}
	public function __set($val_name, $val_value) {
		$this->$val_name = $val_value;
	}
	public function __isset($val_name) {
		return isset($this->$val_name);
	}
	public function __unset($val_name) {
		unset($this->$val_name);
	}
	function get_content($urlvalue){
		$url_parts = parse_url($urlvalue);
		//["scheme"]=>string(4) "http"	["host"]=>string(13) "www.baidu.com"	["path"]=>string(6) "/a/b/s"	["query"]=>string(13) "wd=123&wq=456"
		if(isset($url_parts["host"])) {
			file_put_contents("./hostlog.txt", $url_parts["host"], FILE_APPEND);
			$fp = fsockopen($url_parts["host"], 80, $errno, $errstr, 30);
			//file_put_contents("./hostlog.txt", $url_parts["host"], FILE_APPEND);
			if(!$fp) {
				file_put_contents("./hostlog.txt", "aa", FILE_APPEND);
				echo "$errstr ($errno) </br>\n";
			} else {
				if(isset($url_parts["scheme"])) {
					file_put_contents("./hostlog.txt", "bb", FILE_APPEND);
					$out = "GET /"." ".$url_parts["scheme"]."/1.1\r\n";
					$out .= "User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:20.0) Gecko/20100101 Firefox/20.0\r\n"; 
					if(isset($url_parts["path"])) {
						file_put_contents("./hostlog.txt", "cc", FILE_APPEND);
						$out .= "Path: ".$url_parts["path"]."\r\n";
					}
					if(isset($url_parts["query"])) {
						file_put_contents("./hostlog.txt", "dd", FILE_APPEND);
						$out .= "Query: ".$url_parts["query"]."\r\n";
					}
					if(isset($url_parts["path"])) {
						file_put_contents("./hostlog.txt", "ee\n", FILE_APPEND);
						$out .= "Path: ".$url_parts["path"]."\r\n";
					}
					$out .= "Host: ". $url_parts["host"]. "\r\n";
					$out .= "Connection: Close\r\n\r\n";
					fputs($fp, $out);
					$result = "";
					while(!feof($fp)) {
						$result .= fgets($fp, 128);			
					}
					fclose($fp);

				}
				file_put_contents("./hostlog.txt", "***********\n", FILE_APPEND);
				return $result;
			}
		} else {
			echo "wrong" ;
		}
	}

	function match_urltitle($urlvalue, $depth, $refer) {
		$content = $this->get_content($urlvalue);
		$urltitle = array();
		//"<a href="http://image.baidu.com name="hello"">图&nbsp;片</a>"
		preg_match_all("/<a href=(\"|\')(.*?)(\"|\')[^>]*>(.*?)<\/a>/", $content, $urltitle);
		$utvalue = array();
		$url = array();
		$title = array();
		$i=0;
		$j=0;
		$k=0;
		foreach($urltitle[2] as $node) {
			$url[$i] = $node;
			$i++;	
		}
		foreach($urltitle[4] as $node1) {
			$title[$j] = $node1;
			$j++;	
		}
		for($k=0; $k<count($url);$k++) {
			$urltitle[$k] = array("url"=>$url[$k], "title"=>$title[$k]);
			add_url_title($depth, $urlvalue, $url[$k], $title[$k], $refer);
		}
		//var_dump($urltitle);
		return $urltitle;
	}
}

function create_urltitles() {
	$db = new PDO('sqlite:./url_titles');

	//创建表
	$db->beginTransaction();
	$q = $db->query("SELECT name FROM sqlite_master WHERE type = 'table'" . " AND name= 'url_titles'");
	//如果没有返回结果行 就创建这个表
	if($q->fetch() === false) {
		$db->exec("
			CREATE TABLE url_titles (
				id integer primary key autoincrement, 
				depth integer,
				url varchar(255),
				child_url varchar(255),
				child_title varchar(255),
				refer varchar(255)
		)");
		//$db->exec("insert into url_titles (id, depth, url, child_url, child_title, refer) values ('1', '1', 'url','child_url','child_title','refer')");
		$db->commit();
	}else {
		$db->rollback();
	}
}
function add_url_title($depth, $url, $childurl, $childtitle, $refer){
	//var_dump($depth);
	//var_dump($url);
	//var_dump($childurl);
	//var_dump($childtitle);
	//var_dump($refer);	
	$db = new PDO('sqlite:./url_titles');
	$db->beginTransaction();
	$sql = "INSERT INTO url_titles (depth, url, child_url, child_title, refer) VALUES (?, ?, ?, ?, ?)";
	$stmt = $db->prepare($sql);
	$stmt->bindParam(1, $depth, PDO::PARAM_INT);
	$stmt->bindParam(2, $url, PDO::PARAM_STR);
	$stmt->bindParam(3, $childurl, PDO::PARAM_STR);
	$stmt->bindParam(4, $childtitle, PDO::PARAM_STR);
	$stmt->bindParam(5, $refer, PDO::PARAM_STR);
	$stmt->execute();
	$db->commit();
}

function get_url_title(){
	$db = new PDO('sqlite:./url_titles');
	$sql = "SELECT * FROM url_titles";
	$stmt = $db->prepare($sql);
	$stmt->execute();
	var_dump($stmt->fetchAll());
}
function run($spider) {
	create_urltitles();	
	$urltitle = $spider->match_urltitle($spider->url, $spider->depth, $spider->refer);
	$spider->depth = $spider->depth-1;
	
	if($spider->depth >= 1) {	
		foreach($urltitle as $node) {
			$spider2 = new Spider();
			$spider2->url = $node["url"];
			$spider2->depth = $spider->depth;
			$spider2->refer = $spider->url;
			run($spider2);
		}
	}
}
$spider = new Spider();
$spider->url = $argv[1];
$depth = (int)$argv[2];
$spider->depth = $depth;
$urltitle1 = run($spider);
get_url_title();
?>
