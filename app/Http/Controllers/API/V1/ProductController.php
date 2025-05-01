<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Jobs\ProcessProductMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use App\Http\Resources\V1\ProductResource;
use App\Http\Resources\V1\ProductCollection;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Product::query();

        // If search is applied, filter based on the title using FULLTEXT search
        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->whereRaw("MATCH(title) AGAINST (? IN NATURAL LANGUAGE MODE)", [$searchTerm]);
        }

        // Check if we need to eager load 'user' based on frontend requirement
        $withUser = $request->has('include_user') && $request->input('include_user') == 'true';

        // If we need to include the 'user' relationship, eager load it
        if ($withUser) {
            $query->with('user');
        }

        // Pagination setup
        $page = $request->get('page', 1);
        $searchKey = $request->input('search', '');

        // Use cache to prevent repeating the same query for the same page and search term
        $products = Cache::remember("products_page_{$page}_{$searchKey}_user_{$withUser}", 600, function () use ($query) {
            return $query->select('id', 'title', 'price', 'image_path', 'video_path')->simplePaginate(11);
        });

        // If 'user' was not eagerly loaded, load it dynamically after pagination
        if (!$withUser) {
            $products->loadMissing('user');
        }

        return new ProductCollection($products);
    }






    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // Validate the incoming request manually
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'price' => 'required|numeric|min:0',
                'image' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
                'video' => 'nullable|file|mimes:mp4,avi,mov,webm|max:10240',
                'image_path' => 'nullable|string',
                'video_path' => 'nullable|string',
            ]);

            // Begin database transaction
            DB::beginTransaction();

            // Create the product entry in the database
            $product = Product::create([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'price' => $validated['price'],
                'image_path' => $validated['image_path'] ?? null,
                'video_path' => $validated['video_path'] ?? null,
            ]);

            // Process image/video files asynchronously using a background job
            if ($request->hasFile('image') || $request->hasFile('video')) {
                ProcessProductMedia::dispatch(
                    $product,
                    $request->file('image'),
                    $request->file('video')
                );
            }

            // Commit the transaction
            DB::commit();

            // Cache the product for repeated access
            Cache::put('product_' . $product->id, $product, now()->addMinutes(10));

            // Return the created product as a response with a 201 status
            return (new ProductResource($product))
                ->response()
                ->setStatusCode(201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product creation failed: ' . $e->getMessage());
            return response()->json(['error' => 'Product creation failed, please try again later.'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        $cacheKey = "product_show_{$product->id}";

        $product = Cache::remember($cacheKey, 60, function () use ($product) {
            return $product->load(['user']);
        });

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        try {
            // Validate the incoming request manually
            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'description' => 'sometimes|required|string',
                'price' => 'sometimes|required|numeric|min:0',
                'image' => 'nullable|file|mimes:jpg,jpeg,png|max:2048', // 2MB max
                'video' => 'nullable|file|mimes:mp4,avi,mov,webm|max:10240', // 10MB max
                'image_path' => 'nullable|string',
                'video_path' => 'nullable|string',
            ]);

            // Begin database transaction
            DB::beginTransaction();

            // Update fields conditionally (only if present)
            $product->update([
                'title' => $validated['title'] ?? $product->title,
                'description' => $validated['description'] ?? $product->description,
                'price' => $validated['price'] ?? $product->price,
                'image_path' => $validated['image_path'] ?? $product->image_path,
                'video_path' => $validated['video_path'] ?? $product->video_path,
            ]);

            // Dispatch background job for media processing (image/video)
            if ($request->hasFile('image') || $request->hasFile('video')) {
                ProcessProductMedia::dispatch(
                    $product,
                    $request->hasFile('image') ? $request->file('image') : null,
                    $request->hasFile('video') ? $request->file('video') : null
                );
            }

            // Commit the transaction
            DB::commit();

            // Cache the updated product
            $product->refresh();
            Cache::put('product_' . $product->id, $product, now()->addMinutes(10));

            return (new ProductResource($product))
                ->response()
                ->setStatusCode(200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product update failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to update product. Please try again later.'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        try {
            // Optionally delete any cached version
            Cache::forget('product_' . $product->id);

            // Optionally delete related files asynchronously (image/video)
            // You could dispatch a job to remove from storage (optional)
            // Delete the product
            $product->delete();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            Log::error('Product deletion failed: ' . $e->getMessage());

            return response()->json([
                'error' => 'Failed to delete product. Please try again later.'
            ], 500);
        }
    }
}
