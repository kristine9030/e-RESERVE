<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Only real reference data: the registrar masterlist. There are no sample
     * students or documents. Create the first staff account with
     * `php artisan ereserve:make-admin <email>`.
     */
    public function run(): void
    {
        $this->call(EnrolledStudentSeeder::class);
    }
}