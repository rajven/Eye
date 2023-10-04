$(document).ready(function () {
	$("form").submit(function () {
		var formID = $(this).attr('id');
		if (formID == "formAuthApply") {
			var hangoutButton = document.getElementById("btn_filter");
			var formNm = $('#' + formID);
			var formTID = $('#def').attr('id');
			var formTm = $('#' + formTID);
			var cur_href = location.href;
			var post_data = {};
			post_data = formTm.serializeArray();
			post_data = post_data.concat(formNm.serializeArray());
			$.ajax({
				type: "POST",
				url: "/utils/auth_apply.php",
				data: post_data,
				success: function (data) {
					$(formNm).html(data);
					location.href = cur_href.replace('#modal', '');
					setTimeout(hangoutButton.click, 1000);
				},
				error: function (jqXHR, text, error) {
					$(formNm).html(error);
					location.href = cur_href.replace('#modal', '');
					setTimeout(hangoutButton.click, 5000);
				}
			});
			return false;
		}

		if (formID == "formAuthDel") {
			var hangoutButton = document.getElementById("btn_filter");
			var formNm = $('#' + formID);
			var formTID = $('#def').attr('id');
			var formTm = $('#' + formTID);
			var cur_href = location.href;
			var post_data = {};
			post_data = formTm.serializeArray();
			post_data = post_data.concat(formNm.serializeArray());
			$.ajax({
				type: "POST",
				url: "/utils/auth_remove.php",
				data: post_data,
				success: function (data) {
					$(formNm).html(data);
					location.href = cur_href.replace('#modalDel', '');
					setTimeout(hangoutButton.click, 1000);
				},
				error: function (jqXHR, text, error) {
					$(formNm).html(error);
					location.href = cur_href.replace('#modalDel', '');
					setTimeout(hangoutButton.click, 5000);
				}
			});
			return false;
		}
		if (formID == "formAuthExport") {
			var hangoutButton = document.getElementById("btn_filter");
			var formNm = $('#' + formID);
			var formTID = $('#def').attr('id');
			var formTm = $('#' + formTID);
			var cur_href = location.href;
			var post_data = {};
			post_data = formTm.serializeArray();
			post_data = post_data.concat(formNm.serializeArray());
			$.ajax({
				type: "POST",
				url: "/utils/auth_export.php",
				data: post_data,
				success: function (data) {
					$(formNm).html(data);
					location.href = cur_href.replace('#modalExport', '');
					setTimeout(hangoutButton.click, 1000);
				},
				error: function (jqXHR, text, error) {
					$(formNm).html(error);
					location.href = cur_href.replace('#modalExport', '');
					setTimeout(hangoutButton.click, 5000);
				}
			});
			return false;
		}
	});
});
