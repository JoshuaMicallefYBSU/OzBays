<?php

namespace Tests\Feature;

use App\Jobs\AerodromeUpdates;
use App\Models\Airports;
use App\Models\Bays;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AirportImportTest extends TestCase
{
    use RefreshDatabase;

    private string $publicPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Point public_path() at a temp folder so tests never touch the real airport files
        $this->publicPath = sys_get_temp_dir().'/ozbays-import-'.uniqid();
        File::ensureDirectoryExists($this->publicPath.'/config/airports');
        $this->app->usePublicPath($this->publicPath);

        Role::firstOrCreate(['name' => 'Pilot']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publicPath);

        parent::tearDown();
    }

    private function airport(string $icao, array $bays = ['1' => 'B738']): array
    {
        $parking = [];
        foreach ($bays as $bay => $ac) {
            $parking[$bay] = ['lat' => -34.9, 'lon' => 138.5, 'Operator' => null, 'Terminal' => 'T1', 'AC' => $ac, 'Type' => null, 'Priority' => 5];
        }

        return [
            'icao' => $icao, 'name' => "Test {$icao}", 'lat' => -34.9, 'lon' => 138.5,
            'settings' => ['status' => 'testing', 'airport_type' => 'Major2'],
            'parking' => $parking,
        ];
    }

    private function writeAirportFile(string $icao, $contents): void
    {
        File::put($this->publicPath."/config/airports/{$icao}.json", is_string($contents) ? $contents : json_encode($contents));
    }

    private function createUser(int $id, string $roleName): User
    {
        DB::table('users')->insert([
            'id' => $id, 'fname' => 'Test', 'lname' => "User{$id}", 'email' => "user{$id}@example.com",
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $user = User::find($id);
        $user->assignRole($roleName);

        return $user;
    }

    private function upload(array|string $airport): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('airport.json', is_string($airport) ? $airport : json_encode($airport));
    }

    public function test_job_imports_every_airport_file_in_the_folder(): void
    {
        $this->writeAirportFile('YAAA', $this->airport('YAAA', ['1' => 'B738', '2' => 'A320']));
        $this->writeAirportFile('YBBB', $this->airport('YBBB', ['5' => 'DH8D']));

        (new AerodromeUpdates)->handle();

        $this->assertEqualsCanonicalizing(['YAAA', 'YBBB'], Airports::pluck('icao')->all());
        $this->assertSame(2, Bays::where('airport', 'YAAA')->count());
        $this->assertSame(1, Bays::where('airport', 'YBBB')->count());
    }

    public function test_long_names_are_matched_to_bays_on_import(): void
    {
        $airport = $this->airport('YAAA', ['D55A' => 'B738', 'MB1' => 'DH8D', 'NJ4' => 'F100', 'R1' => 'B738', '12' => 'B738']);
        $airport['long_name'] = ['D' => 'Domestic', 'MB' => 'Maroomba', 'NJ4' => 'National Jet Four'];
        $this->writeAirportFile('YAAA', $airport);

        (new AerodromeUpdates)->handle();

        $bays = Bays::where('airport', 'YAAA')->get()->keyBy('bay');

        // Prefix letters swapped for the long name
        $this->assertSame('Domestic 55A', $bays['D55A']->long_name);
        $this->assertSame('Domestic 55A (D55A)', $bays['D55A']->display_name);
        $this->assertSame('Maroomba 1 (MB1)', $bays['MB1']->display_name);

        // A key matching the whole bay is used as-is
        $this->assertSame('National Jet Four (NJ4)', $bays['NJ4']->display_name);

        // No match - just the bay identifier
        $this->assertNull($bays['R1']->long_name);
        $this->assertSame('R1', $bays['R1']->display_name);
        $this->assertSame('12', $bays['12']->display_name);
    }

    public function test_job_removes_airports_and_bays_no_longer_in_any_file(): void
    {
        $this->writeAirportFile('YAAA', $this->airport('YAAA', ['1' => 'B738', '2' => 'A320']));
        $this->writeAirportFile('YBBB', $this->airport('YBBB'));
        (new AerodromeUpdates)->handle();

        File::delete($this->publicPath.'/config/airports/YBBB.json');
        $this->writeAirportFile('YAAA', $this->airport('YAAA', ['1' => 'B738']));
        (new AerodromeUpdates)->handle();

        $this->assertSame(['YAAA'], Airports::pluck('icao')->all());
        $this->assertSame(['1'], Bays::pluck('bay')->all());
    }

    public function test_job_keeps_an_airport_whose_file_is_broken(): void
    {
        $this->writeAirportFile('YAAA', $this->airport('YAAA'));
        (new AerodromeUpdates)->handle();

        $this->writeAirportFile('YAAA', '{ not valid json');
        (new AerodromeUpdates)->handle();

        $this->assertTrue(Airports::where('icao', 'YAAA')->exists());
        $this->assertSame(1, Bays::where('airport', 'YAAA')->count());
    }

    public function test_job_does_nothing_when_the_folder_is_empty(): void
    {
        $this->writeAirportFile('YAAA', $this->airport('YAAA'));
        (new AerodromeUpdates)->handle();

        File::delete($this->publicPath.'/config/airports/YAAA.json');
        (new AerodromeUpdates)->handle();

        $this->assertTrue(Airports::where('icao', 'YAAA')->exists());
    }

    public function test_developer_can_import_a_new_airport(): void
    {
        $user = $this->createUser(1, 'Developer');

        $response = $this->actingAs($user)->post(route('dashboard.admin.airport.import.store'), [
            'airport_file' => $this->upload($this->airport('YCCC', ['1' => 'B738', '2' => 'A320'])),
        ]);

        $response->assertRedirect(route('dashboard.admin.airport.view', 'YCCC'));
        $this->assertTrue(Airports::where('icao', 'YCCC')->exists());
        $this->assertSame(2, Bays::where('airport', 'YCCC')->count());
        $this->assertFileExists($this->publicPath.'/config/airports/YCCC.json');
        $this->assertSame('YCCC', json_decode(File::get($this->publicPath.'/config/airports/YCCC.json'), true)['icao']);
    }

    public function test_import_rejects_an_existing_airport(): void
    {
        $user = $this->createUser(1, 'Lead Developer');
        $this->writeAirportFile('YAAA', $this->airport('YAAA'));
        (new AerodromeUpdates)->handle();

        $response = $this->actingAs($user)->post(route('dashboard.admin.airport.import.store'), [
            'airport_file' => $this->upload($this->airport('YAAA', ['1' => 'B738', '9' => 'A320'])),
        ]);

        $response->assertSessionHasErrors('airport_file');
        $this->assertSame(1, Bays::where('airport', 'YAAA')->count());
    }

    public function test_import_rejects_an_invalid_file(): void
    {
        $user = $this->createUser(1, 'Developer');

        $response = $this->actingAs($user)->post(route('dashboard.admin.airport.import.store'), [
            'airport_file' => $this->upload(['icao' => 'YDDD', 'name' => 'No Bays', 'lat' => 1, 'lon' => 1]),
        ]);

        $response->assertSessionHasErrors('airport_file');
        $this->assertFalse(Airports::where('icao', 'YDDD')->exists());
        $this->assertFileDoesNotExist($this->publicPath.'/config/airports/YDDD.json');
    }

    public function test_non_developers_cannot_access_the_importer(): void
    {
        $user = $this->createUser(1, 'Pilot');

        $this->actingAs($user)->get(route('dashboard.admin.airport.import'))->assertForbidden();
        $this->actingAs($user)->post(route('dashboard.admin.airport.import.store'), [
            'airport_file' => $this->upload($this->airport('YCCC')),
        ])->assertForbidden();
    }
}
