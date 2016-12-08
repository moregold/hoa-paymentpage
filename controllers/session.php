<?php 
session_start();
foreach ($_POST as $key => $value) {
	if($value && $value!='null') {
		$_SESSION[ $key ] = $value;
	}
}
echo json_encode( $_SESSION );