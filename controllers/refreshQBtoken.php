<?php namespace controllers;


require_once dirname(__FILE__).'/../clients/QuickBooks/QuickbooksClient.php';
require_once dirname(__FILE__).'/../vendor/autoload.php';

use QuickBooks\QuickBooksClient;
use Exception;

	
try {
	$token_arr=array();
	$quick_book = new QuickBooksClient();
	$response = $quick_book->refreshToken();
	if ($response->ErrorCode != '0')
	{
		echo $response->ErrorMessage.' (Error_code:'.$response->ErrorCode.')';		
	}
	else
	{
		$access_token = [
			'oauth_token' => $response->OAuthToken,
			'oauth_token_secret' => $response->OAuthTokenSecret,
		];
		$token_arr['token'] = serialize( $access_token );
		$quick_book->saveToken($token_arr);
		echo "Reconnect successful!";
	}
  } catch(Exception $e) {
	print_r($e);
}
