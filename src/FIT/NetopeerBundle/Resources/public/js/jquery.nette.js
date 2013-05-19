/**
 * AJAX Nette Framwork plugin for jQuery
 *
 * @copyright   Copyright (c) 2009 Jan Marek
 * @license     MIT
 * @link        http://nettephp.com/cs/extras/jquery-ajax
 * @version     0.2
 */

jQuery.extend({
	nette: {
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

				var tmp = $('<section></section>').attr('id', 'block--config').insertAfter($afterEl);
				tmp.addClass('left-nav-defined');
				$afterEl.attr('id', 'block--state').addClass('left-nav-defined');
			}
			$("#" + id).html(html);
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
					jQuery.nette.updateSnippet(i, payload.snippets[i]);
				}

				prepareAlertsActions();
				prepareTooltipActions();
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
		var $THIS = $("");

		if ($('a[href="'+ href +'"]').length) {
			$THIS = $('a[href="'+ href +'"]');
		}

		$("#block--alerts").html('');

		/**
		 * spinner zobrazit az po 100ms
		 */
		$.nette.spinnerTimer = setTimeout(function() {
			$('#ajax-spinner').fadeIn();
		}, 100);

		$.ajax({
			dataType: 'json',
			type: 'post',
			url: href,
			success: function(data, textStatus, jqXHR) {
				$THIS.data().disableHistory = true;
				successAjaxFunction(data, textStatus, jqXHR, href, $THIS);
			},
			error: function() {
				window.location.href = href;
			}
		});
	}
};

jQuery(function($) {
	$.nette.createSpinner();

	$(document).on('click', 'a.ajaxLink', function(e) {
		loadAjaxLink(e, $(this), $(this).attr('href'), "GET", '');
	});

	$("section").on('submit', 'form', function(e) {
		loadAjaxLink(e, $(this), $(this).attr('action'), 'POST', $(this).serialize());
	})
});

function loadAjaxLink(e, $THIS, href, type, data) {
	e.preventDefault();

	$("#block--alerts").html('');

	/**
	 * spinner zobrazit az po 100ms
	 */
	$.nette.spinnerTimer = setTimeout(function() {
		$('#ajax-spinner').fadeIn();
	}, 100);

	$.ajax({
		url: href,
		dataType: "json",
		data: data,
		type: type,
		success: function(data, textStatus, jqXHR) {
			successAjaxFunction(data, textStatus, jqXHR, href, $THIS);
		},
		error: function() {
			window.location.href = href;
		}
	});
}

function successAjaxFunction(data, textStatus, jqXHR, href, $elem) {
	if ($elem.data().ajaxRedirect) {
		data.redirect = href;
	}
	$('#ajax-spinner').fadeOut();
	clearTimeout($.nette.spinnerTimer);

	l(data);
	l($elem);

	$.nette.success(data);
	if ($('a[href="'+ href +'"]').length) {
		$.nette.setActiveLink($('a[href="'+ href +'"]'));
	}

	if ($elem.data().disableHistory !== true) {
		var historyHref = href;
		if (data.historyHref !== "") {
			historyHref = data.historyHref;
		}
		history.pushState(historyHref, "", historyHref);
	}

	if ($elem.data().callback !== undefined) {
		var a = $elem.data().callback;
		eval(a + '();');
	}
}