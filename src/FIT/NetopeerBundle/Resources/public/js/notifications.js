var wsUri = "ws://sauvignon.liberouter.org:8080/";
var notifOutput; 
var number_notification = 0;

function notifInit() {
	notifOutput = $("#block--notifications");
}

function notifWebSocket() {
	websocket = new WebSocket(wsUri, "notification-protocol");
	websocket.onopen = function(evt) {
		addInfo("Connection establised.");
	};

	websocket.onclose = function(evt) {
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

