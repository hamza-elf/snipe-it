<?php

namespace Tests\Feature\Api\Assets;

use App\Models\Asset;
use App\Models\CustomField;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class AssetUpdateTest extends TestCase
{
    // TODO - this 'helper' is duplicated in AssetStoreTest - we should extract it out if we can figure out how
    public function markIncompleteIfMySQL()
    {
        if (config('database.default') === 'mysql') {
            $this->markTestIncomplete('Custom Fields tests do not work on MySQL');
        }
    }


    public function testEncryptedCustomFieldCanBeUpdated()
    {
        $this->markIncompleteIfMySQL();
        $field = CustomField::factory()->testEncrypted()->create();
        $asset = Asset::factory()->hasEncryptedCustomField($field)->create();
        $superuser = User::factory()->superuser()->create();

        $this->actingAsForApi($superuser)
            ->patchJson(route('api.assets.update', $asset->id), [
                $field->db_column_name() => 'This is encrypted field'
            ])
            ->assertStatusMessageIs('success')
            ->assertOk();

        $asset->refresh();
        $this->assertEquals('This is encrypted field', Crypt::decrypt($asset->{$field->db_column_name()}));
    }

    public function testPermissionNeededToUpdateEncryptedField()
    {
        $this->markIncompleteIfMySQL();
        $field = CustomField::factory()->testEncrypted()->create();
        $asset = Asset::factory()->hasEncryptedCustomField($field)->create();
        $normal_user = User::factory()->editAssets()->create();

        $asset->{$field->db_column_name()} = Crypt::encrypt("encrypted value should not change");
        $asset->save();

        // test that a 'normal' user *cannot* change the encrypted custom field
        $this->actingAsForApi($normal_user)
            ->patchJson(route('api.assets.update', $asset->id), [
                $field->db_column_name() => 'Some Other Value Entirely!'
            ])
            ->assertStatusMessageIs('success')
            ->assertOk()
            ->assertMessagesAre('Asset updated successfully, but encrypted custom fields were not due to permissions');

        $asset->refresh();
        $this->assertEquals("encrypted value should not change", Crypt::decrypt($asset->{$field->db_column_name()}));
    }
}
