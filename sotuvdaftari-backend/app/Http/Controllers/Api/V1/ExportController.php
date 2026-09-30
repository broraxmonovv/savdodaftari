<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Export\ExportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/** Pro: Excel/PDF eksport (TZ 17, 35) */
class ExportController extends Controller
{
    /**
     * GET /exports/{type}?format=xlsx|pdf&from=Y-m-d&to=Y-m-d
     * type: report | sales | expenses | debts | inventory. `from/to` berilmasa — oxirgi 30 kun.
     */
    public function download(Request $request, ExportService $exports, string $type): Response
    {
        abort_unless(in_array($type, ExportService::TYPES, true), 404);

        $data = $request->validate([
            'format' => ['required', Rule::in(['xlsx', 'pdf'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $to = $data['to'] ?? today()->toDateString();
        $from = $data['from'] ?? today()->subDays(29)->toDateString();

        $document = $exports->build($request->user(), $type, $from, $to);
        $name = "bozorpro-{$type}-{$from}_{$to}.{$data['format']}";

        if ($data['format'] === 'pdf') {
            return response($exports->pdf($document), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$name}\"",
            ]);
        }

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $exports->xlsx($document, $path);

        return (new BinaryFileResponse($path, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]))->setContentDisposition('attachment', $name)->deleteFileAfterSend(true);
    }
}
