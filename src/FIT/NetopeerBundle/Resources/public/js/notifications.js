var notifOutput;
var notificationsHeight = 0.1; // in percent
var notifications = new Array();

function notifInit() {
	notifOutput = $("#block--notifications");
	$(notifOutput).resizable({
		handles: 'n',
		minHeight: 10,
		resize: function(event, ui) {
			ui.size.width = ui.originalSize.width;
			ui.position.left = ui.originalPosition.left;
			notificationsHeight = ui.size.height * 100 / $(window).height();
			ui.position.top = $(window).height() * (1 - notificationsHeight);
		}
	});
}

$.fn.notifWebSocket = function(key, wsUri) {
	this.key = key;
	if (notifications[key] === undefined) {
		notifications[key] = new Array();
	}
	if (!notifications[key].length) {
		this.websocket = new WebSocket(wsUri, "notification-protocol");
		notifications[key] = this;

		this.websocket.onopen = function(evt) {
			notifications[key].isActive = true;
			notifications[key].addInfo("Connection establised.");
		};

		this.websocket.onclose = function(evt) {
			notifications[key].isActive = false;
			notifications[key].addInfo("Connection closed.");
		};

		this.websocket.onmessage = function(evt) {
			notifications[key].addMessage(evt.data);
		};
		this.websocket.onerror = function(evt) {
			notifications[key].addError(evt.data);
		};

//		this.websocket.send(toSend);
	}

	this.doSend = function(message) {
		this.websocket.send(message);
		this.addSend(message);
	};

	this.addInfo = function(mess) {
		this.writeToScreen(mess, "info", "Info:");
	};

	this.addError = function(mess) {
		this.writeToScreen(mess, "error red", "Error:");
	};

	this.addMessage = function(mess) {
		this.writeToScreen(mess, "message green", "Message:");
	};

	this.addSend = function(mess) {
		this.writeToScreen(mess, "send", "Sent:");
	};

	this.writeToScreen = function(mess, textClass, text) {
		if (!notifOutput) {
			notifInit();
		}

		var parsed_message = mess;
		if (mess[0] === '{') {
			/* TODO sanitize string? handle error? */
			var parsed = JSON.parse(mess);
			parsed_message = parsed.eventtime + ": " + parsed.content;
		}

		var output = $("<div></div>").addClass('notif').append($("<strong></strong>").addClass(textClass).text(text)).append($('<span></span>').addClass('mess').text(parsed_message));
		var notifCover = notifOutput.find('.notif-cover');
		notifCover.append(output);
		notifCover.animate({
			scrollTop: notifCover.scrollTop() + $(output).offset().top
		}, 10);
		notifCover.animate({
			opacity: 0.3
		}, 200, function() {
			notifCover.animate({
				opacity: 1
			}, 100);
		});

	};

	return this;
};

