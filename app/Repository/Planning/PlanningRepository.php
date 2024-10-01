<?php

namespace App\Repository\Planning;

// use App\Models\TrafficEndpoint;
// use App\Models\Offer;

use App\Repository\BaseRepository;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\Planning\IPlanningRepository;

use App\Classes\Planning;

class PlanningRepository extends BaseRepository implements IPlanningRepository
{
    public function __construct()
    {
    }

    public function get_countries_and_languages(array $payload): array {
        $planning = new Planning();
        $data = $planning->get_countries_and_languages($payload);
        return $data;
    }

    public function run(array $payload): array
    {
        $planning = new Planning();
        $data = $planning->feedCrgs($payload);
        return ($data);//collect
    }
}
