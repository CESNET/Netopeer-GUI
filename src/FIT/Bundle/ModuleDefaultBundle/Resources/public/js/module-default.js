var newNodeFormCnt;

$(document).ready(function() {
	initModuleDefaultJS();
});

function initModuleDefaultJS() {
	newNodeFormCnt = 0;
	
	// zobrazime jinak skryte ikonky pro pridavani potomku (novych listu XML)
	$(".type-list .edit-bar .sibling, .type-list .edit-bar .remove-child, .type-list .edit-bar .child").show();

	$('.edit-bar').unbind('click').on('click', '.sibling', function() {
		duplicateNode($(this));
	}).on('click', ".remove-child", function() {
		removeNode($(this));
	}).on('click', ".create-child", function() {
//				generateNode($(this));
		createNode($(this));
	});

	prepareSortable();

	// line of XML output
	$(".leaf-line").hover(function() {
		$(this).addClass("hover");
	}, function() {
		$(this).removeClass("hover");
	});

	prepareTooltipActions();

	$("form[name=formConfigData]").on("change", "input, select", function(event){
		formInputChanged = true;
	});

	$("form[name=formConfigData] input[type=submit], form[name=newNodeForm].addedForm  input[type=submit]").on("click", function(event){
		formInputChanged = false;
	});

	$("form").on("change", ".js-auto-submit-on-change", function() {
		$(this).parents('form').submit();
	});

	/* when range input type, add number of current value before input */
	$("input[type='range']").each(function(i, e) {
		var tmp = $("<input>").attr({
			'class': 'range-cover-number',
			type: 'number',
			disabled: 'disabled',
			value: e.value
		}).text(e.value);
		$(e).after(tmp);
		$(e).bind('change', function() {
			$(e).next('.range-cover-number').val(e.value);
		});
	});

	$("body").on('change', 'input.value', function() {
		if (!$(this).parent().hasClass('generated')) {
			$(this).parent('.leaf-line').addClass('modified');
		}
	});

	showIconsOnLeafLine();
}

function prepareTooltipActions() {
	// tooltip
	$('.tooltip-cover .icon-help').each(function() {
		initDefaultTooltip($(this));
	});
}

function showIconsOnLeafLine() {
	/*
	 if ($(window).width() < 1550) {
	 $('.root').delegate(".leaf-line", 'hover', function() {
	 $(this).find('.icon-bar').fadeToggle();
	 });
	 } else {
	 $('.root').undelegate('.leaf-line', hover);
	 }
	 */
}

function initDefaultTooltip($el) {
	$el.gips({ 'theme': 'blue', placement: 'top', animationSpeed: 100, bottom: $el.parent().parent().parent().outerHeight(), text: $el.siblings('.tooltip-description').text() });
}

function prepareSortable() {
	$(".sortable-node").parent().parent().sortable({
		placeholder: "sortable-placeholder ui-state-highlight",
		axis: "y",
		items: "> div:has(.sortable-node)",
		handle: ".sort-item",
		deactivate: function(e, ui) {
			var $leafs = $(ui.item).parent().find("> div:has(.sortable-node) .sortable-node");

			// set new index order
			$leafs.each(function(i, elem) {
				$(elem).find('input, select').each(function(j, e) {
					var s = $(e).attr('name');
					var delimIndex = s.lastIndexOf('|');
					if (delimIndex == -1) {
						delimIndex = s.lastIndexOf('[');
					}
					var newXpath = s.substring(0, s.lastIndexOf('[')) + "[index" + i + "|" + s.substring(delimIndex + 1);
					$(e).attr('name', newXpath);
				});
			});

			// move all children of prev sortable node
			$(ui.item).nextUntil("div:has(.sortable-node)").each(function(i, e) {
				$(e).insertBefore($(ui.item));
			});

			$(".sortable-placeholder").remove();
		}
	}).disableSelection();
}

function duplicateNode($elem) {
	var $cover = createFormUnderlay($elem);

	var xPath = $elem.attr('rel'),	// parent xPath - in anchor attribute rel
		level = findLevelValue($elem);

	var $currentParent = $elem.parent().parent();
	var $currentParentLevel = $elem.parents('.level-' + level);

	// generate new form
	var $form = generateFormObject('duplicatedNodeForm');

	// current element clone - with all children
	var $newClone = $currentParent.clone();
	$form.html($newClone);
	if ($currentParent.is(':first-child')) {
		$currentParent.nextAll("*").each(function(i, el) {
			$form.append($(el).clone());
		});
	}

	// remove all state nodes (this won't be duplicated)
	$form.find('.state').remove();

	// create hidden input with path to the duplicated node
	var $elementWithParentXpath = $("<input>")
		.attr({
			type: 'hidden',
			name: "duplicatedNodeForm[parent]",
			value: xPath
		});
	$form.prepend($elementWithParentXpath);

	// we have to modify inputs for all children
	$form.children().each(function(i, el) {
		modifyInputAttributes(el, i, 'duplicatedNodeForm');
	});

	// create submit and close button
	createSubmitButton($form, "Save changes");
	createCloseButton($cover, $form);

	// append created form into the parent
	$currentParentLevel.append($form);

	unwrapCoverForm($currentParentLevel, $cover);

	// finally, initialization of Tooltip on cloned elements
	// must be after showing form
	$form.find('.tooltip-cover .icon-help').each(function() {
		initDefaultTooltip($(this));
	});

	scrollToGeneratedForm($elem, $form);
}

function scrollToGeneratedForm($elem, $form) {
	var section = $elem.parents("section");
	$(section).animate({
		scrollTop: $(section).scrollTop() + $form.offset().top - $("nav#top").outerHeight() - 100
	}, 1000);
}

function removeNode($elem) {
	var $cover = createFormUnderlay($elem);

	var xPath = $elem.attr('rel');	// parent XPath - from attribute rel
	var level = findLevelValue($elem);
	var $currentParentLevel = $elem.parents('.level-' + level);
	$elem.parents('.leaf-line').addClass('active');

	// generate new form
	var $form = generateFormObject('removeNodeForm');

	// create hidden input with path to the duplicated node
	var $elementWithParentXpath = $("<input>")
		.attr({
			type: 'hidden',
			name: "removeNodeForm[parent]",
			value: xPath
		});
	$form.prepend($elementWithParentXpath);

	// create submit and close button
	createSubmitButton($form, "Delete record");
	createCloseButton($cover, $form);

	// append created form into the parent
	$form.insertAfter($currentParentLevel);

	unwrapCoverForm($currentParentLevel, $cover);
	scrollToGeneratedForm($elem, $form);
}

function generateNode($elem) {
	var $cover = createFormUnderlay($elem);

	var rel = $elem.attr('rel').split('_');	// rel[0] - xPath, rel[1] - serialized route params
	var level = findLevelValue($elem);
	var $currentParentLevel = $elem.parents('.level-' + level);

	var xPath = rel[0];
	var loadUrl = rel[1];

	// generate new form
	var $form = generateFormObject('generateNodeForm');

	// create hidden input with path to the duplicated node
	var $elementWithParentXpath = $("<input>")
		.attr({
			type: 'hidden',
			name: "generateNodeForm[parent]",
			value: xPath
		});
	$form.prepend($elementWithParentXpath);

	// load URL with HTML form
	var $tmpDiv = $("<div>").addClass('root');
	$tmpDiv.load(document.location.protocol + "//" + document.location.host + loadUrl, function() {
		// we have to modify inputs for all children
		$tmpDiv.children().each(function(i, el) {
			modifyInputAttributes(el, i, 'generatedNodeForm');
		});
	});
	$form.append($tmpDiv);

	// create submit and close button
	createSubmitButton($form, "Add information");
	createCloseButton($cover, $form);

	// append created form into the parent
	$currentParentLevel.append($form);
	unwrapCoverForm($currentParentLevel, $cover);
	scrollToGeneratedForm($elem, $form);
}

function createFormUnderlay($elem) {
	var $cover = findFormUnderlayCover($elem);

	// if form-underlay already exists, will be removed
	if ( $cover.find(".form-underlay").length === 0 ) {

		// append form-underlay to cover
		$cover.append($("<div>").addClass('form-underlay'));
		$cover.append($("<div>").addClass('form-cover'));

		recountFormUnderlayDimensions($cover);

		$cover.find(".form-underlay").click(function() {
			hideAndEmptyModalWindow();
		});
	}

	return $cover;
}

function findFormUnderlayCover($elem) {
	// find cover - if we are on state, it would be state column, or we could be on config
	if ($elem.parents('section').length) {
		return $elem.parents('section');

		// or we have single column layout
	} else {
		return $("body");
	}
}

function recountFormUnderlayDimensions($cover, minusHeight) {
	if (minusHeight == undefined) {
		minusHeight = 0;
	}
	// we have to count new dimensions for new form-underlay
	// and fill it over whole cover part
	var nWidth = $cover.outerWidth(),
		nHeight = $cover[0].scrollHeight - minusHeight;

	// we have to set form to fill cover (from top)
	$cover.find(".form-underlay").width(nWidth).height(nHeight).css({
		'margin-top': 0,
		'margin-left': 0 - parseInt($cover.css('padding-left'), 10)
	});
}

function findLevelValue($elem) {
	var levelRegex = /level-(\d+)/,	// regex for level value
		level = $elem.parents('div[class*="level-"]').attr('class');	// parent class for level

	if ( level.match(levelRegex) === null || ( level.match(levelRegex) !== null && isNaN(level.match(levelRegex)[1]) ) ) {

		// level does not have to be by first parent, could be on previous level too
		if ( $elem.parents('.leaf-line').parent().length ) {
			level = $elem.parents('.leaf-line').parent().attr('class');
			if ( level.match(levelRegex) === null || ( level.match(levelRegex) !== null && isNaN(level.match(levelRegex)[1]) ) ) {
				level = 0;
			} else {
				level = parseInt(level.match(levelRegex)[1], 10);
			}
		} else {
			level = 0;
		}

	} else {
		level = parseInt(level.match(levelRegex)[1], 10);
	}

	return level;
}

function generateFormObject(formName) {
	var $form;
	// new form object - if is not created, we will create new one
	if ( $(".generatedForm").length !== 0 ) {
		$form = $('.generatedForm').last();
	} else {
		// vytvorime formular
		$form = $("<form>")
			.attr({
				action: "",
				method: "POST",
				name: formName,
				'class': 'generatedForm form'
			});
		$form.append($("<input>").attr({
			type: 'hidden',
			name: 'formId',
			value: new Date().getTime()
		}));
		$form.append($("<div/>", {
			'id': 'modelTreeDump'
		}));
	}

	return $form;
}

function modifyInputAttributes(el, newIndex, newInputName) {
	// clean edit-bar html
	$(el).find('.edit-bar').html('');

	// find all input in this level
	var inputArr = $.merge( $(el).children('input, select'), $(el).children('.config-value-cover').find('input, select'));
	// modify every input
	inputArr.each(function(i, e) {
		// rewrite name to duplicatedNodeForm
		if ( $(e).attr('name') ) {
			var elName = $(e).attr('name').replace('configDataForm', newInputName);
			$(e).attr('name', elName);

			if ( $(e).attr('type') === 'range' ) {
				$(e).bind('change', function() {
					$(e).next('.range-cover-number').val(e.value);
				});
			}

			// check, if default attribute is defined
			// if yes, default value will be used instead of current value
			if ( $(e).attr('default') !== "" ) {
				if ( $(e).attr('type') === 'radio' ) {
					if ( $(e).attr('value') === $(e).attr('default') ) {
						$(e).parent().parent().find('input[checked=checked]').removeAttr('checked');
						$(e).attr('checked', 'checked');
					}
				} else {
					if ( $(e).attr('value') !== $(e).attr('default') ) {
						$(e).attr('value', $(e).attr('default'));
					}
				}
			}
			// we have to remove disabled attribute on input (user should be able to edit this value)
			$(e).removeAttr('disabled');
		}
	});

	// recursively find next level of input
	if ( $(el).children('.leaf-line, div[class*=level]').length ) {
		$(el).children('.leaf-line, div[class*=level]').each(function(j, elem) {
			modifyInputAttributes(elem, j, newInputName);
		});
	}
}

function createSubmitButton($form, inputValue) {
	// create form submit - if already exists, we will delete
	// it and append to the end
	if ( $form.children("input[type=submit]").length ) {
		$form.children("input[type=submit]").remove();
	}
	// show submit button only when no form was appended
	if (newNodeFormCnt < 1 || inputValue == "Delete record") {
		var $elementSubmit = $("<input>")
			.attr({
				type: 'submit',
				value: inputValue
			});
		$form.append($elementSubmit);
	}
}

function createAppendButton($cover, $form) {
	// create commit button (if already exists, delete it)
	if ( $form.find('a.append-changes').length ) {
		$form.find('a.append-changes').remove();
	}

	var $elementAppend = $("<a>")
		.attr({
			class: 'append-changes button grey right'
		})
		.text('Append changes');
	$form.append($elementAppend);

	$elementAppend.bind('click', function() {
		appendChanges($cover, $form);
	});
}

// put modified form back into parent tree
function appendChanges($cover, $form) {
	// wrap back root form
	var $originalForm = $(".old-form").removeClass('old-form');
	var $parentsForm = $form;
	$cover.find('.root').wrap($originalForm);
	$originalForm.remove();

	// remove underlay
	$('.form-underlay').remove();
	$('.form-cover').remove();

	// remove unwanted elements from form
	$form.children('a, input[type=submit], #modelTreeDump').remove();
	$form.find('.generated').removeClass('generated');
	$form.removeClass('generatedForm').addClass('addedForm');

	// unbind changes (typeahead callbacks)
	$form.find('input.label').unbind('click').unbind('change').unbind('focus');

	// if we add form to newAddedForm, don't append form, only children
	if ($form.parents('form[name*=newNodeForm]').length) {
		$parentsForm = $form.parents('form[name*=newNodeForm]');

		$form.children('input[type=hidden]').remove();
		$form.children().unwrap();
	} else {
		$form.data('formIndex', newNodeFormCnt++);
	}

	// we have to modify xpath and rel attributes for generated icons and inputs
	modifyAllInputsXPath($parentsForm.find('.leaf-line'), true);

	$parentsForm.find('input, select').each(function(i,e) {
		var name = $(e).attr('name');
		var modifiedName = name.replace(/newNodeForm(\[[\d+]\])?/, 'newNodeForm['+$parentsForm.data('formIndex')+']');
		$(e).attr('name', modifiedName);
	});

	// disable all get-config inputs
	$cover.find('.root').find('input:not([type="hidden"]):not([type="submit"]), select').each(function(i,e) {
		if (!$(e).parents('.addedForm').length) {
			$(e).attr('disabled', 'true');
		}
	});
	$(".js-only-for-append-mode").show();

	$cover.find('.active').removeClass('active');
	formInputChanged = true;
}

function createCloseButton($cover, $form) {
	// create close button and append at the end of form
	if ( $form.children("a.close").length ) {
		$form.children("a.close").remove();
	}
	var $closeButton = $("<a href='#' title='Close' class='close red button-link'>Close</a>");
	$form.append($closeButton);

	// bind click and keydown event
	$closeButton.bind('click', function() {
		wrapCoverForm($cover, $form);
	});
	$(document).bind('keydown', function(event) {
		if ( event.which === 27 ) {
			//event.preventDefault();
			wrapCoverForm($cover, $form);
		}
	});
	if ($cover.find('.form-underlay')) {
		$cover.find('.form-underlay').click(function() {
			wrapCoverForm($cover, $form);
		});
	}
}

// wrap unwrapped form back to cover whole tree form
function wrapCoverForm($cover, $form) {
	var $originalForm = $(".old-form").removeClass('old-form');
	$cover.find('.root').wrap($originalForm);
	$originalForm.remove();
	$form.remove();
	$('.form-underlay').remove();
	$('.form-cover').remove();
	$('.generatedForm').remove();
	$cover.find('.active').removeClass('active');

	formInputChanged = false;
}

// unwrap old form (we can't have two forms inside in HTML
// if we want to work properly), so old form will stay
// alone prepending cover - so we can wrap it always back,
// for example while close button is clicked
function unwrapCoverForm($currentParentLevel, $cover) {
	if ($(".old-form").length) return;

	var $form = $currentParentLevel.parents('section').find('form').first();
	var $oldForm = $form.clone().addClass('old-form');
	$oldForm.html('');
	$cover.prepend($oldForm);
	$form.children('.root').unwrap();

	recountFormUnderlayDimensions($cover);
}

function createNode($elem) {
	var $cover = createFormUnderlay($elem);

	// we will create cover div
	var $coverDiv = $("<div>").addClass('leaf-line').addClass('generated');

	// generate new form
	var $form = generateFormObject('newNodeForm');

	createNodeElements($elem, $coverDiv, $form);

	// create submit and close button
	createSubmitButton($form, "Create new node");
	createAppendButton($cover, $form);
	createCloseButton($cover, $form);

	// reload content of model dump tree
	if ($("#hiddenModelTreeDump").length) {
		reloadModalTreeDumpContent($cover, $form);
	}

	var $currentParent = $elem.parent().parent();
	unwrapCoverForm($currentParent, $cover);
	if ( !$('.generatedForm').length ) {
		scrollToGeneratedForm($elem, $form);
	}

	// finally focus on new created elem
	$coverDiv.find('input.label').focus();
}

function createNodeElements($elem, $coverDiv, $form, childName, childData) {
	var level = findLevelValue($elem) + 1;
	var xPath = $elem.attr('rel');	// parent XPath - from attribute rel
	var $currentParent = $elem.parent().parent();
	var $currentParentLevel = $elem.parents('.level-' + level);
	var $editBar = $elem.parent().clone();	// editBar clone - we will modify it below

	// remove last index and replace it with attr name
	var parentName = "";
	if ($currentParent.find('.label-cover strong').length) {
		parentName = $currentParent.find('.label-cover strong').text();
	} else {
		parentName = $currentParent.find('input.label').val();
	}
	var lastIndex = xPath.lastIndexOf("*?");
	if (lastIndex == -1) {
		lastIndex = xPath.length;
	}
	var parentXPath = xPath.substring(0, lastIndex) + parentName;

	var uniqueId = generateUniqueId();
	var urlTemplate = $elem.data().typeaheadPath;
	var sourceUrl = urlTemplate
		.replace("FORMID", $form.find('input[name=formId]').val())
		.replace("XPATH", encodeURIComponent(parentXPath));

	var labelValue = "";
	var generatedInput =
		labelAttributes =
			editBarAttributes = false;

	if (childData !== undefined && childName !== undefined) {
		labelValue = childName;
		generatedInput = childData.valueElem;
		labelAttributes = childData.labelAttributes;
		editBarAttributes = childData.editBar;
	}

	var refreshTooltipData = function($currentInput, labelAttributes) {
		// remove old tooltip
		$currentInput.parent().find('.tooltip-cover').remove();

		// if description is defined, show tooltip icon
		if (labelAttributes !== undefined && labelAttributes.description !== undefined) {
			var $tooltip = $("<span/>").addClass('tooltip-cover').addClass('help');
			$tooltip.append($("<span/>").addClass('icon-help').text("?"));
			$tooltip.append($("<span/>").addClass('tooltip-description').text(labelAttributes.description));
			$tooltip.insertBefore($currentInput);
			initDefaultTooltip($tooltip.find(".icon-help"));
		}

		// if mandatory is defined, show tooltip icon
		if (labelAttributes !== undefined && labelAttributes.mandatory !== undefined && labelAttributes.mandatory == "true") {
			var $tooltip = $("<span/>").addClass('tooltip-cover').addClass('mandatory');
			$tooltip.append($("<span/>").addClass('icon-help').text("*"));
			$tooltip.append($("<span/>").addClass('tooltip-description').text("Mandatory item"));
			$tooltip.insertBefore($currentInput);
			initDefaultTooltip($tooltip.find(".icon-help"));
		}
	};

	var insertValueElement = function($currentInput, valueElem) {
		var $newHtml = $(valueElem);
		if ($newHtml.prop('tagName') == "INPUT") {
			$newHtml.attr('name', $currentInput.attr('name').replace('label', 'value'));
			$newHtml.val('');
			if ($newHtml.attr('default') != "") {
				$newHtml.val($newHtml.attr('default'));
			}
			$newHtml.removeAttr('disabled');
			$currentInput.parents('.leaf-line').append($newHtml);
			$newHtml.focus();
		} else {
			$newHtml.find('input, select').attr('name', $currentInput.attr('name').replace('label', 'value')).removeAttr('disabled');
			$currentInput.parents('.leaf-line').append($newHtml);
			$newHtml.find('input, select').not('.label').first().focus();
		}
	};

	var createInputValue = function() {
		// input for value
		var $elementValue = $("<input>")
			.attr({
				name: 'newNodeForm[value' + uniqueId + '_' + xPath + ']',
				type: 'text',
				'class': 'value text'
			});
		$coverDiv.append($elementValue);
	};

	var appendCoverDivToParent = function() {
		if ( $('.generatedForm').length ) {
			if ($elem.parent().hasClass('generated')) {
				if ( $elem.parent().parent().next('.level-'+String(level)).length ) {
					$elem.parent().parent().next('.level-'+String(level)).append($coverDiv);
				} else {
					$elem.parent().parent().after($("<div>").addClass('level-' + String(level)).addClass('generated').append($coverDiv));
				}
			} else {
				if ( $form.children('.level-'+String(level)).length ) {
					$form.children('.level-'+String(level)).append($coverDiv);
				} else {
					$currentParent.append($("<div>").addClass('level-' + String(level)).addClass('generated').append($coverDiv));
				}
			}
		} else {
			// create hidden input with path to the duplicated node
			var $elementWithParentXpath = $("<input>")
				.attr({
					type: 'hidden',
					name: "newNodeForm[parent]",
					value: xPath
				});
			$form.prepend($elementWithParentXpath);


			$form.append($("<div>").addClass('level-' + String(level)).addClass('generated').append($coverDiv));

			$elem.parents('.leaf-line').addClass('active');
			$form.insertAfter($currentParent);
		}

		// we have to modify xpath and rel attributes for generated icons and inputs
		var $originalInput = $coverDiv.find('input.value, input.label');
		var newIndex = getNewIndex($coverDiv);

		modifyInputXPath($originalInput, $coverDiv, newIndex);
	};

	var removeEditBarIcons = function($editBar, $newEditBar, labelAttributes) {
		// if is editBar available, replace whole HTML
		if ($newEditBar.length) {
			if (!$newEditBar.find('.create-child').length) {
				$editBar.find('.create-child').remove();
			}

			// leave only remove edit bar action
		} else {
			$editBar.find('img:not(.remove-child)').remove();
		}

		if (labelAttributes !== undefined && labelAttributes.mandatory !== undefined && labelAttributes.mandatory == "true") {
			$editBar.find('.remove-child').remove();
		}
	};

	// input for label name
	var $elementName = $("<input>")
		.attr({
			name: 'newNodeForm[label' + uniqueId + '_' + xPath + ']',
			type: 'text',
			'class': 'label',
			'data-unique-id': uniqueId,
			'data-original-xPath': xPath,
			'data-parrent-xPath': encodeURIComponent(parentXPath),
			value: labelValue,
			'autocomplete': 'off'
		}).typeahead({
			minLength: 0,
			items: 9999,
			autoSelect: false,
			source: function(query, process) {
				$.ajax({
					url: sourceUrl,
					data: {
						'typed': query
					},
					type: "GET",
					dataType: "json",
					success: function(data){
						process(data);
					}
				})
			},
			matcher: function(item) {
				if (this.query == typeaheadALLconst) {
					return true;
				} else if (item.toLowerCase().indexOf(this.query.trim().toLowerCase()) != -1) {
					return true;
				}
				return false;
			}
		}).change(function() {
			var $currentInput = $(this);

			$("ul.typeahead.dropdown-menu").hide();
			$currentInput.blur();

			$.ajax({
				url: sourceUrl,
				data: {
					'label': $(this).val(),
					'command': 'attributesAndValueElem'
				},
				type: "GET",
				dataType: "json",
				success: function(data){
					if (data !== false) {
						if (data.labelAttributes !== undefined) {
							refreshTooltipData($currentInput, data.labelAttributes);
						}

						if (data.children !== false) {
							$.each(data.children, function(name, childElem) {
								var $icon = $editBar.find('.create-child');
								var $newCoverDiv = $("<div>").addClass('leaf-line').addClass('generated');
								createNodeElements($icon, $newCoverDiv, $form, name, childElem);
								modifyAllInputsXPath($newCoverDiv);
							});
						}

						// remove undefined icons in edit-bar
						if (data.editBar !== undefined) {
							var $newEditBar = $(data.editBar);

							removeEditBarIcons($editBar, $newEditBar, data.labelAttributes);
						}

						// replace whole value element and change his name attr
						if (data.valueElem !== undefined) {
							// remove current value element
							$currentInput.parents('.leaf-line').find("input.value, .config-value-cover").remove();

							insertValueElement($currentInput, data.valueElem);
						}
						$currentInput.blur();
						$currentInput.typeahead('hide');
						$("ul.typeahead.dropdown-menu").remove();
					}
				}
			});
		}).on('focus', function() {
			if ($(this).val() == "") {
				$(this).val(typeaheadALLconst);
				$(this).typeahead('lookup');
				$(this).val('');
			}
		});
	$coverDiv.append($("<span>").addClass('label').append($("<span>").addClass('dots')).append($elementName));
	if (editBarAttributes !== false) removeEditBarIcons($editBar, $(editBarAttributes), labelAttributes);

	// append edit bar to cover
	$editBar = bindEditBarModification($editBar, $form);

	$coverDiv.append($editBar);

	if (generatedInput !== false) {
		insertValueElement($elementName, generatedInput);
	} else {
		createInputValue();
	}
	appendCoverDivToParent();

	// must be at the end because of right position compute
	if (labelAttributes !== false) refreshTooltipData($elementName, labelAttributes);
}

function reloadModalTreeDumpContent($cover, $form) {
	if (!$(".model-tree-opener").length) {
		var $modelOpener = $("<a/>", {
			'class': 'model-tree-opener',
			html: '<span class="toToggle">Show</span><span class="toToggle" style="display:none;">Hide</span> model tree'
		}).insertBefore($form.find('.close'));
		$modelOpener.click(function() {
			$("#modelTreeDump").toggle(50, function() {
				var minusHeight;
				if ($(this).is(":visible")) {
					minusHeight = 0;
				} else {
					minusHeight = $("#modelTreeDump").outerHeight();
				}
				recountFormUnderlayDimensions($cover, minusHeight);
			});
			$(".model-tree-opener .toToggle").toggle();
		});
	} else {
		$(".model-tree-opener").insertBefore($form.find('.close'));
	}
	$form.find("#modelTreeDump").html($("#hiddenModelTreeDump").html()).appendTo($form);
}

function modifyInputXPath($inputs, $coverDiv, newIndex) {
	$inputs.each(function(i,e) {
		var s = $(e).attr('name');
		var newXpath = s.substring(0, s.length - 1) + '--*?' + newIndex + '!]';
		$(e).attr('name', newXpath);
	});

	var $labelInput = $coverDiv.find('input.label');
	var $editBarImg = $coverDiv.find('.edit-bar img');
	var newRel = $editBarImg.attr('rel');
	if ($labelInput.length) {
		newRel = $labelInput.data('originalXpath');
	}

	$editBarImg.attr('rel', newRel + '--*?' + newIndex + '!');
}

function rewriteOriginalXPath($inputs, newXpath, newIndex) {
	$inputs.each(function(i,e) {
		var modifiedXPath = newXpath + '--*?' + newIndex + "!";
		$(e).data('originalXpath', modifiedXPath);
		$(e).attr('data-original-xpath', modifiedXPath);
	});
}

function bindEditBarModification($editBar, $form) {
	// necessary edit bar modifications - bind all actions
	$editBar.addClass('generated');
	$editBar.children("img.sibling, img.sort-item").remove();
	$editBar.children("img.remove-child").on('click', function() {
		var confirmBox = confirm("Are you sure you want to delete this element and all his children? This can not be undone!");
		if (confirmBox) {
			// remove all children and itself
			$(this).parents(".leaf-line").nextAll("div[class*='level-']").remove();
			$(this).parents(".leaf-line").remove();
		} else {
			return false;
		}


		modifyAllInputsXPath($form.find('.leaf-line'), true);
	});
	$editBar.children("img.create-child").unbind('click').on('click', function() {
		createNode($(this));
	});

	return $editBar;
}

function modifyAllInputsXPath($leafLines, forceRewriteOriginalXpath) {
	$leafLines.each(function() {
		var $inputs = $(this).find('input.value, input.label, input.hidden-input-value, select, input[type="radio"]');
		var $labelInput = $(this).find("input.label");
		var $valueInput = $(this).find("input.value, select, input[type='radio'], input.hidden-input-value");

		var newIndex = getNewIndex($(this));

		if (forceRewriteOriginalXpath != undefined && forceRewriteOriginalXpath == true) {
			var $childrenLeafs = $(this).nextAll("div[class*='level-']").children('.leaf-line');
			rewriteOriginalXPath($childrenLeafs.find('input.label'), $labelInput.data('originalXpath'), newIndex);
			modifyAllInputsXPath($childrenLeafs);
		}
		var newXpath = $labelInput.data('uniqueId') + '_' + $labelInput.data('originalXpath');

		// recover original uniqueId and xPath and generate new input name
		$labelInput.attr('name', 'newNodeForm[label' + newXpath + ']');
		$valueInput.attr('name', 'newNodeForm[value' + newXpath + ']');
		modifyInputXPath($inputs, $(this), newIndex);
	});
}

function getNewIndex($line) {
	var ind = $line.index();
	if ($line.hasClass('leaf-line')) {
		ind -= $line.prevAll(":not('.leaf-line')").length;
	}
	var newIndex = ind + $line.siblings(".is-key").length;
	if (newIndex < 1) newIndex = 0;
	newIndex++;
	return newIndex;
}
