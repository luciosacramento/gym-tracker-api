<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Carbon\Carbon;

class GymController extends Controller
{
    // Mapeia prefixo PT-BR -> 1..7
    private array $ptDays = [
        'Segunda' => 1,
        'Terça' => 2, 'Terca' => 2,
        'Quarta' => 3,
        'Quinta' => 4,
        'Sexta' => 5,
        'Sábado' => 6, 'Sabado' => 6,
        'Domingo' => 7
    ];

    public function uploadXlsx(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx'
        ]);

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, true, true);

        // Descobrir cabeçalhos
        $headers = array_map(fn($v) => trim((string)$v), $rows[1] ?? []);
        $map = $this->buildHeaderMap($headers);

        $created = 0; $updated = 0;

        DB::beginTransaction();
        try {
            for ($i = 2; $i <= count($rows); $i++) {
                $r = $rows[$i];

                $diaRaw = $this->val($r, $map, 'Dia da Semana');
                $exerciseName = trim((string)$this->val($r, $map, 'Exercício'));

                if (!$diaRaw || !$exerciseName) continue;

                $diaPrefix = trim(explode('-', $diaRaw)[0]);
                $dow = $this->ptDays[$diaPrefix] ?? 1;

                $repsSchema = $this->val($r, $map, 'Repetições');
                $suggestedWeight = $this->lastFilledWeekValue($r, $map);

                $existing = Exercise::where('name', $exerciseName)
                    ->where('day_of_week', $dow)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'reps_schema' => $repsSchema ?: $existing->reps_schema,
                        'suggested_weight' => $suggestedWeight ?? $existing->suggested_weight,
                    ]);
                    $updated++;
                } else {
                    Exercise::create([
                        'name' => $exerciseName,
                        'day_of_week' => $dow,
                        'reps_schema' => $repsSchema ?: null,
                        'suggested_weight' => $suggestedWeight,
                    ]);
                    $created++;
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erro ao importar XLSX', 'error' => $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Importação concluída', 'created' => $created, 'updated' => $updated]);
    }

    public function today()
    {
        // Carbon usa timezone do APP_TIMEZONE
        $dowJs = Carbon::now()->dayOfWeek; // 0=Dom .. 6=Sáb
        $dow = $dowJs === 0 ? 7 : $dowJs;   // 1..7

        $exercises = Exercise::where('day_of_week', $dow)->orderBy('id')->get();

        $result = $exercises->map(function ($ex) {
            $last = ActivityLog::where('exercise_id', $ex->id)
                ->orderByDesc('performed_at')
                ->first();

            return [
                'id' => $ex->id,
                'name' => $ex->name,
                'repsSchema' => $ex->reps_schema,
                'suggestedWeight' => $ex->suggested_weight ? (float)$ex->suggested_weight : null,
                'lastWeight' => $last?->weight !== null ? (float)$last->weight : ($ex->suggested_weight ? (float)$ex->suggested_weight : null),
            ];
        });

        return response()->json($result);
    }

    public function saveBulk(Request $request)
    {
        $data = $request->validate([
            'performedAt' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.exerciseId' => 'required|integer|exists:exercises,id',
            'items.*.setIndex' => 'required|integer|min:1',
            'items.*.weight' => 'nullable|numeric',
            'items.*.reps' => 'nullable|integer|min:1'
        ]);

        $performedAt = isset($data['performedAt']) ? Carbon::parse($data['performedAt']) : Carbon::now();

        DB::beginTransaction();
        try {
            foreach ($data['items'] as $it) {
                ActivityLog::create([
                    'exercise_id' => $it['exerciseId'],
                    'performed_at' => $performedAt,
                    'set_index' => $it['setIndex'],
                    'weight' => $it['weight'] ?? null,
                    'reps' => $it['reps'] ?? null,
                ]);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erro ao salvar treino', 'error' => $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Treino salvo', 'count' => count($data['items'])]);
    }

    private function buildHeaderMap(array $headers): array
    {
        // Cria um map nome->coluna (ex.: "Dia da Semana" => 'A')
        $map = [];
        foreach ($headers as $col => $name) {
            if (!$name) continue;
            $map[$name] = $col; // $col é letra: 'A','B',...
        }
        return $map;
    }

    private function val(array $row, array $map, string $header): mixed
    {
        if (!isset($map[$header])) return null;
        return $row[$map[$header]] ?? null;
    }

    private function lastFilledWeekValue(array $row, array $map): ?float
    {
        for ($i = 6; $i >= 1; $i--) {
            $h = "Semana $i";
            if (isset($map[$h])) {
                $v = $row[$map[$h]] ?? null;
                if ($v !== null && $v !== '') {
                    $num = (float)$v;
                    if (!is_nan($num)) return $num;
                }
            }
        }
        return null;
    }
}
