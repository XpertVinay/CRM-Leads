<?php

namespace Tests\Support;

use App\Modules\CustomFields\Traits\HasCustomFields;
use App\Modules\Tenancy\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

abstract class TestCrmEntityStub extends Model
{
    use BelongsToOrganization, HasCustomFields;

    protected $table = 'test_crm_entities';

    protected $fillable = [
        'organization_id',
        'entity_type',
        'name',
        'custom_fields',
    ];
}
