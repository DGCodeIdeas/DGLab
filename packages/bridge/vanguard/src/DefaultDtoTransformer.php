<?php

declare(strict_types=1);

namespace SovereignStack\Bridge;

/**
 * A pass-through DTO transformer that strips fields beginning with underscore
 * (`_internal_*`) and any field listed in a static redactKeys array.
 *
 * Useful for depth-2 tests and for contracts that don't need custom transformation.
 *
 * @package SovereignStack\Bridge
 */
final class DefaultDtoTransformer implements DtoTransformerInterface
{
    /**
     * @param array<string> $redactKeys Field keys to strip from the data.
     */
    public function __construct(
        private readonly array $redactKeys = [],
    ) {
    }

    public function transform(mixed $internalData): mixed
    {
        if (!is_array($internalData)) {
            return $internalData;
        }
        return $this->stripInternal($internalData);
    }

    public function transformResponse(mixed $publicResponse): mixed
    {
        if (!is_array($publicResponse)) {
            return $publicResponse;
        }
        return $this->stripInternal($publicResponse);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function stripInternal(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && str_starts_with($key, '_')) {
                continue;
            }
            if (in_array($key, $this->redactKeys, true)) {
                continue;
            }
            $result[$key] = is_array($value) ? $this->stripInternal($value) : $value;
        }
        return $result;
    }
}
