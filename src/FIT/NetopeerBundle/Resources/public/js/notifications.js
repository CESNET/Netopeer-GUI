var wsUri = "ws://sauvignon.liberouter.org:8080/";
var notifEsteblised = false;
var notifOutput; 
var number_notification = 0;
	var notificationsHeight = 0.1; // in percent

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

function notifWebSocket() {
	websocket = new WebSocket(wsUri, "notification-protocol");
	websocket.onopen = function(evt) {
		notifEsteblised = true;
		addInfo("Connection establised.");
	};

	websocket.onclose = function(evt) {
		notifEsteblised = false;
		addInfo("Connection closed.");
	};

	websocket.onmessage = function(evt) {
		addMessage(evt.data);
		number_notification++;
		if (number_notification >= 10) {
			websocket.close();
		}
	};
	websocket.onerror = function(evt) {
		addError(evt.data);
	};
}

function doSend(message) {
	addSend(message);
	websocket.send(message);
}

function addInfo(mess) {
	writeToScreen(mess, "info", "Info:");
}

function addError(mess) {
	writeToScreen(mess, "error red", "Error:");
}

function addMessage(mess) {
	writeToScreen(mess, "message green", "Message:");
}

function addSend(mess) {
	writeToScreen(mess, "send", "Sent:");
}

function writeToScreen(mess, textClass, text) {
	if (!notifOutput) {
		notifInit();
	}
	var output = $("<div></div>").append($("<strong></strong>").addClass(textClass).text(text)).append($('<span></span>').addClass('mess').text(mess));
	notifOutput.prepend(output);
}

