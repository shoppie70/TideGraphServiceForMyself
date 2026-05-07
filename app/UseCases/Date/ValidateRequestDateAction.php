<?php

namespace App\UseCases\Date;

class ValidateRequestDateAction
{
    public function __invoke($request): array
    {
        if (!isset($request['year'], $request['month'], $request['date'])) {
            throw new \RuntimeException('有効な日付をリクエストしてください。');
        }

        if (!checkdate($request['month'], $request['date'], $request['year'])) {
            throw new \RuntimeException('存在しない日付です。');
        }

        return [
            'year'  => $request['year'],
            'month' => $request['month'],
            'date'  => $request['date']
        ];
    }
}
