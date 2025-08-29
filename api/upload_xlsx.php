<?php
require __DIR__.'/config.php';
require __DIR__.'/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Responde corretamente o preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // sem conteúdo
    exit;
}

if (empty($_FILES['file'])) { http_response_code(400); echo json_encode(array('message'=>'Arquivo não enviado')); exit; }

try {
  // Nome do treino: prioriza POST[planName], senão o nome do arquivo (sem extensão)
  $planName = isset($_POST['planName']) ? trim((string)$_POST['planName']) : '';
  if ($planName === '') {
    $fname = isset($_FILES['file']['name']) ? $_FILES['file']['name'] : 'Treino';
    $planName = preg_replace('/\.[^.]+$/', '', $fname); // remove extensão
    if ($planName === '') $planName = 'Treino';
  }

  // Garante o treino (create or get)
  $stmt = $pdo->prepare("SELECT id FROM training_plans WHERE name=? LIMIT 1");
  $stmt->execute(array($planName));
  $plan = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($plan) {
    $planId = (int)$plan['id'];
  } else {
    $stmt = $pdo->prepare("INSERT INTO training_plans (name, created_at, updated_at) VALUES (?, NOW(), NOW())");
    $stmt->execute(array($planName));
    $planId = (int)$pdo->lastInsertId();
  }

  // Lê XLSX
  $spreadsheet = IOFactory::load($_FILES['file']['tmp_name']);
  $sheet = $spreadsheet->getSheet(0);
  $rows = $sheet->toArray(null, true, true, true);

  // Cabeçalhos (compatível PHP 7.2)
  $headers = array_map(function ($v) { return trim((string)$v); }, isset($rows[1]) ? $rows[1] : array());
  $map = array();
  foreach ($headers as $col => $name) { if ($name) $map[$name] = $col; }

  $ptDays = array(
    'Segunda'=>1, 'Terça'=>2, 'Terca'=>2, 'Quarta'=>3,
    'Quinta'=>4, 'Sexta'=>5, 'Sábado'=>6, 'Sabado'=>6, 'Domingo'=>7
  );

  $pdo->beginTransaction();
  $created = 0; $updated = 0;

  // Prepara queries (com plan_id)
  $qFind = $pdo->prepare("SELECT id FROM exercises WHERE name=? AND day_of_week=? AND plan_id=? LIMIT 1");
  $qUpd  = $pdo->prepare("UPDATE exercises SET reps_schema=COALESCE(?, reps_schema), suggested_weight=COALESCE(?, suggested_weight), updated_at=NOW() WHERE id=?");
  $qIns  = $pdo->prepare("INSERT INTO exercises(name, day_of_week, plan_id, reps_schema, suggested_weight, created_at, updated_at) VALUES(?,?,?,?,?,NOW(),NOW())");

  for ($i = 2; $i <= count($rows); $i++) {
    $r = $rows[$i];

    $diaRaw = !empty($map['Dia da Semana']) ? (isset($r[$map['Dia da Semana']]) ? $r[$map['Dia da Semana']] : null) : null;
    $name   = trim((string)(!empty($map['Exercício']) ? (isset($r[$map['Exercício']]) ? $r[$map['Exercício']] : '') : ''));

    if (!$diaRaw || $name === '') continue;

    $diaPrefix = trim(explode('-', $diaRaw)[0]);
    $dow = isset($ptDays[$diaPrefix]) ? $ptDays[$diaPrefix] : 1;

    $reps = !empty($map['Repetições']) ? (isset($r[$map['Repetições']]) ? $r[$map['Repetições']] : null) : null;

    // Pega último preenchido entre Semana 6..1
    $suggested = null;
    for ($w = 6; $w >= 1; $w--) {
      $h = "Semana $w";
      if (!empty($map[$h])) {
        $val = isset($r[$map[$h]]) ? $r[$map[$h]] : null;
        if ($val !== null && $val !== '') { $suggested = (float)$val; break; }
      }
    }

    // Existe exercício com mesmo nome, dia e plano?
    $qFind->execute(array($name, $dow, $planId));
    $ex = $qFind->fetch(PDO::FETCH_ASSOC);

    if ($ex) {
      $qUpd->execute(array($reps ?: null, $suggested, $ex['id']));
      $updated++;
    } else {
      $qIns->execute(array($name, $dow, $planId, $reps ?: null, $suggested));
      $created++;
    }
  }

  $pdo->commit();
  echo json_encode(array(
    'message' => 'Importação concluída',
    'planId'  => $planId,
    'planName'=> $planName,
    'created' => $created,
    'updated' => $updated
  ));
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(array('message' => 'Erro ao importar XLSX', 'error' => $e->getMessage()));
}
