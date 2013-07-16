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

 $(document).ready(function() {
	initJS();
});

$(window).resize(function() {
	changeSectionHeight();
	showIconsOnLeafLine();
	collapseTopNav();
});

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

	$(window).on('click', '.alert', function(e) {
		e.preventDefault();
		$(this).stop(true,true).fadeOut('fast', function() {
			$(this).remove();
		});
	});

	// line of XML output
	$(".leaf-line").hover(function() {
		$(this).toggleClass("hover");
	});

	prepareAlertsActions();
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
}

/**
 * set animation for alerts in .alert-cover
 */
(function( $ ) {
	$.fn.animateAlert = function() {
		if (!$(this).is(":hidden")) {
			$(this).hide();
		}
		var topOffset = $(this).position().top;
		var $alert = $(this);

		$(this).css('top', 0 - $(this).outerHeight() - parseInt($(".alert-cover").css('top'), 10)).show().animate({
			top: topOffset
		}, 1000, 'easeOutBack');

		/* hide alerts after some time - only successfull */
		if ($(this).hasClass('success')) {
			var $flash = $(this);
			setTimeout(function() {
				$flash.fadeOut(function() {
					$flash.remove();
				});
			}, 5000); /* 3s animation + 4s visible */
		}
	};
})( jQuery );

function prepareAlertsActions() {
	$(".alert-cover .alert").hide().delay(500).each(function() {
		$(this).animateAlert();
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

	$("body > section, body > section#content").css('min-height', '0%').height(h);
	$(notifOutput).css('top', h);
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
		scrollTop: $(section).scrollTop() + $form.offset().top - $("nav#top").outerHeight() - 20
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
	var $cover;
	// find cover - if we are on state, it would be state column, or we could be on config
	if ($elem.parents('section').length) {
		$cover = $elem.parents('section');

	// or we have single column layout
	} else {
		$cover = $("#content");
	}

	// if form-underlay already exists, will be removed
	if ( $cover.find(".form-underlay").length === 0 ) {

		// append form-underlay to cover
		$cover.append($("<div>").addClass('form-underlay'));
		$cover.append($("<div>").addClass('form-cover'));

		// we have to count new dimensions for new form-underlay
		// and fill it over whole cover part
		var nWidth = $cover.outerWidth(),
			nHeight = $cover[0].scrollHeight + $elem.parent().parent().parent().outerHeight() + 150; // 150 px for buttons

		// we have to set form to fill cover (from top)
		$cover.find(".form-underlay").width(nWidth).height(nHeight).css({
			'margin-top': 0,
			'margin-left': 0 - parseInt($cover.css('padding-left'), 10)
		});

		$cover.find(".form-underlay").click(function() {

		});
	}

	return $cover;
}

function findLevelValue($elem) {
	var levelRegex = /level-(\d+)/,	// regex for level value
		level = $elem.parents('div[class*="level-"]').attr('class');	// parent class for level

	l(level);

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
	l(level);
	return level;
}

function generateFormObject(formName) {
	var $form;
	// new form object - if is not created, we will create new one
	if ( $(".generatedForm").length ) {
		$form = $('.generatedForm');
	} else {
		// vytvorime formular
		$form = $("<form>")
			.attr({
				action: "",
				method: "POST",
				name: formName,
				'class': 'generatedForm'
			});
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
	var $originalForm = $cover.children('form');
	$cover.find('.root').wrap($originalForm);
	$form.remove();
	$('.form-underlay').remove();
	$('.form-cover').remove();
	$('.generatedForm').remove();
}

// unwrap old form (we can't have two forms inside in HTML
// if we want to work properly), so old form will stay
// alone prepending cover - so we can wrap it always back,
// for example while close button is clicked
function unwrapCoverForm($currentParentLevel, $cover) {
	var $oldForm = $currentParentLevel.parents('form').clone();
	$oldForm.html('');
	$cover.prepend($oldForm);
	$currentParentLevel.parents('form').children('.root').unwrap();
}

function l (str) {
	if (console !== null) { console.log(str); }
}



function createNode($elem) {
	var $cover = createFormUnderlay($elem);

	// vytvorime div obalujici inputy
	var level = findLevelValue($elem) + 1;
	var $coverDiv = $("<div>").addClass('leaf-line');

	var xPath = $elem.attr('rel');	// parent XPath - from attribute rel
	var $currentParent = $elem.parent().parent();
	var $currentParentLevel = $elem.parents('.level-' + level);
	var $editBar = $elem.parent().clone();	// editBar clone - we will modify it below

	// generate new form
	var $form = generateFormObject('newNodeForm');

	var uniqueId = generateUniqueId();

	// input pro nazev elementu
	var $elementName = $("<input>")
		.attr({
			name: 'newNodeForm[label_' + uniqueId + '_' + xPath + ']',
			type: 'text',
			'class': 'label'
		});
	$coverDiv.append($("<span>").addClass('label').append($("<span>").addClass('dots')).append($elementName));

	// upravime si naklonovany editBar - pridame tridu pro odliseni vygenerovaneho baru
	$editBar.addClass('generated');
	// delegujeme click akci na nove vytvoreny element editBar
//	$editBar.children("img.sibling").on('click', function() {
//		duplicateNode($(this));
//	});
	$editBar.children("img.sibling").remove();
	$editBar.children("img.create-child").on('click', function() {
		createNode($(this));
	});
	// ke coveru pripojime editBar
	$coverDiv.append($editBar);

	// input pro hodnotu elementu
	var $elementValue = $("<input>")
		.attr({
			name: 'newNodeForm[value_' + uniqueId + '_' + xPath + ']',
			type: 'text',
			'class': 'value text'
		});
	$coverDiv.append($elementValue);


	// formular jiz mame vytvoreny, pouze tedy pridame
	var disableScrolling = false;
	if ( $('.generatedForm').length ) {
		disableScrolling = true;
		if ($elem.parent().hasClass('generated')) {
			if ( $elem.parent().parent().next('.level-'+String(level)).length ) {
				l("xx");
				$elem.parent().parent().next('.level-'+String(level)).append($coverDiv);
			} else {
				l("1");
				$elem.parent().parent().after($("<div>").addClass('level-' + String(level)).addClass('generated').append($coverDiv));
			}
		} else {
			if ( $form.children('.level-'+String(level)).length ) {
				l("2");
				$form.children('.level-'+String(level)).append($coverDiv);
			} else {
				l("3");
				$currentParent.append($("<div>").addClass('level-' + String(level)).addClass('generated').append($coverDiv));
			}
		}
	} else {
		l("4");
		$form.append($("<div>").addClass('level-' + String(level)).addClass('generated').append($coverDiv));

		$elem.parents('.leaf-line').addClass('active');
		$form.insertAfter($currentParent);
	}

	// nyni je nutne upravit xPath vygenerovanych inputu a ikonek
	var $originalInput = $coverDiv.children('input.value, input.label');
	newIndex = $coverDiv.index();
	if (newIndex < 1) newIndex = 0;
	newIndex++;

//	duplicatedNodeForm[action_-*-*?3!-*?4!-*?4!]

	$originalInput.each(function(i,e) {
		var s = $(e).attr('name');
		var newXpath = s.substring(0, s.length - 1) + '-*?' + newIndex + '!]';
		$(e).attr('name', newXpath);
	});

	// nesmime zapomenout pridat pozmeneny xPath take k ikonkam pro pridani dalsi node
	$newRel = $coverDiv.children('.edit-bar').children('img');
	$newRel.attr('rel', $newRel.attr('rel') + '-*?' + newIndex + '!');

	// create submit and close button
	createSubmitButton($form, "Create new node");
	createCloseButton($cover, $form);

	unwrapCoverForm($currentParentLevel, $cover);
	if (!disableScrolling) {
		scrollToGeneratedForm($elem, $form);
	}
}

var generateUniqueId = (function(){var id=0;return function(){if(arguments[0]===0)id=0;return id++;}})();