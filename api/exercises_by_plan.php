<?php
require __DIR__.'/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$planId = isset($_GET['planId']) ? (int)$_GET['planId'] : 0;

try {
  if ($planId <= 0) {
    $q = $pdo->query("SELECT id FROM training_plans ORDER BY created_at DESC LIMIT 1");
    $p = $q->fetch(PDO::FETCH_ASSOC);
    if ($p) $planId = (int)$p['id'];
  }

  if ($planId <= 0) { echo json_encode(array()); exit; }

  $stmt = $pdo->prepare("SELECT id, name, day_of_week FROM exercises WHERE plan_id=? ORDER BY day_of_week, name");
  $stmt->execute(array($planId));
  echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(array('message'=>'Erro ao listar exercícios','error'=>$e->getMessage()));
}
