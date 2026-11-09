<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One row of the registrar's masterlist. */
class EnrolledStudent extends Model
{
    protected $table = 'enrolled_students';
    protected $primaryKey = 'sr_code';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];
}
