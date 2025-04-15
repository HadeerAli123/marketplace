<?php

namespace App\Http\Controllers\API;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\UserResource;
use App\Models\Category;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Product;
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

        $result = [];

        foreach ($drivers as $driver) {
            $deliveries = $driver->deliveries()
                ->where('status', 'delivered')
                ->with('order.orderItems')
                ->get();

            $totalCollected = 0;

            foreach ($deliveries as $delivery) {
                if ($delivery->order && $delivery->order->orderItems) {
                    foreach ($delivery->order->orderItems as $item) {
                        $totalCollected += $item->price * $item->quantity;
                    }
                }
            }

            $result[] = [
                'id' => $driver->id,
                'full_name' => trim($driver->first_name . ' ' . $driver->last_name),
                'phone' => $driver->phone,
                'status' => $driver->status,
                'total_collected' => $totalCollected,
                'deliveries_count' => $deliveries->count(),
            ];
        }

        return response()->json($result);
    }


    public function getOrders(Request $request)
    {
        $query = Order::with(['user', 'products', 'delivery.driver']);

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
        try {
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
    
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Category not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    

    public function getUser($id)
    {
        try {
            $user = User::with(['addresses'])->findOrFail($id);
            return new UserResource($user);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong.',
                'error' => $e->getMessage(),
            ], 500);
        }
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
    
        try {
            $category = Category::findOrFail($id);
            
            $data = $request->only(['category_name', 'description']);
    
            if ($request->hasFile('image')) {
                $data['image'] = ImageService::update($request->file('image'), $category->image, 'uploads/categories');
            }
    
            $category->update($data);
    
            return new CategoryResource($category);
    
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Category not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    


    public function deleteCategory($id)
    {
        try {
            $category = Category::find($id);

            if (!$category) {
                return response()->json(['message' => 'Category not found.'], 404);
            }

            $hasProducts = Product::where('category_id', $id)->exists();

            if ($hasProducts) {
                return response()->json(['message' => 'Cannot delete category. It has associated products.'], 400);
            }

            $category->delete();

            return response()->json(['message' => 'Category deleted successfully.'], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getProducts()
    {
        $products = Product::with('category')->get();

        $formatted = $products->map(function ($product) {
            return [
                'price' => $product->price,
                'category_name' => $product->category?->category_name,
                'id' => $product->id,
                'product_name' => $product->product_name,
                'stock' => $product->stock,
                'image' => $product->cover_image
                    ?  asset('uploads/products/' . $product->cover_image)
                    : asset('uploads/products/default.png'),

            ];
        });

        return response()->json($formatted);
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
                    'driver_name' => $delivery->driver->first_name.''. $delivery->driver->last_name?? 'غير معروف',
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
        $today = Carbon::today()->toDateString();

        $summaries = Order::with(['user', 'items.product'])
            ->whereDate('created_at', $today)
            ->get()
            ->map(function ($order) {
                return $order->items->map(function ($item) use ($order) {
                    return [
                        'order_id' => $order->id,
                        'customer_name' => $order->user->first_name.' '.  $order->user->last_name?? 'غير معروف',
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
            ->select('id', 'phone', 'created_at', 'status', \DB::raw("CONCAT(first_name, ' ', last_name) AS full_name"))
            ->get();
    
        if ($customers->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No customers found',
            ], 404);
        }
    
        return response()->json([
            'status' => 'success',
            'data' => $customers,
        ]);
    }
    

    public function addDriver(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'nullable|string',
            'last_name' => 'nullable|string',
            'username' => 'nullable|unique:users,username',
            'phone' => 'required|string',
            'password' => 'required|string|min:8',
            'address.country' => 'nullable|string',
            'address.city' => 'nullable|string',
            'address.address' => 'nullable|string',
            'address.state' => 'nullable|string',
            'address.zip_code' => 'nullable|string',
            'address.type' => 'nullable|in:billing,shipping',
            'address.company_name' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'first_name' => $request->first_name?? null,
            'last_name' => $request->last_name?? null,
            'username' => $request->username?? null,
            'role' => 'driver',
            'phone' => $request->phone,
            'password' => bcrypt($request->password),
        ]);

        $address = new UsersAddress([
            'country' => $request->address['country']?? "country",
            'state' => $request->address['state'] ?? null,
            'zip_code' => $request->address['zip_code'] ?? null,
            'city' => $request->address['city']?? "city",
            'address' => $request->address['address']??"address",
            'type' => $request->address['type'] ?? 'billing',
            'company_name' => $request->address['company_name'] ?? null
        ]);

        $user->addresses()->save($address);

        return new UserResource($user);
    }


    public function addcustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'nullable|string',
            'last_name' => 'nullable|string',
            'username' => 'nullable|unique:users,username',
            'phone' => 'required|string',
            'password' => 'required|string|min:8',
            'address.country' => 'nullable|string',
            'address.city' => 'nullable|string',
            'address.address' => 'nullable|string',
            'address.state' => 'nullable|string',
            'address.zip_code' => 'nullable|string',
            'address.type' => 'nullable|in:billing,shipping',
            'address.company_name' => 'f|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }


        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'username' => $request->username,
            'role' => 'customer',
            'phone' => $request->phone,
            'password' => bcrypt($request->password),
        ]);

        $address = new UsersAddress([
            'country' => $request->address['country']?? "country",
            'state' => $request->address['state'] ?? null,
            'zip_code' => $request->address['zip_code'] ?? null,
            'city' => $request->address['city']?? "city",
            'address' => $request->address['address']??"address",
            'type' => $request->address['type'] ?? 'billing',
            'company_name' => $request->address['company_name'] ?? null
        ]);

        $user->addresses()->save($address);

        return new UserResource($user);
    }

    public function getStats()
    {
        $today = Carbon::today();
        $lastWeekStart = Carbon::now()->subWeek()->startOfWeek();
        $lastWeekEnd = Carbon::now()->subWeek()->endOfWeek();

        $ordersLastWeek = Order::whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])
            ->selectRaw('DAYOFWEEK(created_at) as day_of_week, COUNT(*) as total_orders')
            ->groupBy('day_of_week')
            ->orderBy('day_of_week')
            ->get();

            $ordersPerMonth = Order::with('orderItems')
            ->whereYear('created_at', $today->year)
            ->get()
            ->map(function ($order) {
                if ($order->orderItems->isEmpty()) {
                    return null;
                }
        
                $totalAmount = $order->orderItems->sum(function ($item) {
                    return $item->price * $item->quantity;
                });
        
                return [
                    'month' => $order->created_at->month,
                    'total_amount' => $totalAmount,
                ];
            })
            ->filter()
            ->groupBy('month')
            ->map(function ($orders, $month) {
                return [
                    'month' => (int) $month,
                    'total_amount' => $orders->sum('total_amount')
                ];
            })
            ->values();
        

        $activeUsersCount = User::where('status', 'active')->count();
        $inactiveUsersCount = User::where('status', 'inactive')->count();

        $totalUsersCount = $activeUsersCount + $inactiveUsersCount;
        $activeUserPercentage = $totalUsersCount > 0 ? ($activeUsersCount / $totalUsersCount) * 100 : 0;
        $inactiveUserPercentage = $totalUsersCount > 0 ? ($inactiveUsersCount / $totalUsersCount) * 100 : 0;

        $ordersToday = Order::whereDate('created_at', $today)->count();

        $ordersYesterday = Order::whereDate('created_at', $today->yesterday())->count();
        $orderIncreasePercentage = $ordersYesterday > 0 ? (($ordersToday - $ordersYesterday) / $ordersYesterday) * 100 : 0;

        $usersToday = User::whereDate('created_at', $today)->count();

        $usersYesterday = User::whereDate('created_at', $today->yesterday())->count();
        $userIncreasePercentage = $usersYesterday > 0 ? (($usersToday - $usersYesterday) / $usersYesterday) * 100 : 0;

        $productsToday = Order::whereDate('created_at', $today)->count();

        $productsYesterday = Order::whereDate('created_at', $today->yesterday())->count();
        $productIncreasePercentage = $productsYesterday > 0 ? (($productsToday - $productsYesterday) / $productsYesterday) * 100 : 0;

        $driversToday = User::whereDate('created_at', $today)->where('role', 'driver')->count();

        $driversYesterday = User::whereDate('created_at', $today->yesterday())->where('role', 'driver')->count();
        $driverIncreasePercentage = $driversYesterday > 0 ? (($driversToday - $driversYesterday) / $driversYesterday) * 100 : 0;

        return response()->json([
            'orders_last_week' => $ordersLastWeek,
            'orders_per_month' => $ordersPerMonth,
            'active_user_percentage' => $activeUserPercentage,
            'inactive_user_percentage' => $inactiveUserPercentage,
            'orders_today' => $ordersToday,
            'order_increase_percentage' => $orderIncreasePercentage,
            'users_today' => $usersToday,
            'user_increase_percentage' => $userIncreasePercentage,
            'products_today' => $productsToday,
            'product_increase_percentage' => $productIncreasePercentage,
            'drivers_today' => $driversToday,
            'driver_increase_percentage' => $driverIncreasePercentage,
        ]);
    }





    public function destroyuser($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'User deleted successfully',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


}
