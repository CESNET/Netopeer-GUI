var storage = Rhaboo.perishable(window.location.href);
var historyIndex = 0,
		historyUndo = 0;

var app = angular.module('NetopeerGUIApp', ['JSONedit', 'ngTraverse', 'NetopeerGUIServices'])

	.controller('ConfigurationController', function ($scope, $filter, $http, $window, $timeout, traverse, AjaxService) {

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

		$scope.reload = function() {
			AjaxService.reloadData()
				.then(function successCallback(data) {
					$scope.jsonData = data.data;
				}, function errorCallback(data) {
					alert('error');
					console.log('data');
				});
		}

		$scope.$watch('jsonData', function (newValue, oldValue) {
			$scope.jsonString = JSON.stringify(newValue);
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

		var cleanupJSON = function(jsonData) {
			var cleanJson = $filter('json')(jsonData);
			var jsonObj = angular.fromJson(cleanJson);
			var removeSchemaNodes = function(obj) {
				traverse(obj).forEach(function (element, index, array) {
					if (typeof this.key !== "undefined") {
						var schemaPath = this.path.slice(0, -1);
						var attrPath = this.path.slice(0, -1);
						var attrChildPath = this.path.slice(0); // clone an array
						schemaPath.push('$@'+this.key);
						attrPath.push('@'+this.key);
						attrChildPath.push('@');
						var schema = traverse(obj).get(schemaPath);
						var attr = traverse(obj).get(attrPath);
						var attrChild = traverse(obj).get(attrChildPath);
						//if (this.key == 'groups') {
						//	console.log(attrChildPath);
						//	console.log(attrChild);
						//}
						if ((!angular.isUndefined(attr) || !angular.isUndefined(attrChild)) || (!angular.isUndefined(schema) && schema['iskey'] == true)) {
							// leave key elements

						} else if (this.key.indexOf('$@') !== -1 || this.key.indexOf('@') !== -1) {
							// leave attributes

						} else if (this.notRoot && !angular.isUndefined(this.parent.key) && this.path.toString().indexOf('@') === -1 && this.isLeaf) {
							//this.remove();
						}
					}
				});

				// remove schema nodes
				traverse(obj).forEach(function (element, index, array) {
					if (typeof this.key !== "undefined") {
						if (this.key.indexOf('$@') !== -1 || this.key.indexOf('netopeergui:status') !== -1) {
							this.remove();
						}
					}
				});

				// remove empty atribute nodes
				traverse(obj).forEach(function (element, index, array) {
					if (typeof this.key !== "undefined") {
						if ((this.key.indexOf('@') !== -1 && this.isLeaf) || (Object.prototype.toString.call(this.node) == '[object Array]' && !this.node.length)){
							this.delete(true);
						}
					}
				});
			};
			removeSchemaNodes(jsonObj);
			cleanJson = $filter('json')(jsonObj);
			return cleanJson;
		};

		$scope.download = function (jsonData) {
			var cleanJson = cleanupJSON(jsonData);
			$window.open("data:application/json;charset=utf-8," + encodeURIComponent(cleanJson));
		};

		$scope.undo = function() {
			var json = storage.revisions[historyIndex - historyUndo - 2];
			isUndo = true;
			$scope.jsonString = '{}';

			$timeout(function() {
				isUndo = true;
				$scope.jsonString = json;
				//$scope.jsonData = JSON.parse(json);
				historyUndo++;
			}, 1);
		};

		$scope.redo = function() {
			var json = storage.revisions[historyIndex - historyUndo];
			isRedo = true;
			$scope.jsonString = '{}';

			$timeout(function() {
				isRedo = true;
				$scope.jsonString = json;
				//$scope.jsonData = JSON.parse(json);
				historyUndo--;
			}, 1);
		};

		$scope.submitConfiguration = function(jsonData) {
			$.netopeergui.createSpinner();
			$.netopeergui.showSpinner();
			var cleanJson = cleanupJSON(jsonData);
			cleanJson = JSON.parse(cleanJson);
			AjaxService.submitConfiguration(cleanJson)
				.then(function successCallback(data) {
					$.netopeergui.processResponseData(data.data, function() {
						//$scope.reload();
						$.netopeergui.hideSpinner();
					});
				}, function errorCallback(data) {
					alert('error');
					console.log(data);
					$.netopeergui.hideSpinner();
				});
		};
	}
);