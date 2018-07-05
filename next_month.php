<?php
require_once 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use northgoingzax\DukesCalendar;

$DukesCalendar = new DukesCalendar();

// Update date
$DukesCalendar->setDate('Today + 1 month');

// Run next month
$DukesCalendar->run();