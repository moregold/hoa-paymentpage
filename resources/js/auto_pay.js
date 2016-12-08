app.controller('autoPay', function($scope, $http, $location, $interval, ajax) {

	$scope.auto_pay_data = [];
	ajax.get('./controllers/getCustomerAutopayList.php', {}, function(response){
		$scope.auto_pay_data = response.data.response_result;
	}, function(response){
		alert('Fail to get data.');
	});

});