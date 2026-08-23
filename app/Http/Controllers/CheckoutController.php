<?php

namespace App\Http\Controllers;

use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CheckoutController extends Controller
{
    public function __construct(protected CartService $cart)
    {
    }

    public function show()
    {
        if ($this->cart->count() === 0) {
            return redirect()->route('cart.show');
        }

        $payload = $this->cart->payload();
        $shippingMethods = $this->cart->shippingMethods();
        $paymentMethods = \App\Models\PaymentMethod::active()->orderBy('sort_order')->get();
        $address = auth()->user()?->addresses()->where('is_default', true)->first();

        return view('checkout.show', compact('payload', 'shippingMethods', 'paymentMethods', 'address'));
    }

    public function store(Request $request)
    {
        if ($this->cart->count() === 0) {
            return back()->withErrors(['cart' => 'Giỏ hàng trống.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'min:2'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\s.-]{8,20}$/'],
            'address' => ['required', 'string', 'min:5'],
            'ward' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
            'shipping_method' => ['nullable', 'string'],
            'payment_method' => ['required', 'string'],
            'terms' => ['accepted'],
        ]);

        if ($data['shipping_method'] ?? null) {
            $this->cart->setShippingMethod($data['shipping_method']);
        }

        $payload = $this->cart->payload();

        $order = DB::transaction(function () use ($request, $data, $payload) {
            $order = Order::create([
                'order_number' => Order::generateNumber(),
                'user_id' => auth()->id(),
                'email' => $data['email'],
                'name' => $data['name'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'ward' => $data['ward'] ?? null,
                'district' => $data['district'] ?? null,
                'province' => $data['province'] ?? null,
                'note' => $data['note'] ?? null,
                'subtotal' => $payload['subtotal'],
                'discount' => $payload['discount'],
                'shipping_fee' => $payload['shipping_fee'],
                'tax' => 0,
                'total' => $payload['total'],
                'shipping_method' => $payload['shipping_method']['name'] ?? null,
                'payment_method' => $data['payment_method'],
                'payment_status' => 'unpaid',
                'coupon_id' => $this->cart->cart()->coupon_id,
                'status' => Order::STATUS_PENDING,
                'placed_at' => now(),
            ]);

            foreach ($this->cart->cart()->items as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'product_name' => $item->product?->name ?? 'Sản phẩm',
                    'sku' => $item->product?->sku,
                    'options' => $item->options,
                    'price' => $item->price,
                    'quantity' => $item->quantity,
                    'subtotal' => round($item->price * $item->quantity, 2),
                    'image' => $item->product?->image,
                ]);

                $this->stockOut($item->product_id, $item->variant_id, $item->quantity);
            }

            if ($this->cart->cart()->coupon) {
                $coupon = $this->cart->cart()->coupon;
                $coupon->increment('used_count');
            }

            return $order;
        });

        $isOnline = in_array($data['payment_method'], ['vnpay', 'momo']);
        $this->cart->clear();
        session()->forget('shipping_method');

        try {
            Mail::to($data['email'])->send(new OrderConfirmation($order));
        } catch (\Throwable $e) {
            logger()->error('Send order confirmation email failed: '.$e->getMessage());
        }

        if ($isOnline) {
            return redirect()->route('checkout.pay', $order);
        }

        return redirect()->route('checkout.success', $order);
    }

    public function pay(Order $order)
    {
        if ($order->user_id && $order->user_id !== auth()->id()) {
            abort(403);
        }

        return view('checkout.pay', compact('order'));
    }

    public function confirmPay(Request $request, Order $order)
    {
        if ($order->user_id && $order->user_id !== auth()->id()) {
            abort(403);
        }

        $order->update(['payment_status' => 'paid', 'paid_at' => now()]);

        return redirect()->route('checkout.success', $order);
    }

    public function success(Order $order)
    {
        return view('checkout.success', compact('order'));
    }

    protected function stockOut(int $productId, ?int $variantId, int $quantity): void
    {
        if ($variantId) {
            ProductVariant::where('id', $variantId)->decrement('stock', $quantity);
            $product = Product::find($productId);
            if ($product) {
                $product->increment('sales_count', $quantity);
                $product->stock = $product->variants()->sum('stock');
                $product->save();
            }
        } else {
            Product::where('id', $productId)->decrement('stock', $quantity);
            Product::where('id', $productId)->increment('sales_count', $quantity);
        }
    }
}