<?php
require __DIR__.'/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$exerciseId = isset($_GET['exerciseId']) ? (int)$_GET['exerciseId'] : 0;
if ($exerciseId <= 0) { http_response_code(400); echo json_encode(array('message'=>'exerciseId inválido')); exit; }

try {
  $stmt = $pdo->prepare("
    SELECT DATE(performed_at) as d, SUBSTRING_INDEX(GROUP_CONCAT(weight ORDER BY id DESC), ',', 1) as w
    FROM activity_logs
    WHERE exercise_id=?
    GROUP BY DATE(performed_at)
    ORDER BY d ASC
  ");
  $stmt->execute(array($exerciseId));
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $out = array();
  foreach ($rows as $r) {
    $wstr = isset($r['w']) ? (string)$r['w'] : null;
    $wnum = null;
    if ($wstr !== null && $wstr !== '') {
      // tenta extrair o primeiro número (suporta vírgula)
      if (preg_match('/(-?\d+(?:[.,]\d+)?)/', $wstr, $m)) {
        $tmp = str_replace(',', '.', $m[1]);
        if (is_numeric($tmp)) $wnum = (float)$tmp;
      }
    }
    $out[] = array(
      'date' => $r['d'],
      'weight' => $wstr,
      'weight_num' => $wnum
    );
  }

  echo json_encode($out);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(array('message'=>'Erro ao obter histórico','error'=>$e->getMessage()));
}
