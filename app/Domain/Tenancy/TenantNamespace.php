<?php

namespace App\Domain\Tenancy;

final class TenantNamespace
{
    public function cacheKey(TenantContext $context, string $resource, string $identifier): string
    {
        return implode(':', [
            'tenant',
            $context->merchantPublicId,
            'store',
            $context->storePublicId,
            $this->segment($resource),
            $this->segment($identifier),
        ]);
    }

    public function objectPath(TenantContext $context, string $category, string $filename): string
    {
        return implode('/', [
            'merchants',
            $context->merchantPublicId,
            'stores',
            $context->storePublicId,
            $this->segment($category),
            $this->segment($filename),
        ]);
    }

    private function segment(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9._-]/', '_', $value) ?? '';
    }
}
