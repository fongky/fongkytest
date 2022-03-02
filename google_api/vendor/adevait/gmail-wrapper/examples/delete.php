<?php
require_once '../vendor/autoload.php';
require_once('includes/config.php');
require_once('includes/auth.php');

use GmailWrapper\Messages;

if (!isset($_GET['messageId'])) {
    header('Location:messages.php');
    exit;
}
$msgs = new Messages($authenticate);
var_dump($msgs->trash($_GET['messageId']));