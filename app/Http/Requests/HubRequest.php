<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\Access\AccessService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Base form request: authorization runs before validation, and records the
 * user cannot read produce 404 so their existence is not revealed.
 */
abstract class HubRequest extends FormRequest
{
    protected function hubUser(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }

    protected function access(): AccessService
    {
        return app(AccessService::class);
    }

    protected function canUpdate(Model $model, string $ability = 'update'): bool
    {
        abort_unless($this->access()->canRead($this->hubUser(), $model), 404);

        return Gate::allows($ability, $model);
    }
}
