
$(document).ready(function() {
	$("body > section").height(	$(window).height() - $("nav#top").height());

	if ( $(".edit-bar").length ) {
		// zobrazime jinak skryte ikonky pro pridavani potomku (novych listu XML)
		$(".type-list .edit-bar .sibling").show();

		$('.type-list .edit-bar .sibling').click(function() {
			duplicateNode($(this));
		});
		$(".edit-bar .remove-child").click(function() {
			removeNode($(this));
		});

		// $('.edit-bar .child').click(function() {
		//	createNode($(this));
		// });
	}

	// tooltip
	$('.tooltip .icon-help').each(function() {
		$(this).gips({ 'theme': 'blue', placement: 'top', animationSpeed: 100, bottom: $(this).parent().parent().parent().outerHeight(), text: $(this).siblings('.tooltip-description').text() });
	});

	// zebra style on XML
	// $(".level-0:not(.container):not(.list)").find('*[class*=level]:even, .leaf-line:even').addClass('even');

	/* hide alerts after some time */
	setTimeout(function() {
		$('.alert').fadeOut();
	}, 10000); /* 10s */
});

function duplicateNode($elem) {
	if ($elem.parents('#state').length) {
		$cover = $("#state");
	} else if ($elem.parents('#config').length) {
		$cover = $("#config");
	} else {
		$cover = $("#content");
	}

	if ( $cover.find(".form-underlay").length === 0 ) {
		$cover.append($("<div>").addClass('form-underlay'));
		$cover.append($("<div>").addClass('form-cover'));
	}

	// pro novy form-underlay budeme muset vypocitat rozmery
	// a natahnout ho pres celou konfiguracni cast. Nelze zde
	// pouzit position absolute, protoze to zamezuje scrollovani
	// v konfiguracni casti
	l($cover.children('form').outerHeight());
	l($cover);
	var nWidth = $cover.outerWidth(),
		nHeight = $cover.children('form').outerHeight() + parseInt($cover.css('padding-top'), 10) + parseInt($cover.css('padding-bottom'), 10) + 200 + $elem.parent().parent().parent().outerHeight();
	$cover.find(".form-underlay").width(nWidth).height(nHeight * 2).css({
		'margin-top': 0 - nHeight,
		'margin-left': 0 - parseInt($cover.css('padding-left'), 10)
	});

	var xPath = $elem.attr('rel'),	// zjistime xPath rodice - udavame v atributu rel u odkazu
		levelRegex = /level-(\d+)/,	// regularni vyraz pro zjisteni cisla levelu
		level = $elem.parents('.leaf-line').attr('class');	// trida rodice pro zjisteni levelu

	if ( level.match(levelRegex) === null || ( level.match(levelRegex) !== null && isNaN(level.match(levelRegex)[1]) ) ) {

		// level nemusi byt u prvniho rodice uveden, muze se stat, ze se nachazi az o jednu uroven vyse
		if ( $elem.parents('.leaf-line').parent().length ) {
			level = $elem.parents('.leaf-line').parent().attr('class');
			if ( level.match(levelRegex) === null || ( level.match(levelRegex) !== null && isNaN(level.match(levelRegex)[1]) ) ) {
				level = 0;
			} else {
				level = level.match(levelRegex)[1];
			}
		} else {
			level = 0;
		}
		
	} else {
		level = level.match(levelRegex)[1];
	}

	// pokud se jedna o vygenerovanou cast, pridame potomka k rodici (obalujici div)
	// level = level - 1;
	$currentParent = $elem.parent().parent();
	$currentParentLevel = $elem.parents('.level-' + level);

	// objekt formulare, pokud existuje, pouzijeme stavajici, jinak vytvorime novy
	if ( $(".generatedForm").length ) {
		$form = $('.generatedForm');
	} else {
		// vytvorime formular
		$form = $("<form>")
			.attr({
				action: "#",
				method: "POST",
				name: "duplicatedNodeForm",
				'class': 'generatedForm'
			});
	}

	// naklonujeme si aktualni element
	$newClone = $elem.parent().parent().clone();
	$form.html($newClone);
	if ($elem.parent().parent().is(':first-child')) {
		$elem.parent().parent().nextAll("*").each(function(i, el) {
			$form.append($(el).clone());
		});
	}
	$form.find('.state').remove();

	// vlozime skryty input s cestou k duplikovanemu elementu
	$elementWithParentXpath = $("<input>")
		.attr({
			type: 'hidden',
			name: "duplicatedNodeForm[parent]",
			value: xPath
		});
	$form.prepend($elementWithParentXpath);

	$form.children().each(function(i, el) {
		modifyInputAttributes(el, i, xPath);
	});

	// nakonec vytvorime submit - pokud existuje, smazeme jej
	if ( $form.children("input[type=submit]").length ) {
		$form.children("input[type=submit]").remove();
	}
	$elementSubmit = $("<input>")
		.attr({
			type: 'submit',
			value: 'Save changes'
		});
	$form.append($elementSubmit);
	$elementSubmit.bind('click', function() {
		$form.submit();
	});

	$closeButton = $("<a href='#' title='Close' class='close button'>Close</a>");
	$form.append($closeButton);
	$closeButton.bind('click', function() {
		$originalForm = $cover.children('form');
		$cover.find('.root').wrap($originalForm);
		$form.remove();
		$('.form-underlay').remove();
		$('.form-cover').remove();
	});
	$currentParentLevel.append($form);
	$oldForm = $currentParentLevel.parents('form').clone();
	$oldForm.html('');
	$cover.prepend($oldForm);
	$currentParentLevel.parents('form').children('.root').unwrap();
}

function modifyInputAttributes(el, newIndex, xPath) {
	uniqueId = String(getUniqueId());

	// vyprazdnime pripadny edit-bar
	$(el).find('.edit-bar').html('');

	newXpath = xPath + '*?' + newIndex + '!';
	inputArr = $.merge( $(el).children('input'), $(el).children('.config-value-cover').find('input') );
	inputArr.each(function(i, e) {
		elName = $(e).attr('name').replace('configDataForm', 'duplicatedNodeForm');
		$(e).attr('name', elName);
		if ( $(e).attr('default') !== "" ) {
			if ( $(e).attr('type') == 'radio' ) {
				if ( $(e).attr('value') == $(e).attr('default') ) {
					$(e).parent().parent().find('input[checked=checked]').removeAttr('checked');
					$(e).attr('checked', 'checked');
				}
			} else {
				if ( $(e).attr('value') != $(e).attr('default') ) {
					$(e).attr('value', $(e).attr('default'));
				}
			}
		}
		$(e).removeAttr('disabled');
	});

	if ( $(el).children('.leaf-line, div[class*=level]').length ) {
		$(el).children('.leaf-line, div[class*=level]').each(function(j, elem) {
			modifyInputAttributes(elem, j, newXpath);
		});
	}
}

function createNode($elem) {
	if ( $(".form-underlay").length === 0 ) {
		$("#config").append($("<div>").addClass('form-underlay'));
		$("#config").append($("<div>").addClass('form-cover'));
	}

	// pro novy form-underlay budeme muset vypocitat rozmery
	// a natahnout ho pres celou konfiguracni cast. Nelze zde
	// pouzit position absolute, protoze to zamezuje scrollovani
	// v konfiguracni casti
	var nWidth = $("#config").outerWidth(),
		nHeight = $("form[name='formConfigData']").outerHeight() + parseInt($("#config").css('padding-top'), 10) + parseInt($("#config").css('padding-bottom'), 10) + 200;
	$(".form-underlay").width(nWidth).height(nHeight).css({
		'margin-top': 0 - nHeight,
		'margin-left': 0 - parseInt($("#config").css('padding-left'), 10)
	});

	var xPath = $elem.attr('rel'),	// zjistime xPath rodice - udavame v atributu rel u odkazu
		levelRegex = /level-(\d+)/,	// regularni vyraz pro zjisteni cisla levelu
		level = $elem.parents('.leaf-line').attr('class'),	// trida rodice pro zjisteni levelu
		$editBar = $elem.parent().clone();	// naklonujeme si editBar (nize dochazi k upravam)

	if ( level.match(levelRegex) === null || ( level.match(levelRegex) !== null && isNaN(level.match(levelRegex)[1]) ) ) {

		// level nemusi byt u prvniho rodice uveden, muze se stat, ze se nachazi az o jednu uroven vyse
		if ( $elem.parents('.leaf-line').parent().length ) {
			level = $elem.parents('.leaf-line').parent().attr('class');
			if ( level.match(levelRegex) === null || ( level.match(levelRegex) !== null && isNaN(level.match(levelRegex)[1]) ) ) {
				level = 0;
			} else {
				level = level.match(levelRegex)[1];
			}
		} else {
			level = 0;
		}
		
	} else {
		level = level.match(levelRegex)[1];
	}

	// vytvorime div obalujici inputy
	level = parseInt(level, 10) + 1;
	$cover = $("<div>").addClass('leaf-line').addClass('level-' + String(level)).addClass('generated');

	// objekt formulare, pokud existuje, pouzijeme stavajici, jinak vytvorime novy
	if ( $(".generatedForm").length ) {
		$form = $('.generatedForm');
	} else {
		// vytvorime formular
		$form = $("<form>")
			.attr({
				action: "#",
				method: "POST",
				name: "newNodeForm",
				'class': 'generatedForm'
			});
	}

	uniqueId = String(getUniqueId());
	// input pro nazev elementu
	$elementName = $("<input>")
		.attr({
			name: 'newNodeForm[label_' + uniqueId + '_' + xPath + ']',
			type: 'text',
			'class': 'label'
		});
	$cover.append($elementName);
	$elementName.before($("<span>").addClass('dots'));

	// input pro hodnotu elementu
	$elementValue = $("<input>")
		.attr({
			name: 'newNodeForm[value_' + uniqueId + '_' + xPath + ']',
			type: 'text',
			'class': 'value'
		});
	$cover.append($elementValue);

	// upravime si naklonovany editBar - pridame tridu pro odliseni vygenerovaneho baru
	$editBar.children("img").addClass('generated');
	// delegujeme click akci na nove vytvoreny element editBar
	$editBar.children("img.sibling").on('click', function() {
		createNode($(this));
	});

	// ke coveru pripojime editBar
	$cover.prepend($editBar);

	// pokud se jedna o vygenerovanou cast, pridame potomka k rodici (obalujici div)
	level = level - 1;
	$currentParent = $elem.parent().parent();
	$currentParentLevel = $elem.parents('.level-' + level);

	if ( $currentParentLevel.length && $currentParentLevel.hasClass('leaf-line') ) {
		l ( "ano");
		// jelikoz pridavame dalsi potomky, musime vlozit aktualni inputy rodice take do coveru leaf-line
		$leaf = $("<div>").addClass('leaf-line').html($currentParent.html());

		// formular jiz mame vytvoreny, pouze tedy pridame
		if ( $('.generatedForm').length ) {
			$currentParent.removeClass('leaf-line').html('').prepend($leaf).append($cover);
		// jinak se jedna o prvni node, vytvorime tedy formular
		} else {
			$currentParent.removeClass('leaf-line').html('').prepend($leaf).append($form);
			$form.append($cover);
			$leaf.addClass('active');
		}

		// jelikoz jsme premistili ikonky do coveru ($leaf), musime jim znova pridat akci click
		$currentParent.children("form .leaf-line:first-child").children('.edit-bar').children("img").on('click', function() {
			createNode($(this));
		});

		l($cover);

	} else {
		l ( "ne");
		// formular jiz mame vytvoreny, pouze tedy pridame
		if ( $('.generatedForm').length ) {
			if ( $currentParent.parents('.generatedForm').length ) {
				$currentParent.nextAll(":last").after($cover);
			} else {
				$(".generatedForm").append($cover);
			}
		// jinak se jedna o prvni node, vytvorime tedy formular
		} else {
			$currentParentLevel.append($form);
			$form.append($cover);
			$elem.parents('.leaf-line').addClass('active');
		}
	}

	// nyni je nutne upravit xPath vygenerovanych inputu a ikonek
	$originalInput = $cover.children('input.value, input.label');
	newIndex = $cover.index();
	if (newIndex < 1) newIndex = 1;

	$originalInput.each(function(i,e) {
		newXpath = $(e).attr('name') + '[' + newIndex + ']';
		$(e).attr('name', newXpath);
	});

	// nesmime zapomenout pridat pozmeneny xPath take k ikonkam pro pridani dalsi node
	$newRel = $cover.children('.edit-bar').children('img');
	$newRel.attr('rel', $newRel.attr('rel') + '][' + newIndex);

	// nakonec vytvorime submit - pokud existuje, smazeme jej
	if ( $form.children("input[type=submit]").length ) {
		$form.children("input[type=submit]").remove();
	}
	$elementSubmit = $("<input>")
		.attr({
			type: 'submit',
			value: 'Save changes'
		});
	$form.append($elementSubmit);
}

function removeNode($elem) {
	if ($elem.parents('#state').length) {
		$cover = $("#state");
	} else if ($elem.parents('#config').length) {
		$cover = $("#config");
	} else {
		$cover = $("#content");
	}

	if ( $cover.find(".form-underlay").length === 0 ) {
		$cover.append($("<div>").addClass('form-underlay'));
		$cover.append($("<div>").addClass('form-cover'));
	}

	// pro novy form-underlay budeme muset vypocitat rozmery
	// a natahnout ho pres celou konfiguracni cast. Nelze zde
	// pouzit position absolute, protoze to zamezuje scrollovani
	// v konfiguracni casti
	l($cover.children('form').outerHeight());
	l($cover);
	var nWidth = $cover.outerWidth(),
		nHeight = $cover.children('form').outerHeight() + parseInt($cover.css('padding-top'), 10) + parseInt($cover.css('padding-bottom'), 10) + 200 + $elem.parent().parent().parent().outerHeight();
	$cover.find(".form-underlay").width(nWidth).height(nHeight * 2).css({
		'margin-top': 0 - nHeight,
		'margin-left': 0 - parseInt($cover.css('padding-left'), 10)
	});

	var xPath = $elem.attr('rel'),	// zjistime xPath rodice - udavame v atributu rel u odkazu
		levelRegex = /level-(\d+)/,	// regularni vyraz pro zjisteni cisla levelu
		level = $elem.parents('.leaf-line').attr('class');	// trida rodice pro zjisteni levelu
	

	if ( level.match(levelRegex) === null || ( level.match(levelRegex) !== null && isNaN(level.match(levelRegex)[1]) ) ) {

		// level nemusi byt u prvniho rodice uveden, muze se stat, ze se nachazi az o jednu uroven vyse
		if ( $elem.parents('.leaf-line').parent().length ) {
			level = $elem.parents('.leaf-line').parent().attr('class');
			if ( level.match(levelRegex) === null || ( level.match(levelRegex) !== null && isNaN(level.match(levelRegex)[1]) ) ) {
				level = 0;
			} else {
				level = level.match(levelRegex)[1];
			}
		} else {
			level = 0;
		}
		
	} else {
		level = level.match(levelRegex)[1];
	}

	// pokud se jedna o vygenerovanou cast, pridame potomka k rodici (obalujici div)
	// level = level - 1;
	$currentParent = $elem.parent().parent();
	$currentParentLevel = $elem.parents('.level-' + level);

	// objekt formulare, pokud existuje, pouzijeme stavajici, jinak vytvorime novy
	if ( $(".generatedForm").length ) {
		$form = $('.generatedForm');
	} else {
		// vytvorime formular
		$form = $("<form>")
			.attr({
				action: "#",
				method: "POST",
				name: "removeNodeForm",
				'class': 'generatedForm'
			});
	}

	$form.find('.state').remove();

	// vlozime skryty input s cestou k duplikovanemu elementu
	$elementWithParentXpath = $("<input>")
		.attr({
			type: 'hidden',
			name: "removeNodeForm[parent]",
			value: xPath
		});
	$form.prepend($elementWithParentXpath);

	$form.children().each(function(i, el) {
		modifyInputAttributes(el, i, xPath);
	});

	// nakonec vytvorime submit - pokud existuje, smazeme jej
	if ( $form.children("input[type=submit]").length ) {
		$form.children("input[type=submit]").remove();
	}
	$elementSubmit = $("<input>")
		.attr({
			type: 'submit',
			value: 'Delete record'
		});
	$form.append($elementSubmit);
	$elementSubmit.bind('click', function() {
		$form.submit();
	});

	$closeButton = $("<a href='#' title='Cancel' class='close button'>Cancel</a>");
	$form.append($closeButton);
	$closeButton.bind('click', function() {
		$originalForm = $cover.children('form');
		$cover.find('.root').wrap($originalForm);
		$form.remove();
		$('.form-underlay').remove();
		$('.form-cover').remove();
	});
	$currentParentLevel.append($form);
	$oldForm = $currentParentLevel.parents('form').clone();
	$oldForm.html('');
	$cover.prepend($oldForm);
	$currentParentLevel.parents('form').children('.root').unwrap();
}

function getUniqueId() {
	return Math.floor( Math.random()*99999 );
}

function l (str) {
	if (console.log) console.log(str);
}
