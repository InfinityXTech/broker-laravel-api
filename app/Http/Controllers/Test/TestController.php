<?php

namespace App\Http\Controllers\Test;

use MongoDB\BSON\ObjectId;
use App\Models\TechSupport;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Classes\Mongo\MongoDBObjects;
use App\Http\Controllers\ApiController;
use \Illuminate\Support\Facades\Validator;
use App\Models\Masters\MasterBillingEntity;
use App\Models\TrafficEndpoints\TrafficEndpointBillingChargebacks;

class TestController extends ApiController
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['index']]);
    }

    public function index(Request $request)
    {
        $input_data = $request->all();
        
        $data = [];

        // $data = [
        //     'success' => true,
        //     'data' => [
        //         'request' => $input_data,
        //         'something' => []
        //     ]
        // ];

        // $items = TechSupport::all()->sortByDesc(function ($a, $b) {
		// 	// return (((array)$a->timestamp)['$date']['$numberLong'] > ((array)$b->timestamp)['$date']['$numberLong']);
		// 	// echo (int)(((array)$a->timestamp)['milliseconds']);
        //     $a = (array)($a->timestamp ?? []);
        //     $b = (array)($b->timestamp ?? []);
        //     // echo (int)($a['milliseconds'] ?? 0) .' > '.(int)($b['milliseconds'] ?? 0);
        //     return intval($a['milliseconds'] ?? 0) > intval($b['milliseconds'] ?? 0);
            
		// 	// > $b->timestamp);
		// 	// return true;
		// });


        // $data = MasterBillingEntity::all()->toArray();
        // $model = MasterBillingEntity::query()->find('619e042e485a1e1fda52a313');
        // $model->update(['vat_id' => 3]);
        // print_r($model->fresh()->toArray());

        // $mongo = new MongoDBObjects('masters_billing_entities', ['_id' => new \MongoDB\BSON\ObjectId('619e042e485a1e1fda52a313')]);
        // $mongo->update(['vat_id' => 4]);

        // $mongo = new MongoDBObjects('masters_billing_chargebacks', ['_id' => new \MongoDB\BSON\ObjectId('619e1465d0329310c15d48f3')]);
        // $mongo->update(['amount' => 4]);


        // $insert = array();
        // $insert['endpoint'] = '60538ff3f37a45113d261b2a';
        // $insert['amount'] = 999;

        // $model = new TrafficEndpointBillingChargebacks();
        // $model->fill($insert);
        // $model->save();

        // $credentials = [
        //     'account_email' => 'qa@roibees.com',
        //     'password' => 'test',
        // ];

        // $data['token'] = Auth::attempt($credentials);

        // $this->revokeAccessAndRefreshTokens($request, 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOlwvXC9xYTMtYXBpLmFsYmVydHJvaS5jb21cL2FwaVwvYXV0aFwvbG9naW4iLCJpYXQiOjE2NTg3NDk5NjUsImV4cCI6MTY1ODc2NDM2NSwibmJmIjoxNjU4NzQ5OTY1LCJqdGkiOiJpUGZ4aHVyaUVNakZJMGZiIiwic3ViIjoiNjA0ZjZkYzU3Zjk3NmJmYWNhMmY0OTNlIiwicHJ2IjoiMjNiZDVjODk0OWY2MDBhZGIzOWU3MDFjNDAwODcyZGI3YTU5NzZmNyJ9.rwPq_RJotvrj9n7MJ5CGANmqnPEZhh4w8ex6OnifgKM');
        // Auth::user()->token()->revoke();
        // $request->user()->token()->revoke();
        // $request->user()->token()->delete(); 

        // Auth::logout();

        return response()->json($data, 200);
    }

    public function get(string $id)
    {
        $data = [
            'success' => true,
            'id' => $id
        ];
        return response()->json($data, 200);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|min:4',
            'active' => 'bool',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $data = [
            'success' => true,
            'payload' => $payload
        ];
        return response()->json($data, 200);
    }

    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|min:4',
            'active' => 'bool',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $data = [
            'success' => true,
            'payload' => $payload
        ];
        return response()->json($data, 200);
    }

    public function delete(string $id)
    {
        $data = [
            'success' => true,
            'id' => $id
        ];
        return response()->json($data, 200);
    }
}
