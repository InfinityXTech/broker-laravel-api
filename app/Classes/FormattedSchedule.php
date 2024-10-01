<?php

namespace App\Classes;

use Illuminate\Support\Facades\Cache;

// interface BlockedSchedule
// {
//     public function getData();
// }

class FormattedSchedule //implements BlockedSchedule
{
    /**  @var array $data */
    private array $data;
    /**  @var array $weekType */
    private array $weekType;

    /**  @var string $cacheKey */
    private string $cacheKey;
    /**  @var string $cachedData */
    private ?string $cachedData = null;

    /**
     * @param array $data Our main data array
     * @param bool|null $weekType Weekend toggler
     */
    function __construct(array $data, ?bool $weekType = null)
    {
        $this->cacheKey = 'FormattedSchedule_' . md5(serialize($data ?? [])) . '_' . (string)($weekType ?? '');
        $this->cachedData = Cache::get($this->cacheKey, null);

        if ($this->cachedData == null) {
            $this->weekType = $this::weekend($weekType);
            $this->data = $this->recalculateData($data);
        }
    }

    /**
     * @param bool|null $type Weekend type toggler
     * @return array Days of the week in proper format
     */
    private static function weekend(?bool $type): array
    {
        $days = [
            1 => 'Mon',
            2 => 'Tue',
            3 => 'Wed',
            4 => 'Thu',
            5 => 'Fri',
            6 => 'Sat',
            7 => 'Sun',
        ];

        if ($type === true) return array_slice($days, 5, null, true);
        if ($type === false) return array_slice($days, 0, 5, true);
        else return $days;
    }

    /**
     * @param array $num_list Array of numbered array
     * @return array Returns array with missing numbers
     */
    private function missing_number(array $num_list): array
    {
        //$new_arr = range($num_list[0],max($num_list));
        $new_arr = range(0, 23);
        return array_values(array_diff($new_arr, $num_list));
    }

    /**
     * @param array $array Array of data
     * @return array Filtered data which we can use
     */
    private function recalculateData(array $blocked_schedule): array
    {
        $new = [];
        foreach ($blocked_schedule as $k => $v) {
            if (!empty($v)) {
                $new[$k] = $this->missing_number((array)$v);
            }
        }

        return $new;
    }

    /**
     * @param array $array Formatted data as input
     * @return array Returns properly formatted schedules
     */
    private function generateSchedules(array &$array): array
    {
        $newArray = [];
        foreach ($array as $key => $value) {
            $previous = $value[0] ?? 0;
            $result = [];
            $hourBlocks = [];

            foreach ($value as $number) {
                if ($number == $previous + 1 || $number === $previous) {
                    $hourBlocks[] = $number;
                } else {
                    if (!empty($hourBlocks)) {
                        $from = min($hourBlocks) - 0 ?? 0;
                        $to = max($hourBlocks) + 1 ?? 0;

                        $result[] = sprintf("%02d:00", $from) . '-' . sprintf("%02d:00", $to);
                        $hourBlocks = array($number);
                    }
                }
                $previous = $number;
            }

            $from = count($hourBlocks) ? min($hourBlocks) : null;
            $toOutput = count($hourBlocks) ? max($hourBlocks) !== 23 ? sprintf("%02d:00", max($hourBlocks) + 1) : sprintf("%02d:59", max($hourBlocks)) : null;
            $to = count($hourBlocks) ? $toOutput : null;

            if ($from && $to) {
                $result[] = sprintf("%02d:00", $from) . '-' . $toOutput;
                $newArray[$key] = $result;
            }
        }

        return $newArray;
    }

    /**
     * @param array $array Array of formatted schedules
     * @param array $days Formatted days array
     * @return array Returns grouped array of schedules by day
     */
    private function groupSchedules(array &$array, array $days): array
    {
        $result = array_unique($array, SORT_REGULAR);
        $uniqueDays = [];
        $sameDays = [];

        foreach (array_keys($days) as $key) {
            if (!array_key_exists($key, $result)) {
                if (isset($array[$key])) {
                    $sameDays[$key] = $array[$key];
                }
            } else {
                $uniqueDays[$key] = $result[$key];
            }
        }

        foreach ($result as $ky => $rv) {
            if (in_array($rv, array_values($sameDays))) {
                $sameDays[$ky] = $rv;
                unset($uniqueDays[$ky]);
            }
        }

        ksort($sameDays);

        $sameDayNames = [];
        foreach (array_keys($sameDays) as $dayId) {
            if (isset($days[$dayId])) {
                array_push($sameDayNames, $days[$dayId]);
            }
        }

        $uniqueDaysArray = [];
        foreach ($uniqueDays as $uniqueDayId => $uniqueDay) {
            $uniqueDaysArray[$days[$uniqueDayId]] = implode(', ', $uniqueDay);
        }

        return [
            'sameNames' => $sameDayNames,
            'sameDays' => $sameDays,
            'uniqueDays' => $uniqueDaysArray,
        ];
    }

    /**
     * @return array Get formatted schedules
     */
    private function getSchedules(): array
    {
        return $this->generateSchedules($this->data);
    }

    /**
     * @param array $array Takes properly formated data with days
     * @return string Returns string of mutated data
     */
    private function formatDates(array &$array): string
    {
        if (!empty($this->data)) {
            $uniqueDates = '';
            $sameDays = implode(', ', $array['sameNames']) . ': ' . implode(', ', array_pop($array['sameDays']) ?? []);

            foreach ($array['uniqueDays'] as $id => $uniqueDay) {
                $uniqueDates .= $id . ': ' . $uniqueDay . ' ';
            }

            $sameDaysOutput = !empty($array['sameDays']) ? $sameDays . ' ' : null;
            $uniqueDatesOutput = !empty($array['uniqueDays']) ? $uniqueDates : null;

            return $sameDaysOutput . $uniqueDatesOutput;
        }

        return 'No data available';
    }

    /**
     * @return string Get properly formatted data
     */
    public function getData(): string
    {
        if ($this->cachedData != null) {
            return $this->cachedData;
        }

        $schedules = $this->getSchedules();
        $groupSchedules = $this->groupSchedules($schedules, $this->weekType);
        $result = $this->formatDates($groupSchedules);

        $seconds = 60 * 60 * 12;
        Cache::put($this->cacheKey, $result, $seconds);

        return $result;
    }
}
