<?php

namespace Tests\Feature\Assets\Ui;

use Tests\Concerns\TestsFullMultipleCompaniesSupport;
use Tests\Concerns\TestsPermissionsRequirement;
use Tests\TestCase;

class DeleteAssetsTest extends TestCase implements TestsFullMultipleCompaniesSupport, TestsPermissionsRequirement
{
    public function testRequiresPermission()
    {
        $this->markTestIncomplete();
    }

    public function testAdheresToFullMultipleCompaniesSupportScoping()
    {
        $this->markTestIncomplete();
    }

    public function testCannotDeleteAssetThatHasAssetsCheckedOutToIt()
    {
        $this->markTestIncomplete();
    }

    public function testCannotDeleteAssetThatHasComponentsCheckedOutToIt()
    {
        $this->markTestIncomplete();
    }

    public function testCannotDeleteAssetThatHasLicensesCheckedOutToIt()
    {
        $this->markTestIncomplete();
    }
}
