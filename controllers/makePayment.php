<?php namespace controllers;

require_once dirname(__FILE__).'/../clients/Sparrow/SparrowClient.php';
require_once dirname(__FILE__).'/../clients/Mysql/MysqlClient.php';
require_once dirname(__FILE__).'/../vendor/autoload.php';

use Sparrow\SparrowClient;
use Mysql\MysqlClient;
use Exception;


class MakePayment
{

	public $mysql;
	public $sparrow;
	public $payment_id;
	public $request_data;
    public $pay_method;
	public function __construct()
	{
		$this->mysql = new MysqlClient;
		$this->request_data = $_POST;
        $this->sparrow = new SparrowClient($this->request_data['pay_method']);
	}

	public function validateRequestParam()
	{
        if ($this->request_data['pay_method'] == 'Credit Card') {
            if (
                !isset($this->request_data['first_name']) ||
                !isset($this->request_data['last_name']) ||
                !isset($this->request_data['amt_total_payment']) ||
                !isset($this->request_data['customer_id']) ||
                !isset($this->request_data['card_number'])
            ) {
                throw new Exception("Missing parameters", 100);
            }
        }
        if ($this->request_data['pay_method'] == 'ACH') {
            if (
                !isset($this->request_data['first_name']) ||
                !isset($this->request_data['last_name']) ||
                !isset($this->request_data['amt_total_payment']) ||
                !isset($this->request_data['customer_id']) ||
                !isset($this->request_data['bankname']) ||
                !isset($this->request_data['routing']) ||
                !isset($this->request_data['account']) ||
                !isset($this->request_data['achaccounttype']) ||
                !isset($this->request_data['achaccountsubtype'])

            ) {
                throw new Exception("Missing parameters", 100);
            }
        }
	}

    public function mergeTwoRequestParam($request_param_1=[], $request_param_2=[])
    {
        return array_merge($request_param_1, $request_param_2);
    }

	public function savePayment()
	{
		if(isset($this->request_data['is_recurring']) && $this->request_data['is_recurring'] == 'true') {
			$_is_recurring = 'Y';
			$_start_date   = date('Y-m-d');
			if($this->request_data['payment_method'] == 'days') {
				$_end_date = date('Y-m-d', strtotime('+'.($this->request_data['schedule_frequency']*$this->request_data['duration'].' days')));
				$_payment_method = 'PER_DAYS';
				$_payment_day = $this->request_data['schedule_frequency'];
			} else {
				$_tmp_year  = (date('m') + $this->request_data['duration']) / 12 > 1 ? date('Y') + 1 : date('Y');
				$_tmp_month = (date('m') + $this->request_data['duration']) % 12;
				$_tmp_day = $this->request_data['schedule_day'];
				if($this->request_data['duration'] != '-1') {
					$_end_date  = date('Y-m-d', strtotime(date("{$_tmp_year}-{$_tmp_month}-{$_tmp_day}")));	
				} else {
					$_end_date = '';
				}
				$_payment_method = 'PER_MONTH';
				$_payment_day = $this->request_data['schedule_day'];
			}
			$_payment_duration = $this->request_data['duration'];
		} else {
			$_is_recurring     = 'N';
			$_start_date       = date('Y-m-d');
			$_end_date 		   = '';
			$_payment_method   = 'ONCE';
			$_payment_day 	   = '';
			$_payment_duration = '';
		}
		$this->payment_id = $this->mysql->save('payment', [
			'customer_id'  => $this->request_data['customer_id'], 
			'description' => $this->request_data['description'],
			'amount' => $this->request_data['amt_total_payment'],
			'is_recurring' => $_is_recurring,
			'start_date' => $_start_date,
			'end_date' => $_end_date,
			'payment_method' => $_payment_method,
			'payment_day' => $_payment_day,
			'payment_duration' => $_payment_duration,
			'create_at' => date('Y-m-d H:i:s'),
			'update_at' => date('Y-m-d H:i:s'),
		]);

	}

	public function saveCustomer()
	{
		$customer_exist = $this->mysql->exec('SELECT * FROM customer WHERE customer_id = :id', [':id' => $this->request_data['customer_id']]);
		if (!$customer_exist) {
			$this->mysql->save('customer', [
				'customer_id'  => $this->request_data['customer_id'],
				'quickbook_id' => '',
				'create_at'    => date('Y-m-d H:i:s'),
				'update_at'    => date('Y-m-d H:i:s'),
			]);
		}
	}

	public function sendTransaction()
	{
		if(isset($this->request_data['is_recurring']) && $this->request_data['is_recurring'] == 'true') {
			$plan_request_param = [
				'planname' 		 => $this->request_data['description'],
				'plandesc' 		 => $this->request_data['description'],
				'startdate' 	 => date('m/d/Y'),
				'sequence_1' 	 => '1',
				'amount_1' 		 => sprintf('%.2f', $this->request_data['amt_total_payment']),
				'productid_1' 	 => $this->payment_id,
				'scheduletype_1' => $this->request_data['payment_method'] == 'days' ? 'custom': 'monthly',
				'scheduleday_1'  => $this->request_data['payment_method'] == 'days' ? $this->request_data['schedule_frequency'] : $this->request_data['schedule_day'],
				'duration_1' 	 => $this->request_data['duration'],
				'userecycling' 	 => 'true',
				'retrycount' 	 => '1',
				'retrytype' 	 => 'daily',
				'retryperiod' 	 => '1'
			];
            if ($this->request_data['pay_method'] == 'Credit Card') {
                $customer_request_param = [
                    'firstname' => $this->request_data['first_name'],
                    'lastname' => $this->request_data['last_name'],
                    'customer_id' => $this->request_data['customer_id'],
                    'address1' => @$this->request_data['address'],
                    'city' => @$this->request_data['city'],
                    'state' => @$this->request_data['state'],
                    'zip' => @$this->request_data['postal_code'],
                    'phone' => @$this->request_data['phone'],
                    'email' => @$this->request_data['email_address'],
                    'paytype_1' => 'creditcard',
                    'firstnam_1' => $this->request_data['first_name'],
                    'lastname_1' => $this->request_data['last_name'],
                    'address1_1' => @$this->request_data['address'],
                    'city_1' => @$this->request_data['city'],
                    'zip_1' => @$this->request_data['postal_code'],
                    'state_1' => @$this->request_data['state'],
                    'phone_1' => @$this->request_data['phone'],
                    'email_1' => @$this->request_data['email_address'],
                    'cardnum_1' => $this->request_data['card_number'],
                    'cardexp_1' => $this->request_data['month'] . substr($this->request_data['year'], 2, 2),
                ];
            }
            if ($this->request_data['pay_method'] == 'ACH') {
                $customer_request_param = [
                    'firstname' => $this->request_data['first_name'],
                    'lastname' => $this->request_data['last_name'],
                    'customer_id' => $this->request_data['customer_id'],
                    'address1' => @$this->request_data['address'],
                    'city' => @$this->request_data['city'],
                    'state' => @$this->request_data['state'],
                    'zip' => @$this->request_data['postal_code'],
                    'phone' => @$this->request_data['phone'],
                    'email' => @$this->request_data['email_address'],
                    'paytype_2' => 'ach',
                    'firstnam_2' => $this->request_data['first_name'],
                    'lastname_2' => $this->request_data['last_name'],
                    'address1_2' => @$this->request_data['address'],
                    'city_2' => @$this->request_data['city'],
                    'zip_2' => @$this->request_data['postal_code'],
                    'state_2' => @$this->request_data['state'],
                    'phone_2' => @$this->request_data['phone'],
                    'email_2' => @$this->request_data['email_address'],
                    'bankname_2' => $this->request_data['bank_name'],
                    'routing_2' => $this->request_data['routing_number'],
                    'account_2' => $this->request_data['account_number'],
                    'achaccounttype_2' => $this->request_data['account_type'],
                    'achaccountsubtype_2' => $this->request_data['account_subtype']
                ];
            }

			$plan_token 	   = $this->sparrow->postAddPaymentPlan($plan_request_param);
			$customer_response = $this->sparrow->postAddCustomer($customer_request_param);
			$assignment_token  = $this->sparrow->postAssignCustomerToPlan($customer_response['customer_token'], $plan_token, $customer_response['payment_token']);

			$response_result = [
				'plan_token'       => $plan_token,
				'customer_token'   => $customer_response['customer_token'],
				'assignment_token' => $assignment_token,
			];

            return $response_result;

		} else {
			$sale_request_param = [
                'orderdesc'  => $this->request_data['customer_id'].'_'.date('YmdHis'),
                'orderid'	 => $this->payment_id,
                'amount'	 => sprintf('%.2f', $this->request_data['amt_total_payment']),
				'firstname'	 => $this->request_data['first_name'],
				'lastname'	 => $this->request_data['last_name'],
				'address1'	 => @$this->request_data['address'],
				'city'	 	 => @$this->request_data['city'],
				'zip'		 => @$this->request_data['postal_code'],
				'state'		 => @$this->request_data['state'],
				'phone'		 => @$this->request_data['phone'],
				'email'		 => @$this->request_data['email_address'],
			];
            if ($this->request_data['pay_method'] == 'Credit Card') {
                $credit_card_param = [
                    'cardnum'	 => $this->request_data['card_number'],
                    'cardexp'	 => $this->request_data['month'].substr($this->request_data['year'], 2, 2),
                    'cvv'		 => @$this->request_data['cvv'],
                    'paytype'    => SparrowClient::PAY_TYPE_CREDITCARD
                ];

                $sale_request_param = $this->mergeTwoRequestParam($credit_card_param, $sale_request_param);
                return $this->sparrow->postSaleTransaction($sale_request_param);
            }
            if ($this->request_data['pay_method'] == 'ACH') {
                $ach_param = [
                    'bankname' => $this->request_data['bank_name'],
                    'routing' => $this->request_data['routing_number'],
                    'account' => $this->request_data['account_number'],
                    'achaccounttype' => $this->request_data['account_type'],
                    'achaccountsubtype' => $this->request_data['account_subtype']
                ];

                $sale_request_param = $this->mergeTwoRequestParam($ach_param, $sale_request_param);
                return $this->sparrow->postSaleTransaction($sale_request_param);
            }
		}
	}

}


$makePayment = new MakePayment;

try {
	$makePayment->mysql->exec("begin");

	$response_code = 0;
	$response_result = '';

	$makePayment->saveCustomer();
	$makePayment->savePayment();
    $response_result = $makePayment->sendTransaction();

	$makePayment->mysql->exec("commit");
	$response_code = 200;

} catch (Exception $e) {
	$makePayment->mysql->exec("rollback");
	$response_code = $e->getCode();
	$response_result = $e->getMessage();
}

echo json_encode([
	'response_code'   => $response_code,
	'response_result' => $response_result
]);