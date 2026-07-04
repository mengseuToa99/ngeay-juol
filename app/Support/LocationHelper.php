<?php

namespace App\Support;

class LocationHelper
{
    protected static ?array $data = null;

    protected static function loadData(): array
    {
        if (self::$data === null) {
            $path = base_path('database/cambodia_gazetteer.json');
            if (file_exists($path)) {
                self::$data = json_decode(file_get_contents($path), true) ?? [];
            } else {
                self::$data = [];
            }
        }
        return self::$data;
    }

    public static function getProvinces(): array
    {
        $provinces = [];
        foreach (self::loadData() as $province) {
            $latin = $province['latin'] ?? '';
            $khmer = $province['khmer'] ?? '';
            if ($latin) {
                $provinces[$latin] = $latin . ' (' . $khmer . ')';
            }
        }
        asort($provinces);
        return $provinces;
    }

    public static function getDistricts(?string $provinceLatin): array
    {
        if (empty($provinceLatin)) {
            return [];
        }

        $districts = [];
        foreach (self::loadData() as $province) {
            if (($province['latin'] ?? '') === $provinceLatin) {
                foreach ($province['districts'] ?? [] as $district) {
                    $latin = $district['latin'] ?? '';
                    $khmer = $district['khmer'] ?? '';
                    if ($latin) {
                        $districts[$latin] = $latin . ' (' . $khmer . ')';
                    }
                }
                break;
            }
        }
        asort($districts);
        return $districts;
    }

    public static function getCommunes(?string $provinceLatin, ?string $districtLatin): array
    {
        if (empty($provinceLatin) || empty($districtLatin)) {
            return [];
        }

        $communes = [];
        foreach (self::loadData() as $province) {
            if (($province['latin'] ?? '') === $provinceLatin) {
                foreach ($province['districts'] ?? [] as $district) {
                    if (($district['latin'] ?? '') === $districtLatin) {
                        foreach ($district['communes'] ?? [] as $commune) {
                            $latin = $commune['latin'] ?? '';
                            $khmer = $commune['khmer'] ?? '';
                            if ($latin) {
                                $communes[$latin] = $latin . ' (' . $khmer . ')';
                            }
                        }
                        break 2;
                    }
                }
            }
        }
        asort($communes);
        return $communes;
    }

    public static function getVillages(?string $provinceLatin, ?string $districtLatin, ?string $communeLatin): array
    {
        if (empty($provinceLatin) || empty($districtLatin) || empty($communeLatin)) {
            return [];
        }

        $villages = [];
        foreach (self::loadData() as $province) {
            if (($province['latin'] ?? '') === $provinceLatin) {
                foreach ($province['districts'] ?? [] as $district) {
                    if (($district['latin'] ?? '') === $districtLatin) {
                        foreach ($district['communes'] ?? [] as $commune) {
                            if (($commune['latin'] ?? '') === $communeLatin) {
                                foreach ($commune['villages'] ?? [] as $village) {
                                    $latin = $village['latin'] ?? '';
                                    $khmer = $village['khmer'] ?? '';
                                    if ($latin) {
                                        $villages[$latin] = $latin . ' (' . $khmer . ')';
                                    }
                                }
                                break 3;
                            }
                        }
                    }
                }
            }
        }
        asort($villages);
        return $villages;
    }
}
