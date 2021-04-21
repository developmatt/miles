<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Flight extends Model
{

    public function getFlights() {
        return $this->curlCall(env('API_URL'));
    }

    public function getInbounds() {
        return $this->curlCall(env('API_URL') . '?inbound=1');
    }

    public function getOutbounds() {
        return $this->curlCall(env('API_URL') . '?outbound=1');
    }

    public function curlCall($url) {
        try {
            $flights = [];
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            $output = curl_exec($curl);
            curl_close($curl);

            $flightsObject = json_decode($output);
            foreach($flightsObject as $flight) {
                $flights[] = $flight;
            }

            usort($flights, function($a, $b) {
                return $a->price > $b->price ? 1 : -1;
            });

            return $flights;
        } catch(\Exception $e) {
            return [];
        }
    }
}
