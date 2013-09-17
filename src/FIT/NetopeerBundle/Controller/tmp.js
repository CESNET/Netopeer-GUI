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