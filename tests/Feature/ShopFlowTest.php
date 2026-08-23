<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmation;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
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

    public function test_admin_can_create_update_and_delete_user(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        $this->post('/admin/users', [
            'name' => 'Khách Mới',
            'email' => 'khachmoi@trillfa.com',
            'phone' => '0900111222',
            'role' => 'customer',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $user = User::where('email', 'khachmoi@trillfa.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('password123', $user->password));

        $this->put('/admin/users/'.$user->id, [
            'name' => 'Khách Đã Sửa',
            'email' => 'khachmoi@trillfa.com',
            'role' => 'admin',
            'password' => '',
            'password_confirmation' => '',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Khách Đã Sửa', $user->fresh()->name);
        $this->assertSame('admin', $user->fresh()->role);

        $this->delete('/admin/users/'.$admin->id)->assertSessionHas('error');

        $other = User::where('email', 'khach1@trillfa.com')->first();
        $this->delete('/admin/users/'.$other->id)->assertSessionHas('success');
        $this->assertNull(User::find($other->id));
    }

    public function test_admin_can_reset_user_password(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $customer = User::where('email', 'customer@trillfa.com')->first();
        $this->actingAs($admin);

        $this->post('/admin/users/'.$customer->id.'/password', [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('newpassword123', $customer->fresh()->password));
    }

    public function test_admin_can_update_banner(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);
        $banner = \App\Models\Banner::first();

        $this->put('/admin/banners/'.$banner->id, [
            'title' => 'Banner Đã Sửa',
            'position' => 'hero',
            'sort_order' => 5,
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Banner Đã Sửa', $banner->fresh()->title);
        $this->assertSame(5, (int) $banner->fresh()->sort_order);
    }


    public function test_admin_can_preview_draft_post(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        $post = \App\Models\Post::create([
            'title' => 'Bài viết nháp',
            'slug' => 'bai-viet-nhap-'.uniqid(),
            'excerpt' => 'Nội dung nháp',
            'body' => '<p>Đây là bài viết nháp.</p>',
            'tags' => ['nha'],
            'status' => 'draft',
            'author_id' => $admin->id,
        ]);

        $this->get('/admin/posts/'.$post->id.'/preview')
            ->assertOk()
            ->assertSee('Bài viết nháp');
    }

    public function test_admin_logout(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        $this->post('/dang-xuat')->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_admin_can_delete_order(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);
        $order = \App\Models\Order::first();

        $this->delete('/admin/orders/'.$order->id)->assertSessionHas('success');
        $this->assertNull(\App\Models\Order::find($order->id));
    }


    public function test_admin_can_toggle_widgets(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        $this->post('/admin/widgets', [
            'enabled_featured' => '0',
            'limit_featured' => 6,
            'enabled_hero' => '1',
        ])->assertSessionHas('success');

        $this->assertSame('0', setting('widget_featured_enabled'));
        $this->assertSame(6, (int) setting('widget_featured_limit'));
        $this->assertSame('1', setting('widget_hero_enabled'));
    }

    public function test_admin_can_update_category_with_icon(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);
        $cat = \App\Models\Category::first();

        $this->put('/admin/categories/'.$cat->id, [
            'name' => $cat->name,
            'is_active' => '1',
            'icon' => 'star',
        ])->assertSessionHasNoErrors();

        $this->assertSame('star', $cat->fresh()->icon);
    }

    public function test_admin_reports_page(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin)
            ->get('/admin/reports')
            ->assertOk()
            ->assertSee('Doanh thu')
            ->assertSee('Sản phẩm bán chạy');
    }


    public function test_post_with_string_tags_still_renders(): void
    {
        // Legacy: tags stored as a comma string (JSON string literal) must not crash the view.
        $post = \App\Models\Post::create([
            'title' => 'Bài viết tag chuỗi',
            'slug' => 'bai-tag-chuoi-'.uniqid(),
            'status' => 'published',
            'excerpt' => 'Tóm tắt bài viết.',
            'body' => '<p>Nội dung bài viết.</p>',
            'tags' => 'thoi-trang, meo-hay',
            'author_id' => 1,
        ]);

        $this->get('/blog/'.$post->slug)
            ->assertOk()
            ->assertSee('thoi-trang');
    }

    public function test_admin_saving_tags_stored_as_array(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        $this->post('/admin/posts', [
            'title' => 'Bài post tag test',
            'status' => 'published',
            'body' => '<p>Nội dung test.</p>',
            'tags' => 'alpha, beta, gamma',
        ])->assertSessionHasNoErrors();

        $post = \App\Models\Post::where('title', 'Bài post tag test')->first();
        $this->assertNotNull($post);
        $this->assertIsArray($post->tags);
        $this->assertEquals(['alpha', 'beta', 'gamma'], $post->tags);
    }


    public function test_category_custom_icon_image_renders(): void
    {
        $cat = \App\Models\Category::create([
            'name' => 'Danh mục icon ảnh',
            'slug' => 'danh-muc-icon-anh-'.uniqid(),
            'is_active' => true,
            'icon_image' => 'samples/2aOboQqOBTR5uosVCsNhbUXA5FrAsBRBPGV455LU.jpg',
        ]);

        $this->get('/shop')->assertOk()->assertSee('/samples/2aOboQqOBTR5uosVCsNhbUXA5FrAsBRBPGV455LU.jpg');
        $this->get('/')->assertOk()->assertSee('/samples/2aOboQqOBTR5uosVCsNhbUXA5FrAsBRBPGV455LU.jpg');
    }


    public function test_admin_can_update_about_page(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        $this->post('/admin/pages/about', [
            'about_heading' => 'Giới thiệu Trillfa Fa mới',
            'about_intro' => 'Nội dung mở đầu mới.',
            'about_v1_title' => 'Sáng tạo',
            'about_v1_text' => 'Luôn sáng tạo.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Giới thiệu Trillfa Fa mới', setting('about_heading'));

        $this->get('/gioi-thieu')->assertOk()->assertSee('Giới thiệu Trillfa Fa mới');
    }

    public function test_admin_can_manage_payment_method(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        $this->post('/admin/payments', [
            'name' => 'Ví ZaloPay',
            'code' => 'zalopay',
            'description' => 'Thanh toán qua ví ZaloPay.',
            'fee' => 0,
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $method = \App\Models\PaymentMethod::where('code', 'zalopay')->first();
        $this->assertNotNull($method);

        $this->delete('/admin/payments/'.$method->id)->assertSessionHas('success');
        $this->assertNull(\App\Models\PaymentMethod::find($method->id));
    }
}
