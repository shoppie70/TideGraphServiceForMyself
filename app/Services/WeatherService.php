<?php

namespace App\Services;

class WeatherService
{
    private string $base_uri = "https://api.open-meteo.com/v1/forecast";
    private float $lat;
    private float $lng;
    private string $date;

    public function __construct(float $lat, float $lng, string $date)
    {
        $this->lat = $lat;
        $this->lng = $lng;
        $this->date = $date; // YYYY-MM-DD
    }

    public function get_weather_data(): array
    {
        $query = http_build_query([
            'latitude' => $this->lat,
            'longitude' => $this->lng,
            'daily' => 'weathercode,temperature_2m_max,temperature_2m_min',
            'hourly' => 'wind_speed_10m',
            'timezone' => 'Asia/Tokyo',
            'wind_speed_unit' => 'ms',
            'start_date' => $this->date,
            'end_date' => $this->date,
        ]);

        $api_url = $this->base_uri . '?' . $query;
        $json_data = @file_get_contents($api_url);

        if (!$json_data) {
            return [
                'status' => 400,
                'message' => 'Weather API error'
            ];
        }

        $array = json_decode($json_data, true);

        if (!isset($array['daily'])) {
             return [
                'status' => 400,
                'message' => 'Invalid weather data'
            ];
        }

        $code = $array['daily']['weathercode'][0] ?? null;
        $temp_max = $array['daily']['temperature_2m_max'][0] ?? null;
        $temp_min = $array['daily']['temperature_2m_min'][0] ?? null;
        
        $wind_speed = $array['hourly']['wind_speed_10m'] ?? [];

        return [
            'status' => 200,
            'code' => $code,
            'label' => $this->get_weather_label($code),
            'icon' => $this->get_weather_icon($code),
            'temp_max' => $temp_max,
            'temp_min' => $temp_min,
            'wind_speed' => $wind_speed,
        ];
    }

    public function get_monthly_weather_data(int $year, int $month): array
    {
        $start_date = sprintf('%04d-%02d-01', $year, $month);
        $daysInMonth = (int)date('t', strtotime($start_date));
        $end_date = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        $today = new \DateTime();
        $min_date = (clone $today)->modify('-92 days');
        $max_date = (clone $today)->modify('+15 days');

        $req_start = new \DateTime($start_date);
        $req_end = new \DateTime($end_date);

        // クランプ
        if ($req_start < $min_date) $req_start = $min_date;
        if ($req_end > $max_date) $req_end = $max_date;

        if ($req_start > $req_end) {
            return ['status' => 400, 'message' => 'Out of range'];
        }

        $query = http_build_query([
            'latitude' => $this->lat,
            'longitude' => $this->lng,
            'daily' => 'weathercode,temperature_2m_max,temperature_2m_min',
            'timezone' => 'Asia/Tokyo',
            'start_date' => $req_start->format('Y-m-d'),
            'end_date' => $req_end->format('Y-m-d'),
        ]);

        $api_url = $this->base_uri . '?' . $query;
        $json_data = @file_get_contents($api_url);

        if (!$json_data) {
            return ['status' => 400, 'message' => 'Weather API error'];
        }

        $array = json_decode($json_data, true);
        if (!isset($array['daily'])) {
            return ['status' => 400, 'message' => 'Invalid weather data'];
        }

        $daily = $array['daily'];
        $result = [];
        
        for ($i = 0; $i < count($daily['time']); $i++) {
            $date = $daily['time'][$i];
            $code = $daily['weathercode'][$i] ?? null;
            $result[$date] = [
                'code' => $code,
                'label' => $this->get_weather_label($code),
                'icon' => $this->get_weather_icon($code),
                'temp_max' => $daily['temperature_2m_max'][$i] ?? null,
                'temp_min' => $daily['temperature_2m_min'][$i] ?? null,
            ];
        }

        return [
            'status' => 200,
            'data' => $result
        ];
    }

    private function get_weather_label(?int $code): string
    {
        if ($code === null) return '不明';
        
        if ($code === 0) return '快晴';
        if (in_array($code, [1, 2, 3])) return '晴れ時々曇り';
        if (in_array($code, [45, 48])) return '霧';
        if (in_array($code, [51, 53, 55, 56, 57])) return '霧雨';
        if (in_array($code, [61, 63, 65, 66, 67, 80, 81, 82])) return '雨';
        if (in_array($code, [71, 73, 75, 77, 85, 86])) return '雪';
        if (in_array($code, [95, 96, 99])) return '雷雨';
        
        return '不明';
    }

    private function get_weather_icon(?int $code): string
    {
        if ($code === null) return '❓';
        
        if ($code === 0) return '☀️';
        if ($code === 1) return '🌤️';
        if ($code === 2) return '⛅';
        if ($code === 3) return '☁️';
        if (in_array($code, [45, 48])) return '🌫️';
        if (in_array($code, [51, 53, 55, 56, 57])) return '🌧️';
        if (in_array($code, [61, 63, 65, 66, 67, 80, 81, 82])) return '☔';
        if (in_array($code, [71, 73, 75, 77, 85, 86])) return '❄️';
        if (in_array($code, [95, 96, 99])) return '⛈️';
        
        return '❓';
    }
}
