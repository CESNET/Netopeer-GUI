var storage;
var historyIndex = 0,
		historyUndo = 0;

var app = angular.module('NetopeerGUIApp', ['JSONedit', 'ngRoute', 'ngTraverse', 'NetopeerGUIServices'])

	.controller('ConfigurationController', function ($rootScope, $scope, $filter, $http, $route, $routeParams, $location, $window, $timeout, traverse, AjaxService) {

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
			try {
				storage = Rhaboo.perishable(window.location.href);
			} catch (e) {
				storage = Rhaboo.persistent(window.location.href);
			}

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
		$scope.datastore = 'running';
		$scope.rpcName = false;
		$scope.yangSchema = 'not loaded yet...';

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

		$scope.reload = function() {
			$route.reload();
		}

		$scope.reloadData = function (processResponseData) {
			var targetUrl;

			if (typeof processResponseData === "undefined") {
				processResponseData = true;
			}

			if (typeof $routeParams.action !== "undefined") {
				targetUrl = window.location.origin + window.location.pathname.replace('sections', 'info-page') + $routeParams.action + '/';
			} else if (typeof $routeParams.rpcName !== "undefined") {
				targetUrl = window.location.origin + window.location.pathname.replace('sections', 'sections/rpc') + $routeParams.moduleName + '/' + $routeParams.rpcName;
			} else {
				targetUrl = window.location.origin + window.location.pathname + $scope.moduleName + '/';
				if (!angular.isUndefined($routeParams.sectionName)) {
					targetUrl += $routeParams.sectionName + '/';
				}
			}

			$.netopeergui.showSpinner();
			AjaxService.reloadData(targetUrl)
				.then(function successCallback(data) {
					$scope.jsonEditable = jsonEditable = data.data.variables.jsonEditable;
					var datastore = 'running';
					if (typeof data.data.variables.datastore !== "undefined") {
						datastore = data.data.variables.datastore;
					}
					$scope.datastore = datastore;
					var rpcName = false;
					if (typeof data.data.variables.rpcName !== "undefined") {
						rpcName = data.data.variables.rpcName;
					}
					$scope.rpcName = rpcName;
					$scope.jsonData = data.data.configuration || {};

					var tmpData = data.data;
					if (!processResponseData) {
						delete tmpData.snippets['block--singleContent'];
						delete tmpData.snippets['block--state'];
					}
					$.netopeergui.processResponseData(tmpData, function() {
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

		$scope.getSchema = function() {
			var targetUrl = window.location.origin + window.location.pathname.replace('sections', 'ajax/getschema') + '?moduleName=' + $routeParams.moduleName;
			AjaxService.getSchema(targetUrl)
				.then(function successCallback(data) {
					$scope.yangSchema = data.data;
				}, function errorCallback(data) {
					$scope.yangSchema = 'Schema loading failed';
				});
		}

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

		$scope.submitRpc = function(jsonData) {
			$.netopeergui.createSpinner();
			$.netopeergui.showSpinner();
			var cleanJson = cleanupJSON(jsonData);
			cleanJson = JSON.parse(cleanJson);

			AjaxService.submitRpc(cleanJson, window.location.href)
				.then(function successCallback(data) {
					var tmpData = data.data;

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

		$scope.commitConfiguration = function() {
			$.netopeergui.createSpinner();
			$.netopeergui.showSpinner();

			AjaxService.commitConfiguration(window.location.href)
				.then(function successCallback(data) {
					var tmpData = data.data;

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
			.when('/module/:moduleName/:sectionName', {
				templateUrl: 'main/view.html',
				controller: 'ConfigurationController'
			})
			.when('/rpc/:moduleName/:rpcName', {
				templateUrl: 'main/view.html',
				controller: 'ConfigurationController'
			})
			.when('/action/:action', {
				templateUrl: 'main/view.html',
				controller: 'ConfigurationController'
			})
			.otherwise($("#block--topMenu .nth-0").attr('href').replace('#', '').replace(':', '%3A'))
		;
	}])
;