<?php namespace QuickBooks;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Uri;
use Exception;
use OAuth;


/**
 *
 * Doc: https://developer.intuit.com/docs/api/accounting/invoice
 		https://developer.intuit.com/docs/0100_quickbooks_online/0300_references/rest_essentials_for_the_quickbooks_api
 * Online test: https://developer.intuit.com/v2/apiexplorer?apiname=V3QBO#?id=Invoice
 *
 */
class QuickBooksClient
{
	public $oauth_request_url;
	public $oauth_access_url;
	public $oauth_authorise_url;
	public $callback_url;
	public $token_file;
	// The url to this page. it needs to be dynamic to handle runnable's dynamic urls
	

	private $client;
	private $request_uri;
	private $config;
	private $refresh_url;
	public function __construct()
	{
		$config = require dirname(__FILE__).'/../../config/config.php';
		$this->token_file = dirname(__FILE__).'/../../config/accessToken.php';
		$this->request_uri = 'https://quickbooks.api.intuit.com/v3/company/'.$config['quickbooks']['company_id'];
		$this->config = $config;
		$this->oauth_request_url = 'https://oauth.intuit.com/oauth/v1/get_request_token';
		$this->oauth_access_url = 'https://oauth.intuit.com/oauth/v1/get_access_token';
		$this->oauth_authorise_url = 'https://appcenter.intuit.com/Connect/Begin';
		$this->callback_url = 'http://'.$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];
		$this->refresh_url = 'https://appcenter.intuit.com/api/v1/Connection/Reconnect';

	}

	public function request($request_param=[], $type)
	{	
		$access_token = $this->readToken();
		$oauth = new OAuth($this->config['quickbooks']['oauth_key'], $this->config['quickbooks']['oauth_secret_key']);
		$oauth->setToken($access_token['oauth_token'], $access_token['oauth_token_secret']);
		$oauth->enableDebug();
		$oauth->setAuthType(OAUTH_AUTH_TYPE_AUTHORIZATION);
		$oauth->disableSSLChecks();
		$data = json_encode($request_param);
		$requestUri = $this->request_uri.'/'.$type.'?minorversion=3';
		$httpHeaders = array(
			'host'          => parse_url($requestUri, PHP_URL_HOST), 
			'user-agent'    => 'sparrow',
			'accept'        => 'application/json',
			'connection'    => 'close',
			'content-type'  => 'application/json',
			'content-length'=> strlen($data)
		);
		$oauth->fetch($requestUri, $data, 'POST', $httpHeaders);
		$response=json_decode($oauth->getLastResponse());
		return $response;
	}


	public function editInvoice($data = [])
	{
		$request_param = [
			"Line" => [
				'0' => [
					"Amount" 	 => $data['amount'],
					"DetailType" => "SalesItemLineDetail",
					"SalesItemLineDetail" => [
						"ItemRef" => [
						"value"   => $data['product_id'],
						"name"    => $data['product_name']
						]
					]
				]
			],
			"CustomerRef" => [
				"value" => $data['customer_id']
			]
		];
		$response = $this->request($request_param, 'invoice');
		return $response;
	}


	public function createCustomer($data = [])
	{
		$request_param = [
			"Title" => $data['title'],
			"GivenName" => $data['given_name'],
			"FamilyName" => $data['family_name'],
			'DisplayName' =>$data['display_name']
		];
		$response = $this->request($request_param, 'customer');
		return $response;
	}


	public function requestAccessToken(){
		session_start();
		if ( isset($_GET['start'] ) ) {
			unset($_SESSION['token']);
		}
		try {
			$oauth = new OAuth( $this->config['quickbooks']['oauth_key'], $this->config['quickbooks']['oauth_secret_key'], OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
			$oauth->enableDebug();
			$oauth->disableSSLChecks(); 
			if (!isset( $_GET['oauth_token'] ) && !isset($_SESSION['token']) ){
				// step 1: get request token from Intuit
				$request_token = $oauth->getRequestToken( $this->oauth_request_url, $this->callback_url );
				$_SESSION['secret'] = $request_token['oauth_token_secret'];
				// step 2: send user to intuit to authorize 
				header('Location: '. $this->oauth_authorise_url .'?oauth_token='.$request_token['oauth_token']);
			}
			if ( isset($_GET['oauth_token']) && isset($_GET['oauth_verifier']) ){
				// step 3: request a access token from Intuit
			    $oauth->setToken($_GET['oauth_token'], $_SESSION['secret']);
				$access_token = $oauth->getAccessToken( $this->oauth_access_url );
				$_SESSION['token'] = serialize( $access_token );
			    $_SESSION['realmId'] = $_REQUEST['realmId'];
			    $_SESSION['dataSource'] = $_REQUEST['dataSource'];
				
				$token = $_SESSION['token'] ;
				$realmId = $_SESSION['realmId'];
				$dataSource = $_SESSION['dataSource'];
				$secret = $_SESSION['secret'] ;
				$this->saveToken($_SESSION);
				echo "Get token success!";
			}
		} catch(Exception $e) {
			echo '<a href="http://'.$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'].'?start=1">Get token failed, try again.('.$e->getMessage().')</a>';
		}
	}

	public function refreshToken(){
		try {
			$access_token = $this->readToken();
			$oauth = new OAuth($this->config['quickbooks']['oauth_key'], $this->config['quickbooks']['oauth_secret_key']);
			$oauth->setToken($access_token['oauth_token'], $access_token['oauth_token_secret']);
			$oauth->enableDebug();
			$oauth->setAuthType(OAUTH_AUTH_TYPE_AUTHORIZATION);
			$oauth->disableSSLChecks();
			$data = '';
			$requestUri = $this->refresh_url;
			$httpHeaders = array(
				'host'          => parse_url($requestUri, PHP_URL_HOST), 
				'user-agent'    => 'sparrow',
				'accept'        => 'application/json',
				'connection'    => 'close',
				'content-type'  => 'application/json',
				'content-length'=> strlen($data)
			);
			$oauth->fetch($requestUri, '', 'GET', $httpHeaders);
			return json_decode($oauth->getLastResponse()); 
		} catch(OAuthException $e) {
			print_r($e);
		}
	}

	public function saveToken($token_arr){
		file_put_contents($this->token_file, serialize($token_arr));
	}

	public function readToken(){
		if(!file_exists($this->token_file)){
			throw new \Exception("Can't get accessToken file.", 0);
		}
		$access_array = unserialize(file_get_contents($this->token_file));
		$access_token = unserialize($access_array['token']);
		return $access_token;
	}

}