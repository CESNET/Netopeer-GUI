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

			l(payload);

			// snippets
			if (payload.snippets) {
				if (!("block--config" in payload.snippets) && payload.treeColumns !== true) {
					$("#block--config").remove();
					$("#block--state").attr('id', 'singleContent');
				}
				if (("block--config" in payload.snippets) && (("block--state" in payload.snippets) || ("block--singleContent" in payload.snippets))) {
					$("#block--config, #block--singleContent, #block--state").addClass('column');
				} else {
					$("#block--config, #block--singleContent, #block--state").removeClass('column');
				}

				for (var i in payload.snippets) {
					jQuery.nette.updateSnippet(i, payload.snippets[i]);
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
		$.nette.setActiveLink($('a[href="'+ href +'"]'));

		$.ajax({
			dataType: 'json',
			type: 'post',
			url: href,
			success: function (data) {
				$.nette.success(data);
			}
		});
	}
};

jQuery(function($) {
	$.nette.createSpinner();

	$(document).on('click', 'a.ajaxLink', function(e) {
		e.preventDefault();

		/**
		 * spinner zobrazit az po 100ms
		 */
		$.nette.spinnerTimer = setTimeout(function() {
			$('#ajax-spinner').fadeIn();
		}, 100);

		var $THIS = $(this);
		var href = $THIS.attr('href');
		$.ajax({
			url: href,
			dataType: "json",
			success: function(data, textStatus, jqXHR) {
				if ($THIS.data().ajaxRedirect) {
					data.redirect = href;
				}
				$('#ajax-spinner').fadeOut();
				clearTimeout($.nette.spinnerTimer);

				l(data);

				$.nette.success(data);
				$.nette.setActiveLink($('a[href="'+ href +'"]'));

				if ($THIS.data().disableHistory !== true) {
					history.pushState(href, "", href);
				}
			},
			error: function() {
				window.location.href = href;
			}
		});
	});
});
