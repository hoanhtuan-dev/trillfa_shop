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


    public function test_admin_can_manage_menu(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        $this->post('/admin/menu', [
            'location' => 'header',
            'label' => 'Khuyến mãi',
            'url' => '/shop?sort=price_desc',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $item = \App\Models\MenuItem::where('label', 'Khuyến mãi')->first();
        $this->assertNotNull($item);

        $this->get('/')->assertOk()->assertSee('Khuyến mãi');

        $this->delete('/admin/menu/'.$item->id)->assertSessionHas('success');
        $this->assertNull(\App\Models\MenuItem::find($item->id));
    }

    public function test_admin_can_update_contact_page(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        $this->post('/admin/pages/contact', [
            'contact_heading' => 'Liên hệ Trillfa Fa',
            'contact_intro' => 'Chúng tôi sẵn sàng hỗ trợ bạn.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Liên hệ Trillfa Fa', setting('contact_heading'));
        $this->get('/lien-he')->assertOk()->assertSee('Liên hệ Trillfa Fa');
    }


    public function test_menu_multi_level_renders(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        $this->post('/admin/menu', ['location' => 'header', 'label' => 'Parent', 'url' => '/shop', 'is_active' => '1'])->assertSessionHasNoErrors();
        $parent = \App\Models\MenuItem::where('label', 'Parent')->first();

        $this->post('/admin/menu', ['location' => 'header', 'label' => 'Child', 'url' => '/danh-muc/ao-nu', 'parent_id' => $parent->id, 'is_active' => '1'])->assertSessionHasNoErrors();
        $child = \App\Models\MenuItem::where('label', 'Child')->first();

        $this->post('/admin/menu', ['location' => 'header', 'label' => 'Grandchild', 'url' => '/danh-muc/ao-nu', 'parent_id' => $child->id, 'is_active' => '1'])->assertSessionHasNoErrors();

        $this->get('/')
            ->assertOk()
            ->assertSee('Parent')
            ->assertSee('Child')
            ->assertSee('Grandchild');
    }


    public function test_menu_category_auto_renders_subcategories(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        $cat = \App\Models\Category::create(['name' => 'Cat Auto', 'slug' => 'cat-auto-'.uniqid(), 'is_active' => true]);
        \App\Models\Category::create(['name' => 'Sub Auto', 'slug' => 'sub-auto-'.uniqid(), 'is_active' => true, 'parent_id' => $cat->id]);

        $this->post('/admin/menu', [
            'location' => 'header',
            'label' => 'Cat Auto Menu',
            'url' => '/danh-muc/'.$cat->slug,
            'type' => 'category',
            'category_id' => $cat->id,
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->get('/')
            ->assertOk()
            ->assertSee('Cat Auto Menu')
            ->assertSee('Sub Auto');
    }


    public function test_quick_checkout_guest_creates_pending_account(): void
    {
        $this->withSession(['marker' => 'x']);
        $product = Product::first();
        $this->postJson('/api/cart/add', ['product_id' => $product->id, 'quantity' => 2])->assertOk();

        $ordersBefore = \App\Models\Order::count();

        $this->post('/thanh-toan-nhanh', [
            'name' => 'Khách Vãng Lai',
            'phone' => '0912345888',
            'address' => '123 Nguyễn Huệ, Quận 1',
            'terms' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('orders', $ordersBefore + 1);

        $order = \App\Models\Order::latest('id')->first();
        $this->assertSame('0912345888', $order->phone);
        $this->assertNull($order->email);
        $this->assertSame('123 Nguyễn Huệ, Quận 1', $order->address);

        // Pending account silently persisted (inactive) and linked to the order
        $user = \App\Models\User::where('phone', '0912345888')->first();
        $this->assertNotNull($user);
        $this->assertFalse($user->is_active);
        $this->assertSame($user->id, $order->user_id);

        // Cart cleared and success page encourages completing registration
        $this->getJson('/api/cart')->assertJsonPath('count', 0);
        $this->get(route('checkout.quick-success', $order))
            ->assertOk()
            ->assertSee('Hoàn thiện tài khoản')
            ->assertSee('0912345888');
    }

    public function test_guest_can_complete_pending_account(): void
    {
        $this->withSession(['marker' => 'x']);
        $product = Product::first();
        $this->postJson('/api/cart/add', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $this->post('/thanh-toan-nhanh', [
            'name' => 'Khách Cần Hoàn Thiện',
            'phone' => '0912345999',
            'terms' => '1',
        ])->assertSessionHasNoErrors();

        $order = \App\Models\Order::latest('id')->first();

        $this->get(route('account.complete', $order))
            ->assertOk()
            ->assertSee('Hoàn thiện tài khoản');

        $this->post(route('account.complete.store', $order), [
            'email' => 'guest.completed@example.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ])->assertRedirect(route('account.dashboard'));

        $this->assertDatabaseHas('users', ['email' => 'guest.completed@example.com', 'is_active' => 1]);
        $this->assertAuthenticated();
        $this->assertSame('guest.completed@example.com', $order->fresh()->email);
    }

    public function test_quick_checkout_links_existing_customer(): void
    {
        $this->withSession(['marker' => 'x']);
        $user = User::where('email', 'customer@trillfa.com')->first();
        $product = Product::first();

        $this->postJson('/api/cart/add', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $this->post('/thanh-toan-nhanh', [
            'name' => $user->name,
            'phone' => $user->phone,
            'terms' => '1',
        ])->assertSessionHasNoErrors();

        $order = \App\Models\Order::latest('id')->first();
        $this->assertSame($user->id, $order->user_id);

        $this->get(route('checkout.quick-success', $order))
            ->assertOk()
            ->assertSee('Đăng ký để quản lý đơn tốt hơn');
    }

    public function test_newsletter_subscribe_stores_email(): void
    {
        $this->post('/dang-ky-ban-tin', ['email' => 'subscriber@example.com'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'subscriber@example.com']);
    }

    public function test_admin_can_update_widget_content(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        $this->get('/admin/widgets')->assertOk();

        $this->post('/admin/widgets', [
            'enabled_announcement' => '1',
            'widget_announcement_text' => 'Thanh thông báo mới',
            'enabled_cta' => '1',
            'widget_cta_title' => 'Tiêu đề CTA mới',
            'widget_cta_subtitle' => 'Mô tả CTA mới',
            'widget_cta_button_text' => 'Nút mới',
            'widget_cta_button_link' => '/shop?sort=price_asc',
            'enabled_featured' => '1',
            'limit_featured' => 12,
        ])->assertSessionHas('success');

        $this->assertSame('Thanh thông báo mới', setting('widget_announcement_text'));
        $this->assertSame('Tiêu đề CTA mới', setting('widget_cta_title'));
        $this->assertSame('Nút mới', setting('widget_cta_button_text'));
        $this->assertSame(12, (int) setting('widget_featured_limit'));
    }


    public function test_admin_can_create_and_view_landing_page(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        $this->get('/admin/pages')->assertOk();

        $this->post('/admin/pages', [
            'title' => 'Bộ sưu tập mới',
            'slug' => 'bo-suu-tap-moi',
            'template' => 'landing',
            'content' => '<p>Khám phá bộ sưu tập mới của Trillfa Fa.</p>',
            'hero_heading' => 'Bộ sưu tập mới 2026',
            'hero_subtitle' => 'Mô tả ngắn bộ sưu tập.',
            'hero_button_text' => 'Mua ngay',
            'hero_button_link' => '/shop',
            'hero_button_category_id' => \App\Models\Category::first()->id,
            'is_active' => '1',
            'product_ids' => [Product::first()->id],
        ])->assertSessionHas('success');

        $page = \App\Models\CustomPage::where('slug', 'bo-suu-tap-moi')->first();
        $this->assertNotNull($page);
        $this->assertSame('Bộ sưu tập mới', $page->title);
        $this->assertSame('landing', $page->template);

        // Public render as guest
        $this->get('/trang/bo-suu-tap-moi')
            ->assertOk()
            ->assertSee('Bộ sưu tập mới 2026')
            ->assertSee(route('shop.category', \App\Models\Category::first()->slug));
    }


    public function test_admin_can_create_category(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        $this->post('/admin/categories', [
            'name' => 'Danh mục test create',
            'slug' => 'danh-muc-tu-chinh',
            'parent_id' => '',
            'is_active' => '1',
            'sort_order' => 3,
            'icon' => 'tag',
        ])->assertSessionHasNoErrors();

        $cat = \App\Models\Category::where('name', 'Danh mục test create')->first();
        $this->assertNotNull($cat);
        $this->assertSame('danh-muc-tu-chinh', $cat->slug);
    }


    public function test_menu_can_link_to_landing_page(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        $page = \App\Models\CustomPage::create([
            'title' => 'Bộ sưu tập thu đông 2026',
            'slug' => 'bo-suu-tap-thu-dong-2026-'.uniqid(),
            'template' => 'landing',
            'content' => '<p>Nội dung.</p>',
            'is_active' => true,
            'published_at' => now(),
        ]);

        $this->post('/admin/menu', [
            'location' => 'header',
            'label' => $page->title,
            'type' => 'landing_page',
            'custom_page_id' => $page->id,
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $item = \App\Models\MenuItem::where('label', $page->title)->first();
        $this->assertNotNull($item);
        $this->assertSame('landing_page', $item->type);
        $this->assertSame($page->url, $item->getUrl());

        // Menu renders on the storefront header
        $this->get('/')->assertOk()->assertSee($page->title);
    }


    public function test_quick_checkout_success_blocks_other_orders(): void
    {
        $this->withSession(['marker' => 'x']);
        $product = Product::first();
        $this->postJson('/api/cart/add', ['product_id' => $product->id, 'quantity' => 1])->assertOk();
        $this->post('/thanh-toan-nhanh', ['name' => 'Khách A', 'phone' => '0912000001', 'terms' => '1'])->assertSessionHasNoErrors();
        $order = \App\Models\Order::latest('id')->first();

        // A different visitor (no matching session order id) is blocked.
        session()->forget('quick_order_id');
        $this->get(route('checkout.quick-success', $order))->assertNotFound();
        $this->get(route('account.complete', $order))->assertNotFound();
    }


    public function test_non_admin_cannot_access_admin(): void
    {
        $customer = User::where('email', 'customer@trillfa.com')->first();
        $this->actingAs($customer)->get('/admin')->assertForbidden();
    }

    public function test_draft_and_unpublished_posts_not_public(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $post = \App\Models\Post::create([
            'title' => 'Nháp bí mật',
            'slug' => 'nhap-bi-mat-'.uniqid(),
            'status' => 'draft',
            'excerpt' => 'x',
            'body' => '<p>x</p>',
            'author_id' => $admin->id,
        ]);

        $this->get('/blog/'.$post->slug)->assertNotFound();
    }

    public function test_guest_redirected_from_account(): void
    {
        $this->get('/tai-khoan')->assertRedirect(route('login'));
        $this->get('/yeu-thich')->assertRedirect(route('login'));
    }


    public function test_studio_requires_auth(): void
    {
        $this->get('/studio')->assertRedirect(route('login'));
    }

    public function test_studio_ideate_returns_prompts(): void
    {
        $this->actingAs(User::where('email', 'admin@trillfa.com')->first());

        $this->postJson('/studio/ideate', [
            'idea' => 'Váy dạ hội biển xanh',
            'preset_ids' => [],
        ])->assertOk()->assertJsonStructure(['history_id', 'image_prompt_en', 'video_prompt_en', 'creative_level', 'adherence']);
    }

    public function test_studio_ideate_honours_creative_level(): void
    {
        $this->actingAs(User::where('email', 'admin@trillfa.com')->first());

        // Low creativity -> strict adherence (directive stamped into the prompt).
        $low = $this->postJson('/studio/ideate', ['idea' => 'Váy dạ hội', 'preset_ids' => [], 'creative_level' => 2])->assertOk()->json();
        $this->assertSame(2, $low['creative_level']);
        $this->assertSame(9, $low['adherence']);
        $this->assertStringContainsString('low', strtolower($low['image_prompt_en']));

        // High creativity -> loose adherence.
        $high = $this->postJson('/studio/ideate', ['idea' => 'Váy dạ hội', 'preset_ids' => [], 'creative_level' => 9])->assertOk()->json();
        $this->assertSame(9, $high['creative_level']);
        $this->assertSame(2, $high['adherence']);
        $this->assertStringContainsString('high', strtolower($high['image_prompt_en']));

        // The video prompt is always derived consistent with the image prompt.
        $this->assertNotSame($low['image_prompt_en'], $low['video_prompt_en']);
        $this->assertNotEmpty($low['video_prompt_en']);
    }

    public function test_studio_generate_image_and_video(): void
    {
        $user = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($user);

        // 2D image (cost 1) runs synchronously in tests.
        $image = $this->postJson('/studio/generate', [
            'prompt' => 'photo of a blue evening gown',
            'history_id' => null,
        ])->assertOk()->json();

        $gen = \App\Models\Generation::find($image['generation_id']);
        $this->assertSame('pending', $gen->status);
        $this->getJson('/studio/generations/'.$gen->id)->assertOk();
        $this->assertSame('completed', $gen->fresh()->status);
        $this->assertSame('image', $gen->type);
        $this->assertNotNull($gen->fresh()->media_url);
        $this->assertSame(999, $user->fresh()->credits_balance);
        // Creation metadata recorded (real elapsed time + info used).
        $this->assertNotNull($gen->fresh()->elapsed_ms);
        $this->assertSame('image', ($gen->fresh()->meta['type'] ?? null));

        // Video (cost 10).
        $video = $this->postJson('/studio/video', [
            'prompt' => 'catwalk video of a blue evening gown',
            'base_image' => $gen->media_url,
            'camera' => '360 degree rotating camera shot',
            'history_id' => null,
        ])->assertOk()->json();

        $vgen = \App\Models\Generation::find($video['generation_id']);
        $this->assertSame('pending', $vgen->status);
        $this->getJson('/studio/generations/'.$vgen->id)->assertOk();
        $this->assertSame('completed', $vgen->fresh()->status);
        $this->assertSame('video', $vgen->type);
        $this->assertNotNull($vgen->fresh()->media_url);
        $this->assertSame(989, $user->fresh()->credits_balance);
        // Video metadata recorded.
        $this->assertNotNull($vgen->fresh()->elapsed_ms);
        $this->assertSame('video', ($vgen->fresh()->meta['type'] ?? null));
    }


    public function test_studio_is_admin_only(): void
    {
        $customer = User::where('email', 'customer@trillfa.com')->first();
        $this->actingAs($customer)->get('/studio')->assertForbidden();

        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin)->get('/studio')->assertOk();
    }


    public function test_studio_library_renders_for_admin(): void
    {
        $this->get('/studio/library')->assertRedirect(route('login'));

        $customer = User::where('email', 'customer@trillfa.com')->first();
        $this->actingAs($customer)->get('/studio/library')->assertForbidden();

        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin)->get('/studio/library')->assertOk()->assertSee('Thư viện');
        $this->actingAs($admin)->get('/studio')->assertOk()->assertSee('workflowSteps')->assertSee('previewId');
    }

    public function test_studio_generation_resolution_ratio_duration(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        $img = $this->postJson('/studio/generate', ['prompt' => 'a silk gown', 'resolution' => '2K', 'ratio' => '9:16'])->assertOk();
        $gen = \App\Models\Generation::find($img->json('generation_id'));
        $this->assertSame('2K', $gen->resolution);
        $this->assertSame('9:16', $gen->ratio);

        $src = $admin->generations()->create([
            'type' => 'image', 'status' => 'completed', 'prompt' => 'src',
            'media_url' => '/storage/studio/test.jpg', 'credits_cost' => 1,
        ]);
        $vid = $this->postJson('/studio/video', [
            'prompt' => 'walk', 'base_image' => '/storage/studio/test.jpg',
            'camera' => 'runway', 'resolution' => '1080', 'duration' => '15',
        ])->assertOk();
        $vg = \App\Models\Generation::find($vid->json('generation_id'));
        $this->assertSame('1080', $vg->resolution);
        $this->assertSame('15', $vg->duration);
    }

    public function test_studio_latest_endpoint(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin)->getJson('/studio/latest')->assertOk()->assertJsonStructure(['items' => []]);
    }

    public function test_studio_pattern_and_tryon_pages(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        $this->get('/studio/pattern')->assertOk()->assertSee('Pattern Maker');
        $this->get('/studio/tryon')->assertOk()->assertSee('Virtual Try-On');

        $p = $this->postJson('/studio/pattern', ['prompt' => 'floral toile vintage'])->assertOk();
        $this->assertNotEmpty($p->json('generation_id'));
        $pg = \App\Models\Generation::find($p->json('generation_id'));
        $this->assertSame('pending', $pg->status);
        $this->getJson('/studio/generations/'.$pg->id)->assertOk();
        $this->assertSame('completed', $pg->fresh()->status);

        $t = $this->postJson('/studio/tryon', ['prompt' => 'silk a-line dress'])->assertOk();
        $this->assertNotEmpty($t->json('generation_id'));
        $tg = \App\Models\Generation::find($t->json('generation_id'));
        $this->getJson('/studio/generations/'.$tg->id)->assertOk();
        $this->assertSame('completed', $tg->fresh()->status);
    }

    public function test_studio_preset_manager_and_references(): void
    {
        $customer = User::where('email', 'customer@trillfa.com')->first();
        $this->actingAs($customer)->get('/studio/presets')->assertForbidden();

        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin)->get('/studio/presets')->assertOk()->assertSee('Prompt Templates');

        $this->actingAs($admin)->getJson('/studio/references')
            ->assertOk()
            ->assertJsonStructure(['items' => []]);

        $this->actingAs($admin)->post('/studio/presets', [
            'category' => 'style',
            'ui_label' => 'Test Key',
            'prompt_injection' => 'test value, elegant',
            'sort_order' => 99,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('presets', ['ui_label' => 'Test Key', 'category' => 'style']);
    }

    public function test_studio_settings_and_api_admin_only(): void
    {
        $this->get('/studio/settings')->assertRedirect(route('login'));
        $this->get('/studio/api')->assertRedirect(route('login'));

        $customer = User::where('email', 'customer@trillfa.com')->first();
        $this->actingAs($customer)->get('/studio/settings')->assertForbidden();

        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin)->get('/studio/settings')->assertOk();
        $this->actingAs($admin)->get('/studio/api')->assertOk();
    }

    public function test_studio_update_settings_and_api_key(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        $this->post('/studio/settings', [
            'image_credits' => 3,
            'video_credits' => 20,
            'max_generations' => 60,
            'image_provider' => 'qwen',
            'prompt_provider' => 'gemini',
            'vision_provider' => 'gemini',
            'prompt_model' => 'gemini-2.5-flash',
            'image_model' => 'flux-1.1-schnell',
            'wan_model' => 'wan2.7-image-pro',
            'qwen_model' => 'qwen-image-plus',
            'qwen_edit_model' => 'qwen-image-edit',
            'video_model' => 'wan2.5-t2v',
            'vision_model' => 'qwen-vl-plus',
            'dashscope_base' => 'https://dashscope-intl.aliyuncs.com',
            'processing' => 'queue',
            'image_resolution' => '1K',
            'video_resolution' => '1080',
            'image_ratio' => '9:16',
            'video_duration' => '15',
        ])->assertSessionHas('success');

        $this->assertSame('3', setting('studio_image_credits'));
        $this->assertSame('20', setting('studio_video_credits'));
        $this->assertSame('qwen', setting('studio_image_provider'));
        $this->assertSame('gemini-2.5-flash', setting('studio_prompt_model'));
        $this->assertSame('qwen-image-plus', setting('studio_qwen_model'));
        $this->assertSame('https://dashscope-intl.aliyuncs.com', setting('studio_dashscope_base'));
        $this->assertSame('https://token-plan.ap-southeast-1.maas.aliyuncs.com', setting('studio_dashscope_token_plan_base'));
        $this->assertSame('1', setting('studio_face_sync_enabled'));
        $this->assertSame('queue', setting('studio_processing'));
        $this->assertSame('1K', setting('studio_image_resolution'));
        $this->assertSame('1080', setting('studio_video_resolution'));
        $this->assertSame('9:16', setting('studio_image_ratio'));
        $this->assertSame('15', setting('studio_video_duration'));

        $this->post('/studio/api', ['key_gemini' => 'AIzaTestKey'])
            ->assertSessionHas('success');

        $this->assertSame('AIzaTestKey', studio_api_key('gemini'));
        $this->assertNull(studio_api_key('wan'));
    }


    public function test_studio_suggest_from_image(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        $response = $this->post('/studio/suggest', [
            'image' => \Illuminate\Http\UploadedFile::fake()->image('ref.jpg', 400, 500),
        ])->assertOk();

        $response->assertJsonStructure([
            'preset_ids', 'styles', 'image_prompt_en', 'video_prompt_en', 'creative_level', 'adherence',
        ]);
        $data = $response->json();
        $this->assertNotEmpty($data['image_prompt_en']);
        $this->assertNotEmpty($data['video_prompt_en']);
        $this->assertSame(6, (int) $data['creative_level']);
        $this->assertSame(5, (int) $data['adherence']);
    }


    public function test_studio_presets_camera_lens_video_scene(): void
    {
        $camera = \App\Models\Preset::category('camera')->get();
        $this->assertGreaterThanOrEqual(8, $camera->count(), 'Phải có ít nhất 8 góc máy ảnh.');
        $this->assertNotEmpty($camera->first()->note, 'Góc máy phải có chú giải (note).');
        $this->assertStringContainsString('eye', strtolower($camera->first()->prompt_injection));

        $lens = \App\Models\Preset::category('lens')->get();
        $this->assertGreaterThanOrEqual(7, $lens->count(), 'Phải có ít nhất 7 tiêu cự ống kính.');
        $this->assertNotEmpty($lens->first()->note);

        $video = \App\Models\Preset::category('video_scene')->get();
        $this->assertGreaterThanOrEqual(8, $video->count(), 'Phải có ít nhất 8 kịch bản quay.');
        $this->assertNotEmpty($video->first()->note);
        $this->assertStringContainsString('runway', strtolower($video->first()->prompt_injection));
    }


    public function test_studio_api_qwen_edit_key(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        // The API page shows a dedicated card for the Qwen image-edit key.
        $this->get('/studio/api')->assertOk()->assertSee('Qwen Edit')
            ->assertSee('QWEN_EDIT_KEY');

        // studio_api_key('qwen_edit') resolves the dedicated setting (encrypted).
        set_setting('api_qwen_edit_key', \Illuminate\Support\Facades\Crypt::encryptString('sk-edit-123456'));
        $this->assertSame('sk-edit-123456', studio_api_key('qwen_edit'));
    }


    public function test_studio_dashscope_base_url_routing(): void
    {
        // Pay-As-You-Go (sk-…) -> dashscope-intl host.
        $this->assertSame('https://dashscope-intl.aliyuncs.com', dashscope_base_url('sk-abcdef123456'));
        // Token / Coding Plan (sk-sp-…) -> token-plan host (separate, never mixed).
        $this->assertSame('https://token-plan.ap-southeast-1.maas.aliyuncs.com', dashscope_base_url('sk-sp-abcdef123456'));
        // A custom pay-as-you-go base is honoured.
        set_setting('studio_dashscope_base', 'https://dashscope.aliyuncs.com');
        $this->assertSame('https://dashscope.aliyuncs.com', dashscope_base_url('sk-abc'));
    }


    public function test_studio_show_heals_stuck_processing_generation(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        // A generation left 'processing' by a killed request (old updated_at) should be
        // healed by the poll (show) endpoint instead of spinning "Đang tạo" forever.
        $gen = $admin->generations()->create([
            'type' => 'image', 'status' => 'processing', 'prompt' => 'x', 'credits_cost' => 1,
        ]);
        // Force a stale updated_at (not mass-assignable) so show() sees it as stuck.
        \Illuminate\Support\Facades\DB::table('generations')->where('id', $gen->id)
            ->update(['updated_at' => now()->subMinutes(20)]);

        $this->getJson('/studio/generations/'.$gen->id)->assertOk();
        $this->assertSame('failed', $gen->fresh()->status);
        $this->assertStringContainsString('Hết thời gian xử lý', $gen->fresh()->error);
        $this->assertSame(1001, $admin->fresh()->credits_balance); // 1000 + 1 refund
    }


    public function test_studio_cancel_and_delete_generation(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        // Cancel a pending generation -> cancelled + refund.
        $gen = $admin->generations()->create([
            'type' => 'image', 'status' => 'pending', 'prompt' => 'x', 'credits_cost' => 5,
        ]);
        $this->postJson('/studio/generations/'.$gen->id.'/cancel')->assertOk();
        $this->assertSame('cancelled', $gen->fresh()->status);
        $this->assertSame(1005, $admin->fresh()->credits_balance);

        // Delete a generation.
        $g2 = $admin->generations()->create(['type' => 'image', 'status' => 'completed', 'prompt' => 'x']);
        $this->deleteJson('/studio/generations/'.$g2->id)->assertOk();
        $this->assertNull(\App\Models\Generation::find($g2->id));
    }

}