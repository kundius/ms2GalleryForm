var ms2GalleryForm = {
	initialize: function() {
		if(!jQuery().ajaxForm) {
			document.write('<script src="'+ms2GalleryFormConfig.jsUrl+'lib/jquery.form.min.js"><\/script>');
		}
		if(!jQuery().jGrowl) {
			document.write('<script src="'+ms2GalleryFormConfig.jsUrl+'lib/jquery.jgrowl.min.js"><\/script>');
		}
	}
};
ms2GalleryForm.Message = {
    success: function(message) {
        if (message) {
            $.jGrowl(message, {theme: 'ms2galleryform-message-success'});
        }
    }
    ,error: function(message) {
        if (message) {
            $.jGrowl(message, {theme: 'ms2galleryform-message-error'/*, sticky: true*/});
        }
    }
    ,info: function(message) {
        if (message) {
            $.jGrowl(message, {theme: 'ms2galleryform-message-info'});
        }
    }
    ,close: function() {
        $.jGrowl('close');
    }
};

ms2GalleryForm.initialize();