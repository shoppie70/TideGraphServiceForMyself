<?php

namespace App\Services;

use Exception;

class TideGraphService
{
    private string $base_uri = "https://tide736.net/api/get_tide.php?rg=day&";
    protected string $query;
    protected string $json_data;
    protected string $date;

    public function __construct(int $year, int $month, int $date,string $prefecture, string $code)
    {
        $data = [
            'yr' => $year,
            'mn' => $month,
            'dy' => $date,
            'pc' => $prefecture,
            'hc' => $code
        ];

        $this->date = $year . '-' . sprintf('%02d', $month) . '-' . sprintf('%02d', $date);
        $this->query = http_build_query($data);

        $this->get_json_data();
    }

    public function get_json_data(): void
    {
        $api_url = $this->base_uri . $this->query;
        $this->json_data = file_get_contents($api_url);
    }

    public function get_tide_data_array(): array
    {
        try {
            $array = json_decode($this->json_data, $associative = true, $depth = 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            return [
                'status' => 400,
                'data' => [],
                'message' => $e->getMessage(),
            ];
        }

        $data = $array['tide']['chart'][$this->date];

        return [
            'status' => 200,
            'tide' => $data['tide'],
            'sun' => $data['sun'],
            'edd' => $data['edd'],
            'flood' => $data['flood'],
            'moon' => $data['moon'],
            'port' => $array['tide']['port']['harbor_namej']
        ];
    }
}