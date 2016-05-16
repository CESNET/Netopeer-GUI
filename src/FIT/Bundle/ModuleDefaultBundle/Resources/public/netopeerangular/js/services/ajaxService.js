var services = angular.module('NetopeerGUIServices', [])

.service('AjaxService', function ($http) {
	$http.defaults.cache = true;

	this.reloadData = function(targetUrl) {
		var url = targetUrl || window.location.href;
		return $http({
			url: url + '?angular=true',
			method: 'GET'
		});
	};

	this.loadSchema = function(connIds, filters) {
		return $http({
			url: baseURL + '/ajax/schema/',
			method: 'POST',
			data: {'angular': true, 'connIds': connIds, 'filters': filters}
		});
	};

	this.submitConfiguration = function(cleanJson) {
		return $http({
			url: window.location.href + '?angular=true',
			method: 'POST',
			data: cleanJson
		});
	};
});