// @author David Alexa <alexa.david@me.com>
//
// Copyright (C) 2012-2015 CESNET
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
	collapseTopNav();
	prepareAlertsVariables();
	hideAlertsPanel();
}).bind('beforeunload', function() {
	var shouldLoadingContinue = $.netopeergui.formInputChangeConfirm(false);
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
	prepareAlerts();
	changeSectionHeight();
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

	$("#block--leftColumn").css('min-height', '0%').css('height', $(window).height() + 'px');
	$("body section, body section#content").css('min-height', '0%').css('height', h + 'px');
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
		var $alertsBlock = $("#block--alerts");
		var $alerts = $alertsBlock.children();
		var previousCnt = parseInt($("#alerts-icon .count").text(), 10);
		var cnt = $alerts.length;

		$icon.find('.count').text(cnt);
		if (cnt) {
			if ($alertsBlock.find('.error').length) {
				$icon.addClass('red').removeClass('green');

				$alertsBlock.find('.error:not(.no-popup)').each(function(i, e) {
					$(e).addClass('popup');
					$(e).stop(true,true).animate({
						"left": 0 - $("#block--alerts").width()
					}, 300, "linear");
					setTimeout(function() {
						if ($(e).hasClass('popup')) {
							$(e).fadeOut(100, function() {
								$(e).removeClass('popup').addClass('no-popup').show();
							});
						}
					}, 2000);
				});

			} else if ($alertsBlock.find('.success').length) {
				$icon.addClass('green').removeClass('red');
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
		return false;
	}).on('click', '.message.popup', function(e) {
		$(e).stop(true,true).removeClass('popup').addClass('no-popup');
		$("#alerts-icon .header-icon").click();
		return false;
	});
}

function hideAlertsPanel() {
	$("body").unbind('click');
	$(".cover-wo-alerts").css('margin-left', '0px');
	$("#block--notifications").css('width', '');
	$("#block--alerts").css('margin-right', '-20%').removeClass('openAlerts');
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

function l (str) {
	if (console !== null) { console.log(str); }
}

// generates unique id (in sequence)
var generateUniqueId = (function(){var id=0;return function(){if(arguments[0]===0)id=0;return id++;}})();
var formInputChanged = false;
