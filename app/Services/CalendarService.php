<?php

namespace App\Services;

use Exception;

class CalendarService
{
    private string $base_uri = "https://tide736.net/api/get_tide.php";
    private int $year;
    private int $month;
    private string $prefecture;
    private string $code;

    public function __construct(int $year, int $month, string $prefecture, string $code)
    {
        $this->year = $year;
        $this->month = $month;
        $this->prefecture = $prefecture;
        $this->code = $code;
    }

    public function get_monthly_tide_data(): array
    {
        $query1 = http_build_query([
            'rg' => 'month',
            'yr' => $this->year,
            'mn' => $this->month,
            'dy' => 1,
            'pc' => $this->prefecture,
            'hc' => $this->code
        ]);

        $json_data = @file_get_contents($this->base_uri . '?' . $query1);
        if (!$json_data) {
            return ['status' => 400];
        }

        $array = json_decode($json_data, true);
        if (!isset($array['tide']['chart'])) {
            return ['status' => 400];
        }

        $daysInMonth = (int)date('t', strtotime(sprintf('%04d-%02d-01', $this->year, $this->month)));
        $chartData = $array['tide']['chart'];
        $portName = $array['tide']['port']['harbor_namej'] ?? '';

        // If the month has 31 days, fetch the 31st day
        if ($daysInMonth === 31) {
            $query2 = http_build_query([
                'rg' => 'day',
                'yr' => $this->year,
                'mn' => $this->month,
                'dy' => 31,
                'pc' => $this->prefecture,
                'hc' => $this->code
            ]);
            $json_data2 = @file_get_contents($this->base_uri . '?' . $query2);
            if ($json_data2) {
                $array2 = json_decode($json_data2, true);
                if (isset($array2['tide']['chart'])) {
                    $chartData = array_merge($chartData, $array2['tide']['chart']);
                }
            }
        }

        // Filter out dates from the next month if February or months with 30 days
        $filteredData = [];
        $prefix = $this->year . '-' . sprintf('%02d', $this->month) . '-';
        foreach ($chartData as $dateStr => $data) {
            if (str_starts_with($dateStr, $prefix)) {
                $filteredData[$dateStr] = $data;
            }
        }

        $raw_lat = $array['tide']['port']['latitude'] ?? 0;
        $raw_lng = $array['tide']['port']['longitude'] ?? 0;

        $lat_deg = floor($raw_lat);
        $lat_min = round(($raw_lat - $lat_deg) * 100);
        $lat_decimal = $lat_deg + ($lat_min / 60);

        $lng_deg = floor($raw_lng);
        $lng_min = round(($raw_lng - $lng_deg) * 100);
        $lng_decimal = $lng_deg + ($lng_min / 60);

        return [
            'status' => 200,
            'chart' => $filteredData,
            'port' => $portName,
            'lat' => $lat_decimal,
            'lng' => $lng_decimal
        ];
    }
}
