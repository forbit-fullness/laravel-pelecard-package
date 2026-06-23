<?php

namespace Yousefkadah\Pelecard\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Yousefkadah\Pelecard\Billable;

class User extends Model
{
    use Billable;

    protected $table = 'users';

    protected $guarded = [];

    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];
}
