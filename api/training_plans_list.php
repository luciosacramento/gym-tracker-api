<?php
require __DIR__.'/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

try {
  $stmt = $pdo->query("
    SELECT tp.id, tp.name, tp.start_weight, tp.end_weight, tp.created_at,
           (SELECT COUNT(*) FROM exercises e WHERE e.plan_id = tp.id) AS exercises_count
    FROM training_plans tp
    ORDER BY tp.created_at DESC, tp.id DESC
  ");
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode($rows);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(array('message'=>'Erro ao listar treinos','error'=>$e->getMessage()));
}
