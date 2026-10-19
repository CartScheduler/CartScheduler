<?php

namespace App\Casts;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Collection;

class SetAsEnumCollectionCast implements Castable
{
    /**
     * Get the caster class to use when casting from / to this cast target.
     *
     * @template TEnum of \UnitEnum|\BackedEnum
     *
     * @param  array{class-string<TEnum>}  $arguments
     * @return CastsAttributes<Collection<array-key, TEnum>, iterable<TEnum>>
     */
    public static function castUsing(array $arguments = []): CastsAttributes
    {
        return new class($arguments) implements CastsAttributes
        {
            public function __construct(protected array $arguments) {}

            public function get($model, $key, $value, $attributes): ?Collection
            {
                if (! isset($attributes[$key])) {
                    return null;
                }

                $data = explode(',', (string) $attributes[$key]);

                if (! is_array($data)) {
                    return null;
                }

                $enumClass = $this->arguments[0];

                return (new Collection($data))->map(fn ($value) => is_subclass_of($enumClass, BackedEnum::class)
                    ? $enumClass::from($value)
                    : constant($enumClass.'::'.$value));
            }

            public function set($model, $key, $value, $attributes): array
            {
                $value = $value !== null
                    ? (new Collection($value))->implode(fn ($enum) => $this->getStorableEnumValue($enum), ',')
                    : null;

                return [$key => $value];
            }

            protected function getStorableEnumValue($enum)
            {
                if (is_string($enum) || is_int($enum)) {
                    return $enum;
                }

                return $enum instanceof BackedEnum ? $enum->value : $enum->name;
            }
        };
    }
}
