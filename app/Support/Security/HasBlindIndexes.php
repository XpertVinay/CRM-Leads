<?php

namespace App\Support\Security;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait HasBlindIndexes
{
    public static function bootHasBlindIndexes(): void
    {
        static::saving(function (Model $model): void {
            $model->syncBlindIndexes();
        });
    }

    public function syncBlindIndexes(): void
    {
        /** @var array<string, array{column: string, type?: string}> $map */
        $map = $this->blindIndexMap();
        $encrypter = DataEncrypter::instance();

        foreach ($map as $attribute => $config) {
            $hashColumn = $config['column'];
            $type = $config['type'] ?? 'default';

            $plain = $this->getAttribute($attribute);
            $this->setAttribute(
                $hashColumn,
                is_string($plain) ? $encrypter->blindIndex($plain, $type) : null,
            );
        }
    }

    /**
     * @return array<string, array{column: string, type?: string}>
     */
    abstract protected function blindIndexMap(): array;

    public function scopeWhereBlindIndex(Builder $query, string $hashColumn, ?string $plainValue, string $type = 'default'): Builder
    {
        $hash = DataEncrypter::instance()->blindIndex($plainValue, $type);
        if ($hash === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($hashColumn, $hash);
    }
}
