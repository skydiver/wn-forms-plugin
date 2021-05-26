<?php

namespace Martin\Forms\Updates;

use Winter\Storm\Support\Facades\Schema;
use Winter\Storm\Database\Updates\Migration;

class AddGroupField extends Migration
{
    public function up()
    {
        Schema::table('martin_forms_records', function ($table) {
            $table->string('group')->default('(Empty)')->after('id');
        });
    }

    public function down()
    {
        if (Schema::hasColumn('martin_forms_records', 'group')) {
            Schema::table('martin_forms_records', function ($table) {
                $table->dropColumn('group');
            });
        }
    }
}
