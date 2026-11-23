<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * IT-2026-000152: a per-year counter row locked for update, so two requests
 * submitted at the same moment never get the same number.
 */
class TicketNumberGenerator
{
    public function next(): string
    {
        $year = (int) now(config('helpdesk.working_hours.timezone'))->format('Y');

        $number = DB::transaction(function () use ($year): int {
            DB::table('ticket_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);

            $current = DB::table('ticket_sequences')->where('year', $year)->lockForUpdate()->value('last_number');
            DB::table('ticket_sequences')->where('year', $year)->update(['last_number' => $current + 1]);

            return $current + 1;
        });

        return sprintf('%s-%d-%06d', config('helpdesk.ticket_prefix'), $year, $number);
    }
}
