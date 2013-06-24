var notifOutput;
var notificationsHeight = 0.1; // in percent
var notifications = new Array();

function notifInit() {
	notifOutput = $("#block--notifications");
}

function notifResizable() {
	if (!notifOutput) {
		notifInit();
	}

	if (!$('.ui-resizable-handle').length) {
		try {
			if ($(notifOutput).resizable) {
				$(notifOutput).resizable('destroy');
			}
		} catch(err) {
			// nothing happened, resizable not initialized yet
		}
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
}

function getNotifWebSocket(key, hash, wsUri) {
	notifResizable();

	var socket;
	if (notifications[key] === undefined) {
		socket = new $.fn.notifWebSocket(key, wsUri);
		var sendInterval = setInterval(function() {
			if (socket.isActive === true) {
				socket.doSend(hash + ' -10 0');
				clearInterval(sendInterval);
			}
		}, 1000);
	} else {
		socket = notifications[key];
		if (!notifOutput.find('.notif').length) {
			socket.printSavedMessages();
		}
	}

	return socket;
}

function unsetNotificationsForKey(key) {
	notifications.splice(notifications.indexOf(key),1);
}

$.fn.notifWebSocket = function(key, wsUri) {
	this.key = key;
	this.messages = new Array();
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

		this.saveMessage = function(mess) {
			this.messages.push(mess);
		};

		this.printSavedMessages = function() {
			var i = 0;
			var notifCover = notifOutput.find('.notif-cover');
			while(i < this.messages.length) {
				notifCover.append(this.messages[i]);
				i++;
			}
		};

		this.writeToScreen = function(mess, textClass, text) {
			if (!notifOutput) {
				notifInit();
			}

			var parsed_text = mess;
			var parsed_time = '';
			if (mess[0] === '{') {
				/* TODO sanitize string? handle error? */
				var parsed = $.parseJSON(mess);
				parsed_text = parsed.content;
				parsed_time = parsed.eventtime;
			}

			var output = $("<div></div>").addClass('notif').append($("<strong></strong>").addClass(textClass).text(text)).append($('<span></span>').addClass('mess').text(parsed_text));
			if (parsed_time !== '') {
				output.prepend($("<div></div>").addClass('time').text(parsed_time));
			}
			var notifCover = notifOutput.find('.notif-cover');
			this.saveMessage(output);
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
	}

	return this;
};