<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');

if (!CLI)
    die('not in cli mode');


CLISetup::registerSetup("sql", new class extends SetupScript
{
    protected array $info = array(
        'events' => [[], CLISetup::ARGV_PARAM, 'Compiles data for type: Event from world db.']
    );

    protected array $dbcSourceFiles  = ['holidays'];      // aowow - custom: for the holiday stage times below
    protected array $worldDependency = ['game_event', 'game_event_prerequisite', 'holiday_dates'];

    public function generate() : bool
    {
        DB::Aowow()->qry('TRUNCATE ::events');

        $events = DB::World()->selectAssoc(
           'SELECT    ge.eventEntry,
                      holiday,
                      0,                                    -- cuFlags
                      IFNULL(UNIX_TIMESTAMP(start_time), 0) AS "startTime",
                      IFNULL(UNIX_TIMESTAMP(end_time), 0),
                      occurence * 60 AS "occurence",
                      length * 60 AS "length",
                      IF (gep.eventEntry IS NOT NULL, GROUP_CONCAT(prerequisite_event SEPARATOR " "), NULL),
                      description,
                      holidayStage
            FROM      game_event ge
            LEFT JOIN game_event_prerequisite gep ON gep.eventEntry = ge.eventEntry
            GROUP BY  ge.eventEntry',
        );

        // aowow - custom start: an event bound to a holiday stage does not run on its own game_event times
        $holidays = DB::Aowow()->selectAssoc('SELECT `id` AS ARRAY_KEY, h.* FROM dbc_holidays h');
        foreach (DB::World()->selectAssoc('SELECT `id`, `date_id`, `date_value`, `holiday_duration` FROM holiday_dates') as $hd)
        {
            if (!isset($holidays[$hd['id']]) || $hd['date_id'] >= 26)
                continue;

            $holidays[$hd['id']]['date'.$hd['date_id']] = $hd['date_value'];
            if ($hd['holiday_duration'])
                $holidays[$hd['id']]['duration0'] = $hd['holiday_duration'];
        }

        foreach ($events as &$e)
        {
            if ($e['holidayStage'] && $e['holidayStage'] <= 10 && isset($holidays[$e['holiday']]))
                $this->setHolidayEventTime($e, $holidays[$e['holiday']], $e['holidayStage']);

            unset($e['holidayStage']);
        }
        unset($e);
        // aowow - custom end

        foreach ($events as $e)
            DB::Aowow()->qry('INSERT INTO ::events VALUES %l', $e);

        $this->reapplyCCFlags('events', Type::WORLDEVENT);

        return true;
    }

    // aowow - custom start
    /**
     * GameEventMgr::SetHolidayEventTime(), with holiday_dates already merged into the dbc row as GameEventMgr::LoadHolidayDates() does
     * the next start is picked relative to now, so an event on a fixed list of dates (CalendarFilterType 1) only holds until the
     * next run of this step after its date passed - as on the server, which only picks it on startup
     */
    private function setHolidayEventTime(array &$event, array $holiday, int $stage) : void
    {
        if (!$holiday['date0'] || !$holiday['duration0'])
            return;

        $stageIdx        = $stage - 1;
        $event['length'] = $holiday['duration'.$stageIdx] * HOUR;

        $stageOffset = 0;
        for ($i = 0; $i < $stageIdx; $i++)
            $stageOffset += $holiday['duration'.$i] * HOUR;

        $event['occurence'] = match ((int)$holiday['scheduleType'])
        {
            -1      => 365 * DAY,                           // yearly; the core's YEAR (ours is 364 days)
             0      => WEEK,                                // weekly
            default => $event['occurence']                  // 1: fixed dates only; 2: looping only
        };

        if ($holiday['looping'])
        {
            $event['occurence'] = 0;
            for ($i = 0; $i < 10 && $holiday['duration'.$i]; $i++)
                $event['occurence'] += $holiday['duration'.$i] * HOUR;
        }

        // the year bits are all set on dates that recur every year
        $singleDate = (($holiday['date0'] >> 24) & 0x1F) == 31;
        $now        = time();
        $thisYear   = (int)date('Y', $now);

        for ($i = 0; $i < 26 && $holiday['date'.$i]; $i++)
        {
            $date = $holiday['date'.$i];
            $mon  = (($date >> 20) & 0xF) + 1;
            $day  = (($date >> 14) & 0x3F) + 1;
            $hour = ($date >> 6) & 0x1F;
            $min  = $date & 0x3F;
            $year = $singleDate ? $thisYear - 1 : (($date >> 24) & 0x1F) + 2000;

            $start = mktime($hour, $min, 0, $mon, $day, $year);
            if ($now < $start + $event['length'])
            {
                $event['startTime'] = $start + $stageOffset;
                break;
            }
            else if ($singleDate)
            {
                $event['startTime'] = mktime($hour, $min, 0, $mon, $day, $thisYear) + $stageOffset;
                break;
            }
            // else: date is past, try the next one; with none left the game_event start stays
        }
    }
    // aowow - custom end
});

?>
