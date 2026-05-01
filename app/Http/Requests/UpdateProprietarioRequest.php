<?php

namespace App\Http\Requests;

class UpdateProprietarioRequest extends StoreProprietarioRequest
{
    // Mesmas regras e sanitização. O 'unique' do email já considera o user_id atual via route binding.
}
