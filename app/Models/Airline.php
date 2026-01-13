<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Airline extends Model
{
    protected $casts = [
        'freight_regex' => 'array',
    ];

    protected $fillable = [
        'icao',
        'name',
        'freight_regex',
    ];

    public static function isFreightCallsign(string $callsign): bool
    {
        $callsign = trim($callsign);

        if ($callsign === '' || strlen($callsign) < 3) {
            return false;
        }

        $operator = strtoupper(substr($callsign, 0, 3));

        $airline = self::where('icao', $operator)->first();
        if ($airline === null) {
            return false;
        }

        return $airline->matchesFreightCallsign($callsign);
    }

    public function matchesFreightCallsign(string $callsign): bool
    {
        // Assumes that if a airline has no freight_regex, all flights are freight
        $raw = $this->freight_regex;
        if ($raw === null) {
            return true;
        }

        $patterns = $raw;

        // Backward compatibility: allow a single string pattern (or JSON stored as a string).
        if (is_string($patterns)) {
            $patterns = trim($patterns);

            if ($patterns === '') {
                return true;
            }

            $decoded = json_decode($patterns, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $patterns = $decoded;
            } else {
                $patterns = [$patterns];
            }
        }

        if (!is_array($patterns)) {
            $patterns = [$patterns];
        }

        if (empty($patterns)) {
            return true;
        }

        foreach ($patterns as $regex) {
            if (!is_string($regex)) {
                continue;
            }

            $regex = trim($regex);
            if ($regex === '') {
                continue;
            }

            $pattern = $this->normaliseRegexPattern($regex);

            try {
                if (preg_match($pattern, $callsign) === 1) {
                    return true;
                }
            } catch (\Throwable $e) {
                Log::warning("Invalid freight_regex for airline {$this->icao} ({$regex}): {$e->getMessage()}");
                continue;
            }
        }

        return false;
    }

    private function normaliseRegexPattern(string $regex): string
    {
        $regex = trim($regex);
        $first = $regex[0] ?? '';

        // If it looks like a delimited pattern already (e.g. /.../i or ~...~), keep it.
        if ($first !== '' && !ctype_alnum($first) && preg_match('/^' . preg_quote($first, '/') . '.*' . preg_quote($first, '/') . '[a-zA-Z]*$/s', $regex)) {
            return $regex;
        }

        return '~' . str_replace('~', '\\~', $regex) . '~i';
    }

    protected static function booted()
    {
        static::saving(function (self $airline) {
            if ($airline->icao !== null) {
                $airline->icao = strtoupper($airline->icao);
            }
        });
    }
}
