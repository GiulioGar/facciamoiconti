<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterFinancialBalancesDropUniqueIndex extends Migration
{
    public function up()
    {
        Schema::table('financial_balances', function (Blueprint $table) {
            // 1) Droppa le foreign key che fanno capo al composite index
            $table->dropForeign(['user_id']);
            $table->dropForeign(['family_id']);

            // 2) Droppa il unique index composite
            //    Se il nome standard non funziona, controlla con SHOW INDEX in MySQL
            $table->dropUnique('financial_balances_user_id_family_id_accounting_month_unique');

            // 3) (Opzionale) Ricrea indici separati per performance
            $table->index('user_id');
            $table->index('family_id');

            // 4) Ricrea le foreign key sui singoli campi
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            $table->foreign('family_id')
                  ->references('id')
                  ->on('families')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('financial_balances', function (Blueprint $table) {
            // In rollback, togliamo i nuovi indici e FK singoli
            $table->dropForeign(['user_id']);
            $table->dropForeign(['family_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['family_id']);

            // Ripristiniamo il unique composite
            $table->unique(['user_id','family_id','accounting_month']);

            // E infine le FK originali
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            $table->foreign('family_id')
                  ->references('id')
                  ->on('families')
                  ->onDelete('cascade');
        });
    }
}
