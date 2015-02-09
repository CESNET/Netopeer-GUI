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

$(document).ready(function() {
	setIndexActions();
});

function setIndexActions() {
	setLeftPaneHeight();
	reloadHistoryOfConnectedDevices();
	reloadProfilesOfConnectedDevices();
}

$(window).resize(function() {
	setLeftPaneHeight();
});

function prepareFlashMessage(message) {
	var flashMessages = $("<div/>");
	$.each(message, function(status, messArr) {
		$.each(messArr, function(key, mess) {
			var flashMessage = $("<div/>", {
				"class": "message " + status
			});
			flashMessage.text(mess);
			flashMessage.prepend($("<span/>").addClass('circle'));
			flashMessage.prepend($("<span/>").addClass('close').text('X'));

			flashMessage.appendTo($(flashMessages));
		});
	});

	return flashMessages.html();
}

function setLeftPaneHeight() {
	$("#history-and-profiles").height($(window).height() - parseInt($("#block--leftColumn").css('padding-top'), 10));
}

function delegateAnchorActions($elem) {
	$elem.delegate(".icon", 'click', function($e) {
		$e.preventDefault();
		var $target = $($e.target);
		$.get($target.data().action, function(data) {
			var jsonArr = $.parseJSON(data);
			if (jsonArr['result'] === 0) {
				if ($target.hasClass('delete')) {
					$target.parents('a.device-item').unbind('click').remove();
					if ($elem.attr('id') == "block--profilesOfConnectedDevices") {
						reloadProfilesOfConnectedDevices(true);
					}
				} else if ($target.hasClass('addToProfiles')) {
					reloadProfilesOfConnectedDevices(true);
				}
			}
			if (jsonArr['message']) {
				var flash = prepareFlashMessage(jsonArr['message']);
				$("#block--alerts").append($(flash));
			}
		});
	}).delegate("a.device-item", 'click', function($e) {
		$e.preventDefault();

		var $target = $($e.target);
		if (!$target.hasClass('icon')) {
			updateConnectFormValues($(this));
		}
	})
}

function reloadHistoryOfConnectedDevices(force) {
	$.ajax({
		url: historyOfConnectedDevices,
		dataType: "json",
		success: function(data) {
			createHistoryOrProfileBox("block--historyOfConnectedDevices", data, true, force);
		}
	});
}

function createHistoryOrProfileBox(selector, data, prepend, force) {
	if (!$("#"+selector).length || force === true) {
		$("#"+selector).remove();

		if (selector == "block--profilesOfConnectedDevices" && data.snippets['block--profilesOfConnectedDevices'].trim() === "") {
			return;
		}

		var newBox = $("<div/>").attr('id', selector).addClass('scrollable-cover').attr("data-parent", "#"+selector);

		if (prepend) {
			$("#history-and-profiles").prepend(newBox);
		} else {
			$("#history-and-profiles").append(newBox);
		}

		$.netopeergui.processResponseData(data);
		delegateAnchorActions($("#"+selector));
	}
}

function reloadProfilesOfConnectedDevices(force) {
	$.ajax({
		url: profilesOfConnectedDevices,
		dataType: "json",
		success: function(data) {
			createHistoryOrProfileBox("block--profilesOfConnectedDevices", data, false, force);
		}
	});
}

function updateConnectFormValues($el) {
	var data = $el.data();

	$("#form_host").val(data.host);
	$("#form_port").val(data.port);
	$("#form_user").val(data.user);
	$("#form_password").focus();

	$("#history-and-profiles").find('a').removeClass('active');
	$el.addClass('active');
}

function disconnectCallback(key) {
	unsetNotificationsForKey(key);
}