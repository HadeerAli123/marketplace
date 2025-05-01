<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\Product;
use App\Models\Category;
use App\Models\SpotMode;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductImageResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\UserResource;

class ProductController extends Controller
{


    public function index()
    {
        $products = Product::all();
        $isSpotModeActive = SpotMode::isActive();

        $productsData = $products->map(function ($product) use ($isSpotModeActive) {
            return [
                'id' => $product->id,
                'product_name' => $product->product_name,
                'stock' => $product->stock,
                'cover_image' => asset('uploads/products/' . $product->cover_image),
                'price' => $isSpotModeActive ? $product->price : $product->regular_price,
            ];
        });

        return response()->json([
            'status' => 'success',
            'products' => $productsData,
        ], 200);
    }



    
    public function getProductsByCategory($categoryId, Request $request)
   
    {
        try {
            $category = Category::find($categoryId);
            if (!$category) {
                return response()->json(['error' => 'Category not found'], 404);
            }

            $isSpotModeActive = SpotMode::isActive();

            $products = Product::where('category_id', $categoryId)
                ->whereNull('deleted_at')
                ->with(['images:id,product_id,image,created_at,updated_at'])
                ->select(['id', 'product_name', 'price', 'regular_price', 'stock', 'description', 'cover_image'])
                ->paginate(10);

            $productsData = $products->map(function ($product) use ($isSpotModeActive) {
                $price = $isSpotModeActive ? $product->price : $product->regular_price;
                return [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'price' => $price,
                    'stock' => $product->stock,
                    'description' => $product->description,
                    'cover_image' => asset('uploads/products/' . $product->cover_image),
                    'images' => ProductImageResource::collection($product->images),
                ];
            });

            return response()->json([
                'message' => 'Products retrieved successfully',
                'data' => $productsData,
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function productDetails($id)
    {
        try {
            $product = Product::with(['category', 'images'])->find($id);

            if (!$product) {
                return response()->json(['error' => 'Product not found'], 404);
            }

            $isSpotModeActive = SpotMode::isActive();
            $price = $isSpotModeActive ? $product->price : $product->regular_price;

            return response()->json([
                'status' => 'success',
                'product' => [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'price' => $price,
                    'description' => $product->description,
                    'cover_image' => asset('uploads/products/' . $product->cover_image),
                    'category' => new CategoryResource($product->category),
                    'images' => ProductImageResource::collection($product->images),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_name' => 'required|string|max:255',
                'cover_image' => 'required|file|image|mimes:jpeg,png,jpg,gif,webp,avif|max:2048',
                'price' => 'required|numeric|min:0',
                'regular_price' => 'required|numeric|min:0',
                'description' => 'nullable|string',
                'images' => 'nullable|array',
                'images.*' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp,avif|max:2048',
                'stock' => 'required|integer|min:0',
                'category_id' => 'required|integer|exists:categories,id',
            ], [
                'product_name.required' => 'Product name is required.',
                'product_name.string' => 'Product name must be a string.',
                'product_name.max' => 'Product name must not exceed 255 characters.',
                'cover_image.required' => 'Cover image is required.',
                'cover_image.image' => 'Cover image must be a valid image.',
                'price.required' => 'Spot Mode price is required.',
                'price.numeric' => 'Spot Mode price must be a number.',
                'regular_price.required' => 'Regular price is required.',
                'regular_price.numeric' => 'Regular price must be a number.',
                'stock.required' => 'Stock is required.',
                'stock.integer' => 'Stock must be an integer.',
                'category_id.required' => 'Category ID is required.',
                'category_id.exists' => 'The selected category ID is invalid.',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $data = $validator->validated();

            $existingProduct = Product::where('product_name', $data['product_name'])->first();
            if ($existingProduct) {
                return response()->json(['error' => 'A product with this name already exists.'], 400);
            }

            $category = Category::find($data['category_id']);
            if (!$category) {
                return response()->json(['error' => 'Invalid category ID.'], 400);
            }

            $image_path = '';
            if ($request->hasFile('cover_image')) {
                $image_path = $request->file('cover_image')->store('cover_images', 'products');
            }

            $product = Product::create([
                'product_name' => $data['product_name'],
                'price' => $data['price'],
                'regular_price' => $data['regular_price'],
                'description' => $data['description'] ?? null,
                'stock' => $data['stock'],
                'cover_image' => $image_path,
                'user_id' => Auth::id(),
                'category_id' => $data['category_id'],
            ]);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('images', 'products');
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image' => $path,
                    ]);
                }
            }

            $product->load('images');

            return response()->json([
                'message' => 'Product created successfully.',
                'product' => new ProductResource($product),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_name' => 'nullable|string|max:255',
                'cover_image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp,avif|max:2048',
                'price' => 'nullable|numeric|min:0',
                'regular_price' => 'nullable|numeric|min:0',
                'description' => 'nullable|string',
                'images' => 'nullable|array',
                'images.*' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp,avif|max:2048',
                'stock' => 'nullable|integer|min:0',
                'category_id' => 'nullable|integer|exists:categories,id',
            ], [
                'product_name.string' => 'The product name must be a string.',
                'product_name.max' => 'The product name must not exceed 255 characters.',
                'price.numeric' => 'The spot mode price must be a number.',
                'regular_price.numeric' => 'The regular price must be a number.',
                'stock.integer' => 'Stock must be an integer.',
                'category_id.exists' => 'The selected category ID does not exist.',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $data = $validator->validated();

            $product = Product::find($id);
            if (!$product) {
                return response()->json(['error' => 'Product not found.'], 404);
            }

            if ($product->user_id != Auth::id()) {
                return response()->json(['error' => 'Unauthorized: You can only update your own products.'], 403);
            }

            if (isset($data['category_id'])) {
                $category = Category::find($data['category_id']);
                if (!$category) {
                    return response()->json(['error' => 'Invalid category ID.'], 404);
                }
                $product->category_id = $data['category_id'];
            }

            if (isset($data['product_name'])) {
                $product->product_name = $data['product_name'];
            }
            if (isset($data['price'])) {
                $product->price = $data['price'];
            }
            if (isset($data['regular_price'])) {
                $product->regular_price = $data['regular_price'];
            }
            if (isset($data['description'])) {
                $product->description = $data['description'];
            }
            if (isset($data['stock'])) {
                $product->stock = $data['stock'];
            }

            if ($request->hasFile('cover_image')) {
                if ($product->cover_image && Storage::disk('products')->exists($product->cover_image)) {
                    Storage::disk('products')->delete($product->cover_image);
                }
                $path = $request->file('cover_image')->store('cover_images', 'products');
                $product->cover_image = $path;
            }

            if ($request->hasFile('images')) {
                $existingImages = ProductImage::where('product_id', $id)->get();
                foreach ($existingImages as $image) {
                    if (Storage::disk('products')->exists($image->image)) {
                        Storage::disk('products')->delete($image->image);
                    }
                    $image->delete();
                }

                foreach ($request->file('images') as $image) {
                    $path = $image->store('images', 'products');
                    ProductImage::create([
                        'product_id' => $id,
                        'image' => $path,
                    ]);
                }
            }

            $product->save();

            return response()->json([
                'message' => 'Product updated successfully.',
                'data' => [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'price' => $product->price,
                    'regular_price' => $product->regular_price,
                    'description' => $product->description,
                    'stock' => $product->stock,
                    'cover_image' => asset('uploads/products/' . $product->cover_image),
                    'category' => new CategoryResource($product->category),
                    'images' => ProductImageResource::collection($product->images),
                ],
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Failed to update product: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to update product: ' . $e->getMessage()], 500);
        }
    }
    public function destroy(Product $product)
    {
        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }
        $product->delete();
        return response()->json(['message' => 'Product soft deleted successfully.']);
    }
}

  // public function show(Product $product)
    // {
    //     try {
    //         $isSpotModeActive = SpotMode::isActive();
    //         $price = $isSpotModeActive ? $product->price : 'Price to be confirmed later';
    
    //         $product->load('category', 'user');
    
    //         return response()->json([
    //             'message' => 'Product retrieved successfully',
    //             'data' => [
    //                 'id' => $product->id,
    //                 'product_name' => $product->product_name,
    //                 'price' => $price,
    //                 'stock' => $product->stock,
    //                 'description' => $product->description,
    //                 'cover_image' => asset('uploads/products/' . $product->cover_image), // URL كامل
    //                 'category' => new CategoryResource($product->category),
    //                 'user' => $product->user ? $product->user->first_name . ' ' . $product->user->last_name : null,
    //                 'images' => ProductImageResource::collection($product->images),
    //             ],
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => $e->getMessage()], 500);
    //     }
    // }


       
        
    // public function restore($product_id)
    // {
    //     $product = Product::withTrashed()->find($product_id);
    //     if (!$product) {
    //         return response()->json(['message' => 'Product not found.'], 404);
    //     }
    //     $product->restore();
    //     return response()->json(['message' => 'done successfully.'], 200);
    // }

    // public function getAlldeleted()
    // {
    //     $products = Product::onlyTrashed()->with(['category', 'user', 'images'])->get();
    
    //     $productsData = $products->map(function ($product) {
    //         return [
    //             'id' => $product->id,
    //             'product_name' => $product->product_name,
    //             'price' => $product->price, 
    //             'description' => $product->description,
    //             'stock' => $product->stock,
    //             'cover_image' => asset('uploads/products/' . $product->cover_image),
    //             'category' => new CategoryResource($product->category),
    //             'user' => $product->user ? new UserResource($product->user) : null,
    //             'images' => ProductImageResource::collection($product->images),
    //         ];
    //     });
    
    //     return response()->json([
    //         'message' => 'Deleted products retrieved successfully',
    //         'data' => $productsData,
    //     ], 200);
    // }
    // public function forceDestroy($id)
    // {
    //     $product = Product::withTrashed()->find($id);
    
    //     if (!$product) {
    //         return response()->json(['message' => 'Product not found.'], 404);
    //     }
    
    //     $product->forceDelete();
    //     return response()->json(['message' => 'Product hard deleted successfully.']);
    // }

