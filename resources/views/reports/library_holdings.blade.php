@extends('layouts.sec')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/books/create.css') }}">
@endsection

@section('content')
<div class="catalog-page">
    <header class="catalog-page__hero">
        <div>
            <h1 class="catalog-page__title">Library Holdings Report</h1>
            <p class="catalog-page__subtitle">
                Generate CHED-style Report 1 (library collection list and per-course summary) from cataloged books.
            </p>
        </div>
        <a href="{{ route('book.index') }}" class="btn btn-back btn-sm">← Back to catalog</a>
    </header>

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('reports.library_holdings.download') }}">
                @csrf

                <div class="mb-3">
                    <label for="program_id" class="form-label fw-semibold">Program</label>
                    <select name="program_id" id="program_id" class="form-control" required>
                        <option value="">— Select program —</option>
                        @foreach($programs as $program)
                            <option value="{{ $program->id }}" @selected(old('program_id') == $program->id)>
                                {{ $program->program_name }} ({{ $program->program_code }})
                            </option>
                        @endforeach
                    </select>
                    <p class="small text-muted mt-1">
                        Includes books linked to this program on the cataloging form that have a <strong>course</strong> set.
                    </p>
                </div>

                <div class="mb-3">
                    <label for="program_suffix" class="form-label fw-semibold">Program name suffix (optional)</label>
                    <input type="text" name="program_suffix" id="program_suffix" class="form-control"
                           value="{{ old('program_suffix') }}"
                           placeholder="e.g. MAJOR IN ENGLISH">
                    <p class="small text-muted mt-1">
                        Appended to the program name on the report header, e.g.
                        <em>Bachelor of Secondary Education (MAJOR IN ENGLISH)</em>.
                    </p>
                </div>

                <div class="mb-3">
                    <label for="date_accomplished" class="form-label fw-semibold">Date accomplished (optional)</label>
                    <input type="text" name="date_accomplished" id="date_accomplished" class="form-control"
                           value="{{ old('date_accomplished') }}"
                           placeholder="Leave blank for a signature line">
                </div>

                <div class="alert alert-light border small mb-4">
                    <strong>Report 1 includes:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Left: each unique title per course with collection type, curriculum area, author, year, and copy count</li>
                        <li>Right: per-course summary — titles, titles within the last {{ config('reports.recent_publication_years', 5) }} years, and total copies</li>
                        <li>Footer: signatory block (configure names in <code>REPORT_PREPARED_BY_NAME</code> / <code>REPORT_APPROVED_BY_NAME</code>)</li>
                    </ul>
                </div>

                <button type="submit" class="btn btn-save">Download Excel (Report 1)</button>
            </form>
        </div>
    </div>
</div>
@endsection
