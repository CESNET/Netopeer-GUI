var app = angular.module('NetopeerGUIApp', ['JSONedit'])

.controller('ConfigurationController', function($scope, $filter, $http, $window) {

	$http.get('data/get.json').success(function(data) {
		$scope.jsonData = data;
	});

	$scope.$watch('jsonData', function (json) {
		$scope.jsonString = $filter('json')(json);
	}, true);
	$scope.$watch('jsonString', function (json) {
		try {
			$scope.jsonData = JSON.parse(json);
			$scope.wellFormed = true;
		} catch (e) {
			$scope.wellFormed = false;
		}
	}, true);

	$scope.download = function(jsonData) {
		//var result = JSON.stringify(jsonData);
		//result = result.replace(/xmlns:all/g, 'xmlns');
		//result = result.replace(/\s[^(xmlns)]\w*=\"[a-zA-Z0-9\s\d\.\(\)\\\/\-,\'\|:]*\"/gim, ''); // remove all attributes
		var cleanJson = JSON.stringify(jsonData);
		$window.open("data:application/json;charset=utf-8," + encodeURIComponent(cleanJson));
	};
}
);