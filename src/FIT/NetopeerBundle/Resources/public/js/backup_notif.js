jQuery.extend({
	notifications: {
		socket: null,
		opts: null,

		init: function( options ){

			this.opts = $.extend({
				host: "ws://localhost:8080/",
				cover: "#block--notifications",
				globalActive: "isNotificationActive"
			}, options );

			try{
				var socket = new WebSocket(this.opts.host, "notification-protocol");

				socket.onopen = function(){
					$.notifications.addInfo('Socket Status: Open');
					eval($.notifications.opts.globalActive + " = true;");
				};

				socket.onmessage = function(msg){
					$.notifications.addMessage(msg.data);
				};

				socket.onerror = function(msg){
					$.notifications.addError(msg.data);
				};

				socket.onclose = function(){
					$.notifications.addInfo('Socket Status: Closed');
					eval($.notifications.opts.globalActive + " = false;");
				};

				this.socket = socket;
				l(this);

			} catch(exception){
				this.addError(exception);
				this.opts.isActive = false;
			}

			return this;
		},

		addInfo: function(mess) {
			this.writeToScreen(mess, "info", "Info:");
		},

		addError: function(mess) {
			this.writeToScreen(mess, "error red", "Error:");
		},

		addWarning: function(mess) {
			this.writeToScreen(mess, "warning red", "Warning:");
		},

		addMessage: function(mess) {
			this.writeToScreen(mess, "message", "Message:");
		},

		addSend: function(mess) {
			this.writeToScreen(mess, "send green", "Sent:");
		},

		send: function(text){
			try{
				this.socket.send(text);
				this.addSend(text);
			} catch(exception){
				this.addWarning(exception);
			}
		},

		writeToScreen: function(mess, textClass, text) {
			var output = $("<div></div>").append($("<strong></strong>").addClass(textClass).text(text)).append($('<span></span>').addClass('mess').text(mess));
			$(this.opts.cover).prepend(output);
		}
	}
});

var notif;
var isNotifActive;
function initNotifications() {
	notif = $.notifications.init({
		globalActive: "isNotifActive"
	});
}

function sendNotification(mess) {
	if (!isNotifActive) {
		initNotifications();
	}
	notif.send(mess);
}
