<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Program;
use Illuminate\Support\Collection;

class LibraryHoldingsReportBuilder
{
    /**
     * @return array{
     *     detail: list<array{
     *         collection_type: string,
     *         curriculum_label: string,
     *         course: string,
     *         title: string,
     *         author: string,
     *         pub_year: int|string|null,
     *         copy_count: int
     *     }>,
     *     summary: list<array{
     *         curriculum_label: string,
     *         course: string,
     *         title_count: int,
     *         recent_title_count: int,
     *         copy_count: int
     *     }>
     * }
     */
    public function buildForProgram(Program $program, ?int $recentYears = null): array
    {
        $recentYears = $recentYears ?? (int) config('reports.recent_publication_years', 5);
        $cutoffYear = (int) now()->year - $recentYears + 1;

        $books = Book::query()
            ->whereNull('archived_at')
            ->whereNotNull('course')
            ->where('course', '!=', '')
            ->whereHas('programs', fn ($q) => $q->where('programs.id', $program->id))
            ->get();

        $courseOrder = $this->prospectusCourseOrder($program);

        $detail = $this->buildDetailLines($books, $courseOrder);
        $summary = $this->buildSummaryLines($books, $courseOrder, $cutoffYear);

        return [
            'detail' => $detail,
            'summary' => $summary,
        ];
    }

    /**
     * @return list<string>
     */
    protected function prospectusCourseOrder(Program $program): array
    {
        $program->loadMissing(['years.courses']);

        $ordered = [];
        foreach ($program->years->sortBy('year_level') as $year) {
            foreach ($year->courses->sortBy('course_code') as $course) {
                $name = trim((string) $course->course_name);
                if ($name !== '') {
                    $ordered[] = $name;
                }
            }
        }

        return array_values(array_unique($ordered));
    }

    /**
     * @param  list<string>  $courseOrder
     * @return list<array<string, mixed>>
     */
    protected function buildDetailLines(Collection $books, array $courseOrder): array
    {
        $groups = $books->groupBy(function (Book $book) {
            return mb_strtolower(trim((string) $book->course)).'|'.mb_strtolower(trim((string) $book->title_statement));
        });

        $lines = $groups->map(function (Collection $copies) {
            /** @var Book $sample */
            $sample = $copies->first();

            return [
                'collection_type' => $this->collectionTypeFor($sample),
                'curriculum_label' => $this->curriculumLabelFor($sample->curriculum),
                'curriculum_key' => $this->curriculumSortOrder($sample->curriculum),
                'course' => trim((string) $sample->course),
                'title' => trim((string) $sample->title_statement),
                'author' => trim((string) ($sample->main_author ?? '')),
                'pub_year' => $this->normalizePubYear($sample->pub_year),
                'copy_count' => $copies->count(),
            ];
        })->values();

        return $this->sortLines($lines, $courseOrder)->values()->all();
    }

    /**
     * @param  list<string>  $courseOrder
     * @return list<array<string, mixed>>
     */
    protected function buildSummaryLines(Collection $books, array $courseOrder, int $cutoffYear): array
    {
        $byCourse = $books->groupBy(fn (Book $book) => mb_strtolower(trim((string) $book->course)));

        $lines = $byCourse->map(function (Collection $courseBooks, string $courseKey) use ($cutoffYear) {
            /** @var Book $sample */
            $sample = $courseBooks->first();
            $course = trim((string) $sample->course);

            $titleGroups = $courseBooks->groupBy(
                fn (Book $book) => mb_strtolower(trim((string) $book->title_statement))
            );

            $recentTitleGroups = $titleGroups->filter(function (Collection $copies) use ($cutoffYear) {
                $year = $this->normalizePubYear($copies->first()->pub_year);

                return $year !== null && $year >= $cutoffYear;
            });

            return [
                'curriculum_label' => $this->curriculumLabelFor($sample->curriculum),
                'curriculum_key' => $this->curriculumSortOrder($sample->curriculum),
                'course' => $course,
                'title_count' => $titleGroups->count(),
                'recent_title_count' => $recentTitleGroups->count(),
                'copy_count' => $courseBooks->count(),
            ];
        })->values();

        return $this->sortLines($lines, $courseOrder)->values()->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lines
     * @param  list<string>  $courseOrder
     * @return Collection<int, array<string, mixed>>
     */
    protected function sortLines(Collection $lines, array $courseOrder): Collection
    {
        $courseRank = [];
        foreach ($courseOrder as $index => $courseName) {
            $courseRank[mb_strtolower($courseName)] = $index;
        }

        return $lines->sort(function (array $a, array $b) use ($courseRank) {
            $curriculumCompare = ($a['curriculum_key'] ?? 99) <=> ($b['curriculum_key'] ?? 99);
            if ($curriculumCompare !== 0) {
                return $curriculumCompare;
            }

            $aCourse = mb_strtolower($a['course']);
            $bCourse = mb_strtolower($b['course']);
            $aRank = $courseRank[$aCourse] ?? PHP_INT_MAX;
            $bRank = $courseRank[$bCourse] ?? PHP_INT_MAX;
            if ($aRank !== $bRank) {
                return $aRank <=> $bRank;
            }

            $courseCompare = strnatcasecmp($a['course'], $b['course']);
            if ($courseCompare !== 0) {
                return $courseCompare;
            }

            return strnatcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        });
    }

    protected function collectionTypeFor(Book $book): string
    {
        $haystack = mb_strtolower(implode(' ', array_filter([
            (string) $book->content_type,
            (string) $book->media_type,
            (string) $book->carrier_type,
        ])));

        if (str_contains($haystack, 'electronic')
            || str_contains($haystack, 'digital')
            || str_contains($haystack, 'online')
            || str_contains($haystack, 'ebook')
            || str_contains($haystack, 'computer disc')) {
            return 'Electronic';
        }

        return 'Printed';
    }

    protected function curriculumLabelFor(?string $curriculum): string
    {
        $normalized = mb_strtolower(trim((string) $curriculum));

        return config("reports.curriculum_labels.{$normalized}", 'General Education');
    }

    protected function curriculumSortOrder(?string $curriculum): int
    {
        $normalized = mb_strtolower(trim((string) $curriculum));

        return config("reports.curriculum_sort.{$normalized}", 99);
    }

    protected function normalizePubYear(mixed $pubYear): ?int
    {
        if ($pubYear === null || $pubYear === '') {
            return null;
        }

        if (is_numeric($pubYear)) {
            return (int) $pubYear;
        }

        if (preg_match('/\b(19|20)\d{2}\b/', (string) $pubYear, $matches)) {
            return (int) $matches[0];
        }

        return null;
    }
}
