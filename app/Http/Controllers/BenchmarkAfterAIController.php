<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{Order, Product, Category, User, Review};

class BenchmarkAfterAIController extends Controller
{
    # After Assistant:
    // FIXED: eager load user + items to avoid N+1 across all orders
    public function orders()
    {
        return Order::with(['user', 'items'])->get()->map(fn ($o) => [
            'id' => $o->id,
            'user' => $o->user->name,
            'items_count' => $o->items->count(),
        ]);
    }

    // FIXED: eager load user + items.product for the single order view
    public function orderShow(Order $order)
    {
        $order->load(['user', 'items.product']);

        return [
            'id' => $order->id,
            'user' => $order->user->name,
            'items' => $order->items->map(fn ($item) => [
                'product' => $item->product->label,
            ]),
        ];
    }

    // FIXED: this was the worst case (120+ queries) — now just 2 queries total
    public function enterpriseOrder()
    {
        $order = Order::with('items.product')
            ->where('number', 'ENT-00001')
            ->firstOrFail();

        return [
            'id' => $order->id,
            'items' => $order->items->map(fn ($item) => [
                'product' => $item->product->label,
            ]),
        ];
    }

    // FIXED: eager load category for all products
    public function products()
    {
        return Product::with('category')->get()->map(fn ($p) => [
            'label' => $p->label,
            'category' => optional($p->category)->label,
        ]);
    }

    // Untouched — agent confirmed no N+1 issue here
    public function productsByCategory(Category $category)
    {
        return Product::where('category_id', $category->id)->get();
    }

    // FIXED: eager load reviews.user
    public function productReviews(Product $product)
    {
        $product->load('reviews.user');

        return $product->reviews->map(fn ($r) => [
            'rating' => $r->rating,
            'user' => $r->user->name,
        ]);
    }

    // Untouched — agent confirmed no N+1 issue here
    public function productsWithoutCategory()
    {
        return Product::whereNull('category_id')->get();
    }

    // FIXED: eager load products.reviews
    public function categoryProducts(Category $category)
    {
        $category->load('products.reviews');

        return $category->products->map(fn ($p) => [
            'label' => $p->label,
            'reviews_count' => $p->reviews->count(),
        ]);
    }

    // FIXED: eager load orders.items
    public function userOrders(User $user)
    {
        $user->load('orders.items');

        return $user->orders->map(fn ($o) => [
            'number' => $o->number,
            'items_count' => $o->items->count(),
        ]);
    }

    // FIXED: eager load product + user for recent reviews
    public function recentReviews()
    {
        return Review::with(['product', 'user'])
            ->latest()
            ->take(20)
            ->get()
            ->map(fn ($r) => [
                'product' => $r->product->label,
                'user' => $r->user->name,
            ]);
    }
}
