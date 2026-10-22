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

    private string $storagePath;

    protected function setUp(): void
    {
        parent::setUp();

        // Point storage_path() at a temp folder so tests never touch the real airport files
        $this->storagePath = sys_get_temp_dir().'/ozbays-import-'.uniqid();
        File::ensureDirectoryExists($this->storagePath.'/app/airports');
        $this->app->useStoragePath($this->storagePath);

        Role::firstOrCreate(['name' => 'Pilot']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storagePath);

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
            'settings' => ['status' => 'testing'],
            'parking' => $parking,
        ];
    }

    private function writeAirportFile(string $icao, $contents): void
    {
        File::put($this->storagePath."/app/airports/{$icao}.json", is_string($contents) ? $contents : json_encode($contents));
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

    public function test_job_removes_bays_no_longer_in_the_airports_file(): void
    {
        $this->writeAirportFile('YAAA', $this->airport('YAAA', ['1' => 'B738', '2' => 'A320']));
        $this->writeAirportFile('YBBB', $this->airport('YBBB', ['5' => 'DH8D']));
        (new AerodromeUpdates)->handle();

        $this->writeAirportFile('YAAA', $this->airport('YAAA', ['1' => 'B738']));
        (new AerodromeUpdates)->handle();

        $this->assertSame(['1'], Bays::where('airport', 'YAAA')->pluck('bay')->all());
        $this->assertSame(['5'], Bays::where('airport', 'YBBB')->pluck('bay')->all());
    }

    public function test_job_leaves_airports_without_a_site_file_alone(): void
    {
        // Airport already in the database (e.g. from before the move to uploaded files)
        $this->writeAirportFile('YAAA', $this->airport('YAAA', ['1' => 'B738', '2' => 'A320']));
        (new AerodromeUpdates)->handle();
        File::delete($this->storagePath.'/app/airports/YAAA.json');

        // Uploading a different airport must not wipe it
        $this->writeAirportFile('YBBB', $this->airport('YBBB'));
        (new AerodromeUpdates)->handle();

        $this->assertEqualsCanonicalizing(['YAAA', 'YBBB'], Airports::pluck('icao')->all());
        $this->assertSame(2, Bays::where('airport', 'YAAA')->count());
    }

    public function test_job_never_reads_the_github_reference_files(): void
    {
        $publicPath = sys_get_temp_dir().'/ozbays-public-'.uniqid();
        File::ensureDirectoryExists($publicPath.'/config/airports');
        File::put($publicPath.'/config/airports/YZZZ.json', json_encode($this->airport('YZZZ')));
        $this->app->usePublicPath($publicPath);

        (new AerodromeUpdates)->handle();

        $this->assertFalse(Airports::where('icao', 'YZZZ')->exists());
        File::deleteDirectory($publicPath);
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

        File::delete($this->storagePath.'/app/airports/YAAA.json');
        (new AerodromeUpdates)->handle();

        $this->assertTrue(Airports::where('icao', 'YAAA')->exists());
    }

    // Upload, check the review page, then confirm - returns the review page response
    private function uploadAndConfirm(User $user, array $airport)
    {
        $upload = $this->actingAs($user)->post(route('dashboard.admin.airport.import.store'), ['airport_file' => $this->upload($airport)]);
        $reviewUrl = $upload->headers->get('Location');
        $this->assertStringContainsString('/airport/import/', (string) $reviewUrl);

        $review = $this->actingAs($user)->get($reviewUrl)->assertOk();
        $this->actingAs($user)->post($reviewUrl)->assertRedirect(route('dashboard.admin.airport.view', $airport['icao']));

        return $review;
    }

    public function test_developer_can_import_a_new_airport(): void
    {
        $user = $this->createUser(1, 'Developer');

        $upload = $this->actingAs($user)->post(route('dashboard.admin.airport.import.store'), [
            'airport_file' => $this->upload($this->airport('YCCC', ['1' => 'B738', '2' => 'A320'])),
        ]);

        // Nothing is imported until the review is confirmed
        $this->assertFalse(Airports::where('icao', 'YCCC')->exists());
        $this->actingAs($user)->get($upload->headers->get('Location'))
            ->assertOk()
            ->assertSee('is a new airport')
            ->assertSee('Import Airport');

        $this->actingAs($user)->post($upload->headers->get('Location'))->assertRedirect(route('dashboard.admin.airport.view', 'YCCC'));
        $this->assertTrue(Airports::where('icao', 'YCCC')->exists());
        $this->assertSame(2, Bays::where('airport', 'YCCC')->count());
        $this->assertFileExists($this->storagePath.'/app/airports/YCCC.json');
        $this->assertSame('YCCC', json_decode(File::get($this->storagePath.'/app/airports/YCCC.json'), true)['icao']);
    }

    public function test_airport_in_the_database_without_a_site_file_can_be_uploaded(): void
    {
        $user = $this->createUser(1, 'Developer');

        // In the database from before, but never uploaded
        $this->writeAirportFile('YAAA', $this->airport('YAAA', ['1' => 'B738']));
        (new AerodromeUpdates)->handle();
        File::delete($this->storagePath.'/app/airports/YAAA.json');

        $this->uploadAndConfirm($user, $this->airport('YAAA', ['1' => 'B738', '2' => 'A320']));

        $this->assertSame(2, Bays::where('airport', 'YAAA')->count());
        $this->assertFileExists($this->storagePath.'/app/airports/YAAA.json');
    }

    public function test_reuploading_an_airport_shows_the_changes_then_applies_them(): void
    {
        $user = $this->createUser(1, 'Lead Developer');
        $this->writeAirportFile('YAAA', $this->airport('YAAA', ['1' => 'B738', '2' => 'A320', '3' => 'DH8D']));
        (new AerodromeUpdates)->handle();
        Bays::where('airport', 'YAAA')->where('bay', '3')->update(['callsign' => 'QLK1', 'status' => 2]);

        // Bay 1 unchanged, bay 2 now takes an A321, bay 3 removed, bay 9 added, airport now active
        $updated = $this->airport('YAAA', ['1' => 'B738', '2' => 'A321', '9' => 'B789']);
        $updated['settings']['status'] = 'active';

        $review = $this->uploadAndConfirm($user, $updated);

        $review->assertSeeInOrder(['1</strong> bays added', '1</strong> bays changed', '1</strong> bays removed'], false)
            ->assertSeeInOrder(['Airport Settings', 'Status', 'testing', 'active'])
            ->assertSeeInOrder(['Changed Bays', '2', 'Aircraft', 'A320', 'A321'])
            ->assertSeeInOrder(['Added Bays', '9', 'B789'])
            ->assertSeeInOrder(['Removed Bays', '3', 'Occupied by QLK1'])
            ->assertDontSee('Long Name</td>', false); // bay 1 had nothing to change

        $this->assertEqualsCanonicalizing(['1', '2', '9'], Bays::where('airport', 'YAAA')->pluck('bay')->all());
        $this->assertSame('A321', Bays::where('airport', 'YAAA')->where('bay', '2')->value('aircraft'));
        $this->assertSame('active', Airports::where('icao', 'YAAA')->value('status'));
        $this->assertArrayHasKey('9', json_decode(File::get($this->storagePath.'/app/airports/YAAA.json'), true)['parking']);
    }

    public function test_reuploading_an_identical_file_has_nothing_to_apply(): void
    {
        $user = $this->createUser(1, 'Lead Developer');
        $this->writeAirportFile('YAAA', $this->airport('YAAA'));
        (new AerodromeUpdates)->handle();

        $upload = $this->actingAs($user)->post(route('dashboard.admin.airport.import.store'), ['airport_file' => $this->upload($this->airport('YAAA'))]);

        $this->actingAs($user)->get($upload->headers->get('Location'))
            ->assertOk()
            ->assertSee('there is nothing to change')
            ->assertDontSee('Apply Changes');
    }

    public function test_a_pending_upload_cannot_be_used_by_someone_else(): void
    {
        $uploader = $this->createUser(1, 'Developer');
        $other = $this->createUser(2, 'Developer');

        $reviewUrl = $this->actingAs($uploader)->post(route('dashboard.admin.airport.import.store'), [
            'airport_file' => $this->upload($this->airport('YCCC')),
        ])->headers->get('Location');

        $this->flushSession();
        $this->actingAs($other)->post($reviewUrl)->assertRedirect(route('dashboard.admin.airport.import'));
        $this->assertFalse(Airports::where('icao', 'YCCC')->exists());
    }

    public function test_import_rejects_an_invalid_file(): void
    {
        $user = $this->createUser(1, 'Developer');

        $response = $this->actingAs($user)->post(route('dashboard.admin.airport.import.store'), [
            'airport_file' => $this->upload(['icao' => 'YDDD', 'name' => 'No Bays', 'lat' => 1, 'lon' => 1]),
        ]);

        $response->assertSessionHasErrors('airport_file');
        $this->assertFalse(Airports::where('icao', 'YDDD')->exists());
        $this->assertFileDoesNotExist($this->storagePath.'/app/airports/YDDD.json');
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
