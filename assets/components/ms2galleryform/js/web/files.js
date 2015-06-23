var form = $('#ms2GalleryForm');

ms2GalleryForm.Uploader = new plupload.Uploader({
    runtimes: 'html5,flash,silverlight,html4',

    browse_button: 'ms2galleryform-files-select',
    //upload_button: document.getElementById('ms2galleryform-files-upload'),
    container: 'ms2galleryform-files-container',
    filelist: 'ms2galleryform-files-list',
    progress: 'ms2galleryform-files-progress',
    progress_bar: 'ms2galleryform-files-progress-bar',
    progress_count: 'ms2galleryform-files-progress-count',
    progress_percent: 'ms2galleryform-files-progress-percent',
    form: form,

    multipart_params: {
        action: $('#' + this.container).data('action') || 'gallery/file/upload',
        id: this.form.find('[name="id"]').val(),
        form_key: this.form.find('[name="form_key"]').val(),
        ctx: ms2GalleryFormConfig.ctx || 'web'
    },
    drop_element: 'ms2galleryform-files-list',

    url: ms2GalleryFormConfig.actionUrl,

    filters: {
        max_file_size: ms2GalleryFormConfig.source.size,
        mime_types: [{
            title: 'Files',
            extensions: ms2GalleryFormConfig.source.extensions
        }]
    },

    resize: {
        width: ms2GalleryFormConfig.source.width,
        height: ms2GalleryFormConfig.source.height
    },

    flash_swf_url: ms2GalleryFormConfig.jsUrl + 'web/lib/plupload/js/Moxie.swf',
    silverlight_xap_url: ms2GalleryFormConfig.jsUrl + 'web/lib/plupload/js/Moxie.xap',

    init: {
        Init: function(up) {
            if (this.runtime == 'html5') {
                var element = $(this.settings.drop_element);
                element.addClass('droppable');
                element.on('dragover', function() {
                    if (!element.hasClass('dragover')) {
                        element.addClass('dragover');
                    }
                });
                element.on('dragleave drop', function() {
                    element.removeClass('dragover');
                });
            }
        },

        PostInit: function(up) {},

        FilesAdded: function(up, files) {
            this.settings.form.find('[type="submit"]').attr('disabled',true);
            up.start();
        },

        UploadProgress: function(up, file) {
            $(up.settings.browse_button).hide();
            $('#' + up.settings.progress).show();
            $('#' + up.settings.progress_count).text((up.total.uploaded + 1) + ' / ' + up.files.length);
            $('#' + up.settings.progress_percent).text(up.total.percent + '%');
            $('#' + up.settings.progress_bar).css('width', up.total.percent + '%');
        },

        FileUploaded: function(up, file, response) {
            response = $.parseJSON(response.response);
            if (response.success) {
                // Successfull action
                var files = $('#' + up.settings.filelist);
                var clearfix = files.find('.clearfix');
                if (clearfix.length != 0) {
                    $(response.data).insertBefore(clearfix);
                }
                else {
                    files.append(response.data);
                }

            }
            else {
                ms2GalleryForm.Message.error(response.message);
            }
        },

        UploadComplete: function(up, file, response) {
            $(up.settings.browse_button).show();
            $('#' + up.settings.progress).hide();
            up.total.reset();
            up.splice();
            this.settings.form.find('[type="submit"]').attr('disabled',false);
        },

        Error: function(up, err) {
            ms2GalleryForm.Message.error(err.message);
        }
    }
});

ms2GalleryForm.Uploader.init();

$(document).on('click', '.ms2galleryform-file-remove', function(e) {
    var $this = $(this);
    var $form = $this.parents('form');
    var $parent = $this.parents('.ms2galleryform-file');
    var id = $parent.data('id');
    var form_key = $form.find('[name="form_key"]').val();

    $.post(ms2GalleryFormConfig.actionUrl, {action: 'gallery/file/remove', id: id, form_key: form_key}, function(response) {
        if (response.success) {
            $parent.remove();
        }
        else {
            ms2GalleryForm.Message.error(response.message);
        }
    }, 'json');
    return false;
});