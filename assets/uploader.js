/* global plupload, pluploadL10n, _uploaderInit, plwue */
/**!
 * PlUploadHandle | 1.0.0 | 2020
 * Copyright (c) 2020 Vijay Hardaha;
 * @license GPLv3
 */
(function ($) {
	var handle = {
		run: function () {
			handle.uploader = false;
			if (typeof _uploaderInit == "object") {
				handle.uploader_init();
			}
		},
		uploader_init: function () {
			handle.uploader = new plupload.Uploader(_uploaderInit);

			handle.uploader.bind("Init", function (up) {
				var uploaddiv = $("#plupload-upload-ui");

				if (up.features.dragdrop && !$(document.body).hasClass("mobile")) {
					uploaddiv.addClass("drag-drop");

					$("#drag-drop-area")
						.on("dragover.wp-uploader", function () {
							// dragenter doesn"t fire right :(
							uploaddiv.addClass("drag-over");
						})
						.on("dragleave.wp-uploader, drop.wp-uploader", function () {
							uploaddiv.removeClass("drag-over");
						});
				} else {
					uploaddiv.removeClass("drag-drop");
					$("#drag-drop-area").off(".wp-uploader");
				}

				if (up.runtime === "html4") {
					$(".upload-flash-bypass").hide();
				}
			});

			handle.uploader.bind("postinit", function (up) {
				up.refresh();
			});

			handle.uploader.init();

			handle.uploader.bind("FilesAdded", function (up, files) {
				$("#media-upload-error").empty();
				handle.uploadStart();

				plupload.each(files, function (file) {
					handle.fileQueued(file);
				});

				up.refresh();
				up.start();
			});

			handle.uploader.bind("UploadFile", function (up, file) {
				handle.fileUploading(up, file);
			});

			handle.uploader.bind("UploadProgress", function (up, file) {
				handle.uploadProgress(up, file);
			});

			handle.uploader.bind("Error", function (up, error) {
				handle.uploadError(error.file, error.code, error.message, up);
				$("#media-items #media-item-" + error.file.id).remove();
				up.refresh();
			});

			handle.uploader.bind("FileUploaded", function (up, file, response) {
				handle.uploadSuccess(file, response.response);
			});

			handle.uploader.bind("UploadComplete", function () {
				handle.uploadComplete();
			});
		},
		uploadStart: function () {},
		// Progress and success handlers for media multi uploads.
		fileQueued: function (fileObj) {
			// Create a progress bar containing the filename.
			$('<div class="media-item">')
				.attr("id", "media-item-" + fileObj.id)
				.append('<div class="item"><div class="progress"><div class="percent">0%</div><div class="bar"></div></div></div>')
				.appendTo($("#media-items"));
		},
		// Check to see if a large file failed to upload.
		fileUploading: function (up, file) {
			var hundredmb = 100 * 1024 * 1024,
				max = parseInt(up.settings.max_file_size, 10);

			if (max > hundredmb && file.size > hundredmb) {
				setTimeout(function () {
					if (file.status < 3 && file.loaded === 0) {
						// Not uploading.
						handle.fileError(up, file, pluploadL10n.big_upload_failed.replace("%1$s", '<a class="uploader-html" href="#">').replace("%2$s", "</a>"));
						up.stop(); // Stop the whole queue.
						up.removeFile(file);
						up.start(); // Restart the queue.
					}
				}, 10000); // Wait for 10 seconds for the file to start uploading.
			}
		},
		uploadProgress: function (up, file) {
			var item = $("#media-item-" + file.id);
			$(".bar", item).width(file.percent + "%");
			$(".percent", item).html(file.percent + "%");
		},
		uploadError: function (fileObj, errorCode, message, up) {
			var hundredmb = 100 * 1024 * 1024,
				max;

			switch (errorCode) {
				case plupload.FAILED:
					handle.fileError(up, fileObj, pluploadL10n.upload_failed);
					break;
				case plupload.FILE_EXTENSION_ERROR:
					handle.fileExtensionError(up, fileObj, pluploadL10n.invalid_filetype);
					break;
				case plupload.FILE_SIZE_ERROR:
					handle.uploadSizeError(up, fileObj);
					break;
				case plupload.IMAGE_FORMAT_ERROR:
					handle.fileError(up, fileObj, pluploadL10n.not_an_image);
					break;
				case plupload.IMAGE_MEMORY_ERROR:
					handle.fileError(up, fileObj, pluploadL10n.image_memory_exceeded);
					break;
				case plupload.IMAGE_DIMENSIONS_ERROR:
					handle.fileError(up, fileObj, pluploadL10n.image_dimensions_exceeded);
					break;
				case plupload.GENERIC_ERROR:
					handle.queueError(pluploadL10n.upload_failed);
					break;
				case plupload.IO_ERROR:
					max = parseInt(up.settings.filters.max_file_size, 10);
					if (max > hundredmb && fileObj.size > hundredmb) {
						handle.fileError(up, fileObj, pluploadL10n.big_upload_failed.replace("%1$s", '<a class="uploader-html" href="#">').replace("%2$s", "</a>"));
					} else {
						handle.queueError(pluploadL10n.io_error);
					}
					break;
				case plupload.HTTP_ERROR:
					handle.queueError(pluploadL10n.http_error);
					break;
				case plupload.INIT_ERROR:
					$(".media-upload-form").addClass("html-uploader");
					break;
				case plupload.SECURITY_ERROR:
					handle.queueError(pluploadL10n.security_error);
					break;
				default:
					handle.fileError(up, fileObj, pluploadL10n.default_error);
			}
		},
		uploadSuccess: function (fileObj, serverData) {
			var item = $("#media-item-" + fileObj.id);

			// On success serverData should be numeric,
			// fix bug in html4 runtime returning the serverData wrapped in a <pre> tag.
			if (typeof serverData === "string") {
				serverData = serverData.replace(/^<pre>(\d+)<\/pre>$/, "$1");

				// If async-upload returned an error message, place it in the media item div and return.
				if (/media-upload-error|error-div/.test(serverData)) {
					item.html(serverData);
					return;
				}
			}

			item.find(".percent").html(pluploadL10n.crunching);

			handle.prepareMediaItem(fileObj, serverData);
		},
		uploadComplete: function () {},
		uploadSizeError: function (up, file) {
			var message, errorDiv;
			message = pluploadL10n.file_exceeds_size_limit.replace("%s", file.name);

			// Construct the error div.
			errorDiv = $("<div />")
				.attr({
					id: "media-item-" + file.id,
					class: "error",
				})
				.append($("<p />").text(message));

			// Append the error.
			$("#media-file-errors").append(errorDiv);
			up.removeFile(file);
		},
		queueError: function (message) {
			$("#media-upload-error")
				.show()
				.html('<div class="error"><p>' + message + "</p></div>");
		},
		fileError: function (up, file, message) {
			var message, errorDiv;
			message = pluploadL10n.error_uploading.replace("%s", file.name) + message;

			// Construct the error div.
			errorDiv = $("<div />")
				.attr({
					id: "media-item-" + file.id,
					class: "error",
				})
				.append($("<p />").text(message));

			// Append the error.
			$("#media-file-errors").append(errorDiv);
			up.removeFile(file);
		},
		fileExtensionError: function (up, file, message) {
			$("#media-file-errors").append('<div id="media-item-' + file.id + '" class="error"><p>' + message + "</p></div>");
			up.removeFile(file);
		},
		prepareMediaItem: function (fileObj, serverData) {
			var item = $("#media-item-" + fileObj.id + " .item");
			item.load(plwue.ajaxurl, {
				attachment_id: serverData,
				action: "plupload_wordpress_uploader_fetch_action",
			});
		},
	};
	handle.run();
})(jQuery);
