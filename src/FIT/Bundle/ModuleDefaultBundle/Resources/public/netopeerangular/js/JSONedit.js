var storage;
var historyIndex = 0,
		historyUndo = 0;

var app = angular.module('NetopeerGUIApp', ['JSONedit', 'ngRoute', 'ngTraverse', 'NetopeerGUIServices'])

	.controller('ConfigurationController', function ($rootScope, $scope, $filter, $http, $routeParams, $location, $window, $timeout, traverse, AjaxService) {

		$rootScope.$on("$routeChangeStart", function (event, next, current) {
			$.netopeergui.createSpinner();
			$.netopeergui.showSpinner();
		});

		$rootScope.$on("$routeChangeSuccess", function (event, next, current) {
			$.netopeergui.hideSpinner();
			$("#block--topMenu a.active").removeClass('active');
			$('#block--topMenu a[href="'+window.location.hash+'"]').addClass('active');
		});

		var resetRevisions = function() {
			storage = Rhaboo.perishable(window.location.href);
			historyIndex = 0;
			historyUndo = 0;
			storage.write('revisions', []);
			storage.erase('revisions');
			storage.write('revisions', []);
		};
		var revisionsExists = function() {
			return !(angular.isUndefined(storage) || angular.isUndefined(storage.revisions));
		}
		$scope.moduleName = $routeParams.moduleName;

		$scope.hasUndo = function() {
			return (historyIndex - historyUndo - 1) <= 0;
		};
		$scope.hasRedo = function() {
			if (!revisionsExists()) {
				return true;
			}
			return (historyIndex - historyUndo) >= storage.revisions.length;
		};
		var isUndo = false,
				isRedo = false;

		$scope.reloadData = function () {
			//console.log('reload');
			var targetUrl;
			if (typeof $routeParams.action !== "undefined") {
				targetUrl = window.location.origin + window.location.pathname.replace('sections', 'info-page') + $routeParams.action + '/';
			} else {
				targetUrl = window.location.origin + window.location.pathname + $scope.moduleName + '/';
			}

			$.netopeergui.showSpinner();
			AjaxService.reloadData(targetUrl)
				.then(function successCallback(data) {
					$scope.jsonEditable = jsonEditable = data.data.variables.jsonEditable;
					//$scope.jsonString = JSON.stringify(data.data.configuration);
					$scope.jsonData = data.data.configuration;

					var tmpData = data.data;
					$.netopeergui.processResponseData(tmpData, function() {
						//$scope.reload();
						$.netopeergui.hideSpinner();
					});
					//console.log('success reload');
				}, function errorCallback(data) {
					//$scope.jsonData = {};
					//console.log(data);
					//alert('error1');
					$.netopeergui.hideSpinner();
				});
		};
		$scope.resetRevisions = resetRevisions;

		$scope.$watch('jsonData', function (newValue, oldValue) {
			//$scope.jsonString = JSON.stringify(newValue);
			if ( !isUndo && !isRedo && newValue !== oldValue ) {
				historyIndex = historyIndex - historyUndo;
				historyUndo = 0;

				// prevent the future
				if (revisionsExists()) {
					storage.revisions.slice(0, historyIndex + 1);
					storage.revisions.push(JSON.stringify(newValue));
				}

				historyIndex++;
			}
			isUndo = false;
			isRedo = false;
		}, true);

		//$scope.$watch('jsonString', function (json) {
		//	try {
		//		$scope.jsonData = JSON.parse(json);
		//		$scope.wellFormed = true;
		//	} catch (e) {
		//		$scope.wellFormed = false;
		//	}
		//}, true);

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

						} else if (this.notRoot && !angular.isUndefined(this.parent.key) && this.path.toString().indexOf('@') === -1 && this.isLeaf && !Array.isArray(this.parent.node)) {
							this.remove();
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
			//$scope.jsonString = '{}';
			$scope.jsonData = {};

			$timeout(function() {
				isUndo = true;
				//$scope.jsonString = json;
				$scope.jsonData = JSON.parse(json);
				historyUndo++;
			}, 1);
		};

		$scope.redo = function() {
			var json = storage.revisions[historyIndex - historyUndo];
			isRedo = true;
			//$scope.jsonString = '{}';
			$scope.jsonData = {};

			$timeout(function() {
				isRedo = true;
				//$scope.jsonString = json;
				$scope.jsonData = JSON.parse(json);
				historyUndo--;
			}, 1);
		};

		$scope.submitConfiguration = function(jsonData) {
			$.netopeergui.createSpinner();
			$.netopeergui.showSpinner();
			var cleanJson = cleanupJSON(jsonData);
			cleanJson = JSON.parse(cleanJson);

			AjaxService.submitConfiguration(cleanJson, window.location.href)
				.then(function successCallback(data) {
					var tmpData = data.data;
					//console.log(tmpData);
					if (typeof tmpData.snippets['block--state'] !== "undefined") {
						delete(tmpData.snippets['block--state']);
					}
					$.netopeergui.processResponseData(tmpData, function() {
						//$scope.reload();
						$.netopeergui.hideSpinner();
					});
				}, function errorCallback(data) {
					//console.log(data);
					//alert('error2');
					$.netopeergui.hideSpinner();
				});
		};
	})

	.config(['$rootScopeProvider', '$routeProvider', function ($rootScopeProvider, $routeProvider) {
		$rootScopeProvider.digestTtl(20);

		$routeProvider
			.when('/module/:moduleName', {
				templateUrl: 'main/view.html',
				controller: 'ConfigurationController'
			})
			.when('/action/:action', {
				templateUrl: 'main/view.html',
				controller: 'ConfigurationController'
			})
			.otherwise($("#block--topMenu .nth-0").attr('href').replace('#', ''))
		;
	}])
;