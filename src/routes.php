<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use RainLab\User\Facades\Auth;

use BranMuffin\GoogleConnect\Models\Client;

use Renatio\DynamicPDF\Classes\PDF;

Route::get('authenticate/calendar', function(Request $req) {
    $request = Input::get('code');
    if ($request) {
        $client = new Google_Client();
        $client->setAuthConfig(__DIR__.'/components/credentials.json');
        $credentials = $client->authenticate($request);
        $user = new Client;
        $user->userid = Auth::getUser()->id;
        $user->token = $credentials;
        $user->save();
        return Redirect::to('/user');
    } else {
        echo 'Wrong area';
    }
})->middleware(['web']);

/*Route::match(['POST', 'OPTIONS'],'api/update-todo', function(Request $req) {
    $data = $req->input();
    if (!empty($data)) {
        Todo::where('id', $data['id'])
            ->update([
            'name' => $data['name'],
            'description' => $data['description'],
            'status' => $data['status'] 
        ]);
        return response()->json([
            'Success' => $data,
        ]);
    } else {
        return response()->json([
            'Success' => $req,
        ]);
    }
});*/

/*Route::get('api/todolist/populate', function() {
    $faker = Faker\Factory::create();
    
    for($i = 0; $i < 20; $i++) {
        Todo::create([
            'name' => $faker->sentence($nbWords = 6, $variableNbWords = true),
            'description' => $faker->text($maxNbChars = 200),
            'status' => $faker->boolean($chanceOfGettingTrue = 50),
            'created_at' => $faker->date($format = 'Y-m-d H:i:s', $max = 'now')
        ]);
    }
    
    return "Todos Created!";
});*/