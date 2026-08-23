<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmation;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_home_and_pages_load(): void
    {
        $this->get('/')->assertOk();
        $this->get('/shop')->assertOk();
        $this->get('/blog')->assertOk();
        $this->get('/gio-hang')->assertOk();
        $this->get('/dang-nhap')->assertOk();
        $this->get('/dang-ky')->assertOk();
        $this->get('/gioi-thieu')->assertOk();
        $this->get('/lien-he')->assertOk();
        $this->get('/hoi-dap')->assertOk();
        $this->get('/chinh-sach-bao-mat')->assertOk();
        $this->get('/dieu-khoan')->assertOk();

        $product = Product::first();
        $this->get('/san-pham/'.$product->slug)->assertOk();
        $post = \App\Models\Post::published()->first();
        $this->get('/blog/'.$post->slug)->assertOk();
    }

    public function test_cart_add_update_and_coupon(): void
    {
        $user = User::where('email', 'customer@trillfa.com')->first();
        $this->actingAs($user);

        $product = Product::whereHas('variants')->first();
        $variant = $product->variants->first();

        // Add to cart
        $this->postJson('/api/cart/add', [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertOk();

        $cart = $this->getJson('/api/cart');
        $cart->assertOk();
        $this->assertSame(2, $cart['count']);

        // Apply coupon
        $this->postJson('/api/coupon/apply', ['code' => 'WELCOME10'])->assertOk();
        $cart = $this->getJson('/api/cart');
        $this->assertGreaterThan(0, $cart['discount']);

        // Update quantity
        $item = $cart['items'][0];
        $this->postJson('/api/cart/update', ['id' => $item['id'], 'quantity' => 3])->assertOk();
        $cart = $this->getJson('/api/cart');
        $this->assertSame(3, $cart['count']);
    }

    public function test_checkout_creates_order(): void
    {
        $user = User::where('email', 'customer@trillfa.com')->first();
        $this->actingAs($user);

        $product = Product::first();
        $this->postJson('/api/cart/add', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $response = $this->post('/thanh-toan', [
            'name' => 'Nguyễn Văn An',
            'email' => $user->email,
            'phone' => '0912345678',
            'address' => '123 Nguyễn Huệ, Phường Bến Nghé',
            'ward' => 'Phường Bến Nghé',
            'district' => 'Quận 1',
            'province' => 'TP. Hồ Chí Minh',
            'payment_method' => 'cod',
            'terms' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHasNoErrors(); 
        $this->assertDatabaseCount('orders', 5); // 4 seeded + 1 new
    }

    public function test_admin_dashboard_requires_admin(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));

        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_register_creates_account(): void
    {
        $this->post('/dang-ky', [
            'name' => 'Test User',
            'email' => 'newuser@example.com',
            'phone' => '0987654321',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('account.dashboard'));

        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_all_admin_pages_load(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        $this->get('/admin')->assertOk();
        $this->get('/admin/products')->assertOk();
        $this->get('/admin/products/create')->assertOk();
        $product = Product::first();
        $this->get('/admin/products/'.$product->id.'/edit')->assertOk();
        $this->get('/admin/categories')->assertOk();
        $this->get('/admin/orders')->assertOk();
        $this->get('/admin/orders/'.\App\Models\Order::first()->id)->assertOk();
        $this->get('/admin/users')->assertOk();
        $this->get('/admin/coupons')->assertOk();
        $this->get('/admin/reviews')->assertOk();
        $this->get('/admin/posts')->assertOk();
        $this->get('/admin/posts/create')->assertOk();
        $post = \App\Models\Post::first();
        $this->get('/admin/posts/'.$post->id.'/edit')->assertOk();
        $this->get('/admin/banners')->assertOk();
        $this->get('/admin/settings')->assertOk();
        $this->get('/admin/shipping')->assertOk();
        $this->get('/admin/payments')->assertOk();
    }

    public function test_search_is_accent_insensitive(): void
    {
        $this->getJson('/api/search?q=ao')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Áo Thun Cotton Nữ']);

        $this->get('/shop?q=ao')->assertOk();
    }

    public function test_order_export_csv(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin)
            ->get('/admin/orders/export')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_order_confirmation_email_sent(): void
    {
        Mail::fake();

        $user = User::where('email', 'customer@trillfa.com')->first();
        $this->actingAs($user);

        $this->postJson('/api/cart/add', ['product_id' => Product::first()->id, 'quantity' => 1])->assertOk();

        $this->post('/thanh-toan', [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => '123 Nguyễn Huệ',
            'ward' => 'Phường Bến Nghé',
            'district' => 'Quận 1',
            'province' => 'TP. Hồ Chí Minh',
            'payment_method' => 'cod',
            'terms' => '1',
        ])->assertSessionHasNoErrors();

        Mail::assertSent(OrderConfirmation::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_seo_fixtures(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<urlset', false);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Sitemap: ');

        $product = Product::first();
        $this->get('/san-pham/'.$product->slug)
            ->assertOk()
            ->assertSee('application/ld+json', false)
            ->assertSee('"@type":"Product"', false)
            ->assertSee('rel="canonical"', false);
    }
}