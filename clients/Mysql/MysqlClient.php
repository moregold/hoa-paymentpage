<?php namespace Mysql;

use Exception;
use PDO;

class MysqlClient
{
	private $connect;

	public function __construct()
	{
		$config = require dirname(__FILE__).'/../../config/config.php';
		$this->connect = new PDO('mysql:host='.$config['mysql']['host'].';dbname='.$config['mysql']['dbname'], $config['mysql']['user'], $config['mysql']['pass']); 
		$this->connect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->connect->exec('set names utf8');
	}

	public function exec($sql='', $placeholder = [])
	{
		$handle = $this->connect->prepare($sql);
		$handle->execute($placeholder);
		if(preg_match('/insert/ui', $sql)) {
			return $this->connect->lastinsertid();
		} elseif (preg_match('/(update|delete)/ui', $sql)) {
			return $handle->rowCount();
		} elseif(preg_match('/select/ui', $sql)) {
			return $handle->fetchAll(PDO::FETCH_ASSOC);
		}
	}

	public function getNextId($table = '')
	{
		$result = $this->exec("select AUTO_INCREMENT from INFORMATION_SCHEMA.TABLES where TABLE_NAME='".$table."'");
		return $result[0]['AUTO_INCREMENT'];
	}

	public function save($table, $data)
	{
		$sql_key = [];
		$sql_value = [];
		$placeholder_data = [];
		foreach ($data as $key => $value) {
			$sql_key[] = $key;
			$sql_value[] = ":".$key;
			$placeholder_data[ ':'.$key ] = $value;
		}
		$sql = "INSERT INTO ".$table."(".implode(',', $sql_key).") VALUES(".implode(',', $sql_value).")";
		return $this->exec($sql, $placeholder_data);
	}

}
