<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Services\LibraryHoldingsReport1ExcelWriter;
use App\Services\LibraryHoldingsReportBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LibraryHoldingsReportController extends Controller
{
    public function create()
    {
        $programs = Program::orderBy('program_name')->get();

        return view('reports.library_holdings', compact('programs'));
    }

    public function download(Request $request, LibraryHoldingsReportBuilder $builder)
    {
        $validated = $request->validate([
            'program_id' => 'required|integer|exists:programs,id',
            'program_suffix' => 'nullable|string|max:255',
            'date_accomplished' => 'nullable|string|max:255',
        ]);

        $program = Program::findOrFail($validated['program_id']);
        $programName = $program->program_name;
        if (! empty($validated['program_suffix'])) {
            $suffix = trim($validated['program_suffix']);
            $programName .= str_starts_with($suffix, '(') ? ' '.$suffix : ' ('.$suffix.')';
        }

        $report = $builder->buildForProgram($program);

        if ($report['detail'] === [] && $report['summary'] === []) {
            return back()
                ->withInput()
                ->with('error', 'No cataloged books with a course were found for this program. Link books to the program and set a course on each copy.');
        }

        $slug = Str::slug($program->program_code ?: $program->program_name);
        $fileName = 'library_holdings_report_'.$slug.'_'.now()->format('Y-m-d').'.xlsx';
        $filePath = storage_path('app/'.$fileName);

        (new LibraryHoldingsReport1ExcelWriter(
            heiName: config('reports.hei_name'),
            programName: $programName,
            dateAccomplished: $validated['date_accomplished'] ?? null,
            detail: $report['detail'],
            summary: $report['summary'],
        ))->saveTo($filePath);

        return response()->download($filePath, $fileName)->deleteFileAfterSend(true);
    }
}
