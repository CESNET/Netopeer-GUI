var wsUri = "ws://sauvignon.liberouter.org:8080/";
var notifOutput; 
var number_notification = 0;

function notifInit()
{
	notifOutput = document.getElementById('notif-output');
} 
function notifWebSocket()
{
	websocket = new WebSocket(wsUri, "notification-protocol");
	websocket.onopen = function(evt) { onOpen(evt) };
	websocket.onclose = function(evt) { onClose(evt) };
	websocket.onmessage = function(evt) { onMessage(evt) };
	websocket.onerror = function(evt) { onError(evt) };
}
function onOpen(evt)
{
	writeToScreen("CONNECTED");
}
function onClose(evt)
{
	writeToScreen("DISCONNECTED");
}
function onMessage(evt)
{
	writeToScreen(evt.data);
	number_notification++;
	if (number_notification >= 10) {
		websocket.close();
	}
}
function onError(evt)
{
	writeToScreen('ERROR: ' + evt.data);
}
function doSend(message)
{
	writeToScreen("SENT: " + message);
	websocket.send(message);
}
function writeToScreen(message)
{
	var pre = document.createElement("p");
	pre.style.wordWrap = "break-word";
	//pre.innerHTML = message;
	pre.innerText = message;
	notifOutput.appendChild(pre);
}
//window.addEventListener("load", init, false);  

$(document).ready(function () {
	notifInit();
	//$("#notif-bar").dialog({autoOpen: false });
});

