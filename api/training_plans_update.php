<?php
require __DIR__.'/config.php';

// responder o preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // No Content
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$planId = isset($body['planId']) ? (int)$body['planId'] : 0;
$start  = isset($body['startWeight']) ? trim((string)$body['startWeight']) : null;
$end    = isset($body['endWeight'])   ? trim((string)$body['endWeight'])   : null;

if ($planId <= 0) { http_response_code(400); echo json_encode(array('message'=>'planId inválido')); exit; }

try {
  $stmt = $pdo->prepare("UPDATE training_plans SET start_weight=?, end_weight=?, updated_at=NOW() WHERE id=?");
  $stmt->execute(array($start !== '' ? $start : null, $end !== '' ? $end : null, $planId));

  $row = $pdo->prepare("SELECT id, name, start_weight, end_weight, created_at FROM training_plans WHERE id=?");
  $row->execute(array($planId));
  echo json_encode($row->fetch(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(array('message'=>'Erro ao atualizar treino','error'=>$e->getMessage()));
}
