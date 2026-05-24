<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

// =========================
// GENERATION QR CODE
// =========================
$result = new Builder(
    writer: new PngWriter(),
    data: 'TEST QR CODE',
    size: 300,
    margin: 10
);

// =========================
// BUILD
// =========================
$qr = $result->build();

// =========================
// AFFICHAGE
// =========================
header('Content-Type: ' . $qr->getMimeType());

echo $qr->getString();