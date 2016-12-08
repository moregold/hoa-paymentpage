getUrlParam = function(name) {
	var reg = new RegExp("(^|&)" + name + "=([^&]*)(&|$)");
	var r = window.location.search.substr(1).match(reg);
	if (r != null) return unescape(r[2]); return null;
}

var app = angular.module('paymentPage', []);
app.service('ajax', function($http) {
    this.post = function (url, param, success_callback, fail_callback) {
		$http({
			'url' : url,
			'method' : 'POST',
			'data' : param,
			'headers':{
				'Content-Type': 'application/x-www-form-urlencoded'
			},
			'transformRequest' : function(obj) {  
				var str = [];  
				for(var p in obj){  
					str.push(encodeURIComponent(p) + "=" + encodeURIComponent(obj[p]));  
				}  
				return str.join("&");  
			}  
		}).then(success_callback, fail_callback);
	}
	this.get = function (url, param, success_callback, fail_callback) {
		$http({
			'url' : url,
			'method' : 'GET',
			'data' : param,
		}).then(success_callback, fail_callback);
	}
});