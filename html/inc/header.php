<!DOCTYPE html>
<html>
<head>
<title><?php echo WEB_site_title; ?></title>
<link rel="stylesheet" type="text/css" href="/<?php echo HTML_STYLE.'.css'; ?>">
<script src="/js/jq/jquery.min.js"></script>
<link href="/js/select2/css/select2.min.css" rel="stylesheet"/>
<script src="/js/select2/js/select2.min.js"></script>
<link rel="stylesheet" href="/js/jstree/themes/default/style.min.css" />
<script src="/js/jstree/jstree.min.js"></script>
<meta http-equiv="content-type" content="application/xhtml+xml" />
<meta charset="UTF-8" />

<script language="javascript">
function checkAll(check) {

var boxes = document.def.elements.length;
if(check) {
	for(i=0; i<boxes; i++) {
		document.def.elements[i].checked = true;
	}
} else {
	for(i=0; i<boxes; i++) {
		document.def.elements[i].checked = false;
	}
}
}

$(document).ready(function() {
$('.js-select-single').select2();
});
</script>

</head>
<body>

<div id="title"><?php print get_const('org_name')?></div>
<div id="navi">
<a href="/admin/reports/index-full.php">
<?php print $title_reports?>
</a> | <a href="/admin/groups/">
<?php print $title_groups?>
</a> | <a href="/admin/users/">
<?php print $title_users?>
</a> | <a href="/admin/iplist/">
<?php print $title_users_ips?>
</a> | <a href="/admin/filters/">
<?php print $title_filters?>
</a> | <a href="/admin/queues/">
<?php print $title_shapers?>
</a> | <a href="/admin/devices/">
<?php print $title_devices?>
</a> |
<a href="/admin/customers/control.php"> Managment </a> |
<a href="/admin/logs/"> Logs </a> |
<a href="/logout.php">Exit</a>
</div>
