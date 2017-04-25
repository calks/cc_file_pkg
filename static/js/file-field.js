

	fileField = function(field_name, field_id, iframe_src, iframe_width, iframe_height) {

		var me = this;
		
		me.field_name = field_id;
		me.field_id = field_id;
		me.iframe_src = iframe_src;
				
		me.container = jQuery('#' + field_id);
		
		me.css = {};
		me.js = {};
		
		if (me.fileApiSupported()) {
			me.renderField();
		}
		else {
			me.renderLegacyField(iframe_width, iframe_height);
		}

	}
	
	
	
	fileField.prototype = {
			
		fileApiSupported: function() {
			return typeof(File) != 'undefined';			
		},
		
		
		renderLegacyField: function(width, height) {
			var me = this;
			var iframe = jQuery('<iframe />').attr({
				src: me.iframe_src,
				width: width,
				height: height,
				border: 0
			}).css({
				border: 'none'				
			});
			
			me.container.html(iframe);			
		},
		
		
		renderField: function() {
			var me = this;
			
			jQuery.ajax({
				url: me.iframe_src,
				type: 'post',
				data: {
					ajax: 1					
				},
				dataType: 'json',
				success: function(response){
					me.ajaxSuccessCallback(response);
				}
			});
			
		},
		
		
		setLoadedContent: function(content) {
			var me = this;

			if (content === null) return;

			var content = jQuery(content);
			me.container.html(content);
		
			content.find('a').each(function(){
				var link = jQuery(this);
				
				if (link.attr('target')) return;
				
				link.on('click', function(event){					
					event.preventDefault();
										
					var confirmation_text = link.attr('data-confirmation-text');
					
					if (confirmation_text && !confirm(confirmation_text)) {
						return;
					}
					
		            jQuery.ajax({
		                url: jQuery(this).attr('href'),
		                type: 'post',
		                dataType: 'json',
		                data: {
		                	ajax: 1		                
		                },
		                success: function(response) {
		                	me.ajaxSuccessCallback(response);
		                }
		            });
		                
				});
			});
			
			content.find('input[type=submit], input[type=image]').on('click', function(event){
				event.preventDefault();				
				
				var input_clicked = jQuery(this); 
				var form = input_clicked.parents('form:first');
				
				var form_action = form.attr('action'); 
				
	            var formData = new FormData(form[0]);
	            formData.append('ajax', 1);
	            formData.append(input_clicked.attr('name'), input_clicked.val());
	            App.blockUI();
	            	            
	            jQuery.ajax({
	                url: form_action,
	                type: 'post',
	                dataType: 'json',
	                xhr: function() {
	                    myXhr = $.ajaxSettings.xhr();
	                    if(myXhr.upload){ 
	                        //myXhr.upload.addEventListener('progress', progressHandlingFunction, false); // progressbar
	                    }
	                    return myXhr;
	                },
	                success: function(response) {
	                	App.unblockUI();
	                	me.ajaxSuccessCallback(response);
	                },
	                error: function() {
	                	App.unblockUI();
	                	me.ajaxErrorCallback(response);
	                },
	                data: formData,
	                cache: false,
	                contentType: false,
	                processData: false
	            });
			});			
			
		},
		
		
		ajaxSuccessCallback: function(response) {
			var me = this;
			var content = response.content || null;
			var css_list = response.css || [];
			var js_list = response.js || [];
			
			me.addStylesheets(css_list);
			me.addScripts(js_list);
			me.setLoadedContent(content);
			
			var messages = response.messages || {};
			
			jQuery.each(messages, function(message_idx, message){				
				App.displayMessage(message.type, message.message);
			});
		},
		
		
		ajaxErrorCallback: function(response) {
			App.displayMessage('error', 'Request error.');
		},
		
		addStylesheets: function(css_list) {			
			var me = this;
			jQuery.each(css_list, function(idx, css_path){		
				
				if (typeof(me.css[css_path]) == 'undefined') {					
					var head = document.head;
				    var link = document.createElement('link');
					link.type = 'text/css'
					link.rel = 'stylesheet'
					link.href = css_path;
					head.appendChild(link);
					me.css[css_path] = 1;					
				}				
			});
		},
		
		addScripts: function(js_list) {			
			var me = this;
			jQuery.each(js_list, function(idx, js_path){		
				
				if (typeof(me.js[js_path]) == 'undefined') {					
					var head = document.head;
				    var link = document.createElement('script');
					link.type = 'text/javascript'					
					link.src = js_path;
					head.appendChild(link);
					me.js[js_path] = 1;
				}				
			});
		}
			
	}
	
	
