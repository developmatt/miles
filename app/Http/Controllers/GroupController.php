<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Controllers\FlightController;

use App\Models\Flight;

class GroupController extends Controller
{

    public $groups = [];
    public $flights;
    public $inbounds = [];
    public $outbounds = [];
    public $cheapestPrice;
    public $cheapestGroup;

    public function list() {
        $this->groupFlights();
        $this->orderByPrice();
        $data = [
            'flights' => $this->flights,
            'groups' => $this->groups,
            'totalGroups' => count($this->groups),
            'totalFlights' => count($this->flights),
            'cheapestPrice' => $this->cheapestPrice,
            'cheapestGroup' => $this->cheapestGroup
        ];

        return response()
            ->json($data, 200)
            ->header('Content-Type', 'application/json');
    }

    public function groupFlights() {
        $flightController = new FlightController;

        $model = new Flight();
        $this->flights = $model->getFlights();
        $this->outbounds = $model->getOutbounds();
        $this->inbounds = $model->getInbounds();

        foreach($this->outbounds as $o)
        {
            $outboundMatches = $this->doesThisOutboundMatchWithAnyGroup($o);
            foreach($this->inbounds as $i)
            {
                $inboundMatches = $this->doesThisInboundMatchWithAnyGroup($i);
                $combinationMatches = $this->doesThisCombinationMatchWithAnyGroup($o, $i);

                if(count($outboundMatches) > 0) {
                    $this->mergeOutbound($outboundMatches, $o);
                }

                if(count($inboundMatches) > 0) {
                    $this->mergeInbound($inboundMatches, $i);
                }

                if(count($combinationMatches) > 0) {
                    $this->mergeOutboundInboundCombination($combinationMatches, $o, $i);
                } else if($flightController->doOutboundAndInboundMatches($o, $i)){
                    $this->addGroup($o, $i);
                    $this->updateIfIsTheCheapestGroup($o, $i);
                }
            }
        }
    }

    public function addGroup($outbound, $inbound) {
        $data = [
            'uniqueId' => md5(uniqid("")),
            'totalPrice' => $outbound->price + $inbound->price,
            'outbounds' => [$outbound],
            'inbounds' => [$inbound]
        ];

        $this->groups[] = $data;
    }

    public function mergeOutboundInboundCombination($positions, $outbound, $inbound) {
        for($i = 0; $i < count($positions); $i++) {
            $this->addOutboundAtPosition($positions[$i], $outbound);
            $this->addInboundAtPosition($positions[$i], $inbound);
        }
    }

    public function mergeOutbound($positions, $outbound) {
        for($i = 0; $i < count($positions); $i++) {
            $this->addOutboundAtPosition($positions[$i], $outbound);
        }
    }

    public function mergeInbound($positions, $inbound) {
        for($i = 0; $i < count($positions); $i++) {
            $this->addInboundAtPosition($positions[$i], $inbound);
        }
    }

    public function addOutboundAtPosition($position, $outbound) {
        $outboundCopy = array_filter($this->groups[$position]['outbounds'], function($out) use ($outbound) {
            return $out->id === $outbound->id;
        });

        if(count($outboundCopy) === 0) {
            $this->groups[$position]['outbounds'][] = $outbound;
        }
    }

    public function addInboundAtPosition($position, $inbound) {
        $inboundCopy = array_filter($this->groups[$position]['inbounds'], function($in) use ($inbound) {
            return $in->id === $inbound->id;
        });

        if(count($inboundCopy) === 0) {
            $this->groups[$position]['inbounds'][] = $inbound;
        }
    }

    public function doesThisOutboundMatchWithAnyGroup($outbound) {
        $positions = [];
        for($i = 0; $i < count($this->groups); $i++) {
            $outbounds = $this->groups[$i]['outbounds'];
            $matchesOutbounds = array_filter($outbounds, function($outboundInGroup) use ($outbound) {
                return $outboundInGroup->fare === $outbound->fare
                && $outboundInGroup->origin === $outbound->origin
                && $outboundInGroup->destination === $outbound->destination
                && $outboundInGroup->price === $outbound->price;
            });

            if(count($matchesOutbounds) > 0) {
                $positions[] = $i;
            }
        }
        return $positions;
    }

    public function doesThisInboundMatchWithAnyGroup($inbound) {
        $positions = [];
        for($i = 0; $i < count($this->groups); $i++) {
            $inbounds = $this->groups[$i]['inbounds'];
            $matchesInbounds = array_filter($inbounds, function($inboundInGroup) use ($inbound) {
                return $inboundInGroup->fare === $inbound->fare
                && $inboundInGroup->origin === $inbound->origin
                && $inboundInGroup->destination === $inbound->destination
                && $inboundInGroup->price === $inbound->price;
            });

            if(count($matchesInbounds) > 0) {
                $positions[] = $i;
            }
        }
        return $positions;
    }

    public function doesThisCombinationMatchWithAnyGroup($outbound, $inbound) {
        $positions = [];
        $flightController = new FlightController();
        for($i = 0; $i < count($this->groups); $i++) {
            $groupOutbounds = $this->groups[$i]['outbounds'];
            $groupInbounds = $this->groups[$i]['inbounds'];

            $outboundMatches = array_filter($groupOutbounds, function($out) use ($outbound, $flightController) {
                return $flightController->doThisFlightsMatch($out, $outbound);
            });

            $inboundMatches = array_filter($groupInbounds, function($out) use ($inbound, $flightController) {
                return $flightController->doThisFlightsMatch($out, $inbound);
            });

            if(count($outboundMatches) > 0 && count($inboundMatches)) {
                $positions[] = $i;
            }
        }

        return $positions;
    }

    public function updateIfIsTheCheapestGroup($outbound, $inbound) {
        if(!$this->cheapestPrice || $this->cheapestPrice > ($outbound->price + $inbound->price)) {
            $this->cheapestPrice = $outbound->price + $inbound->price;
            $this->cheapestGroup = $this->groups[count($this->groups) - 1]['uniqueId'];
        }
    }

    public function orderByPrice() {
        usort($this->groups, function($groupA, $groupB) {
            return $groupA['totalPrice'] < $groupB['totalPrice'] ? -1 : 1;
        });
    }
}
