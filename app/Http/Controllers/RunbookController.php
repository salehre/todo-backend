<?php

namespace App\Http\Controllers;

use App\Models\Runbook;
use App\Models\RunbookTask;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RunbookController extends Controller
{
    // GET /runbooks
    public function index(Request $request)
    {
        $runbooks = Runbook::withCount('tasks')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'tasks_count' => $r->tasks_count,
                'created_at' => $r->created_at,
            ]);

        return response()->json($runbooks);
    }

    // POST /runbooks  (name + optional excel file)
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'file' => 'nullable|file|mimes:xlsx,xls|max:5120',
        ]);

        $runbook = Runbook::create([
            'name' => $data['name'],
            'created_by' => $request->user()->id,
        ]);

        if ($request->hasFile('file')) {
            $this->importTasksFromExcel($runbook, $request->file('file'));
        }

        return response()->json($this->formatRunbook($runbook->fresh('tasks')));
    }

    // GET /runbooks/{runbook}
    public function show(Runbook $runbook)
    {
        return response()->json($this->formatRunbook($runbook->load('tasks')));
    }

    // DELETE /runbooks/{runbook}
    public function destroy(Runbook $runbook)
    {
        $runbook->delete();
        return response()->json(['success' => true]);
    }

    // POST /runbooks/{runbook}/tasks
    public function storeTask(Request $request, Runbook $runbook)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'priority' => 'nullable|in:low,medium,high',
        ]);

        $maxOrder = $runbook->tasks()->max('order') ?? 0;
        $task = RunbookTask::create([
            'runbook_id' => $runbook->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'order' => $maxOrder + 1,
        ]);

        return response()->json($task);
    }

    // PUT /runbooks/{runbook}/tasks/{task}
    public function updateTask(Request $request, Runbook $runbook, RunbookTask $task)
    {
        abort_unless($task->runbook_id === $runbook->id, 404);
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:2000',
            'priority' => 'sometimes|in:low,medium,high',
        ]);
        $task->update($data);
        return response()->json($task);
    }

    // DELETE /runbooks/{runbook}/tasks/{task}
    public function destroyTask(Runbook $runbook, RunbookTask $task)
    {
        abort_unless($task->runbook_id === $runbook->id, 404);
        $task->delete();
        return response()->json(['success' => true]);
    }

    // GET /runbooks/template
    public function template()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'title');
        $sheet->setCellValue('B1', 'description');
        $sheet->setCellValue('C1', 'priority');

        // استایل هدر
        $sheet->getStyle('A1:C1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '187830'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->getColumnDimension('A')->setWidth(35);
        $sheet->getColumnDimension('B')->setWidth(50);
        $sheet->getColumnDimension('C')->setWidth(18);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'RunbookTemp.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function importTasksFromExcel(Runbook $runbook, $file): void
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        if (empty($rows)) return;

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($rows));
        $titleCol = array_search('title', $header);
        $descCol = array_search('description', $header);
        $priorityCol = array_search('priority', $header);

        if ($titleCol === false) return; // بدون ستون title کاری نمی‌تونیم بکنیم

        $order = 0;
        foreach ($rows as $row) {
            $title = trim((string) ($row[$titleCol] ?? ''));
            if ($title === '') continue;

            $priority = $priorityCol !== false ? strtolower(trim((string) ($row[$priorityCol] ?? ''))) : '';
            if (!in_array($priority, ['low', 'medium', 'high'])) $priority = 'medium';

            RunbookTask::create([
                'runbook_id' => $runbook->id,
                'title' => $title,
                'description' => $descCol !== false ? (trim((string) ($row[$descCol] ?? '')) ?: null) : null,
                'priority' => $priority,
                'order' => $order++,
            ]);
        }
    }

    private function formatRunbook(Runbook $runbook): array
    {
        return [
            'id' => $runbook->id,
            'name' => $runbook->name,
            'created_at' => $runbook->created_at,
            'tasks' => $runbook->tasks->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'priority' => $t->priority,
                'order' => $t->order,
            ]),
        ];
    }
}
