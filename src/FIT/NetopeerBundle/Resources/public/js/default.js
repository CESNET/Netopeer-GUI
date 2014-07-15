// @author David Alexa <alexa.david@me.com>
//
// Copyright (C) 2012-2013 CESNET
//
// LICENSE TERMS
//
// Redistribution and use in source and binary forms, with or without
// modification, are permitted provided that the following conditions
// are met:
// 1. Redistributions of source code must retain the above copyright
//    notice, this list of conditions and the following disclaimer.
// 2. Redistributions in binary form must reproduce the above copyright
//    notice, this list of conditions and the following disclaimer in
//    the documentation and/or other materials provided with the
//    distribution.
// 3. Neither the name of the Company nor the names of its contributors
//    may be used to endorse or promote products derived from this
//    software without specific prior written permission.
//
// ALTERNATIVELY, provided that this notice is retained in full, this
// product may be distributed under the terms of the GNU General Public
// License (GPL) version 2 or later, in which case the provisions
// of the GPL apply INSTEAD OF those given above.
//
// This software is provided ``as is'', and any express or implied
// warranties, including, but not limited to, the implied warranties of
// merchantability and fitness for a particular purpose are disclaimed.
// In no event shall the company or contributors be liable for any
// direct, indirect, incidental, special, exemplary, or consequential
// damages (including, but not limited to, procurement of substitute
// goods or services; loss of use, data, or profits; or business
// interruption) however caused and on any theory of liability, whether
// in contract, strict liability, or tort (including negligence or
// otherwise) arising in any way out of the use of this software, even
// if advised of the possibility of such damage.

var formChangeAlert = 'Some of form values has been changed. Do you want discard and go to another page?';
var gmright, gmleft, gnotWidth;
var typeaheadALLconst = '__ALL__';

 $(document).ready(function() {
	initJS();
});

$(window).resize(function() {
	changeSectionHeight();
	showIconsOnLeafLine();
	collapseTopNav();
	prepareAlertsVariables();
	hideAlertsPanel();
}).bind('beforeunload', function() {
	var shouldLoadingContinue = formInputChangeConfirm(false);
	if (!shouldLoadingContinue) {
		return formChangeAlert;
	}
});

function prepareAlertsVariables() {
	gmright = $("#block--alerts").css('margin-right');
	gmleft = "0";
	gnotWidth = $("#block--notifications").css('width');
}

jQuery.fn.reverse = function() {
	return this.pushStack(this.get().reverse(), arguments);
};

function initJS() {
	collapseTopNav();

	// zobrazime jinak skryte ikonky pro pridavani potomku (novych listu XML)
	$(".type-list .edit-bar .sibling, .type-list .edit-bar .remove-child, .type-list .edit-bar .child").show();

	$('.edit-bar').on('click', '.sibling', function() {
		duplicateNode($(this));
	}).on('click', ".remove-child", function() {
				removeNode($(this));
	}).on('click', ".create-child", function() {
//				generateNode($(this));
		createNode($(this));
	});

	prepareSortable();
	prepareAlerts();

	// line of XML output
	$(".leaf-line").hover(function() {
		$(this).toggleClass("hover");
	});

	prepareTooltipActions();

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

	showIconsOnLeafLine();
	changeSectionHeight();

	$("form[name=formConfigData]").on("change", "input, select", function(event){
		formInputChanged = true;
	}).on("submit", function(event){
		formInputChanged = false;
	});
}

function prepareTooltipActions() {
	// tooltip
	$('.tooltip .icon-help').each(function() {
		initDefaultTooltip($(this));
	});
}

function initPopupMenu($cover) {
	$cover.find('.show-link').unbind('hover');
	$cover.find('.show-link').hover(function() {
		$cover.find(".others").stop(true).slideToggle('fast');
	});
}

/**
 * collapse top nav - when links for sections overflows available space, 
 * double down arrow with popup submenu will appear
 */
function collapseTopNav() {
    var $nav = $("nav#block--topMenu");
	if ( $nav.length ) {
		var $othersCover = $nav.find('.others-cover');
		var $others = $othersCover.find(".others");
    var availableSpace = $nav.outerWidth();

		// reset collapsed behaviour
		$others.hide();

		// we will count available space for sections hrefs
		$nav.find('.static').each(function() {
			availableSpace -= $(this).outerWidth();
		});
		availableSpace -= $othersCover.children(".show-link").outerWidth() + 50;

		// move old links back to top nav bar
		if ($othersCover.hasClass('visible')) {
			$others.children('a').each(function() {
				$("#userpane").before($(this).clone());
				$(this).remove();
			});
			$othersCover.removeClass('visible');
		}

		// check, if dynamic href should be visible or hidden under popup menu
		if ( $nav.find(".dynamic").length ) {
			var firstOffset;
			var maxOffset = $nav.find("#userpane").offset().left;
			var isLastItemVisible = true;
			var i = 0;
			$nav.find('.dynamic').each(function() {
				if (i++ === 0) {
					firstOffset = $(this).offset().left;
				}
				if ( ($(this).offset().left - firstOffset + $(this).outerWidth()) >= availableSpace ||
						($(this).offset().left + $(this).outerWidth()) >= maxOffset ||
						isLastItemVisible === false
					) {
					if (isLastItemVisible === true) {
						$othersCover.addClass('visible');
						initPopupMenu($othersCover);
						isLastItemVisible = false;
					}
					$others.append($(this).clone());
					$(this).remove();
				}
			});
		}
	}
}

function changeSectionHeight() {
	if (!notifOutput) {
		notifInit();
	}

	notifResizable();

	var h = $(window).height();
	if (!$(notifOutput).hasClass('hidden')) {
		h -= $(notifOutput).outerHeight();
	}

	$("body section, body section#content").css('min-height', '0%').height(h);
	$(notifOutput).css('top', h);

	fixOverflowY();
}

function fixOverflowY() {
	var wHeight = $(window).height();
	$(".scrollable-cover").each(function() {
		if ($(this).data('addScrollable') == true && !$(this).children(".scrollable").length) {
			$(this).wrapInner($("<div/>").addClass('scrollable'));
		}
		var scrollableContent = $(this).children('.scrollable');
		var sHeight = scrollableContent.outerHeight();
		var cHeight = wHeight;
		if (scrollableContent.data('parent') !== undefined) {
			cHeight = $(scrollableContent.data('parent')).outerHeight();
		}

		if (sHeight <= cHeight) {
			$(this).css('overflow-y', 'auto');
		} else {
			$(this).css('overflow-y', 'scroll');
		}
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

function prepareAlerts() {
	prepareAlertsVariables();

	// activate column with flash messages
	$("#alerts-icon .header-icon").unbind('click').click(function(e) {
		e.preventDefault();

		mright = gmright;
		mleft = gmleft;
		notWidth = gnotWidth;

		if (!$("#block--alerts").hasClass('openAlerts')) {
			mright = "0";
			mleft = 0 - $("#block--alerts").outerWidth();
			notWidth = "100%";
			$("#block--alerts").addClass('openAlerts');

			// handle click outside of alerts
			$("body").bind("click", function(elem) {
				if (!$(elem.target).closest("#block--alerts").length) {
					$("#alerts-icon .header-icon").click();
					elem.preventDefault();
				}
			});
		} else {
			$("#block--alerts").removeClass('openAlerts');
			$("body").unbind('click');
		}

		$("#block--alerts").stop(true,true).animate({
			"margin-right": mright
		}, 500, "linear");

		$(".cover-wo-alerts").stop(true,true).animate({
			"margin-left": mleft
		}, 500, "linear");

		$("#block--notifications").stop(true,true).animate({
			width: notWidth
		}, 300, "linear");

		return false;
	});

	// refresh number of flash messages, change background color according to last flash state
	setInterval(function() {
		var $icon = $("#alerts-icon .ico-alerts");
		var $alerts = $("#block--alerts").children();
		var previousCnt = parseInt($("#alerts-icon .count").text(), 10);
		var cnt = $alerts.length;
		$icon.find('.count').text(cnt);
		if (cnt) {
			var $lastCh = $alerts.last();
			if ($lastCh.hasClass('success')) {
				$icon.addClass('green').removeClass('red');
			} else if ($lastCh.hasClass('error')) {
				$icon.addClass('red').removeClass('green');
			}
		} else {
			$icon.removeClass('red').removeClass('green');
		}

	}, 1000);

	// handle click on alert or flash message (closes)
	$("body").on('click', '.message .close', function(e) {
		e.preventDefault();
		$(this).parents('.message').stop(true,true).fadeOut('fast', function() {
			$(this).remove();
		});
	});
}

function hideAlertsPanel() {
	$("body").unbind('click');
	$(".cover-wo-alerts").css('margin-left', '0px');
	$("#block--notifications").css('width', '');
	$("#block--alerts").css('margin-right', '-20%').removeClass('openAlerts');
}

function prepareSortable() {
	var sortableChildren;
	$(".sortable-node").parent().parent().sortable({
		placeholder: "sortable-placeholder ui-state-highlight",
		axis: "y",
		items: ".sortable-node",
		handle: ".sort-item",
		deactivate: function(e, ui) {
			var $leafs = $(ui.item).parent().parent().children().children(".sortable-node");

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
			$(ui.item).nextUntil('.sortable-node').each(function(i, e) {
				$(e).insertBefore($(ui.item));
			});

			// move all children of current sortable node
			if (sortableChildren.length) {
				sortableChildren.reverse().each(function(i, elem) {
					$(elem).insertAfter($(ui.item));
				});
			}

			$(".sortable-placeholder").remove();
		},
		activate: function(e, ui) {
			sortableChildren = $(ui.item).nextUntil('.sortable-node');
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
	$form.find('.tooltip .icon-help').each(function() {
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
	$currentParentLevel.append($form);

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

function hideAndEmptyModalWindow() {
	var modalBlock = $("#block--modalWindow");
	if (modalBlock.length) {
		modalBlock.modal('hide');
		modalBlock.html('').hide();
		$('.form-underlay').remove();
	}
}

function bindModalWindowActions() {
	$("#block--modalWindow").on('click', '.close', function() {
		hideAndEmptyModalWindow();
	})
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
				'class': 'generatedForm'
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
	var $elementSubmit = $("<input>")
		.attr({
			type: 'submit',
			value: inputValue
		});
	$form.append($elementSubmit);
}

function createCloseButton($cover, $form) {
	// create close button and append at the end of form
	if ( $form.children("a.close").length ) {
		$form.children("a.close").remove();
	}
	var $closeButton = $("<a href='#' title='Close' class='close red button'>Close</a>");
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

	var $oldForm = $currentParentLevel.parents('form').clone().addClass('old-form');
	$oldForm.html('');
	$cover.prepend($oldForm);
	$currentParentLevel.parents('form').children('.root').unwrap();

	recountFormUnderlayDimensions($cover);
}

function l (str) {
	if (console !== null) { console.log(str); }
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
	var generatedInput = false;
	var labelAttributes = false;

	if (childData !== undefined && childName !== undefined) {
		labelValue = childName;
		generatedInput = childData.valueElem;
		labelAttributes = childData.labelAttributes;
	}

	var refreshTooltipData = function($currentInput, labelAttributes) {
		// remove old tooltip
		$currentInput.parent().find('.tooltip').remove();

		// if description is defined, show tooltip icon
		if (labelAttributes !== undefined && labelAttributes.description !== undefined) {
			var $tooltip = $("<span/>").addClass('tooltip').addClass('help');
			$tooltip.append($("<span/>").addClass('icon-help').text("?"));
			$tooltip.append($("<span/>").addClass('tooltip-description').text(labelAttributes.description));
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

	// input for label name
	var $elementName = $("<input>")
		.attr({
			name: 'newNodeForm[label' + uniqueId + '_' + xPath + ']',
			type: 'text',
			'class': 'label',
			'data-unique-id': uniqueId,
			'data-original-xPath': xPath,
			'data-parrent-xPath': encodeURIComponent(parentXPath),
			value: labelValue
		}).typeahead({
			minLength: 0,
			items: 9999,
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
			} else {
				$(this).typeahead('lookup');
			}
		});
	$coverDiv.append($("<span>").addClass('label').append($("<span>").addClass('dots')).append($elementName));
	if (labelAttributes !== false) refreshTooltipData($elementName, labelAttributes);

	// append edit bar to cover
	$editBar = bindEditBarModification($editBar, $form);
	$coverDiv.append($editBar);

	if (generatedInput !== false) {
		insertValueElement($elementName, generatedInput);
	} else {
		createInputValue();
	}
	appendCoverDivToParent();
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

	var $newRel = $coverDiv.children('.edit-bar').children('img');
	$newRel.attr('rel', $newRel.attr('rel') + '--*?' + newIndex + '!');
}

function bindEditBarModification($editBar, $form) {
	// necessary edit bar modifications - bind all actions
	$editBar.addClass('generated');
	$editBar.children("img.sibling, img.sort-item").remove();
	$editBar.children("img.remove-child").on('click', function() {
		// remove all children and itself
		$(this).parents(".leaf-line").next("div[class*='level-']").remove();
		$(this).parents(".leaf-line").remove();

		modifyAllInputsXPath($form.find(".leaf-line"));
	});
	$editBar.children("img.create-child").on('click', function() {
		createNode($(this));
	});

	return $editBar;
}

function modifyAllInputsXPath($leafLines) {
	$leafLines.each(function() {
		var $inputs = $(this).find('input.value, input.label, input.hidden-input-value, select, input[type="radio"]');
		var $labelInput = $(this).find("input.label");
		var $valueInput = $(this).find("input.value, select, input[type='radio'], input.hidden-input-value");

		var newIndex = getNewIndex($(this));

		// recover original uniqueId and xPath and generate new input name
		$labelInput.attr('name', 'newNodeForm[label' + $labelInput.data().uniqueId + '_' + $labelInput.data().originalXpath + ']');
		$valueInput.attr('name', 'newNodeForm[value' + $labelInput.data().uniqueId + '_' + $labelInput.data().originalXpath + ']');
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

function formInputChangeConfirm(showDialog) {
	if (formInputChanged === true) {
		if ( (showDialog && !confirm(formChangeAlert)) || !showDialog) {
			return false;
		} else {
			formInputChanged = false;
		}
	}
	return true;
}

// generates unique id (in sequence)
var generateUniqueId = (function(){var id=0;return function(){if(arguments[0]===0)id=0;return id++;}})();
var formInputChanged = false;
