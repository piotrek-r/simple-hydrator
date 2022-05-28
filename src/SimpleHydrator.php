<?php

declare(strict_types=1);

namespace PiotrekR\SimpleHydrator;

use DateTimeImmutable;
use ReflectionException;
use ReflectionObject;
use ReflectionProperty;

final class SimpleHydrator
{
    private array $reflections = [];

    public function set(
        object $model,
        string $name,
        array $data,
        string $key,
        Type $type,
        bool $isRequired = true,
        string|array|callable $param = null,
    ): SimpleHydrator {
        if (!array_key_exists($key, $data)) {
            if ($isRequired) {
                throw new SimpleHydratorException('Required field ' . $key . ' not found');
            }
            return $this;
        }

        $value = $data[$key] !== null ? $this->cast($data[$key], $type, $param) : null;

        try {
            $this->reflectProperty($model, $name)->setValue($model, $value);
        } catch (ReflectionException $e) {
            throw new SimpleHydratorException(
                sprintf('Property "%s" not found in "%s"', $name, get_debug_type($model)),
                0,
                $e,
            );
        }

        return $this;
    }

    private function reflectProperty(object $model, string $name): ReflectionProperty
    {
        $objectId = spl_object_id($model);
        if (array_key_exists($objectId, $this->reflections)) {
            $refObject = $this->reflections[$objectId]['object'];
        } else {
            $this->reflections[$objectId] = [
                'object' => ($refObject = new ReflectionObject($model)),
                'props' => [],
            ];
        }

        if (array_key_exists($name, $this->reflections[$objectId]['props'])) {
            $refProperty = $this->reflections[$objectId]['props'][$name];
        } else {
            $refProperty = $this->reflections[$objectId]['props'][$name] = $refObject->getProperty($name);
        }

        return $refProperty;
    }

    private function cast(mixed $value, Type $type, string|array|callable $param = null): mixed
    {
        return match ($type) {
            Type::BOOL => (bool)$value,
            Type::CALLBACK => $this->castWithCallback($value, $param),
            Type::DATETIME => $this->castDatetime($value),
            Type::ENUM => $this->castEnum($value, $param),
            Type::FLOAT => (float)$value,
            Type::INTEGER => (int)$value,
            Type::JSON => $this->castJson($value),
            Type::RAW => $value,
            Type::STRING => (string)$value,
        };
    }

    public function castDatetime(mixed $value): DateTimeImmutable
    {
        if (is_numeric($value)) {
            return (new DateTimeImmutable())->setTimestamp((int)$value);
        }

        return new DateTimeImmutable($value);
    }

    public function castEnum(mixed $value, string|array|callable|null $param = null): mixed
    {
        if (!is_string($param)) {
            throw new SimpleHydratorException(
                sprintf('$param must be string in %s, it was %s', __METHOD__, get_debug_type($param)),
            );
        }

        if (!enum_exists($param)) {
            throw new SimpleHydratorException(
                sprintf('$param must be an existing enum class, it was "%s"', $param),
            );
        }

        return call_user_func([$param, 'from'], $value);
    }

    public function castJson(string $value): mixed
    {
        $result = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new SimpleHydratorException('JSON error: ' . json_last_error_msg(), json_last_error());
        }

        return $result;
    }

    private function castWithCallback(mixed $value, callable|array|string|null $param): mixed
    {
        if (!is_callable($param)) {
            throw new SimpleHydratorException(
                sprintf('$param must be callable in %s, it was %s', __METHOD__, get_debug_type($param)),
            );
        }

        return $param($value);
    }
}
