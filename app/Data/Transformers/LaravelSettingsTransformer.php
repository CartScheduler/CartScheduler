<?php

declare(strict_types=1);

namespace App\Data\Transformers;

use Illuminate\Support\Arr;
use ReflectionClass;
use ReflectionProperty;
use Spatie\LaravelSettings\Settings;
use Spatie\TypeScriptTransformer\Structures\MissingSymbolsCollection;
use Spatie\TypeScriptTransformer\Structures\TransformedType;
use Spatie\TypeScriptTransformer\Transformers\Transformer;
use Spatie\TypeScriptTransformer\Transformers\TransformsTypes;

/**
 * @codeCoverageIgnore
 */
class LaravelSettingsTransformer implements Transformer
{
    use TransformsTypes;

    public function transform(ReflectionClass $class, string $name): ?TransformedType
    {
        if (!$class->isSubclassOf(Settings::class)) {
            return null;
        }

        $missingSymbols = new MissingSymbolsCollection();

        $properties = array_map(
            fn(ReflectionProperty $reflection) => $reflection->name .
                ": " .
                $this->reflectionToTypeScript($reflection, $missingSymbols) .
                ";\n",
            Arr::where(
                $class->getProperties(),
                static fn(ReflectionProperty $property) => $property->isPublic() && !$property->isStatic()
            )
        );

        return TransformedType::create(
            $class,
            $name,
            '{' . implode($properties) . '}',
            $missingSymbols
        );
    }
}
