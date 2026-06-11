<?php

namespace App\Support;

use App\Models\FineSetting;
use App\Models\Holiday;
use Carbon\Carbon;

class LoanDueDate
{
    public static function addBusinessDays(Carbon $start, int $days): Carbon
    {
        $holidays = Holiday::pluck('holiday_date')->map(function ($d) {
            return Carbon::parse($d)->startOfDay()->toDateString();
        });

        $date = $start->copy()->startOfDay();
        $added = 0;

        while ($added < $days) {
            $date->addDay();

            if (! $date->isWeekend() && ! $holidays->contains($date->toDateString())) {
                $added++;
            }
        }

        return $date;
    }

    /**
     * @return array{due_date: ?Carbon, loan_duration_days: ?int}
     */
    public static function resolveFromRequest(
        Carbon $borrowedAt,
        FineSetting $settings,
        ?string $dueDateInput,
        ?int $loanDaysInput,
    ): array {
        if ($dueDateInput) {
            return [
                'due_date' => Carbon::parse($dueDateInput)->startOfDay(),
                'loan_duration_days' => null,
            ];
        }

        if ($loanDaysInput !== null && $loanDaysInput > 0) {
            return [
                'due_date' => self::addBusinessDays($borrowedAt, $loanDaysInput),
                'loan_duration_days' => $loanDaysInput,
            ];
        }

        $defaultDays = (int) ($settings->loan_duration_days ?? 7);

        return [
            'due_date' => self::addBusinessDays($borrowedAt, $defaultDays),
            'loan_duration_days' => $defaultDays,
        ];
    }
}
