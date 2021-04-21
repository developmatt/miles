<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FlightController extends Controller
{
    public function doOutboundAndInboundMatches($outbound, $inbound) {
        return $inbound->fare === $outbound->fare
        && $inbound->destination === $outbound->origin
        && $inbound->origin === $outbound->destination;
    }

    public function doThisFlightsMatch($flightA, $flightB) {
        return $flightA->fare === $flightB->fare
        && $flightA->origin === $flightB->origin
        && $flightA->destination === $flightB->destination
        && $flightA->price === $flightB->price;
    }
}
