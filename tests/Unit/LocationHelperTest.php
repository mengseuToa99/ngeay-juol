<?php

namespace Tests\Unit;

use App\Support\LocationHelper;
use Tests\TestCase;

class LocationHelperTest extends TestCase
{
    public function test_get_provinces()
    {
        $provinces = LocationHelper::getProvinces();
        $this->assertNotEmpty($provinces);
        $this->assertArrayHasKey('Phnom Penh Capital', $provinces);
        $this->assertEquals('Phnom Penh Capital (រាជធានីភ្នំពេញ)', $provinces['Phnom Penh Capital']);
    }

    public function test_get_districts()
    {
        $districts = LocationHelper::getDistricts('Banteay Meanchey');
        $this->assertNotEmpty($districts);
        $this->assertArrayHasKey('Mongkol Borei', $districts);
        $this->assertEquals('Mongkol Borei (មង្គលបូរី)', $districts['Mongkol Borei']);
    }

    public function test_get_communes()
    {
        $communes = LocationHelper::getCommunes('Banteay Meanchey', 'Mongkol Borei');
        $this->assertNotEmpty($communes);
        $this->assertArrayHasKey('Banteay Neang', $communes);
        $this->assertEquals('Banteay Neang (បន្ទាយនាង)', $communes['Banteay Neang']);
    }

    public function test_get_villages()
    {
        $villages = LocationHelper::getVillages('Banteay Meanchey', 'Mongkol Borei', 'Banteay Neang');
        $this->assertNotEmpty($villages);
        $this->assertArrayHasKey('Ou Thum', $villages);
        $this->assertEquals('Ou Thum (អូរធំ)', $villages['Ou Thum']);
    }

    public function test_get_districts_invalid_province()
    {
        $this->assertEmpty(LocationHelper::getDistricts(null));
        $this->assertEmpty(LocationHelper::getDistricts('NonExistentProvince'));
    }

    public function test_get_communes_invalid()
    {
        $this->assertEmpty(LocationHelper::getCommunes(null, null));
        $this->assertEmpty(LocationHelper::getCommunes('Banteay Meanchey', 'NonExistentDistrict'));
    }

    public function test_get_villages_invalid()
    {
        $this->assertEmpty(LocationHelper::getVillages(null, null, null));
        $this->assertEmpty(LocationHelper::getVillages('Banteay Meanchey', 'Mongkol Borei', 'NonExistentCommune'));
    }
}
