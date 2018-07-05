<?php
/* 
 * DukesCalendar
 * github.com/northgoingzax/DukesCalendar
 * 1.0.0
 */
require_once 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use northgoingzax\DukesCalendar;

$DukesCalendar = new DukesCalendar();

// Run this month
$DukesCalendar->run();
