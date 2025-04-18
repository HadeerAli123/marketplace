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
            $data = [
                'id' => $product->id,
                'product_name' => $product->product_name,
                'stock' => $product->stock,
                'cover_image' => asset('uploads/products/' . $product->cover_image), 
            ];
    
            if ($isSpotModeActive) {
                $data['price'] = $product->price;
            }
    
            return $data;
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
            ->select(['id', 'product_name', 'price', 'stock', 'description', 'cover_image'])
            ->paginate(10);

        $productsData = $products->map(function ($product) use ($isSpotModeActive) {
            $price = $isSpotModeActive ? $product->price : 'Price to be confirmed later';
            return [
                'id' => $product->id,
                'product_name' => $product->product_name,
                'price' => $price,
                'stock' => $product->stock,
                'description' => $product->description,
                'cover_image' => asset('uploads/products/' . $product->cover_image), // URL كامل
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
        $price = $isSpotModeActive ? $product->price : 'Price to be confirmed later';

        return response()->json([
            'status' => 'success',
            'product' => [
                'id' => $product->id,
                'product_name' => $product->product_name,
                'price' => $price,
                'description' => $product->description,
                'cover_image' => asset('uploads/products/' . $product->cover_image), // URL كامل
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
                'price' => 'required|numeric',
                'description' => 'nullable|string',
                'images' => 'nullable|array',
                'images.*' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp,avif|max:2048',
                'stock' => 'required|integer',
                'category_id' => 'required|integer|exists:categories,id',
            ], [
                'product_name.required' => 'Product name is required.',
                'product_name.string' => 'Product name must be a string.',
                'product_name.max' => 'Product name may not be greater than 255 characters.',
                'product_name.unique' => 'A product with this name already exists.',
                'cover_image.required' => 'Cover image is required.',
                'cover_image.file' => 'Cover image must be a file.',
                'cover_image.image' => 'Cover image must be an image.',
                'cover_image.mimes' => 'Cover image must be of type: jpeg, png, jpg, gif, webp, or avif.',
                'cover_image.max' => 'Cover image may not be greater than 2 MB.',
                'price.required' => 'Price is required.',
                'price.numeric' => 'Price must be a number.',
                'price.min' => 'Price cannot be negative.',
                'description.string' => 'Description must be a string.',
                'images.array' => 'Images must be an array.',
                'images.max' => 'You cannot upload more than 5 images.',
                'images.*.file' => 'Each image must be a file.',
                'images.*.image' => 'Each file must be an image.',
                'images.*.mimes' => 'Each image must be of type: jpeg, png, jpg, gif, webp, or avif.',
                'images.*.max' => 'Each image may not be greater than 2 MB.',
                'stock.required' => 'Stock is required.',
                'stock.integer' => 'Stock must be an integer.',
                'stock.min' => 'Stock cannot be negative.',
                'category_id.required' => 'Category ID is required.',
                'category_id.integer' => 'Category ID must be an integer.',
                'category_id.exists' => 'Category ID does not exist.',
            ]);
    
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }
    
            $data = $validator->validated();
    
            $existingProduct = Product::where('product_name', $data['product_name'])->first();
            if ($existingProduct) {
                return response()->json(['error' => 'A product with this name already exists'], 400);
            }
    
            $category = Category::find($data['category_id']);
            if (!$category) {
                return response()->json(['error' => 'Invalid category ID'], 400);
            }
    
            $image_path = '';
            if ($request->hasFile('cover_image')) {
                $image_path = $request->file('cover_image')->store('cover_images', 'products');
               
            }
    
            $product = new Product();
            $product->product_name = $data['product_name'];
            $product->price = $data['price'];
            $product->description = $data['description'] ?? $product->description;
            $product->stock = $data['stock'];
            $product->cover_image = $image_path; 
            $product->user_id = Auth::id();
            $product->category_id = $data['category_id'];
            $product->save();
    
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('images', 'products');
                    $productImage = new ProductImage();
                    $productImage->product_id = $product->id;
                    $productImage->image = $path; 
                    $productImage->save();
                }
            }
    
            $product->load('images');
    
            return response()->json([
                'message' => 'Product created successfully',
                'product' => $product
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function show(Product $product)
    {
        try {
            $isSpotModeActive = SpotMode::isActive();
            $price = $isSpotModeActive ? $product->price : 'Price to be confirmed later';
    
            $product->load('category', 'user');
    
            return response()->json([
                'message' => 'Product retrieved successfully',
                'data' => [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'price' => $price,
                    'stock' => $product->stock,
                    'description' => $product->description,
                    'cover_image' => asset('uploads/products/' . $product->cover_image), // URL كامل
                    'category' => new CategoryResource($product->category),
                    'user' => $product->user ? $product->user->first_name . ' ' . $product->user->last_name : null,
                    'images' => ProductImageResource::collection($product->images),
                ],
            ], 200);
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
            'price' => 'nullable|numeric',
            'description' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp,avif|max:2048',
            'stock' => 'nullable|integer',
            'category_id' => 'nullable|integer|exists:categories,id',
        ], [
            'product_name.string' => 'Product name must be a string.',
            'product_name.max' => 'Product name may not be greater than 255 characters.',
            'price.numeric' => 'Price must be a number.',
            'description.string' => 'Description must be a string.',
            'images.file' => 'Image must be a file.',
            'images.image' => 'The file must be an image.',
            'images.mimes' => 'Image must be of type: jpeg, png, jpg, or gif.',
            'images.max' => 'Image may not be greater than 2 MB.',
            'stock.integer' => 'Stock must be an integer.',
            'category_id.integer' => 'Category ID must be an integer.',
            'category_id.exists' => 'Category ID does not exist.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $data = $validator->validated();

        $product = Product::find($id);
        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        if ($product->user_id != Auth::id()) {
            return response()->json(['error' => 'Unauthorized: You can only update your own products.'], 403);
        }

        if (isset($data['category_id'])) {
            $category = Category::find($data['category_id']);
            if (!$category) {
                return response()->json(['error' => 'Invalid category ID'], 404);
            }
            $product->category_id = $data['category_id'];
        }

        if (isset($data['product_name'])) {
            $product->product_name = $data['product_name'];
        }
        if (isset($data['price'])) {
            $product->price = $data['price'];
        }
        if (isset($data['description'])) {
            $product->description = $data['description'];
        }
        if (isset($data['stock'])) {
            $product->stock = $data['stock'];
        }

        if ($request->hasFile('cover_image')) {
            if ($product->cover_image) {
                if (Storage::disk('products')->exists($product->cover_image)) {
                    Storage::disk('products')->delete($product->cover_image);
                }
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
            'message' => 'Product updated successfully',
            'data' => [
                'id' => $product->id,
                'product_name' => $product->product_name,
                'price' => $product->price, 
                'description' => $product->description,
                'stock' => $product->stock,
                'cover_image' => asset('uploads/products/' . $product->cover_image),
                'category' => new CategoryResource($product->category),
                'images' => ProductImageResource::collection($product->images),
            ],
        ], 200);
    } catch (\Exception $e) {
        \Log::error('Product update failed: ' . $e->getMessage());
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
        
    public function restore($product_id)
    {
        $product = Product::withTrashed()->find($product_id);
        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }
        $product->restore();
        return response()->json(['message' => 'done successfully.'], 200);
    }

    public function getAlldeleted()
    {
        $products = Product::onlyTrashed()->with(['category', 'user', 'images'])->get();
    
        $productsData = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'product_name' => $product->product_name,
                'price' => $product->price, 
                'description' => $product->description,
                'stock' => $product->stock,
                'cover_image' => asset('uploads/products/' . $product->cover_image),
                'category' => new CategoryResource($product->category),
                'user' => $product->user ? new UserResource($product->user) : null,
                'images' => ProductImageResource::collection($product->images),
            ];
        });
    
        return response()->json([
            'message' => 'Deleted products retrieved successfully',
            'data' => $productsData,
        ], 200);
    }
    public function forceDestroy($id)
    {
        $product = Product::withTrashed()->find($id);
    
        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }
    
        $product->forceDelete();
        return response()->json(['message' => 'Product hard deleted successfully.']);
    }


    public function search(Request $request)
    {
        $products = Product::searchByName($request->input('q'))->get();

        return response()->json($products);
    }

}
