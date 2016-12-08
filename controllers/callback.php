<?php namespace controllers;

require_once dirname(__FILE__).'/../clients/QuickBooks/QuickbooksClient.php';
require_once dirname(__FILE__).'/../clients/Mysql/MysqlClient.php';
require_once dirname(__FILE__).'/../vendor/autoload.php';

use QuickBooks\QuickBooksClient;
use Mysql\MysqlClient;
use Exception;
use controllers\Callback;

class Callback {

	public $request_xml;
	public $log_message;
	public $log_file;
	public $quickbooks;
	public $request_data_for_quickbook;
	public $customer_id;
	public $quickbook_id;
	public $payment_id;
	public $mysql;


	public function __construct()
	{
		# load xml information from sparrow notification
		$xml  = file_get_contents('php://input');
		$this->addLogMessage($xml."\n");
		$this->request_xml = simplexml_load_string($xml);
		$this->log_file = dirname(__FILE__).'/../logs/callback_log';
		$this->quickbooks = new QuickbooksClient;
		$this->mysql = new MysqlClient;
	}

	public function validateXmlParam()
	{
		if(!isset($this->request_xml->coderesponse) || $this->request_xml->coderesponse != 100) {
			$code 	 = (string)@$this->request_xml->coderesponse;
			$message = (string)@$this->request_xml->codedescription;
			throw new Exception(
				$message ? $message : "Transacion failed.",
				$code ? $code : 0
			);
		}
		return true;
	}

	public function createQuickbookCustomer($request_data = [])
	{
		$quickbooks_customer_info = $this->quickbooks->createCustomer($request_data);
		return $quickbooks_customer_info->Customer;
	}

	public function createQuickbookInvoice($request_data = [])
	{
		$this->quickbooks->editInvoice($request_data);
	}

	public function addLogMessage($message = '')
	{
		$this->log_message .= $message;
	}

	public function saveLog()
	{
		file_put_contents($this->log_file, date('Y-m-d H:i:s')."  [".$_SERVER['REMOTE_ADDR']."]  \n".($this->log_message)."\n\n", FILE_APPEND);
	}

	public function loadCustomerId()
	{
		if(isset($this->request_xml->orderdesc)) {
			$this->customer_id = explode('_', $this->request_xml->orderdesc)[0];
		} elseif (isset($this->request_xml->CustomerId)) {
			$this->customer_id = $this->request_xml->CustomerId;
		} else {
			throw new Exception("Can't get 'customer_id'.", 0);
		}
	}

	public function loadQuickbookId()
	{
		$customer = $this->mysql->exec("SELECT * FROM customer WHERE customer_id=:id", [':id' => $this->customer_id])[0];
		if(!$customer['quickbook_id']) {
			$request_data = [
				'title'		   => 'Mr/Mrs',
				'given_name'   => (string)$this->request_xml->firstname,
				'family_name'  => (string)$this->request_xml->lastname,
				'display_name' => $this->request_xml->firstname.' '.$this->request_xml->lastname.' ('.$this->customer_id.')',
			];
			$quickbooks_customer_info = $this->createQuickbookCustomer($request_data);
			$this->mysql->exec("UPDATE customer SET quickbook_id=:quickbook_id WHERE customer_id=:id", [
				':id' => $this->customer_id, 
				':quickbook_id' => $quickbooks_customer_info->Id
			]);
			$this->quickbook_id = $quickbooks_customer_info->Id;
		} else {
			$this->quickbook_id = $customer['quickbook_id'];		
		}
	}

	public function loadPaymentId()
	{
		if(isset($this->request_xml->orderid)) {
			$this->payment_id = $this->request_xml->orderid;
		} elseif (isset($this->request_xml->productid)) {
			$this->payment_id = $this->request_xml->productid;
		} else {
			throw new Exception("Can't get payment id.", 0);
		}
	}

	public function savePaymentResponse()
	{
		$this->mysql->save('payment_response', [
			'customer_id' 		=> $this->customer_id,
			'payment_id' 		=> $this->payment_id,
			'response_data' 	=> json_encode($this->request_xml),
			'is_send_quickbook' => 'N',
			'create_at' 		=> date('Y-m-d H:i:s'),
			'update_at' 		=> date('Y-m-d H:i:s'),
		]);
	}

	public function updatePaymentResponseStatus($is_send_success = 'N')
	{
		$this->mysql->exec('UPDATE payment_response SET is_send_quickbook=:x WHERE customer_id=:customer_id AND payment_id=:payment_id', [
			':x'  		   => $is_send_success,
			':payment_id'  => $this->payment_id,
			':customer_id' => $this->customer_id,
		]);
	}

}


$callback = new Callback();

try {
	
	$callback->validateXmlParam();
	$callback->loadCustomerId();
	$callback->loadPaymentId();
	$callback->loadQuickbookId();

	$callback->savePaymentResponse();
	
	$request_data = [
		'amount' 	   => (string)$callback->request_xml->amount,
		'product_id'   => '1',
		'product_name' => 'sparrow transaction',
		'customer_id'  => $callback->quickbook_id,
	];
	$callback->createQuickbookInvoice($request_data);

	$callback->updatePaymentResponseStatus('Y');
	
	$callback->addLogMessage("SUCCESS");

} catch (Exception $e) {
	$response_code = $e->getCode();
	if(isset($e->lastResponse) && isset(json_decode($e->lastResponse)->Fault)){
		$response_result = json_decode($e->lastResponse)->Fault->Error[0]->Message;
	}else{
		$response_result = $e->getMessage();
	}
	$callback->addLogMessage("CODE: ".$response_code."\nMESSAGE: ".$response_result."\n");
}

$callback->saveLog();