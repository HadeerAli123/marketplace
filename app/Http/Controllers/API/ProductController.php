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
class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */

/// test ok
public function getProductsByCategory($categoryId)
{
    try {
        $category = Category::find($categoryId);

        if (!$category) {
            return response()->json(['error' => 'Category not found'], 404);
        }

        $products = Product::where('category_id', $categoryId)
                           ->whereNull('deleted_at')
                           ->with(['images:id,product_id,image,created_at,updated_at'])
                           ->select(['id', 'product_name', 'price', 'stock', 'description', 'cover_image',])
                           ->paginate(10);

        return response()->json([
            'message' => 'Products retrieved successfully',
            'data' => ProductResource::collection($products)
        ], 200);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }

}
/// test ok
public function index()
{
    try {
        $products = Product::whereNull('deleted_at')
                           ->with(['images:id,product_id,image,created_at,updated_at', 'category'])
                           ->select(['id', 'product_name', 'price', 'stock', 'description', 'cover_image','category_id'])
                           ->paginate(10); 
        return response()->json([
            'message' => 'Products retrieved successfully',
            'data' => ProductResource::collection($products)
        ], 200);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}


    /**
     * Store a newly created resource in storage.
     */

     ///test
     public function productDetails($id)
     {
         try {
             $product = Product::with(['category', 'images'])->find($id);
 
             if (!$product) {
                 return response()->json(['error' => 'Product not found'], 404);
             }
 
             $isSpotModeActive = SpotMode::isActive();
             $spotMode = $isSpotModeActive ? SpotMode::where('status', 'active')->first() : SpotMode::where('status', 'not_active')->first();
             $sale = $spotMode ? $spotMode->sale : 0;
 
             $price = $isSpotModeActive ? max(0, $product->price - ($product->price * $sale / 100)) : $product->price;
 
             return response()->json([
                 'status' => 'success',
                 'product' => [
                     'id' => $product->id,
                     'product_name' => $product->product_name,
                     'price' => $price,
                     'description' => $product->description,
                     'cover_image' => $product->cover_image,
                     'category' => new CategoryResource($product->category),
                     'images' => ProductImageResource::collection($product->images),
                 ],
             ], 200);
 
         } catch (\Exception $e) {
             return response()->json(['error' => $e->getMessage()], 500);
         }
     }
 




     
     ////test ok
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
                 'price.required' => 'Price is required.',
                 'price.numeric' => 'Price must be a number.',
                 'description.string' => 'Description must be a string.',
                 'images.file' => 'Image must be a file.',
                 'images.image' => 'The file must be an image.',
                 'images.mimes' => 'Image must be of type: jpeg, png, jpg, or gif.',
                 'images.max' => 'Image may not be greater than 2 MB.',
                 'stock.required' => 'Stock is required.',
                 'stock.integer' => 'Stock must be an integer.',
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
                 $path = $request->file('cover_image')->store('cover_images', 'products');
                 $image_path = asset('uploads/products/' . $path);
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
                     $image_path = asset('uploads/products/' . $path);
                     $productImage = new ProductImage();
                     $productImage->product_id = $product->id;
                     $productImage->image = $image_path;
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
        
    

    /**
     * Display the specified resource.
     */

     ///test ok
    public function show(Product $product)
    {
        return new ProductResource($product->load('category','user'));
    }

    /**
     * Update the specified resource in storage.
     */
    //////////test ok
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

        $category = Category::find($data['category_id']);
        if (!$category) {
            return response()->json(['error' => 'Invalid category ID'], 404);
        }

        $image_path = $product->cover_image;
        if ($request->hasFile('cover_image')) {
            if ($product->cover_image) {
                $relativeImagePath = str_replace(asset('uploads/products/') . '/', '', $product->cover_image);
                if (Storage::disk('products')->exists($relativeImagePath)) {
                    Storage::disk('products')->delete($relativeImagePath);
                }
            }
            $path = $request->file('cover_image')->store('cover_images', 'products');
            $image_path = asset('uploads/products/' . $path);
        }

        $product->product_name = $data['product_name'];
        $product->price = $data['price'];
      
        $product->description = isset($data['description']) ? $data['description'] : $product->description;
        $product->stock = $data['stock'];
        $product->cover_image = $image_path;
        $product->category_id = $data['category_id'];
        $product->save();

        if ($request->hasFile('images')) {
            $oldImages = ProductImage::where('product_id', $product->id)->get();
            foreach ($oldImages as $image) {
                $url = $image->image;
                $relativePath = str_replace(asset('uploads/products/') . '/', '', $url);
                if (Storage::disk('products')->exists($relativePath)) {
                    Storage::disk('products')->delete($relativePath);
                }
                $image->delete();
            }

            foreach ($request->file('images') as $image) {
                $path = $image->store('images', 'products');
                $image_path = asset('uploads/products/' . $path);
                $productImage = new ProductImage();
                $productImage->product_id = $product->id;
                $productImage->image = $image_path;
                $productImage->save();
            }
        }

  
        $product->load('images');

        return response()->json(['message' => 'Product updated successfully', 'product' => $product], 200);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

    /**
     * Remove the specified resource from storage.
     */

     ///test ok
    public function destroy(Product $product)
    {
    
        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }
        $product->delete();
        return response()->json(['message' => 'Product soft deleted successfully.']);
    }
        
    //// test ok
    public function restore($product_id)
    {
        $product = Product::withTrashed()->find($product_id);
        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }
        $product->restore();
        return response()->json(['message' => 'done suuccessfully.'], 200);
    }
//test ok
    public function getAlldeleted()
    {
  $products = Product::onlyTrashed()->with(['category', 'user', 'images'])->get();    
  return ProductResource::collection($products);
    }
/// test ok
    public function forceDestroy($id)
    {
        $product = Product::withTrashed()->find($id);
    
        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }
    
        $product->forceDelete();
        return response()->json(['message' => 'Product hard deleted successfully.']);
    }
}
