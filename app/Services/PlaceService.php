<?php

namespace App\Services;


class PlaceService
{
    protected array $places = ALL_PLACES;

    public function get_data(string $prefecture_code, string $port_code)
    {
        foreach($this->places as $place) {
            if($place['prefecture_code'] === $prefecture_code && $place['port_code'] === $port_code) {
                return $place;
            }
        }
    }
}
