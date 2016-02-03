'use strict';

var counts = {
  object: 0
};

var NetopeerGUI = angular.module('JSONedit', ['ui.sortable', 'ui.bootstrap', 'configurationTemplates']);

NetopeerGUI.directive('ngModelOnblur', function() {
    // override the default input to update on blur
    // from http://jsfiddle.net/cn8VF/
    return {
        restrict: 'EAC',
        require: 'ngModel',
        link: function(scope, elm, attr, ngModelCtrl) {
            if (attr.type === 'radio' || attr.type === 'checkbox') return;
            
            elm.unbind('input').unbind('keydown').unbind('change');
            elm.bind('blur', function() {
                scope.$apply(function() {
                    ngModelCtrl.$setViewValue(elm.val());
                });         
            });
        }
    };
})
.directive('json', function($compile, $log) {
  return {
    restrict: 'E',
    scope: {
      child: '=',
      type: '@',
      defaultCollapsed: '=',
      hideCollapse: '='
    },
    controller: function($scope) {
      $scope.getTemplateUrl = function(type) {
          if (typeof type === 'undefined') {
              type = $scope.type;
          }
          //return baseURL + '/bundles/fitmoduledefault/netopeerangular/templates/types/'+type+'.html';
          return 'types/'+type+'.html';
      };
    },
    link: function(scope, element, attributes) {
        var stringName = "Text";
        var objectName = "Object";
        var arrayName = "Array";
        var numberName = "Number";
        var urlName = "Url";
        var refName = "Reference";
        var boolName = "Boolean";
        var literalName = "Literal";

        scope.valueTypes = [stringName, objectName, arrayName, numberName, urlName, refName, boolName, literalName];
        scope.stringName = stringName;
        //scope.valueTypes = [stringName, objectName, arrayName, refName, boolName];
        scope.sortableOptions = {
            axis: 'y'
        };
        if (scope.$parent.defaultCollapsed === undefined) {
            scope.collapsed = false;
        } else {
            scope.collapsed = scope.defaultCollapsed;
        }
        if (scope.collapsed) {
            scope.chevron = "fa-plus-square-o";
        } else {
            scope.chevron = "fa-minus-square-o";
        }
        

        //////
        // Helper functions
        //////
        var isNumberType = function(type) {
            return type.indexOf('int') >= 0;
        };

        var isUrlType = function(type) {
            return type === "inet:uri";
        };

        var getType = function(key, obj, parent) {
            var schema = getSchemaFromKey(key, parent);
            // get custom yang datatype
            var type = Object.prototype.toString.call(obj);

            if (type === "[object Object]") {
                return objectName;
            } else if(type === "[object Array]"){
                return arrayName;
            }

            if (schema && typeof schema['type'] !== "undefined") {
                type = schema['type'];
            }

            if(type === "Boolean" || type === "[object Boolean]"){
                return boolName;
            } else if(isNumberType(type) || type === "[object Number]"){
                // TODO: check range
                return numberName;
            } else {
                return literalName;
            }
        };
        var isNumber = function(n) {
          return !isNaN(parseFloat(n)) && isFinite(n);
        };
        scope.getType = function(key, obj, parent) {
            return getType(key, obj, parent);
        };

        var getSchemaFromKey = function(key, parent) {
            if (typeof parent['$@'+key] === "undefined") {
                return false;
            }
            return parent['$@'+key];
        };

        scope.isConfig = function(key, parent) {
            var schema = getSchemaFromKey(key, parent);
            return (schema && typeof schema['config'] !== "undefined" && schema['config'] === true);
        };

        scope.editBarVisible = function(key, parent) {
            var schema = getSchemaFromKey(key, parent);
            if (schema && typeof schema['type'] !== "undefined") {
                var type = schema['type'];
                return (type == "list" || type == "leaf-list" || type == "container");
            }

            return false;
        };
        scope.toggleCollapse = function() {
            if (scope.collapsed) {
                scope.collapsed = false;
                scope.chevron = "fa-minus-square-o";
            } else {
                scope.collapsed = true;
                scope.chevron = "fa-plus-square-o";
            }
        };
        scope.moveKey = function(obj, key, newkey) {
            //moves key to newkey in obj
            if (key !== newkey) {
                obj[newkey] = obj[key];
                delete obj[key];
            }
        };
        scope.deleteKey = function(key, obj, parent) {
            if (getType(key, obj, parent) == "Object") {
                if( confirm('Delete "'+key+'" and all it contains?') ) {
                    delete obj[key];
                }
            } else if (getType(key, obj, parent) == "Array") {
                if( confirm('Delete "'+obj[key]+'"?') ) {
                    obj.splice(key, 1);
                }
            } else {
                console.error("object to delete from was " + obj);
            }
        };
        scope.addItem = function(key, obj, parent) {
            var type = getType(key, obj, parent);
            if (type == "Object") {
                // check input for key
                if (parent.keyName == undefined || parent.keyName.length == 0){
                    alert("Please fill in a name");
                } else if (parent.keyName.indexOf("$") == 0){
                    alert("The name may not start with $ (the dollar sign)");
                } else if (parent.keyName.indexOf("_") == 0){
                    alert("The name may not start with _ (the underscore)");
                } else {
                    if (obj[parent.keyName]) {
                        if( !confirm('An item with the name "'+parent.keyName
                            +'" exists already. Do you really want to replace it?') ) {
                            return;
                        }
                    }
                    // add item to object
                    switch(parent.valueType) {
                        case stringName: obj[parent.keyName] = parent.valueName ? parent.possibleNumber(parent.valueName) : "";
                                        break;
                        case objectName:  obj[parent.keyName] = {};
                                        break;
                        case arrayName:   obj[parent.keyName] = [];
                                        break;
                        case refName: obj[parent.keyName] = {"Reference!!!!": "todo"};
                                        break;
                        case boolName: obj[parent.keyName] = false;
                                        break;
                    }
                    //clean-up
                    parent.keyName = "";
                    parent.valueName = "";
                    parent.showAddKey = false;
                }
            } else if (type == "Array") {
                // add item to array
                switch(parent.valueType) {
                    case stringName: obj.push(parent.valueName ? parent.valueName : "");
                                    break;
                    case objectName:  obj.push({});
                                    break;
                    case arrayName:   obj.push([]);
                                    break;
                    case boolName:   obj.push(false);
                                    break;
                    case refName: obj.push({"Reference!!!!": "todo"});
                                    break;
                }
                parent.valueName = "";
                parent.showAddKey = false;
            } else {
                console.error("object to add to was " + obj);
            }
        };
        scope.possibleNumber = function(val) {
            return isNumber(val) ? parseFloat(val) : val;
        };

        //////
        // Template Generation
        //////

        // Note:
        // sometimes having a different ng-model and then saving it on ng-change
        // into the object or array is necessary for all updates to work

        var newElement = '<div ng-include="getTemplateUrl()"></div>';
        newElement = angular.element(newElement);
        $compile(newElement)(scope);
        element.replaceWith ( newElement );
    }
  };
})
.filter('skipAttributes', function() {
    return function(items) {
        var result = {};
        angular.forEach(items, function(value, key) {
            if (key.indexOf('@') !== 0) {
                result[key] = value;
            }
        });
        return result;
    };
});
