<?php

namespace App\Http\Controllers\Auth;

use GuzzleHttp\Client;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use GuzzleHttp\Exception\ClientException;

/**
 * Class AuthController.
 */
class AuthController extends Controller
{
    /**
     * Log the user out.
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function logout()
    {
        Auth::logout();

        return redirect()->route('home')->with('success', 'You have been signed out.');
    }

    /*
    Connect integration
    */
    public function connectLogin()
    {
        session()->forget('state');
        session()->forget('token');
        session()->put('state', $state = Str::random(40));

        $query = http_build_query([
            'client_id'       => config('connect.client_id'),
            'redirect_uri'    => config('connect.redirect'),
            'response_type'   => 'code',
            'scope'           => 'full_name vatsim_details email',
            'required_scopes' => 'vatsim_details',
            'state'           => $state,
        ]);

        return redirect(config('connect.endpoint').'/oauth/authorize?'.$query);
    }

    public function validateConnectLogin(Request $request)
    {
        //Written by Harrison Scott
        $http = new Client();

        try {
            $response = $http->post(config('connect.endpoint').'/oauth/token', [
                'form_params' => [
                    'grant_type'    => 'authorization_code',
                    'client_id'     => config('connect.client_id'),
                    'client_secret' => config('connect.secret'),
                    'redirect_uri'  => config('connect.redirect'),
                    'code'          => $request->code,
                ],
            ]);
        } catch (ClientException $e) {
            return redirect()->route('home')->with('error', $e->getMessage());
        }
        session()->put('token', json_decode((string) $response->getBody(), true));

        try {
            $response = (new Client())->get(config('connect.endpoint').'/api/user', [
                'headers' => [
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer '.session()->get('token.access_token'),
                ],
            ]);
        } catch (ClientException $e) {
            return redirect()->route('home')->with('error', $e->getMessage());
        }
        $response = json_decode($response->getBody());

        // return $response;

        if (!isset($response->data->cid)) {
            return redirect()->route('home')->with('error', 'There was an error processing data from Connect (No CID)');
        }
        if (!isset($response->data->vatsim->rating)) {
            return redirect()->route('home')->with('error', 'We cannot create an account without VATSIM details.');
        }
        $user = User::updateOrCreate(['id' => $response->data->cid], [
            'email'         => isset($response->data->personal->email) ? $response->data->personal->email : 'no-reply@ganderoceanic.ca',
            'fname'         => isset($response->data->personal->name_first) ? utf8_decode($response->data->personal->name_first) : $response->data->cid,
            'lname'         => isset($response->data->personal->name_last) ? $response->data->personal->name_last : $response->data->cid,
        ]);

        $user->save();
        Auth::login($user, true);

        return redirect()->route('home')->with('success', "Welcome back, {$user->fullName('F')}!");
    }
}
