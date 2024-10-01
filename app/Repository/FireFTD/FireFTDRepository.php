<?php

namespace App\Repository\FireFTD;

// use App\Models\TrafficEndpoint;
// use App\Models\Offer;

use App\Repository\BaseRepository;

use Illuminate\FireFTD\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\FireFTD\IFireFTDRepository;

class FireFTDRepository extends BaseRepository implements IFireFTDRepository
{
    public function __construct()
    {
    }

    public function run(array $payload): array
    {

        $data =['sucsess' => true];
        
        return ($data);//collect
    }	
}
