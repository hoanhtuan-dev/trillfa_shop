@extends('layouts.admin')

@section('title', isset($boot['product']) ? 'Sửa sản phẩm' : 'Thêm sản phẩm')
@section('page_title', isset($boot['product']) ? 'Sửa sản phẩm' : 'Thêm sản phẩm')

@section('content')
<div id="admin-product-root">
    <div class="grid min-h-[60vh] place-items-center">
        <div class="animate-spin h-8 w-8 rounded-full border-2 border-brand-600/30 border-t-brand-600"></div>
    </div>
</div>
<script>window.__PRODUCT_BOOT__ = @json($boot);</script>
@push('scripts')
    @vite(['resources/js/admin/admin-product.js'])
@endpush
@endsection
