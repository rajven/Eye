<!DOCTYPE html>
<html>
<head>
<title><?php echo WEB_site_title; ?></title>

<link rel="apple-touch-icon" sizes="180x180" href="/img/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/img/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/img/favicon-16x16.png">
<link rel="manifest" href="/site.webmanifest">
<link rel="mask-icon" href="/img/safari-pinned-tab.svg" color="#5bbad5">
<meta name="msapplication-TileColor" content="#da532c">
<meta name="theme-color" content="#ffffff">

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
<a href="/admin/reports/index-full.php"><?php print WEB_title_reports; ?></a> | 
<a href="/admin/groups/"><?php print WEB_title_groups; ?></a> | 
<a href="/admin/users/"><?php print WEB_title_users; ?></a> | 
<a href="/admin/iplist/"><?php print WEB_title_users_ips; ?></a> | 
<a href="/admin/filters/"><?php print WEB_title_filters; ?></a> | 
<a href="/admin/queues/"><?php print WEB_title_shapers; ?></a> | 
<a href="/admin/devices/"><?php print WEB_title_devices; ?></a> |
<a href="/admin/customers/control.php"><?php print WEB_title_control; ?></a> |
<a href="/admin/logs/"><?php print WEB_title_logs; ?></a> |
<a href="/logout.php"><?php print WEB_title_exit; ?></a>
</div>
