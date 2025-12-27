<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use GuzzleHttp\Client;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\DiscordClient;
use App\Models\Roster\RosterMember;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use GuzzleHttp\Exception\ClientException;

class DiscordController extends Controller
{
    public function joinShortcut()
    {
        return redirect()->route('index', ['discord' => '1']);
    }

    /*
    Discord connection/server join
    */
    public function linkRedirectDiscord()
    {
        $query = http_build_query([
            'client_id' => env('DISCORD_CLIENT_ID'),
            'redirect_uri' => env('APP_URL') . '/dashboard/discord/link/callback',
            'response_type' => 'code',
            'scope' => 'identify',
        ]);

        return redirect('https://discord.com/oauth2/authorize?' . $query);
    }

    public function linkCallbackDiscord(Request $request)
    {
        //Get access token using returned code
        $http = new Client();

        try {
            $response = $http->post('https://discord.com/api/v10/oauth2/token', [
                'form_params' => [
                    'client_id' => env('DISCORD_CLIENT_ID'),
                    'client_secret' => env('DISCORD_CLIENT_SECRET'),
                    'grant_type' => 'authorization_code',
                    'code' => $request->code,
                    'redirect_uri' => env('APP_URL') . '/dashboard/discord/link/callback',
                    'scope' => 'identify'
                ],
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded']
            ]);
        } catch (ClientException $e) {
            return redirect()->route('dashboard.index')->with('error', $e->getMessage());
        }

        $access_token = json_decode($response->getBody(), true)['access_token'];

        //Get User Details from access token
        try {
            $response = (new Client())->get('https://discord.com/api/v10/users/@me', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$access_token}"
                ],
            ]);
        } catch (ClientException $e) {
            return redirect()->route('dashboard.index')->with('error', $e->getMessage());
        }

        $discord_user = json_decode($response->getBody(), true);

        //Duplicate?
        if (User::where('discord_user_id', $discord_user['id'])->exists()) {
            return redirect()->route('dashboard.index')->with('error', 'This Discord account has already been linked by another user.');
        }

        $user = auth()->user();

        //Edit user
        $user->discord_user_id = $discord_user['id'];
        $user->discord_username = $discord_user['username'];
        $user->discord_avatar = $discord_user['avatar'] ? 'https://cdn.discordapp.com/avatars/'.$discord_user['id'].'/'.$discord_user['avatar'].'.png' : null;
        $user->save();

        return redirect()->route('dashboard.index')->with('success', 'Linked with account '.$discord_user['username'].'! Please join our Discord!');
    }

    public function joinRedirectDiscord()
    {
        $query = http_build_query([
            'client_id' => env('DISCORD_CLIENT_ID'),
            'redirect_uri' => env('APP_URL') . '/dashboard/discord/server/join/callback',
            'response_type' => 'code',
            'scope' => 'identify guilds.join',
        ]);

        return redirect('https://discord.com/oauth2/authorize?' . $query);
    }

    public function joinCallbackDiscord(Request $request)
    {
        //Get the current user
        $user = auth()->user();

        //let's find all the roles they could possibly have...
        $rolesToAdd = [];

        //Here are all the role ids
        $discordRoleIds = [
            'member'    => 1454245409169080512,
        ];

        //Add the Member role
        array_push($rolesToAdd, $discordRoleIds['member']);

        //Get access token using returned code
        $http = new Client();

        try {
            $response = $http->post('https://discord.com/api/v10/oauth2/token', [
                'form_params' => [
                    'client_id' => env('DISCORD_CLIENT_ID'),
                    'client_secret' => env('DISCORD_CLIENT_SECRET'),
                    'grant_type' => 'authorization_code',
                    'code' => $request->code,
                    'redirect_uri' => env('APP_URL') . '/dashboard/discord/server/join/callback',
                    'scope' => 'identify guilds.join'
                ],
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded']
            ]);
        } catch (ClientException $e) {
            return redirect()->route('dashboard.index')->with('error', $e->getMessage());
        }

        $access_token = json_decode($response->getBody(), true)['access_token'];


        //Make em join Discord 
        try {
            $response = (new Client())
                ->put(
                    'https://discord.com/api/v10/guilds/'.env('DISCORD_GUILD_ID').'/members/'.$user->discord_user_id,
                    [
                        'headers' => [
                            'Authorization' => 'Bot ' . env('DISCORD_BOT_TOKEN')
                        ],
                        'json' => [
                            'access_token' => $access_token,
                            'nick' => Auth::user()->fullName('FLC'),
                            'roles' => $rolesToAdd
                        ]
                    ]
                );
        } catch (ClientException $e) {
            return redirect()->route('dashboard.index')->with('error', $e->getMessage());
        }

        $user->discord_member = true;
        $user->save();

        //Log it

        //And back to the dashboard.index
        return redirect()->route('dashboard.index')->with('success', 'You have now joined the OzBays Discord Server!');
    }

    public function unlinkDiscord()
    {
        //Get user
        $user = auth()->user();

        //If they're a member of the Discord
        if ($user->discord_member) {
            $http = new Client();

            try {
                $http->delete('https://discord.com/api/v10/guilds/'.env('DISCORD_GUILD_ID').'/members/'.$user->discord_user_id, 
                    [
                        'headers' => ['Authorization' => 'Bot '.env('DISCORD_BOT_TOKEN')]
                    ]);
            } catch (ClientException $e) {
                return redirect()->route('dashboard.index')->with('error', $e->getMessage());
            }
        }

        //Remove details from DB
        $user->discord_user_id = null;
        $user->discord_member = false;
        $user->discord_avatar = null;
        $user->discord_username = null;

        //If they have a Discord avatar, remove it
        if ($user->avatar_mode == 2) {
            $user->avatar_mode = 0;
        }

        //Save
        $user->save();

        //Redirect
        return redirect()->route('dashboard.index')->with('success', 'Discord account unlinked.');
    }
}
