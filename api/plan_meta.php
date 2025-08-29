<?php
require __DIR__.'/config.php';

// responder o preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // No Content
    exit;
}

$planId = isset($_GET['planId']) ? (int)$_GET['planId'] : 0;

try {
  if ($planId <= 0) {
    $q = $pdo->query("SELECT id FROM training_plans ORDER BY created_at DESC LIMIT 1");
    $p = $q->fetch(PDO::FETCH_ASSOC);
    if ($p) $planId = (int)$p['id'];
  }

  if ($planId <= 0) { echo json_encode(array()); exit; }

  $stmt = $pdo->prepare("SELECT id, name, start_weight, end_weight, created_at FROM training_plans WHERE id=?");
  $stmt->execute(array($planId));
  $plan = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$plan) { echo json_encode(array()); exit; }

  $start = strtotime($plan['created_at']);
  $now   = strtotime(date('Y-m-d'));
  $weeks = (int)floor(($now - $start) / (7*24*3600)) + 1;
  if ($weeks < 1) $weeks = 1;

  echo json_encode(array(
    'id' => (int)$plan['id'],
    'name' => $plan['name'],
    'start_weight' => $plan['start_weight'],
    'end_weight' => $plan['end_weight'],
    'created_at' => $plan['created_at'],
    'weekNumber' => $weeks
  ));
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(array('message'=>'Erro ao obter meta','error'=>$e->getMessage()));
}
