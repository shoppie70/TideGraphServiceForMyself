<?php

namespace App\Requests;


class TideGraphRequest
{
    protected array $request;

    public function __construct($request)
    {
        $this->request = $request;

        if (empty($request['place'])) {
            throw new \InvalidArgumentException('場所コードが送信されていません。');
        }
        if (empty($request['date'])) {
            throw new \InvalidArgumentException('日時が送信されていません。');
        }
    }

    public function __invoke(): array
    {
        $date = $this->validate_date_request($this->request);
        $places = $this->validate_place_request($this->request['place']);

        return $date + $places;
    }

    private function validate_place_request($request): array
    {
        $place_array = explode('&', $request);

        if (!(in_array($place_array[0], array_column(PLACES, 'prefecture'), true) && in_array($place_array[1], array_column(PLACES, 'code'), true))) {
            throw new \InvalidArgumentException('場所コードが正しくありません。');
        }

        return [
            'prefecture' => $place_array[0],
            'code' => $place_array[1]
        ];
    }

    private function validate_date_request($request): array
    {
        if (isset($request['year'], $request['month'], $request['date'])) {
            if (!checkdate($request['month'], $request['date'], $request['year'])) {
                throw new \RuntimeException('存在しない日付です。');
            }

            return [
                'year' => $request['year'],
                'month' => $request['month'],
                'date' => $request['date']
            ];
        }

        $year = substr($this->request['date'], 0, 4);
        $month = substr($this->request['date'], 5, 2);
        $date = substr($this->request['date'], 8, 2);

        if (!checkdate($month, $date, $year)) {
            throw new \RuntimeException('存在しない日付です。');
        }

        return [
            'year' => $year,
            'month' => $month,
            'date' => $date
        ];
    }
}