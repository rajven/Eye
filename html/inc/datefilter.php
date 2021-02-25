<?php

if (! defined("CONFIG")) die("Not defined");

if (isset($_GET['date_start']) or isset($_POST['date_start'])) {
    if (isset($_GET['date_start'])) { $time_start = strtotime($_GET['date_start']); }
    if (isset($_POST['date_start'])) { $time_start = strtotime($_POST['date_start']); }
    if ($time_start != false) { $date1 = date('Y-m-d', $time_start); }
    if (!isset($date1)) { $date1 = date('Y-m-d', time()); }
    } else {
    if (isset($_SESSION[$page_url]['date_start'])) { $date1=$_SESSION[$page_url]['date_start']; } else {
	if (!isset($default_date_shift)) { $date1 = date('Y-m-d', time()); } else {
	    if ($default_date_shift=='m') {
	              $start = mktime(0, 0, 0, date("m")-1, date("d"), date("Y"));
        	      $date1 = date('Y-m-d', $start);
	              $stop = mktime(0, 0, 0, date("m"), date("d")+1, date("Y"));
        	      $date2 = date('Y-m-d', $stop);
		      }
	    if ($default_date_shift=='d') {
	              $start = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
        	      $date1 = date('Y-m-d', $start);
	              $stop = mktime(0, 0, 0, date("m"), date("d")+1, date("Y"));
        	      $date2 = date('Y-m-d', $stop);
		      }
	    }
	}
}

if (isset($_POST['date_stop']) or isset($_GET['date_stop'])) {
    if (isset($_GET['date_stop'])) { $time_stop = strtotime($_GET['date_stop']); }
    if (isset($_POST['date_stop'])) { $time_stop = strtotime($_POST['date_stop']); }
    if ($time_stop != false) { $date2 = date('Y-m-d', $time_stop); }
    if (!isset($date2)) {
          $tomorrow = mktime(0, 0, 0, date("m"), date("d") + 1, date("Y"));
          $date2 = date('Y-m-d', $tomorrow);
          }
    } else {
    if (isset($_SESSION[$page_url]['date_stop'])) { $date2=$_SESSION[$page_url]['date_stop']; } else {
          $tomorrow = mktime(0, 0, 0, date("m"), date("d") + 1, date("Y"));
          $date2 = date('Y-m-d', $tomorrow);
          }
}

$days_shift = ceil((strtotime($date2) - strtotime($date1))/86400);

$_SESSION[$page_url]['date_start']=$date1;
$_SESSION[$page_url]['date_stop']=$date2;
?>
