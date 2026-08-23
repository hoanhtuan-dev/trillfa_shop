@extends('layouts.account')

@section('title', 'Đánh giá')

@section('account_content')
    <div class="mb-6">
        <h1 class="font-display text-2xl font-semibold text-ink-900">Đánh giá của tôi</h1>
        <p class="mt-1 text-sm text-ink-500">Các đánh giá bạn đã viết cho sản phẩm.</p>
    </div>

    @if($reviews->isEmpty())
        <div class="card flex flex-col items-center justify-center p-16 text-center">
            <p class="font-medium text-ink-900">Bạn chưa có đánh giá nào</p>
            <p class="mt-1 text-sm text-ink-500">Hãy chia sẻ trải nghiệm mua sắm của bạn.</p>
            <a href="{{ route('shop.index') }}" class="btn-primary mt-6">Mua sắm ngay</a>
        </div>
    @else
        <div class="space-y-4">
            @foreach($reviews as $review)
                <div class="card p-6">
                    <div class="flex items-center justify-between">
                        <a href="{{ route('product.show', $review->product->slug) }}" class="flex items-center gap-3">
                            <img src="{{ $review->product->image_url ?: asset('images/placeholder.svg') }}" class="h-12 w-12 rounded-xl object-cover" alt="">
                            <div>
                                <p class="text-sm font-medium text-ink-900 hover:text-brand-700">{{ $review->product->name }}</p>
                                <div class="mt-0.5"><x-rating :value="$review->rating" /></div>
                            </div>
                        </a>
                        <span class="text-xs text-ink-500">{{ $review->created_at?->format('d/m/Y') }}</span>
                    </div>
                    @if($review->title)<p class="mt-3 text-sm font-semibold text-ink-900">{{ $review->title }}</p>@endif
                    <p class="mt-1 text-sm leading-relaxed text-ink-700">{{ $review->body }}</p>
                </div>
            @endforeach
        </div>
        <div class="mt-8">{{ $reviews->links() }}</div>
    @endif
@endsection
