<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomPage extends Model
{
    use HasFactory;

    protected $table = 'custom_pages';

    protected $fillable = [
        'title', 'slug', 'excerpt', 'content', 'hero_image', 'hero_heading',
        'hero_subtitle', 'hero_button_text', 'hero_button_link', 'meta_title',
        'meta_description', 'template', 'product_ids', 'is_active', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'product_ids' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function getUrlAttribute(): string
    {
        return route('page.show', $this->slug);
    }

    public function getHeroImageUrlAttribute(): ?string
    {
        return $this->hero_image ? asset_image($this->hero_image) : null;
    }

    public function products()
    {
        $ids = collect($this->product_ids ?? [])->filter()->map(fn ($id) => (int) $id)->all();

        return Product::whereIn('id', $ids)->active()->inStock()->with('category')->get();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
