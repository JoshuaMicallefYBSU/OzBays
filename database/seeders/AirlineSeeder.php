<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Airline;

class AirlineSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // NOTES
        // - freight_regex NULL  => treat airline as 100% freight ops
        // - freight_regex JSON  => treat airline as mixed; ONLY callsigns matching any regex are freight
        // - Callsign format assumed: <ICAO><flight_number> (e.g. QFA7501)
        //
        // IMPORTANT
        // Mixed-airline freight flight-number ranges are NOT consistently published and vary by region/era.
        // This seeder includes a large set of dedicated cargo airlines (freight_regex = null),
        // and ONLY includes mixed airlines where a reliable/known freight-pattern is provided.

        $airlines = [
            // =========================
            // Australia / Oceania
            // =========================

            ['icao' => 'QFA', 'name' => 'Qantas', 'freight_regex' => ['^QFA75\\d{2}$',]], // QFA7500-7599

            // Dedicated freight / cargo operators (treat as 100% freight)
            ['icao' => 'EFA', 'name' => 'Express Freighters Australia', 'freight_regex' => null],
            ['icao' => 'PAQ', 'name' => 'Pacific Air Express', 'freight_regex' => null],
            ['icao' => 'TMN', 'name' => 'Tasman Cargo Airlines', 'freight_regex' => null],

            // =========================
            // North America
            // =========================
            ['icao' => 'FDX', 'name' => 'FedEx Express', 'freight_regex' => null],
            ['icao' => 'UPS', 'name' => 'UPS Airlines', 'freight_regex' => null],
            ['icao' => 'GTI', 'name' => 'Atlas Air', 'freight_regex' => null],
            ['icao' => 'CKS', 'name' => 'Kalitta Air', 'freight_regex' => null],
            ['icao' => 'PAC', 'name' => 'Polar Air Cargo', 'freight_regex' => null],
            ['icao' => 'ATN', 'name' => 'Air Transport International', 'freight_regex' => null],
            ['icao' => 'ABX', 'name' => 'ABX Air', 'freight_regex' => null],
            ['icao' => 'MZN', 'name' => 'Amazon Air', 'freight_regex' => null],
            ['icao' => 'AJT', 'name' => 'Amerijet International', 'freight_regex' => null],
            ['icao' => 'KFS', 'name' => 'Kalitta Charters II (Kalitta Charters)', 'freight_regex' => null],
            ['icao' => 'NCR', 'name' => 'National Airlines', 'freight_regex' => null],
            ['icao' => 'WGN', 'name' => 'Western Global Airlines', 'freight_regex' => null],
            ['icao' => 'MTN', 'name' => 'Mountain Air Cargo', 'freight_regex' => null],
            ['icao' => 'WIG', 'name' => 'Wiggins Airways', 'freight_regex' => null],
            ['icao' => 'JUS', 'name' => 'USA Jet Airlines', 'freight_regex' => null],
            ['icao' => 'NAC', 'name' => 'Northern Air Cargo', 'freight_regex' => null],
            ['icao' => 'CJT', 'name' => 'Cargojet', 'freight_regex' => null],
            ['icao' => 'MAL', 'name' => 'Morningstar Air Express', 'freight_regex' => null],
            ['icao' => 'SNC', 'name' => 'Air Cargo Carriers', 'freight_regex' => null],
            ['icao' => 'AAH', 'name' => 'Aloha Air Cargo', 'freight_regex' => null],
            ['icao' => 'AMF', 'name' => 'Ameriflight', 'freight_regex' => null],

            // Mexico / LATAM cargo ops
            ['icao' => 'MAA', 'name' => 'Mas Air', 'freight_regex' => null],
            ['icao' => 'VTM', 'name' => 'Aeronaves TSM', 'freight_regex' => null],
            ['icao' => 'TNO', 'name' => 'AeroUnion', 'freight_regex' => null],
            ['icao' => 'GEC', 'name' => 'Lufthansa Cargo', 'freight_regex' => null],

            // =========================
            // Europe
            // =========================
            ['icao' => 'CLX', 'name' => 'Cargolux', 'freight_regex' => null],
            ['icao' => 'BCS', 'name' => 'European Air Transport Leipzig (DHL)', 'freight_regex' => null],
            ['icao' => 'DHK', 'name' => 'DHL Air UK', 'freight_regex' => null],
            ['icao' => 'BOX', 'name' => 'AeroLogic', 'freight_regex' => null],
            ['icao' => 'TAY', 'name' => 'ASL Airlines Belgium', 'freight_regex' => null],
            ['icao' => 'ABR', 'name' => 'ASL Airlines Ireland', 'freight_regex' => null],
            ['icao' => 'FPO', 'name' => 'ASL Airlines France', 'freight_regex' => null],
            ['icao' => 'SWN', 'name' => 'West Atlantic Sweden', 'freight_regex' => null],
            ['icao' => 'NPT', 'name' => 'West Atlantic UK', 'freight_regex' => null],
            ['icao' => 'AHC', 'name' => 'Azal Avia Cargo', 'freight_regex' => null],
            ['icao' => 'ADB', 'name' => 'Antonov Airlines', 'freight_regex' => null],
            ['icao' => 'CVK', 'name' => 'Cavok Air', 'freight_regex' => null],
            ['icao' => 'MNB', 'name' => 'MNG Airlines', 'freight_regex' => null],
            ['icao' => 'KZU', 'name' => 'Kuzu Cargo', 'freight_regex' => null],
            ['icao' => 'MSA', 'name' => 'Poste Air Cargo', 'freight_regex' => null],
            ['icao' => 'SRR', 'name' => 'Maersk Air Cargo', 'freight_regex' => null],
            ['icao' => 'CMA', 'name' => 'CMA CGM Air Cargo', 'freight_regex' => null],
            ['icao' => 'SRN', 'name' => 'SprintAir', 'freight_regex' => null],
            ['icao' => 'SWT', 'name' => 'Swiftair', 'freight_regex' => null],
            ['icao' => 'CYG', 'name' => 'Cygnus Air', 'freight_regex' => null],
            ['icao' => 'CYO', 'name' => 'Coyne Airways', 'freight_regex' => null],

            // =========================
            // Middle East / Asia
            // =========================
            ['icao' => 'QAC', 'name' => 'Qatar Airways Cargo', 'freight_regex' => null],
            ['icao' => 'SQC', 'name' => 'Singapore Airlines Cargo', 'freight_regex' => null],
            ['icao' => 'CKK', 'name' => 'China Cargo Airlines', 'freight_regex' => null],
            ['icao' => 'CSS', 'name' => 'SF Airlines', 'freight_regex' => null],
            ['icao' => 'HYT', 'name' => 'YTO Cargo Airlines', 'freight_regex' => null],
            ['icao' => 'AHK', 'name' => 'Air Hong Kong', 'freight_regex' => null],
            ['icao' => 'NCA', 'name' => 'Nippon Cargo Airlines', 'freight_regex' => null],
            ['icao' => 'AAR', 'name' => 'Asiana Cargo', 'freight_regex' => null],
            ['icao' => 'MAS', 'name' => 'MASkargo (Malaysia Airlines Cargo)', 'freight_regex' => null],
            ['icao' => 'KMI', 'name' => 'K-Mile Air', 'freight_regex' => null],
            ['icao' => 'MJP', 'name' => 'My Jet Xpress Airlines', 'freight_regex' => null],
            ['icao' => 'CAO', 'name' => 'Air China Cargo', 'freight_regex' => null],
            ['icao' => 'BDA', 'name' => 'Blue Dart Aviation', 'freight_regex' => null], 

            // =========================
            // Africa
            // =========================
            ['icao' => 'AJK', 'name' => 'Allied Air', 'freight_regex' => null],
            ['icao' => 'ASL', 'name' => 'Astral Aviation', 'freight_regex' => null],
        ];

        // To NEVER seed mixed carriers without regex, uncomment this filter:
        // $airlines = array_values(array_filter($airlines, fn ($a) => $a['freight_regex'] !== null || str_contains(strtolower($a['name'] ?? ''), 'cargo') || str_contains(strtolower($a['name'] ?? ''), 'freight')));

        $rows = array_map(function (array $a) use ($now) {
            $regex = $a['freight_regex'];

            return [
                'icao' => strtoupper($a['icao']),
                'name' => $a['name'] ?? null,
                'freight_regex' => is_array($regex) ? json_encode(array_values($regex)) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $airlines);

        Airline::upsert(
            $rows,
            ['icao'],
            ['name', 'freight_regex', 'updated_at']
        );
    }
}
