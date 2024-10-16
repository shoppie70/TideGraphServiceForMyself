<?php

namespace App\UseCases\Date;

use Carbon\Carbon;

class GetRequestDateAction
{
    public function __invoke($request): Carbon
    {
        return new Carbon($request['year'] . '-' . sprintf('%02d', $request['month']) . '-' . sprintf('%02d', $request['date']));
    }
}
