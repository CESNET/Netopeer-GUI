$(document).ready(function() {
	initModuleDefaultJS();
});

function initModuleDefaultJS() {
	//angular.bootstrap($('#block--singleContent'));
	angular.element(document).ready(function() {
		try {
			angular.bootstrap(document, ['NetopeerGUIApp']);
		} catch (err) {
			var content = $('#block--singleContent');
			angular.element(document).injector().invoke(function($compile) {
				var scope = angular.element(content).scope();
				$compile(content)(scope);
			});
		}
	});


	$("form").on("change", ".js-auto-submit-on-change", function() {
		$(this).parents('form').submit();
	});
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
