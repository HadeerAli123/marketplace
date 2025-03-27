<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Category;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        
        try{

            $validator = Validator::make($request->all(), [
                'category_name' => 'required|string|max:255|unique:categories,category_name',
                'description' => 'required|string|max:500',
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
    
    
            ], [
                'category_name.required' => 'Category name is required.',
                'category_name.string' => 'Category name must be a string.',
                'category_name.max' => 'Category name may not be greater than 255 characters.',
                'category_name.unique' => 'Category name must be unique.',
                'description.string' => 'Description must be a string.',
                'description.max' => 'Description may not be greater than 500 characters.',
                "image.image" => "The image must be a valid image file.",
    
                "image.required" => "Category image is required.",
                "image.mimes" => "The image must be in jpeg, png, jpg, or gif format.",
                "image.max" => "The image size should not exceed 2MB.",
            ]);
    
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
      
            $my_path = '';
            if(request()->hasFile("image")){
                $image = request()->file("image");
                $my_path=$image->store('images','category_image');
                $my_path= asset('uploads/categories/' . $my_path); 
            }
     // لو كل ده صح هنكريت اوبجكت من الكاتيجوري من الموديل يعني
    
            // $data = $validator->validated();
            $category = new Category();
    
            $category->category_name = $request->category_name;
            $category->description = $request->description;
            $category->image = $my_path;
            $category->save();
    
    
            return response()->json(['message' => 'Category created successfully', 'category' => $category], 201);///$category ده الي بناه من الداتا بيز وده عبارة عن الكاتيجوري نيم الي انا هجيبه من الداتا بيز 
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
        }
    


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
