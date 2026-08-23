@extends('layouts.account')

@section('title', 'Yêu thích')

@section('account_content')
    <div class="mb-6">
        <h1 class="font-display text-2xl font-semibold text-ink-900">Danh sách yêu thích</h1>
        <p class="mt-1 text-sm text-ink-500">Các sản phẩm bạn đã lưu để xem sau.</p>
    </div>

    @if($products->isEmpty())
        <div class="card flex flex-col items-center justify-center p-16 text-center">
            <p class="font-medium text-ink-900">Danh sách yêu thích đang trống</p>
            <p class="mt-1 text-sm text-ink-500">Nhấn vào biểu tượng trái tim trên sản phẩm để lưu lại.</p>
            <a href="{{ route('shop.index') }}" class="btn-primary mt-6">Khám phá sản phẩm</a>
        </div>
    @else
        <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
            @foreach($products as $product)
                <div class="relative">
                    <x-product-card :product="$product" />
                    <form method="POST" action="{{ route('wishlist.toggle') }}" class="absolute right-0 top-0 z-10">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <button type="submit" class="grid h-9 w-9 place-items-center rounded-full bg-white text-clay-500 shadow" aria-label="Xóa khỏi yêu thích">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
        <div class="mt-10">{{ $products->links() }}</div>
    @endif
@endsection
