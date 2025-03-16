<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */


     public function getProductsByCategory($categoryId)
{
    try {
        $category = Category::find($categoryId);

        if (!$category) {
            return response()->json(['error' => 'Category not found'], 404);
        }

        $products = Product::where('category_id', $categoryId)
                          ->with('images:id,product_id,image') 
                          ->select(['id', 'product_name', 'price']) 
                          ->get();

        return response()->json(['message' => 'Products retrieved successfully', 'data' => $products], 200);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
    public function index()
    {
    $products=Product::with('images:id,product_id,image')->select(['id','product_name','price'])
    ->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
     
        try {

            $user = Auth::user();
            if (!$user || $user->role !== 'admin') {
                return response()->json(['error' => 'Unauthorized: Only admins can create products.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'product_name' => 'required|string|max:255',
                'cover_image' => 'required|file|image|mimes:jpeg,png,jpg,gif,webp,avif|max:2048',
                'price' => 'required|numeric',
                'description' => 'nullable|string',
                'images' => 'nullable|array',
                'images.*' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp,avif|max:2048',
                'stock' => 'required|integer',
                'user_id' => 'required|exists:users,id',
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
                'user_id.required' => 'The user ID is required.',
            'user_id.exists' => 'The selected user ID does not exist.',
                'category_id.required' => 'Category ID is required.',
                'category_id.integer' => 'Category ID must be an integer.',
                'category_id.exists' => 'Category ID does not exist.',
            ]);
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()],400);
            }
    
            $data = $validator->validated();

            $category = Category::find($data['category_id']); 

            if (!$category) {
                return response()->json(['error' => 'Invalid category ID'], 400);
            }
    
            if ($data['user_id'] != Auth::id()) {
                return response()->json(['error' => 'Unauthorized: You can only create products for yourself.'], 403);
            } 

            $image_path ='';
            if ($request->hasFile('cover_image')) {

                
                    $path = $data['cover_image'] ->store('cover_images', 'products');
                    $path= asset('uploads/products/' . $path); 
                    $image_path = $path;
               
            }

            $product = new Product();
            $product->product_name = $data['product_name'];
            $product->price = $data['price'];
            $product->description = $data['description'];
            $product->stock = $data['stock'];
            $product->cover_image = $image_path;

            $product->user_id = Auth::id();
            $product->category_id = $data['category_id'];
            $product->save();

        

            if ($request->hasFile('images')) {
                foreach ($data['images'] as $image) {
                    $path = $image->store('images', 'products');
                    $path= asset('uploads/products/' . $path); 
                    $productImage = new ProductImage();
                    $productImage->product_id =  $product->id;
                    $productImage->image = $path;
                    $productImage->save();
                }
            }
         


    
            return response()->json(['message' => 'Product created successfully', 'product' => $product], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
        
        }
        
    

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return new ProductResource($product->load('category', 'user'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
        
            $user = Auth::user();
            if (!$user || $user->role !== 'admin') { 
                return response()->json(['error' => 'Unauthorized: Only admins can update products.'], 403);
            }
    
    
            $validator = Validator::make($request->all(), [
                'id' => 'required|exists:products,id',
                'product_name' => 'required|string|max:255',
                'cover_image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif|max:2048',
                'price' => 'required|numeric',
                'description' => 'nullable|string',
                'images' => 'nullable|array',
                'images.*' => 'nullable|file|image|mimes:jpeg,png,jpg,gif|max:2048',
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

            $product = Product::find($data['id']);
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
                $path = $data['cover_image']->store('cover_images', 'products');
                $path = asset('uploads/products/' . $path);
                $image_path = $path;
            }
    
           
            $product->product_name = $data['product_name'];
            $product->price = $data['price'];
            $product->description = $data['description'];
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
    
              
                foreach ($data['images'] as $image) {
                    $path = $image->store('images', 'products');
                    $path = asset('uploads/products/' . $path);
                    $productImage = new ProductImage();
                    $productImage->product_id = $product->id;
                    $productImage->image = $path;
                    $productImage->save();
                }
            }
    
            return response()->json(['message' => 'Product updated successfully', 'product' => $product], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
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
        return response()->json(['message' => 'done suuccessfully.'], 200);
    }

    public function getAlldeleted()
    {
  $products = Product::onlyTrashed()->with(['category', 'user', 'images'])->get();    
  return ProductResource::collection($products);
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
}
