var storage = Rhaboo.perishable("Some unique name");
var historyIndex = 0,
		historyUndo = 0;

var app = angular.module('NetopeerGUIApp', ['JSONedit', 'ngTraverse'])

	.controller('ConfigurationController', function ($scope, $filter, $http, $window, $timeout, traverse) {

		storage.write('revisions', []);
		storage.erase('revisions');
		storage.write('revisions', []);

		$scope.hasUndo = function() {
			return (historyIndex - historyUndo - 1) <= 0;
		};
		$scope.hasRedo = function() {
			return (historyIndex - historyUndo) >= storage.revisions.length;
		};
		var isUndo = false,
				isRedo = false;

		$http.get('data/real.json').success(function (data) {
			$scope.jsonData = data;
		});

		$scope.$watch('jsonData', function (newValue, oldValue) {
			$timeout(function() {
				$scope.jsonString = JSON.stringify(newValue);
			}, 100);
			if ( !isUndo && !isRedo && newValue !== oldValue ) {
				historyIndex = historyIndex - historyUndo;
				historyUndo = 0;

				// prevent the future
				storage.revisions.slice(0, historyIndex + 1);
				storage.revisions.push(JSON.stringify(newValue));

				historyIndex++;
			}
			isUndo = false;
			isRedo = false;
		}, true);
		$scope.$watch('jsonString', function (json) {
			try {
				$scope.jsonData = JSON.parse(json);
				$scope.wellFormed = true;
			} catch (e) {
				$scope.wellFormed = false;
			}
		}, true);

		$scope.download = function (jsonData) {
			var cleanJson = $filter('json')(jsonData);
			var jsonObj = angular.fromJson(cleanJson);
			var removeSchemaNodes = function(obj) {
				traverse(obj).forEach(function (element, index, array) {
					if (typeof this.key !== "undefined") {
						if (this.key.indexOf('$@') !== -1) {
							this.remove();
						}
					}
				});
			};
			removeSchemaNodes(jsonObj);
			cleanJson = $filter('json')(jsonObj);
			$window.open("data:application/json;charset=utf-8," + encodeURIComponent(cleanJson));
		};

		$scope.undo = function() {
			var json = storage.revisions[historyIndex - historyUndo - 2];
			isUndo = true;
			$scope.jsonData = JSON.parse(json);
			historyUndo++;
		};

		$scope.redo = function() {
			var json = storage.revisions[historyIndex - historyUndo];
			isRedo = true;
			$scope.jsonData = JSON.parse(json);
			historyUndo--;
		};
	}
);