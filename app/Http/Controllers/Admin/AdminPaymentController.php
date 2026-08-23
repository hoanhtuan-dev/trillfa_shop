<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class AdminPaymentController extends Controller
{
    public function index()
    {
        $methods = PaymentMethod::orderBy('sort_order')->get();

        return view('admin.payments.index', compact('methods'));
    }

    public function update(Request $request, PaymentMethod $method)
    {
        $method->update([
            'name' => $request->input('name', $method->name),
            'description' => $request->input('description', $method->description),
            'sort_order' => $request->input('sort_order', $method->sort_order),
            'is_active' => $request->boolean('is_active', $method->is_active),
        ]);

        return back()->with('success', 'Đã cập nhật phương thức thanh toán.');
    }

    public function toggleActive(PaymentMethod $method)
    {
        $method->update(['is_active' => ! $method->is_active]);

        return back()->with('success', 'Đã cập nhật trạng thái.');
    }
}
