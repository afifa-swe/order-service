<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['items', 'delivery', 'payment'])->latest('id');

        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        $perPage = (int) $request->query('per_page', 15);

        return response()->json($query->paginate($perPage));
    }

    public function show(Order $order): JsonResponse
    {
        $order->load(['items', 'delivery', 'payment']);

        return response()->json(['data' => $order]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orders->createOrder($request->validated());

        return response()->json(['data' => $order], 201);
    }
}
