<?php namespace Sparrow;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Uri;
use Exception;

class SparrowClient
{
	const TRANS_TYPE_SALE	  	  = 'sale';
	const TRANS_TYPE_ADD_PLAN 	  = 'addplan';
	const TRANS_TYPE_ADD_CUSTOMER = 'addcustomer';
	const TRANS_TYPE_ASSIGN_PLAN  = 'assignplan';
	const TRANS_TYPE_BILL_ASSIGN  = 'billcustomer';
    const TRANS_TYPE_CREATE_INVOICE = 'createmerchantinvoice';
	const TRANS_TYPE_TOKEN 		  = 'token';
	const PAY_TYPE_CREDITCARD = 'creditcard';
	const SPARROW_PAY_SUCCESS_CODE = '200';
    const PAY_METHOD_CREDIT = 'Credit Card';
    const PAY_METHOD_ACH = 'ACH';

	private $client;
	private $private_key;
	private $public_key;
	private $request_param = [];
	private $request_uri;

	public function __construct($pay_method)
	{
		$config = require dirname(__FILE__).'/../../config/config.php';
        if ($pay_method == self::PAY_METHOD_CREDIT){
		    $this->private_key = $config['sparrow']['credit_private_key'];
        }
        if ($pay_method == self::PAY_METHOD_ACH){
            $this->private_key = $config['sparrow']['ach_private_key'];
        }
        $this->public_key = $config['sparrow']['public_key'];
		$this->client = new Client( ['base_uri' => 'https://secure.5thdl.com/'] );
		$this->request_uri = '/payments/services_api.aspx';
	}

	/**
	 * set up the request data
	 * @param array
	 */
	private function appendRequestParam($request_param = [])
	{
		$this->request_param = array_merge($this->request_param, $request_param);
	}


	private function clearRequestParam()
	{
		$this->request_param = [];
	}

	/**
	 * Send request to sparrow
	 * @param string $transtype
	 */
	public function request($request_param)
	{
		$post_data = [
			'form_params' => $request_param,
			'verify' 	  => false
		];
		$response = $this->client->post($this->request_uri, $post_data);
		if($response->getStatusCode() == 200) {
			$response_decoded = $this->decodeResponse( $response->getBody()->getContents() );
			if($response_decoded['response'] == 1 || $response_decoded['response'] == 00 ||
                (array_key_exists('invoicenumber', $response_decoded)&&strlen($response_decoded['invoicenumber'])>0)) {
				return $response_decoded;
			} else {
				throw new \Exception($response_decoded['textresponse'], 500);
			}
		} else {
			throw new \Exception($response->getReasonPhrase(), $response->getStatusCode());
		}
	}

	/**
	 * get auth token for transaction
	 *
	 * @return string|Exception
	 */
	public function requestAccessToken()
	{
		$response = $this->request(array_merge($this->request_param, [
			'pkey'      => $this->public_key,
			'transtype' => self::TRANS_TYPE_TOKEN
		]));
		$this->appendRequestParam([
			'tt' => $response['tt']
		]);
		return $response['tt'];
	}

	/**
	 *
	 * Add payment plan to sparrow
	 * @param array $request_data
	 * @return string|Exception
	 */
	public function postAddPaymentPlan($request_data = [])
	{
		$this->clearRequestParam();
		$this->appendRequestParam($request_data);
		$this->appendRequestParam([
			'transtype' => self::TRANS_TYPE_ADD_PLAN,
			'mkey'	    => $this->private_key,
		]);

		$this->requestAccessToken();

		$response = $this->request($this->request_param);
		return $response['plantoken'];
	}

	/**
	 *
	 * Add data vault customer to sparrow
	 * @param array $request_data
	 * @return array|Exception
	 */
	public function postAddCustomer($request_data = [])
	{
		$this->clearRequestParam();
		$this->appendRequestParam($request_data);
		$this->appendRequestParam([
			'transtype' => self::TRANS_TYPE_ADD_CUSTOMER,
			'mkey'	    => $this->private_key,
		]);

		$this->requestAccessToken();

		$response = $this->request($this->request_param);
		return [
			'customer_token' => $response['customertoken'],
			'payment_token'	 => $response['paymenttoken_1']
		];
	}

	/**
	 *
	 * Assign payment to a exist plan
	 * @param string $customer_token
	 * @param string $plan_token
	 * @param string $payment_token
	 * @return string|Exception
	 */
	public function postAssignCustomerToPlan($customer_token, $plan_token, $payment_token)
	{
		$this->clearRequestParam();
		$this->appendRequestParam([
			'transtype' 	=> self::TRANS_TYPE_ASSIGN_PLAN,
			'mkey'	    	=> $this->private_key,
			'customertoken' => $customer_token,
			'plantoken'		=> $plan_token,
			'paymenttoken'	=> $payment_token
		]);

		$this->requestAccessToken();

		$response = $this->request($this->request_param);
		return $response['assignmenttoken'];
	}

	/**
	 * send a payment request to sparrow
	 * @return array
	 */
	public function postSaleTransaction($request_data = '')
	{
		$this->clearRequestParam();
		$this->appendRequestParam($request_data);
		$this->appendRequestParam([
			'transtype' => self::TRANS_TYPE_SALE,
			'mkey'	    => $this->private_key
		]);

		$this->requestAccessToken();
		$response = $this->request($this->request_param);
        $invoice_response = $this->request($this->createInvoiceParam($this->request_param['amount']));
		if($response['status'] == self::SPARROW_PAY_SUCCESS_CODE &&
            array_key_exists('invoicenumber', $invoice_response)) {
		    $response['invoice_id'] = $invoice_response['invoicenumber'];
			return $response;
		} else {
			throw new \Exception($response['codedescription'], $response['status'], $invoice_response);
		}
	}

    /**
     * @param $amount
     * @return array
     */
	public function createInvoiceParam($amount)
    {
        return [
            'mkey' => $this->private_key,
            'transtype' => self::TRANS_TYPE_CREATE_INVOICE,
            'invoicedate' => date("m/d/Y"),
            'currency' => 'USD',
            'invoicestatus' => 'draft',
            'invoiceamount' => $amount
        ];
    }

	/**
	 * Bill the customer's plan
	 * @return boolean
	 */
	public function postBillCustomer($assignment_token = '')
	{
		$this->clearRequestParam();
		$this->appendRequestParam([
			'transtype' 	  => self::TRANS_TYPE_BILL_ASSIGN,
			'mkey'	    	  => $this->private_key,
			'assignmenttoken' => $assignment_token,
		]);

		$this->requestAccessToken();

		$this->request($this->request_param);
		return true;
	}

	/**
	 * decode the sparrow response
	 * @param $response string
	 * @return array
	 */
	private function decodeResponse($response = '')
	{
		$result = [];
		$_tmp = explode('&', $response);
		foreach ($_tmp as $string) {
			$result[ explode('=', $string)[0] ] = str_replace('+', ' ', explode('=', $string)[1]);
		}
		return $result;
	}

}
