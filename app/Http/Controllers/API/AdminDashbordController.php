<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\UserResource;
use App\Models\Category;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use App\Models\UsersAddress;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\ImageService;
use Illuminate\Support\Facades\Validator;

class AdminDashbordController extends Controller
{
    public function getDrivers()
    {
        $drivers = User::where('role', 'driver')->get();

        return response()->json([
            'message' => 'Drivers retrieved successfully',
            'drivers' => $drivers
        ], 200);
    }

    public function getOrders(Request $request)
    {
        $query = Order::with(['user', 'products', 'delivery']);

        if ($request->has('order_id')) {
            $query->where('id', $request->order_id);
        }

        if ($request->has('last_status')) {
            $query->where('last_status', $request->last_status);
        }

        if ($request->has('date')) {
            $query->whereDate('date', $request->date);
        }

        $orders = $query->orderBy('date', 'desc')->get();

        return response()->json([
            'message' => 'Orders retrieved successfully',
            'orders' => $orders
        ], 200);
    }

    public function getCategories()
    {
        $categories = Category::withCount('products')
            ->withSum('products', 'stock')
            ->get();

            return CategoryResource::collection($categories);
    }

    public function getCategory($id)
    {
        $category = Category::with(['products'])
            ->withCount('products')
            ->withSum('products', 'stock')
            ->findOrFail($id);

        return response()->json([
            'id' => $category->id,
            'category_name' => $category->category_name,
            'description' => $category->description,
            'image' => $category->image ? asset($category->image) : null,
            'updated_at' => $category->updated_at,
            'products_count' => $category->products_count,
            'products_sum_stock' => $category->products_sum_stock,
            'products' => $category->products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'stock' => $product->stock,
                ];
            }),
        ]);
    }



    public function createCategory(Request $request)
    {
        $request->validate([
            'category_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'

        ]);

        $data = $request->only(['category_name', 'description']);

        if ($request->hasFile('image')) {
            $data['image'] = ImageService::upload($request->file('image'), 'uploads/categories');
        }

        $category = Category::create($data);

        return new CategoryResource($category);
    }

    public function updateCategory(Request $request, $id)
    {
        $request->validate([
            'category_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'

        ]);

        $category = Category::findOrFail($id);
        $data = $request->only(['category_name', 'description']);


        if ($request->hasFile('image')) {
            $data['image'] = ImageService::update($request->file('image'), $category->image, 'uploads/categories');
        }

        $category->update($data);

        return new CategoryResource($category);
    }


    public function deleteCategory($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }


    public function getDailySummaries()
    {
        $today = now()->toDateString();
        $summaries = Delivery::with(['driver', 'order.items.product.category'])
            ->whereHas('order', function ($query) use ($today) {
                $query->whereDate('created_at', $today);
            })
            ->get()
            ->map(function ($delivery) {
                $order = $delivery->order;

                $categoriesSummary = $order->items->groupBy('product.category.category_name')->map(function ($items, $categoryName) {
                    return [
                        'category_name' => $categoryName ?? 'غير معروف',
                        'total_quantity' => $items->sum('quantity') . ' كجم'
                    ];
                })->values();

                return [
                    'driver_name' => $delivery->driver->first_name ?? 'غير معروف',
                    'total_orders' => $order->count(),
                    'total_price' =>  number_format($order->items->sum(fn($item) => $item->quantity * $item->price), 2),
                    'categories' => $categoriesSummary
                ];
            });

        return response()->json([
            'date' => $today,
            'drivers_report' => $summaries
        ]);
    }


    public function getDailyCustomerSummaries()
    {
        $today = Carbon::today()->toDateString(); // تحديد تاريخ اليوم

        $summaries = Order::with(['user', 'items.product'])
            ->whereDate('created_at', $today)
            ->get()
            ->map(function ($order) {
                return $order->items->map(function ($item) use ($order) {
                    return [
                        'order_id' => $order->id,
                        'customer_name' => $order->user->first_name ?? 'غير معروف',
                        'product_name' => $item->product->product_name ?? 'غير معروف',
                        'quantity' => $item->quantity . ' كجم',
                        'payment_method' => $order->payment_method ?? 'نقدي',
                        'notes' => $order->notes ?? 'لا توجد',
                    ];
                });
            });

        return response()->json([
            'date' => $today,
            'customers_report' => $summaries
        ]);
    }

    public function getCustomers()
    {
        $customers = User::where('role', 'customer')
            ->select('phone', 'created_at', 'status', \DB::raw("CONCAT(first_name, ' ', last_name) AS full_name"))
            ->get();

        return response()->json($customers);
    }

    public function addDriver(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'username' => 'required|unique:users,username',
            'phone' => 'required|string',
            'password' => 'required|string|min:8',
            'address.country' => 'required|string',
            'address.city' => 'required|string',
            'address.address' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Create the user with the role "driver"
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'username' => $request->username,
            'role' => 'driver',
            'phone' => $request->phone,
            'password' => bcrypt($request->password),
        ]);

        // Create the user's address
        $address = new UsersAddress([
            'country' => $request->address['country'],
            'state' => $request->address['state'] ?? null,
            'zip_code' => $request->address['zip_code'] ?? null,
            'city' => $request->address['city'],
            'address' => $request->address['address'],
            'type' => $request->address['type'] ?? 'billing',
            'company_name' => $request->address['company_name'] ?? null
        ]);

        // Save the address and associate it with the user
        $user->addresses()->save($address);

        // return response()->json(['message' => 'User created successfully', 'user' => $user], 201);
        return new UserResource($user);
    }
}
