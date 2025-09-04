<?php
require __DIR__ . '/../../../vendor/phpmailer/phpmailer/src/Exception.php';
require __DIR__ . '/../../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require __DIR__ . '/../../../vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

define("SMTP_HOST", "smtp.gmail.com");
define("SMTP_USER", "photosaved727@gmail.com");
define("SMTP_PASS", "axzb fcrw trfa dwjq");
define("SMTP_PORT", 587);