<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFantaBudgetStateTable extends Migration
{
    public function up()
    {
        Schema::create('fanta_budget_state', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('anchor_remaining')->default(0);
            $table->json('caps');              // {"P":110,"D":350,"C":700,"A":1240,...}
            $table->json('spent_at_anchor');   // {"P":0,"D":0,"C":0,"A":0}
            $table->json('open_roles');        // ["D","C","A"]
            $table->timestamps();
        });

        // opzionale: riga “singleton” iniziale
        DB::table('fanta_budget_state')->insert([
            'anchor_remaining' => 0,
            'caps'             => json_encode(new stdClass()), // vuoto
            'spent_at_anchor'  => json_encode(new stdClass()),
            'open_roles'       => json_encode([]),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('fanta_budget_state');
    }
}
