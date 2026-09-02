<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFantaRosaTable extends Migration
{
    public function up()
    {
        Schema::create('fanta_rosa', function (Blueprint $table) {
            $table->id();
            // external_id del giocatore (match con fanta_listone.external_id)
            $table->bigInteger('external_id')->unique();

            // ruoli multipli possibili separati da ';' (es. "W;T")
            $table->string('ruolo_esteso', 50);

            $table->string('nome', 100);
            $table->string('squadra', 100);

            // costo speso in crediti per l’acquisto
            $table->unsignedInteger('costo')->default(0);

            $table->timestamps();

            // Indici utili per ricerche/filtri
            $table->index('ruolo_esteso');
            $table->index('nome');
            $table->index('squadra');
        });

        // (Opzionale) vincolo di FK su fanta_listone.external_id se vuoi garantire coerenza
        // Attivalo solo se la colonna di destinazione è unique/indicizzata (lo è).
        Schema::table('fanta_rosa', function (Blueprint $table) {
            $table->foreign('external_id')
                  ->references('external_id')->on('fanta_listone')
                  ->onUpdate('cascade')
                  ->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::table('fanta_rosa', function (Blueprint $table) {
            $table->dropForeign(['external_id']);
        });
        Schema::dropIfExists('fanta_rosa');
    }
}
