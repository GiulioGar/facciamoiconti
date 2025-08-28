<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('fanta_listone', function (Blueprint $table) {
            $table->unsignedInteger('like')->default(0);
            $table->unsignedInteger('dislike')->default(0);
            $table->tinyInteger('titolare')->nullable()->comment('1=si, 2=no, 3=rotazione');
            $table->decimal('mv24', 3, 2)->nullable()->comment('media voto 2024');
        });
    }

    public function down()
    {
        Schema::table('fanta_listone', function (Blueprint $table) {
            $table->dropColumn(['like', 'dislike', 'titolare', 'mv24']);
        });
    }
};
