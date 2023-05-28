$(document).ready(function () {
    $("form").submit(function () {
	var formID = $(this).attr('id');

        if (formID == "formAuthApply") {
            var formNm = $('#' + formID);
	    var formTID = $('#def').attr('id');
    	    var formTm = $('#' + formTID);
	    var post_data = {};
            post_data = formTm.serializeArray();
	    post_data = post_data.concat(formNm.serializeArray());
    	    $.ajax({
        	type: "POST",
        	url: "/utils/auth_apply.php",
        	data: post_data,
        	success: function (data) {
            	    $(formNm).html(data);
		    location.href=formTm.attr("action");
		    setTimeout(location.reload,1000);
        	},
        	error: function (jqXHR, text, error) {
        	    $(formNm).html(error);
		    location.href=formTm.attr("action");
		    setTimeout(location.reload,5000);
        	}
	    });
	    }

        if (formID == "formAuthDel") {
            var formNm = $('#' + formID);
	    var formTID = $('#def').attr('id');
    	    var formTm = $('#' + formTID);
	    var post_data = {};
            post_data = formTm.serializeArray();
	    post_data = post_data.concat(formNm.serializeArray());
    	    $.ajax({
        	type: "POST",
        	url: "/utils/auth_remove.php",
        	data: post_data,
        	success: function (data) {
            	    $(formNm).html(data);
		    location.href=formTm.attr("action");
		    setTimeout(location.reload,1000);
        	},
        	error: function (jqXHR, text, error) {
        	    $(formNm).html(error);
		    location.href=formTm.attr("action");
		    setTimeout(location.reload,5000);
        	}
	    });
	    }

        return true;
    });
});
