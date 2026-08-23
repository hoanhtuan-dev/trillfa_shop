<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Xác nhận đơn hàng</title>
</head>
<body style="margin:0;padding:0;background:#f4f2ec;font-family:'Inter',Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f2ec;padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:20px;overflow:hidden;border:1px solid #e9e5db;">
                    <!-- Header -->
                    <tr>
                        <td style="background:#1f372b;padding:28px 40px;color:#ffffff;">
                            <span style="display:inline-block;width:34px;height:34px;border-radius:8px;background:#33674d;color:#fff;text-align:center;line-height:34px;font-size:18px;font-weight:700;">T</span>
                            <span style="font-size:24px;font-weight:700;margin-left:8px;">Trillfa <span style="color:#93bda6;">Fa</span></span>
                        </td>
                    </tr>
                    <tr><td style="height:8px;background:#33674d;"></td></tr>
                    <tr>
                        <td style="padding:36px 40px 8px;">
                            <h1 style="margin:0;font-size:22px;color:#1b1a17;">Cảm ơn {{ $order->name }}!</h1>
                            <p style="margin:12px 0 0;color:#6e6a5d;font-size:15px;">Đơn hàng <strong style="color:#1b1a17;">{{ $order->order_number }}</strong> của bạn đã được tiếp nhận và đang được xử lý.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:12px 40px 24px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#faf9f6;border-radius:14px;padding:20px;">
                                <tr>
                                    <td style="padding:8px 0;color:#6e6a5d;font-size:14px;">Ngày đặt</td>
                                    <td align="right" style="font-size:14px;color:#1b1a17;font-weight:600;">{{ $order->placed_at?->format('d/m/Y H:i') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;color:#6e6a5d;font-size:14px;">Thanh toán</td>
                                    <td align="right" style="font-size:14px;color:#1b1a17;font-weight:600;">{{ strtoupper($order->payment_method) }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;color:#6e6a5d;font-size:14px;">Tổng cộng</td>
                                    <td align="right" style="font-size:16px;color:#33674d;font-weight:700;">{{ number_format((float) $order->total, 0, ',', '.') }}đ</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Items -->
                    <tr>
                        <td style="padding:0 40px 8px;">
                            <h2 style="margin:0 0 12px;font-size:16px;color:#1b1a17;">Chi tiết đơn hàng</h2>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 40px 20px;">
                            @foreach($order->items as $item)
                                <div style="padding:12px 0;border-bottom:1px solid #e9e5db;font-size:14px;color:#1b1a17;">
                                    <strong>{{ $item->product_name }}</strong>
                                    @if($item->options) <span style="color:#6e6a5d;">({{ is_array($item->options) ? implode(' / ', $item->options) : $item->options }})</span>@endif
                                    <span style="float:right;color:#6e6a5d;">x{{ $item->quantity }} — <strong style="color:#1b1a17;">{{ number_format((float) $item->subtotal, 0, ',', '.') }}đ</strong></span>
                                </div>
                            @endforeach
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="padding:24px 40px 36px;background:#faf9f6;">
                            <p style="margin:0;color:#6e6a5d;font-size:13px;">Mọi thắc mắc vui lòng liên hệ hotline <strong style="color:#1b1a17;">1900 6363</strong> hoặc email <strong style="color:#1b1a17;">hello@trillfa.com</strong>.</p>
                            <p style="margin:12px 0 0;color:#b0aa9d;font-size:12px;">© {{ date('Y') }} Trillfa Fa. Bảo lưu mọi quyền.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
