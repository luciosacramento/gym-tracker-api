<?php
require __DIR__.'/config.php';

// responder o preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // No Content
    exit;
}

try {
  $dowJs = (int)date('w'); // 0=Dom .. 6=Sáb
  $dow = $dowJs === 0 ? 7 : $dowJs;
  $today = date('Y-m-d');

  // Resolve planId
  $planId = isset($_GET['planId']) ? (int)$_GET['planId'] : 0;
  if ($planId <= 0) {
    $q = $pdo->query("SELECT id FROM training_plans ORDER BY created_at DESC LIMIT 1");
    $plan = $q->fetch(PDO::FETCH_ASSOC);
    if ($plan) $planId = (int)$plan['id'];
  }

  if ($planId <= 0) {
    echo json_encode(array()); // sem treino, sem exercícios
    exit;
  }

  $stmt = $pdo->prepare("SELECT id, name, reps_schema, suggested_weight FROM exercises WHERE plan_id=? AND day_of_week=? ORDER BY id ASC");
  $stmt->execute(array($planId, $dow));
  $exercises = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $out = array();

  $qToday = $pdo->prepare(
    "SELECT weight FROM activity_logs
      WHERE exercise_id=? AND DATE(performed_at)=?
      ORDER BY id DESC LIMIT 1"
  );
  $qAny = $pdo->prepare(
    "SELECT weight FROM activity_logs
      WHERE exercise_id=?
      ORDER BY id DESC LIMIT 1"
  );

  foreach ($exercises as $ex) {
    $lastWeight = null;

    $qToday->execute(array($ex['id'], $today));
    $row = $qToday->fetch(PDO::FETCH_ASSOC);
    if ($row && isset($row['weight']) && $row['weight'] !== '') {
      $lastWeight = (string)$row['weight'];
    } else {
      $qAny->execute(array($ex['id']));
      $row2 = $qAny->fetch(PDO::FETCH_ASSOC);
      if ($row2 && isset($row2['weight']) && $row2['weight'] !== '') {
        $lastWeight = (string)$row2['weight'];
      } elseif ($ex['suggested_weight'] !== null && $ex['suggested_weight'] !== '') {
        $lastWeight = (string)$ex['suggested_weight'];
      }
    }

    $out[] = array(
      'id' => (int)$ex['id'],
      'name' => $ex['name'],
      'repsSchema' => $ex['reps_schema'],
      'suggestedWeight' => $ex['suggested_weight'] !== null ? (float)$ex['suggested_weight'] : null,
      'lastWeight' => $lastWeight
    );
  }

  echo json_encode($out);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(array('message' => 'Erro ao buscar exercícios', 'error' => $e->getMessage()));
}
