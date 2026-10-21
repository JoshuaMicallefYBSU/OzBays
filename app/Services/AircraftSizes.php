<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * Aircraft size groups from public/config/aircraft.json, and the rule for whether a bay can take an aircraft.
 * Shared by BayAllocation and the map so they always agree on what fits.
 */
class AircraftSizes
{
    // Type used for aircraft missing from aircraft.json - same fallback BayAllocation uses
    public const UNKNOWN_TYPE_FALLBACK = 'B738';

    // Map icon sizes - smallest size group to largest, and ignored types (helicopters)
    private const ICON_SCALE_MIN = 0.65;
    private const ICON_SCALE_MAX = 1.35;
    private const ICON_SCALE_IGNORED = 0.75;

    private ?array $raw = null;

    private ?array $groups = null;

    // aircraft.json group key for each entry in groups() (e.g. "GA", "3", "10")
    private array $groupKeys = [];

    // Size groups, smallest first (metadata, FreightOnly and Ignored lists are skipped)
    public function groups(): array
    {
        if ($this->groups !== null) {
            return $this->groups;
        }

        $this->groups = [];
        $this->groupKeys = [];
        foreach ($this->raw() as $key => $types) {
            if (str_starts_with($key, 'Allocation') || in_array($key, ['FreightOnly', 'Ignored'], true) || ! is_array($types)) {
                continue;
            }

            $this->groups[] = array_values(array_unique($types));
            $this->groupKeys[] = (string) $key;
        }

        return $this->groups;
    }

    public function isIgnored(?string $aircraft): bool
    {
        $ignored = $this->raw()['Ignored'] ?? [];

        return is_array($ignored) && in_array(strtoupper((string) $aircraft), array_map('strtoupper', $ignored), true);
    }

    /**
     * Map icon for an aircraft type, from its group's "AllocationIcon_<group>" in aircraft.json.
     * Unknown types use the B738 group (as BayAllocation does) and ignored types the "Ignored" icon.
     * icon is null when the group has no icon file in public/img/aircraft-icon, so the map falls back to its arrow.
     */
    public function icon(?string $aircraft): array
    {
        if ($this->isIgnored($aircraft)) {
            return ['icon' => $this->iconFile('Ignored'), 'ignored' => true, 'scale' => self::ICON_SCALE_IGNORED];
        }

        $groups = $this->groups();
        $index = $this->groupIndex(strtoupper((string) $aircraft)) ?? $this->groupIndex(self::UNKNOWN_TYPE_FALLBACK) ?? 0;
        $position = count($groups) > 1 ? $index / (count($groups) - 1) : 0;

        return [
            'icon'    => $this->iconFile($this->groupKeys[$index] ?? ''),
            'ignored' => false,
            'scale'   => round(self::ICON_SCALE_MIN + (self::ICON_SCALE_MAX - self::ICON_SCALE_MIN) * $position, 2),
        ];
    }

    // Every icon the map can use (one per group with an icon file) - preloaded so aircraft never wait on them
    public function mapIcons(): array
    {
        $icons = [];
        foreach (array_keys($this->raw()) as $key) {
            if (str_starts_with($key, 'AllocationIcon_') && ($icon = $this->iconFile(substr($key, strlen('AllocationIcon_')))) !== null) {
                $icons[] = $icon;
            }
        }

        return array_values(array_unique($icons));
    }

    private function iconFile(string $groupKey): ?string
    {
        $icon = strtoupper((string) ($this->raw()['AllocationIcon_'.$groupKey] ?? ''));

        return $icon !== '' && preg_match('/^[A-Z0-9]+$/', $icon) && File::exists(public_path("img/aircraft-icon/{$icon}.svg"))
            ? $icon
            : null;
    }

    private function raw(): array
    {
        if ($this->raw === null) {
            $raw = json_decode(File::get(public_path('config/aircraft.json')), true);
            $this->raw = is_array($raw) ? $raw : [];
        }

        return $this->raw;
    }

    // Every type in the aircraft's own size group and larger - the bays it can use list one of these
    public function allowedTypes(?string $aircraft): array
    {
        $groups = $this->groups();
        $index = $this->groupIndex(strtoupper((string) $aircraft)) ?? $this->groupIndex(self::UNKNOWN_TYPE_FALLBACK) ?? 0;

        return array_values(array_unique(array_merge(...array_slice($groups, $index) ?: [[]])));
    }

    public function bayFitsAircraft(?string $bayAircraft, ?string $aircraft): bool
    {
        return self::bayFits($bayAircraft, $this->allowedTypes($aircraft));
    }

    // A bay fits when one of its listed aircraft types (e.g. "A320/B738") is in the allowed types
    public static function bayFits(?string $bayAircraft, array $allowedTypes): bool
    {
        if (empty($bayAircraft)) {
            return false;
        }

        $bayTypes = array_map(fn ($type) => strtoupper(trim($type)), explode('/', $bayAircraft));

        return count(array_intersect($bayTypes, $allowedTypes)) > 0;
    }

    private function groupIndex(string $type): ?int
    {
        foreach ($this->groups() as $index => $types) {
            if (in_array($type, $types, true)) {
                return $index;
            }
        }

        return null;
    }
}
