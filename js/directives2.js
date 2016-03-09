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
      hideCollapse: '=',
      key: '='
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
        var enumerationName = "Enumeration";
        var literalName = "Literal";

        scope.valueTypes = [stringName, objectName, arrayName, numberName, urlName, refName, boolName, enumerationName, literalName];
        scope.stringName = stringName;
        //scope.valueTypes = [stringName, objectName, arrayName, refName, boolName];
        scope.sortableOptions = {
            axis: 'y',
            update: function(e, ui) {
                setIetfOperation('replace', scope.$parent.$parent.newkey, scope.$parent.$parent.$parent.$parent.$parent.child);
            }
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

            var eltype = getEltype(key, parent);

            if (eltype === "container") {
                return objectName;
            } else if (eltype === "leaf-list") {
                return arrayName;
            } else if (type === "[object Array]"){
                return arrayName;
            } else if (type === "Boolean" || type === "[object Boolean]") {
                return boolName;
            } else if (type === 'enumeration') {
                return enumerationName;
            } else if (isNumberType(type) || type === "[object Number]") {
                return numberName;
            } else {
                return stringName;
            }
        };
        var getEltype = function(key, parent) {
            var schema = getSchemaFromKey(key, parent);
            // get custom yang datatype
            var eltype = '';

            if (schema && typeof schema['eltype'] !== "undefined") {
                eltype = schema['eltype'];
            }

            return eltype;
        };
        var isNumber = function(n) {
          return !isNaN(parseFloat(n)) && isFinite(n);
        };

        scope.getType = getType;
        scope.log = function(data) {
            console.log(data);
        };

        var getSchemaFromKey = function(key, parent) {
            if (typeof parent === "undefined" || parent['$@'+key] === "undefined") {
                return false;
            }
            return parent['$@'+key];
        };
        scope.getSchemaFromKey = getSchemaFromKey;

        scope.isConfig = function(key, parent) {
            var schema = getSchemaFromKey(key, parent);
            return (schema && typeof schema['config'] !== "undefined" && schema['config'] === true);
        };

        scope.editBarVisible = function(key, parent) {
            var eltype = getEltype(key, parent);
            if (eltype) {
                return (eltype == "list" || eltype == "leaf-list" || eltype == "container");
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
        scope.deleteKey = function(key, obj, parent) {
            if (getType(key, obj, parent) == "Object") {
                if( confirm('Delete "'+key+'" and all it contains?') ) {
                    setIetfOperation('remove', key, obj);
                    //delete obj[key]; // TODO delete children

                }
            } else if (getType(key, obj, parent) == "Array") {
                if( confirm('Delete "'+obj[key]+'"?') ) {
                    setIetfOperation('remove', key, obj);
                    //obj.splice(key, 1); // TODO delete children
                }
            } else {
                console.error("object to delete from was " + obj);
            }
        };
        scope.addItem = function(key, obj, parent) {
            var type = getType(parent.keyName, undefined, obj);
            var parentType = getType(parent.$parent.$parent.$parent.$parent.key, obj);

            if (parentType == "Object") {
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
                        } else {
                            removeIetfOperation(parent.keyName, obj, parent);
                            setIetfOperation('create', key, obj, parent);
                        }
                    }
                    // add item to object
                    switch(type) {
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
                        default:
                            console.log('not implemented ' + parent.valueType); // TOOD
                    }
                    setIetfOperation('create', parent.keyName, obj);
                    //clean-up
                    parent.keyName = "";
                    parent.valueName = "";
                    parent.showAddKey = false;
                }
            } else if (parentType == "Array") {
                // add item to array
                switch(type) {
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
                    default:
                        console.log('2not implemented ' + parent.valueType); // TOOD
                }
                setIetfOperation('replace', parent.$parent.$parent.$parent.$parent.key, parent.$parent.$parent.$parent.$parent.$parent.$parent.child); // TODO replace order in array
                parent.valueName = "";
                parent.showAddKey = false;
            } else {
                console.error("object to add to was " + obj);
            }
        };
        scope.possibleNumber = function(val) {
            return isNumber(val) ? parseFloat(val) : val;
        };

        scope.changeValue = function(val, key, child) {
            child[key] = val;
            setIetfOperation('replace', key, child);
        };

        scope.isVisible = function(key, obj) {
            var attr = getAttribute('ietf-netconf:operation', key, obj);
            return !(attr && attr === "remove");
        };

        scope.getAvailableNodeNames = function (key, obj, parent) {
            console.log(key);console.log(obj);console.log(parent);
            var children = parent.$parent.$parent.$parent.$parent.$parent.$parent.child['$@'+ parent.$parent.$parent.$parent.key]['children'];
            angular.forEach(obj, function(value, key) {
                if (key.indexOf('@') !== 0 && children.indexOf(key) !== -1) {
                    children.splice(children.indexOf(key), 1);
                }
            });
            return children;
        };

        var getAttributeType = function(key, obj) {
            var eltype = getEltype(key, obj);

            if (eltype === "container" || eltype === 'list' || eltype === 'anydata') {
                return 'anydata';
            } else if (eltype === 'leaf' || eltype === 'anyxml') {
                return 'anyxml';
            } else if (eltype === "leaf-list") {
                return 'leaf-list';
            }
            return 'not-supported';
        };

        scope.getAttributesNode = function(key, obj, generateEmpty) {
            if (typeof generateEmpty === "undefined") {
                generateEmpty = false;
            }
            var eltype = getAttributeType(key, obj);

            switch (eltype) {
                case 'anydata':
                    if (typeof obj[key] !== "undefined") {
                        if (generateEmpty && typeof obj[key]['@'] === "undefined") {
                            obj[key]['@'] = {}; // create empty attributes object
                        }
                        if (typeof obj[key]['@'] !== "undefined") {
                            return obj[key]['@'];
                        }
                    }
                    break;
                case 'leaf-list':
                case 'anyxml':
                    if (generateEmpty && typeof obj['@'+key] === "undefined") {
                        obj['@'+key] = {}; // create empty attributes object
                    }
                    if (typeof obj['@'+key] !== "undefined") {
                        return obj['@'+key];
                    }
                    break;
                //case 'leaf-list':
                //    if (typeof obj['@'+key] !== "undefined" && typeof obj['@'+key][attr] !== "undefined") {
                //        return obj['@'+key][attr];
                //    }
                //    break;
            }

            return false;
        };

        var getAttribute = function(attr, key, obj) {
            var node = scope.getAttributesNode(key, obj);
            if (node !== false && typeof node[attr] !== "undefined") {
                return node[attr];
            }

            return false;
        };

        var setAttribute = function(attr, val, key, obj) {
            var node = scope.getAttributesNode(key, obj, true);

            if (node) {
                node[attr] = val;
                return true;
            }

            return false;
        };

        var unsetAttribute = function(attr, key, obj) {
            var node = scope.getAttributesNode(key, obj);
            if (node) {
                if (typeof node[attr] !== "undefined") delete node[attr];
                return true;
            }
            return false;
        };

        var setIetfOperation = function(operation, key, obj) {
            setAttribute('ietf-netconf:operation', operation, key, obj);
        };

        var removeIetfOperation = function(key, obj) {
            unsetAttribute('ietf-netconf:operation', key, obj);
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
