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
//
// @link inspired by @link        http://nettephp.com/cs/extras/jquery-ajax

jQuery.extend({
	netopeergui: {
		updateSnippet: function (id, html) {
			if (id === "block--singleContent" && !$("#block--singleContent").length) {
				id = 'block--state';
			} else if (id === "block--state" && !$("#block--state").length) {
				id = 'block--singleContent';
			}
			if (id === "block--config" && !$("#block--config").length) {
				var $afterEl;
				if ($("#block--state").length) {
					$afterEl = $("#block--state");
				} else {
					$afterEl = $("#block--singleContent");
				}

				var tmp = $('<section></section>').insertAfter($afterEl);
				tmp.addClass('left-nav-defined')
					.addClass('scrollable-cover')
					.attr('add-data-scrollable', 'true')
					.attr('id', 'block--config')
				;
				$afterEl.attr('id', 'block--state').addClass('left-nav-defined');
			}

			if ((id === "block--moduleJavascripts" || id === "block--moduleStylesheet") && window.location.href.indexOf("app_dev.php") !== -1) {
				html = html.replace('_controller', 'app_dev.php');
			}
			if (id === "block--alerts") {
				$("#" + id).append(html);
			} else {
				$("#" + id).html(html);
			}

			if (id === "block--modalWindow") {
				$("#block--modalWindow").show();
				createFormUnderlay($("#block--modalWindow"));
				bindModalWindowActions();
			}

		},

		success: function (payload) {
			// redirect
			if (payload.redirect) {
				window.location.href = payload.redirect;
				return;
			}

			// snippets
			if (payload.snippets) {
				if (!("block--config" in payload.snippets) && payload.treeColumns !== true) {
					$("#block--config").remove();
					$("#block--state").attr('id', 'block--singleContent');
				}
				if (("block--config" in payload.snippets) && (("block--state" in payload.snippets) || ("block--singleContent" in payload.snippets))) {
					$("#block--config, #block--singleContent, #block--state").addClass('column');
				} else {
					$("#block--config, #block--singleContent, #block--state").removeClass('column');
				}

				for (var i in payload.snippets) {
					jQuery.netopeergui.updateSnippet(i, payload.snippets[i]);
				}

				initJS();
				if (typeof initModuleDefaultJS !== "undefined") {
					initModuleDefaultJS();
				}
			}
		},

		// create animated spinner
		createSpinner: function()	{
			return this.spinner = $('<div></div>').attr('id', 'ajax-spinner').appendTo('body').hide();
		},

		setActiveLink: function($link) {
			if ($link.data().doNotActivate === true) {
				return;
			}

			$link.parents('nav').find('.active').removeClass('active');
			$link.addClass('active');
		}
	}
});

window.onpopstate = function(o) {
	if (o.state !== null) {
		var href = o.state;
		var $THIS = $("<div></div>");

		if ($('a[href="'+ href +'"]').length) {
			$THIS = $('a[href="'+ href +'"]');
		}

		$("#block--alerts").html('');

		/**
		 * spinner zobrazit az po 100ms
		 */
		$.netopeergui.spinnerTimer = setTimeout(function() {
			$('#ajax-spinner').fadeIn();
		}, 100);

		$.ajax({
			dataType: 'json',
			type: 'post',
			url: href,
			success: function(data, textStatus, jqXHR) {
				$THIS.attr('data-disable-history', "true");
				successAjaxFunction(data, textStatus, jqXHR, href, $THIS);
			},
			error: function() {
				window.location.href = href;
			}
		});
	}
};

jQuery(function($) {
	$.netopeergui.createSpinner();

	$(document).on('click', 'a.ajaxLink', function(e) {
		loadAjaxLink(e, $(this), $(this).attr('href'), "GET", '');
	});

	$("section, #block--leftColumn").on('submit', 'form', function(e) {
		if ($(this).data().disableActiveLink == undefined) {
			$(this).attr('data-disable-active-link', true);
		}
		var formAction = $(this).attr('action');
		if (formAction == "") {
			formAction = window.location.href;
		}
		loadAjaxLink(e, $(this), formAction, 'POST', $(this).serialize());
	});

	$("body").on('submit', '.modal form', function(e) {
		$(this).attr('data-callback', 'hideAndEmptyModalWindow()');
		loadAjaxLink(e, $(this), $(this).attr('action'), 'POST', $(this).serialize());
		
	});
});

function loadAjaxLink(e, $THIS, href, type, data) {
	e.preventDefault();

	var shouldLoadingContinue = formInputChangeConfirm(true);
	if (!shouldLoadingContinue) {
		return;
	}

	$("#block--alerts").html('');

	/**
	 * spinner zobrazit az po 100ms
	 */
	$.netopeergui.spinnerTimer = setTimeout(function() {
		$('#ajax-spinner').fadeIn();
	}, 100);

	$.ajax({
		url: href,
		dataType: "json",
		data: data,
		type: type,
		success: function(data, textStatus, jqXHR) {
//			l('succ');
			successAjaxFunction(data, textStatus, jqXHR, href, $THIS);
		},
		error: function(qXHR, textStatus, errorThrown) {
//			l(qXHR);
//			l(textStatus);
//			l(errorThrown);
			window.location.href = href;
		}
	});
}

function successAjaxFunction(data, textStatus, jqXHR, href, $elem) {
	if ($elem.data().ajaxRedirect) {
		data.redirect = href;
	}
	$('#ajax-spinner').fadeOut();
	clearTimeout($.netopeergui.spinnerTimer);

//	l(data);
//	l($elem);

	$.netopeergui.success(data);
	if ($('a[href="'+ href +'"]').length && $elem.data().disableActiveLink !== true) {
		$.netopeergui.setActiveLink($('a[href="'+ href +'"]'));
	}

	if ($elem.data().disableHistory !== true) {
		var historyHref = href;
		if (data.historyHref !== "") {
			historyHref = data.historyHref;
		}
		history.pushState(historyHref, "", historyHref);
	}

	if ($elem.data().callback !== undefined) {
		eval($elem.data().callback + ';');
	}
}