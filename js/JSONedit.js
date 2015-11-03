'use strict';

var app = angular.module('exampleApp', ['JSONedit']);

function MainViewCtrl($scope, $filter, $http, $window) {

	$http.get('data/get2.xml').success(function(data) {
		var xml = data;
		// example JSON
		$scope.jsonData = JXON.stringToJs(xml);
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
		var result = JXON.jsToString(jsonData);
		result = result.replace(/xmlns:all/g, 'xmlns');
		result = result.replace(/\s[^(xmlns)]\w*=\"[a-zA-Z0-9\s\d\.\(\)\\\/\-,\'\|:]*\"/gim, ''); // remove all attributes
		$window.open("data:text/xml;charset=utf-8," + encodeURIComponent(result));
	};
}
