<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\RoleAuthorizer;
use App\Domain\Catalog\CatalogScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductImageRequest;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SecurityAuditEvent;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImageController extends Controller
{
    public function show(Request $request, Merchant $merchant, Store $store, Product $product, ProductImage $image, RoleAuthorizer $authorizer, CatalogScope $scope): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::CatalogView, $store);
        abort_unless($product->merchant_id === $merchant->id && $product->store_id === $scope->storeId($merchant, $store) && $image->merchant_id === $merchant->id && $image->product_id === $product->id, 404);
        $disk = Storage::disk($image->disk);
        abort_unless($disk->exists($image->path), 404);

        return response()->stream(function () use ($disk, $image): void {
            $stream = $disk->readStream($image->path);
            abort_unless(is_resource($stream), 404);
            fpassthru($stream);
            fclose($stream);
        }, 200, ['Content-Type' => $image->mime_type, 'Content-Length' => (string) $image->size_bytes, 'Cache-Control' => 'private, max-age=3600']);
    }

    public function store(StoreProductImageRequest $request, Merchant $merchant, Store $store, Product $product, RoleAuthorizer $authorizer, CatalogScope $scope): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::CatalogManage, $store);
        abort_unless($product->merchant_id === $merchant->id && $product->store_id === $scope->storeId($merchant, $store), 404);
        $file = $request->file('image');
        abort_unless($file !== null && $file->isValid(), 422, 'The image upload is invalid.');
        $extension = $file->guessExtension() ?: 'bin';
        $directory = "merchants/{$merchant->public_id}/catalog/{$product->public_id}";
        $path = $file->storeAs($directory, Str::uuid7().'.'.$extension, 'local');
        abort_unless(is_string($path), 500, 'The image could not be stored.');
        $image = ProductImage::query()->create(['merchant_id' => $merchant->id, 'product_id' => $product->id, 'disk' => 'local', 'path' => $path, 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size_bytes' => $file->getSize(), 'alt_text' => $request->input('alt_text')]);
        SecurityAuditEvent::query()->create(['merchant_id' => $merchant->id, 'store_id' => $store->id, 'actor_user_id' => $user->id, 'event_code' => 'catalog.image.created', 'outcome' => 'succeeded', 'metadata' => ['product_id' => $product->public_id, 'image_id' => $image->public_id, 'mime_type' => $image->mime_type, 'size_bytes' => $image->size_bytes], 'occurred_at' => now()]);

        return response()->json(['data' => ['id' => $image->public_id, 'mime_type' => $image->mime_type, 'size_bytes' => $image->size_bytes, 'alt_text' => $image->alt_text]], 201);
    }

    public function destroy(Request $request, Merchant $merchant, Store $store, Product $product, ProductImage $image, RoleAuthorizer $authorizer, CatalogScope $scope): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $authorizer->authorize($user, $merchant, Permission::CatalogManage, $store);
        abort_unless($product->merchant_id === $merchant->id && $product->store_id === $scope->storeId($merchant, $store) && $image->merchant_id === $merchant->id && $image->product_id === $product->id, 404);
        Storage::disk($image->disk)->delete($image->path);
        $image->delete();

        return response()->json(['message' => 'Product image removed.']);
    }
}
