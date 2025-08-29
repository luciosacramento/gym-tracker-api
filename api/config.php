<?php

$allowed = [
  'http://localhost:4200',
  'https://gym-tracker-7yggg7k3m-luciosacramentos-projects.vercel.app'
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed)) {
    header("Access-Control-Allow-Origin: ".$_SERVER['HTTP_ORIGIN']);
} else {
    header("Access-Control-Allow-Origin: *"); // fallback em dev
}
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 86400");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// -------- Timezone --------
date_default_timezone_set('America/Bahia');

// -------- Credenciais do DB --------
$DB_HOST = 'localhost';
$DB_NAME = 'gym_tracker';
$DB_USER = 'root'; // ajuste se necessário
$DB_PASS = '';     // ajuste se necessário

// -------- Conexão PDO (cria $pdo no escopo global) --------
try {
  $pdo = new PDO(
    'mysql:host=' . $DB_HOST . ';dbname=' . $DB_NAME . ';charset=utf8mb4',
    $DB_USER,
    $DB_PASS,
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
  );
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(array('message' => 'Erro de conexão ao banco', 'error' => $e->getMessage()));
  exit;
}
