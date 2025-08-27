<?php
require __DIR__.'/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$body = json_decode(file_get_contents('php://input'), true);
$planId = isset($body['planId']) ? (int)$body['planId'] : 0;

if ($planId <= 0) { http_response_code(400); echo json_encode(array('message'=>'planId inválido')); exit; }

try {
  // (Opcional) impedir apagar se for o último treino
  $cnt = (int)$pdo->query("SELECT COUNT(*) FROM training_plans")->fetchColumn();
  if ($cnt <= 1) { http_response_code(400); echo json_encode(array('message'=>'Não é possível apagar o único treino existente')); exit; }

  $stmt = $pdo->prepare("DELETE FROM training_plans WHERE id=?");
  $stmt->execute(array($planId));

  if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(array('message'=>'Treino não encontrado'));
  } else {
    echo json_encode(array('message'=>'Treino removido','deletedId'=>$planId));
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(array('message'=>'Erro ao remover treino','error'=>$e->getMessage()));
}
