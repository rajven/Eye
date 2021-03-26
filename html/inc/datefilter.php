<?php

if (!defined("CONFIG")) die("Not defined");

$datetime_start = new DateTime();

if (empty($default_date_shift)) { $default_date_shift='d'; }

if (!empty($_GET['date_start']) or !empty($_POST['date_start'])) {
    if (!empty($_GET['date_start'])) { $datetime_start = GetDateTimeFromString($_GET['date_start']); }
    if (!empty($_POST['date_start'])) { $datetime_start = GetDateTimeFromString($_POST['date_start']); }
    $date1 = $datetime_start->format('Y-m-d');
    $time_start = $datetime_start->getTimestamp();
    } else {
    if (!empty($_SESSION[$page_url]['date_start'])) {
        $date1 = $_SESSION[$page_url]['date_start'];
        $datetime_start = GetDateTimeFromString($date1);
        $time_start = $datetime_start->getTimestamp();
        } else {
        $date1 = $datetime_start->format('Y-m-d');
        $time_start = $datetime_start->getTimestamp();
        }
    }

if (!empty($_POST['date_stop']) or !empty($_GET['date_stop'])) {
    if (!empty($_GET['date_stop'])) { $datetime_stop = GetDateTimeFromString($_GET['date_stop']); }
    if (!empty($_POST['date_stop'])) { $datetime_stop = GetDateTimeFromString($_POST['date_stop']); }
    $date2 = $datetime_stop->format('Y-m-d');
    $time_stop = $datetime_stop->getTimestamp();
    } else {
    if (!empty($_SESSION[$page_url]['date_stop'])) {
        $date2 = $_SESSION[$page_url]['date_stop'];
        $datetime_stop = GetDateTimeFromString($date2);
        $time_stop = $datetime_stop->getTimestamp();
        }
    }

if (!empty($datetime_stop) and !empty($datetime_start)) {
    if (($time_stop - $time_start)/86400 <1) { $datetime_stop = NULL; }
    }

if (!isset($datetime_stop) or empty($datetime_stop)) {
    if ($default_date_shift==='d') {
        $datetime_start->modify('+1 day');
        $time_stop = $datetime_start->getTimestamp();
        $date2 = $datetime_start->format('Y-m-d');
        }
    if ($default_date_shift==='m') {
        $datetime_stop = new DateTime($date1);
        $datetime_stop->modify('+1 day');
        $time_stop = $datetime_stop->getTimestamp();
        $date2 = $datetime_start->format('Y-m-d');
        $date1 = $datetime_start->format('Y-m-1');
        $datetime_start = new DateTime($date1);
        $time_start = $datetime_start->getTimestamp();
        }
    if (empty($datetime_stop)) {
        $datetime_stop = new DateTime();
        $datetime_stop->modify('+1 day');
        $time_stop = $datetime_stop->getTimestamp();
        $date2 = $datetime_start->format('Y-m-d');
        }
    } else {
    $date2 = $datetime_stop->format('Y-m-d');
    $time_stop = $datetime_stop->getTimestamp();
    }

$days_shift = ceil(($time_stop - $time_start)/86400);

$_SESSION[$page_url]['date_start']=$date1;
$_SESSION[$page_url]['date_stop']=$date2;

?>
