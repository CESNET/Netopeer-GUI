'use strict';

var counts = {
  object: 0
};

var NetopeerGUI = angular.module('JSONedit', ['ui.sortable', 'ui.bootstrap', 'configurationTemplates', 'NetopeerGUIServices']);

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
.directive('json', function($compile, $log, AjaxService, $cacheFactory, $rootScope) {
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
        var listName = "List";
        var numberName = "Number";
        var urlName = "Url";
        var refName = "Reference";
        var boolName = "Boolean";
        var enumerationName = "Enumeration";
        var literalName = "Literal";

        scope.valueTypes = [stringName, objectName, arrayName, listName, numberName, urlName, refName, boolName, enumerationName, literalName];
        scope.stringName = stringName;
        //scope.valueTypes = [stringName, objectName, arrayName, refName, boolName];
        scope.sortableOptions = {
            axis: 'y',
            update: function(e, ui) {
                setParentChanged(scope.$parent);
                setIetfOperation('replace', scope.$parent.$parent.newkey, scope.getParents($parent, 4).child, scope.$parent);
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

        scope.jsonEditable = jsonEditable;
        scope.contentStatus = '';

        //////
        // Helper functions
        //////
        var isNumberType = function(type) {
            if (!type) return false;
            return type.indexOf('int') >= 0;
        };

        var isUrlType = function(type) {
            return type === "inet:uri";
        };

        var getType = function(key, child, parent) {
            // get custom yang datatype
            var type = Object.prototype.toString.call(child);

            if (!scope.jsonEditable) {
                if (type === "[object Object]") {
                    return objectName;
                } else if (type === "[object Array]") {
                    return arrayName;
                } else if (type === "Boolean" || type === "[object Boolean]") {
                    return boolName;
                } else if (isNumberType(type) || type === "[object Number]") {
                    return numberName;
                } else {
                    return stringName;
                }
            }

            var eltype = getEltype(key, parent);

            if (eltype === "container" || eltype === "list") {
                if (type === "[object Array]") { return listName; }
                return objectName;
            } else if (eltype === "leaf-list") {
                return arrayName;
            } else if (eltype === "boolean" || type === "[object Boolean]") {
                return boolName;
            } else if (eltype === 'enumeration') {
                return enumerationName;
            } else if (isNumberType(eltype) || type === "[object Number]") {
                return numberName;
            } else if (type === "[object Object]") {
                return objectName;
            } else if (type === "[object Array]") {
                return arrayName;
            } else {
                return stringName;
            }
        };
        var getEltype = function(key, parent) {
            if (!scope.jsonEditable) return;

            var schema = getSchemaFromKey(key, parent);
            // get custom yang datatype
            var eltype = '';

            if (schema && typeof schema['eltype'] !== "undefined") {
                eltype = schema['eltype'];
                if (eltype == 'leaf' && typeof schema['typedef'] !== "undefined") {
                    eltype = schema['typedef']['type'];
                }
            }

            return eltype;
        };
        var isNumber = function(n) {
          return !isNaN(parseFloat(n)) && isFinite(n);
        };

        scope.getType = getType;
        scope.getEltype = getEltype;
        scope.log = function(data) {
            console.log(data);
        };

        var getSchemaFromKey = function(key, parent, child) {
            if (!scope.jsonEditable) return;

            if (typeof parent === "undefined" || typeof parent['$@'+key] === "undefined") {
                if (typeof key === "undefined" || typeof parent === "undefined") return false;

                var path = scope.getPath(parent, 'key');

                var parentKey = path.replace('/', '').split(':');
                var ns = parentKey[0] + ":";
                var rootElem = parentKey[1];
                path = path + '/' + key;

                if (angular.isUndefined($rootScope.cache)) {
                    //try {
                    $rootScope.cache = $cacheFactory(window.location.href);
                    //} catch (exception) {};
                }
                if (angular.isUndefined($rootScope.cache.get(path))) {
                    AjaxService.loadSchema([connId], [path])
                      .then(function successCallback(data) {
                          var schema = data.data;

                          if (typeof schema === "undefined" || typeof schema['$@'+ns+key] === "undefined") {
                              return false;
                          }
                          // insert loaded schema into current object
                          $rootScope.cache.put(path, parent['$@'+key]);
                          if (!angular.isUndefined(child)) {
                              child['$@'+key] = schema['$@'+ns+key];
                          } else {
                              parent['$@'+key] = schema['$@'+ns+key];
                          }
                          return schema['$@'+ns+key];
                      }, function errorCallback(data) {
                          return false;
                      });
                } else {
                    return $rootScope.cache.get(path);
                }
            } else {
                return parent['$@'+key];
            }
        };
        scope.getSchemaFromKey = getSchemaFromKey;

        scope.isConfig = function(key, parent) {
            if (!scope.jsonEditable) return false;

            var schema = getSchemaFromKey(key, parent);
            return (schema && typeof schema['config'] !== "undefined" && schema['config'] === true);
        };

        scope.editBarVisible = function(key, parent) {
            if (!scope.jsonEditable) return false;

            var eltype = getEltype(key, parent);
            if (eltype) {
                return (eltype == "list" || eltype == "leaf-list" || eltype == "container");
            }

            return false;
        };
        scope.isObjectOrArray = function(key, val, parent) {
            var type = getType(key, val, parent);
            return (type == objectName || type == arrayName || type == listName);
        };
        scope.getEnumValues = function(key, child, parent) {
            var schema = getSchemaFromKey(key, child);
            if (schema && typeof schema['enumval'] !== "undefined") {
                return schema['enumval'];
            } else if (schema && typeof schema['typedef'] !== "undefined" && typeof schema['typedef']['enumval'] !== "undefined") {
                return schema['typedef']['enumval'];
            } else {
                return [];
            }
        }
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
                    setParentChanged(parent);
                    setIetfOperation('remove', key, obj);
                    //delete obj[key]; // TODO delete children

                }
            } else if (getType(key, obj, parent) == "Array") {
                if( confirm('Delete "'+obj[key]+'"?') ) {
                    setParentChanged(parent);
                    setIetfOperation('remove', key, obj);
                    //obj.splice(key, 1); // TODO delete children
                }
            } else {
                console.error("object to delete from was " + obj);
            }
        };
        scope.addItem = function(key, obj, parent) {
            if (typeof parent.valueType == "undefined") {
                var type = getType(parent.keyName, undefined, obj);
            } else {
                var type = parent.ValueType;
            }

            var parentType = objectName;
            //if (typeof parent.$parent.$parent !== "undefined") {
                parentType = getType(scope.getParents(parent, 4).key, obj);
            //}
            //console.log(key);
            //console.log(obj);
            //console.log(parent);
            //console.log(type);
            //console.log(parentType);

            type = type || parentType;

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
                            setParentChanged(parent);
                            setIetfOperation('create', key, obj);
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
                            console.log('not implemented type: ' + type + ' or parentType ' + parent.valueType); // TOOD
                    }
                    setParentChanged(parent);
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
                setParentChanged(parent);
                setIetfOperation('replace', scope.getParents(parent, 4).key, scope.getParents(parent, 6).child, parent); // TODO replace order in array
                parent.valueName = "";
                parent.showAddKey = false;
            } else {
                console.error("object to add to was " + obj);
            }
        };
        scope.possibleNumber = function(val) {
            return isNumber(val) ? parseFloat(val) : val;
        };

        scope.changeValue = function(val, key, child, parent) {
            child[key] = val;
            setParentChanged(parent);
            setIetfOperation('replace', key, child, parent);
            scope.contentStatus = 'modified';
        };

        scope.isVisible = function(key, obj) {
            var attr = getAttribute('ietf-netconf:operation', key, obj);
            return !(attr && attr === "remove");
        };

        scope.changeParentKeyName = function(key, child, $parent) {
            var val = getType(key, child, $parent);
            if (val) {
                $parent.valueType = val;
            }
        };

        scope.getAvailableNodeNames = function (key, child, parent) {
            //console.log(key);console.log(child);console.log(parent);
            var parentKeyName = scope.getParents(parent, 4).key;
            var children = scope.getParents(parent, 6).child['$@'+ parentKeyName]['children'];

            angular.forEach(child, function(value, key) {
                if (key.indexOf('@') !== 0 && children.indexOf(key) !== -1) {
                    children.splice(children.indexOf(key), 1);
                }
            });

            angular.forEach(children, function (key, value) {
                getSchemaFromKey(key, parent, child);
            });

            return children;
        };

        var getAttributeType = function(key, obj) {
            var eltype = getEltype(key, obj);

            if (eltype === "container" || eltype === 'anydata') {
                return 'anydata';
            } else if (eltype === 'list') {
                return 'list';
            } else if (eltype === 'leaf' || eltype === 'anyxml' || eltype === 'enumeration' || isNumberType(eltype)) {
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
            //if (key == 'state') {
            //    console.log(getEltype(key, obj));
            //    console.log(eltype);
            //}
            switch (eltype) {
                case 'container':
                case 'anydata':
                    if (typeof obj[key] !== "undefined") {
                        if (generateEmpty && typeof obj[key]['@'] === "undefined") {
                            try {
                                obj[key]['@'] = {}; // create empty attributes object
                            } catch (exception) {};
                        }
                        if (typeof obj[key]['@'] !== "undefined") {
                            return obj[key]['@'];
                        }
                    }
                    break;
                case 'list':
                    obj = scope.getParents(obj, 2);
                    if (generateEmpty && typeof obj['@'] === "undefined") {
                        console.log(obj);
                        console.log(scope);
                        try {
                            obj['@'] = {}; // create empty attributes object
                        } catch (exception) {};
                    }
                    if (typeof obj['@'] !== "undefined") {
                        return obj['@'];
                    }
                    break;
                case 'leaf-list':
                case 'anyxml':
                    if (generateEmpty && typeof obj['@'+key] === "undefined") {
                        try {
                            obj['@'+key] = {}; // create empty attributes object
                        } catch (exception) {};
                    }
                    if (typeof obj['@'+key] !== "undefined") {
                        return obj['@'+key];
                    }
                    break;
            }

            return false;
        };

        scope.getParents = function(obj, number) {
            if (typeof obj === "undefined") return false;
            var parent = obj;

            for (var i = 0; i < number; i++) {
                if (typeof parent.$parent === "undefined" || parent.$parent === null) {
                    return parent;
                }
                parent = parent.$parent;
            }
            return parent;
        };

        scope.getPath = function(obj, target) {
            if (typeof obj === "undefined") return false;
            var parent = obj;
            var res = '';

            while (typeof parent.$parent !== "undefined" && parent.$parent !== null) {
                parent = parent.$parent;
                //if (typeof parent[target] !== "undefined") console.log(parent[target]);
                if (parent.hasOwnProperty(target) && typeof parent[target] !== 'undefined') {
                    res = '/' + parent[target] + res;
                }
            }
            return res;
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

        var setIetfOperation = function(operation, key, obj, parent) {
            var tmpParent = scope.getParents(parent, 11);
            if (tmpParent.hasOwnProperty('key') && typeof scope.getParents(tmpParent, 2)['child'] !== 'undefined') {
            } else {
                tmpParent = scope.getParents(parent, 2);
            }

            if (tmpParent.hasOwnProperty('key') && typeof scope.getParents(tmpParent, 2)['child'] !== 'undefined') {
                if (getAttributeType(tmpParent['key'], scope.getParents(tmpParent, 2)['child']) == 'list') {
                    //key = tmpParent['key'];
                    //obj = scope.getParents(tmpParent, 2)['child'];
                    //if (operation == 'replace') {
                    //    operation = 'merge';
                    //}
                }
            }
            setAttribute('ietf-netconf:operation', operation, key, obj);
        };

        var setParentChanged = function(parent) {
            if (typeof parent === "undefined") return false;
            var target = 'key';
            while (typeof parent.$parent !== "undefined" && parent.$parent !== null) {
                var obj = parent;
                parent = parent.$parent;
                var parentToSet = scope.getParents(parent, 2);
                //console.log(parentToSet);
                if (parent.hasOwnProperty(target) && typeof parent[target] !== 'undefined' && typeof parentToSet['child'] !== 'undefined') {
                    setAttribute('netopeergui:status', 'changed', parent[target], parent['child']);
                }
            }
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
            if (typeof key === "string" && key.indexOf('@') !== 0) {
                result[key] = value;
            }
        });
        return result;
    };
});
