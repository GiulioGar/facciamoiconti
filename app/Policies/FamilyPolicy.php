<?php

namespace App\Policies;

use App\Models\Family;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FamilyPolicy
{
    use HandlesAuthorization;

    // ... i metodi viewAny, view, create, update, delete, ecc.

    /**
     * Determine whether the user can manage this family
     * (cioè accettare o rifiutare richieste di membership).
     *
     * @param  \App\Models\User   $user
     * @param  \App\Models\Family $family
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function manage(User $user, Family $family)
    {
        // solo chi ha creato (owner_id) può gestire le richieste
        return $user->id === $family->owner_id;
    }
}
