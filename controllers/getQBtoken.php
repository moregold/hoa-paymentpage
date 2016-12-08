<?php namespace controllers;


require_once dirname(__FILE__).'/../clients/QuickBooks/QuickbooksClient.php';
require_once dirname(__FILE__).'/../vendor/autoload.php';

use QuickBooks\QuickBooksClient;
use Exception;


$quick_book = new QuickBooksClient();
$quick_book -> requestAccessToken();
