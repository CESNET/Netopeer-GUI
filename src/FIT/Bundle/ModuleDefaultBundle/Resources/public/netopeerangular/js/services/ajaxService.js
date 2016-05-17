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
			data: {'angular': true, 'connIds': connIds, 'filters': filters}
		});
	};

	this.submitConfiguration = function(cleanJson, targetUrl) {
		return $http({
			url: targetUrl || window.location.href,
			method: 'POST',
			data: cleanJson
		});
	};
});