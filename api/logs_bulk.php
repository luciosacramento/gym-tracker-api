<?php
require __DIR__.'/config.php';

// responder o preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // No Content
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$items = isset($body['items']) ? $body['items'] : null;
$performedAt = !empty($body['performedAt']) ? date('Y-m-d H:i:s', strtotime($body['performedAt'])) : date('Y-m-d H:i:s');
$today = date('Y-m-d', strtotime($performedAt));

if (!$items || !is_array($items)) { http_response_code(400); echo json_encode(array('message'=>'Itens inválidos')); exit; }

try {
  $pdo->beginTransaction();

  $upd = $pdo->prepare(
    "UPDATE activity_logs
       SET weight = ?, reps = ?, performed_at = ?, updated_at = NOW()
     WHERE exercise_id = ? AND set_index = ? AND DATE(performed_at) = ?"
  );
  $ins = $pdo->prepare(
    "INSERT INTO activity_logs (exercise_id, performed_at, set_index, weight, reps, created_at, updated_at)
     VALUES (?,?,?,?,?,NOW(),NOW())"
  );

  $count = 0;
  foreach ($items as $it) {
    $w = isset($it['weight']) ? trim((string)$it['weight']) : null;
    if ($w === '') { $w = null; }
    $r = (isset($it['reps']) && $it['reps'] !== '') ? (int)$it['reps'] : null;

    $exerciseId = (int)$it['exerciseId'];
    $setIndex   = (int)(isset($it['setIndex']) ? $it['setIndex'] : 1);

    // Tenta atualizar o registro do DIA (mesmo exercício e mesma série)
    $upd->execute(array($w, $r, $performedAt, $exerciseId, $setIndex, $today));

    if ($upd->rowCount() === 0) {
      // Não existia registro hoje → insere
      $ins->execute(array($exerciseId, $performedAt, $setIndex, $w, $r));
    }

    $count++;
  }

  $pdo->commit();
  echo json_encode(array('message'=>'Treino salvo','count'=>$count));
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(array('message'=>'Erro ao salvar treino','error'=>$e->getMessage()));
}
