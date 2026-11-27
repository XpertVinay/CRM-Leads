<?php

namespace App\Modules\Tenancy\Auth;

use App\Support\Security\DataEncrypter;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Contracts\Support\Arrayable;

class EncryptedEloquentUserProvider extends EloquentUserProvider
{
    /**
     * Retrieve a user by the given credentials using blind-index lookup for email.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(array $credentials): ?UserContract
    {
        $credentials = array_filter(
            $credentials,
            fn ($key) => ! str_contains($key, 'password'),
            ARRAY_FILTER_USE_KEY,
        );

        if (empty($credentials)) {
            return null;
        }

        $query = $this->newModelQuery();

        foreach ($credentials as $key => $value) {
            if ($key === 'email' && is_string($value)) {
                $hash = DataEncrypter::instance()->blindIndex($value, 'email');
                if ($hash === null) {
                    return null;
                }
                $query->where('email_hash', $hash);

                continue;
            }

            if (is_array($value) || $value instanceof Arrayable) {
                $query->whereIn($key, $value);
            } elseif ($value instanceof \Closure) {
                $value($query);
            } else {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }
}
