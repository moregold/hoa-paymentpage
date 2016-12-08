app.controller('makePayment', function($scope, $http, $location, $interval, ajax) {

	$scope.form_data = {};
	$scope.form_data.is_recurring  	 	 = getUrlParam('recurring')=='true' ? true : false;
	$scope.form_data.schedule_frequency  = '30';
	$scope.form_data.schedule_day 	  	 = '1';
	$scope.form_data.duration 		  	 = '3';
	$scope.form_data.payment_method	 	 = 'days';
	$scope.submit_lock		  			 = false;
	$scope.btn_html = 'SUBMIT';
	$scope.agreement = false;
	$scope.paymentError = false;
	$scope.paymentSuccess = false;
	$scope.termsConditions = false;
	$scope.returnPolicy = false;
	$scope.ach = false;
	$scope.termsConditionsURL = './terms-conditions.html';
	$scope.returnPolicyURL = './return-policy.html';
	$scope.achURL = './ach-disclosure.html';
	$scope.credit_card_active = true;
	$scope.ach_active = false;
	$scope.ach_agreement = false;
	$scope.invoice_status = 'Total Due';

	var session_data = {
		'amtPayment'  : getUrlParam('amtPayment'),
		'description' : getUrlParam('description'),
		'customerId'  : getUrlParam('customerId')
	};
	ajax.post('./controllers/session.php', session_data, function(response){
		$scope.form_data.amt_total_payment = response.data.amtPayment;
		$scope.form_data.customer_id = response.data.customerId;
		$scope.form_data.description = response.data.description;
		if(!$scope.form_data.amt_total_payment || !$scope.form_data.customer_id || !$scope.form_data.description)
			alert('Missing url param:\namtPayment, description, customerId');
	}, function(response){
	});

	$scope.toggleShow = function(model_name) {
		eval('$scope.'+model_name+' = !$scope.'+model_name+';');
	}

	$scope.submit = function() {
		$scope.paymentError = false;
		$scope.paymentSuccess = false;
		if ($scope.ach_active) {
			$scope.form_data.pay_method = 'ACH';
			$scope.receipt_acct = $scope.form_data.account_number.slice(-4);
		} else {
			$scope.form_data.pay_method = 'Credit Card';
			$scope.receipt_acct = $scope.form_data.card_number.slice(-4);
		}
		/**if($scope.submit_lock)
			return false;
		$scope.submit_lock = true;**/
		$scope.btn_html = 'BEING PAID...';
		ajax.post('./controllers/makePayment.php', $scope.form_data, function(response){
			if(response.data.response_code == '200') {
				$scope.invoice_id = response.data.response_result.invoice_id;
				$scope.paymentSuccess = true;
				$scope.invoice_status = 'Total Paid';
			} else {
				$scope.message = 'Oops:\n'+response.data.response_result;
				$scope.paymentError = true;
			}
			$scope.btn_html = 'SUBMIT';
			// $scope.submit_lock = false;
		}, function(response){
			// $scope.submit_lock = false;
			$scope.message = 'PAYMENT FAILURE';
			$scope.paymentError = true;
			$scope.btn_html = 'SUBMIT';
		});
	}

});