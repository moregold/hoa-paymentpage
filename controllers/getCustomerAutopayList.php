<?php namespace controllers;

session_start();

require_once dirname(__FILE__).'/../clients/Mysql/MysqlClient.php';
require_once dirname(__FILE__).'/../vendor/autoload.php';

use Mysql\MysqlClient;
use Exception;

try {
	$response_code = 0;
	$response_result = '';

	$customer_id = @$_SESSION['customerId'];

	if($customer_id) {
		$mysql = new MysqlClient();
		$response_result = $mysql->exec("SELECT * FROM payment WHERE customer_id=:id AND is_recurring='Y' AND payment_duration='-1' ORDER BY id DESC", [':id' => $customer_id]);
		foreach ($response_result as $key => &$value) {
			if($value['payment_method'] == 'PER_MONTH') {
				$value['recent_payment_day'] = date('M d, Y', strtotime(date('Y').'-'.date('m').'-'.$value['payment_day']));
				if($value['payment_day'] < date('d')) {
					$value['recent_payment_day'] = date('M d, Y', strtotime('+1 month', strtotime($value['recent_payment_day'])));
				}
			} elseif($value['payment_method'] == 'PER_DAYS') {
				$_today = strtotime('today');
				$_start_day = strtotime($value['start_date']);
				$_delta_day = ceil(($_today - $_start_day) / 86400);
				$value['recent_payment_day'] = date('M d, Y', strtotime('+'.($value['payment_day'] - $_delta_day % $value['payment_day']).' days'));
			}
		}
		$response_code = 200;
	}

} catch (Exception $e) {
	$response_code = $e->getCode();
	$response_result = $e->getMessage();
}

echo json_encode([
	'response_code'   => $response_code,
	'response_result' => $response_result
]);