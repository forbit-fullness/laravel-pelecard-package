<?php

namespace Yousefkadah\Pelecard\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Yousefkadah\Pelecard\Billable;

class Tenant extends Model
{
    use Billable;

    protected $table = 'tenants';

    protected $guarded = [];

    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];
}
