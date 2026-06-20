<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateWalletMovementsTable extends Migration
{
    public function up()
    {
        Schema::create('wallet_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->enum('account', ['bank', 'cash']);
            $table->decimal('amount', 12, 2); // positivo = entrata, negativo = uscita
            $table->enum('source_type', ['income', 'expense', 'adjustment']);
            $table->unsignedBigInteger('source_id')->nullable(); // id di income o expense
            $table->date('date');
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'family_id', 'account']);
            $table->index(['user_id', 'family_id', 'date']);
        });

        // Migrazione dati: crea un movimento di apertura dall'ultimo snapshot esistente
        // per ogni coppia user+family, così i saldi storici sono preservati.
        $latestBalances = DB::table('financial_balances as fb1')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('financial_balances as fb2')
                  ->whereRaw('fb2.user_id = fb1.user_id AND fb2.family_id = fb1.family_id AND fb2.id > fb1.id');
            })
            ->select('user_id', 'family_id', 'bank_balance', 'cash', 'accounting_month')
            ->get();

        $now = now();
        $inserts = [];

        foreach ($latestBalances as $fb) {
            foreach (['bank' => 'bank_balance', 'cash' => 'cash'] as $account => $field) {
                $amount = (float) ($fb->$field ?? 0);
                if ($amount != 0) {
                    $inserts[] = [
                        'user_id'     => $fb->user_id,
                        'family_id'   => $fb->family_id,
                        'account'     => $account,
                        'amount'      => $amount,
                        'source_type' => 'adjustment',
                        'source_id'   => null,
                        'date'        => $fb->accounting_month ?? $now->toDateString(),
                        'note'        => 'Saldo iniziale da migrazione',
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }
            }
        }

        if (! empty($inserts)) {
            DB::table('wallet_movements')->insert($inserts);
        }
    }

    public function down()
    {
        Schema::dropIfExists('wallet_movements');
    }
}
