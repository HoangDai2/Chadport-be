<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông báo cập nhật trạng thái yêu cầu đơn hàng {{ $order->oder_number }} từ shop</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f4f4f7;
        }
        .container {
            width: 100%;
            padding: 20px;
        }
        .email-content {
            max-width: 600px;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .header {
            padding: 20px;
            background-color: #4CAF50;
            color: #ffffff;
            text-align: center;
        }
        .body {
            padding: 30px 20px;
            color: #333333;
        }
        .body p {
            font-size: 16px;
            color: #666666;
            line-height: 1.6;
        }
        .footer {
            background-color: #f4f4f7;
            padding: 20px;
            text-align: center;
            color: #999999;
            font-size: 12px;
        }
    </style>
</head>
<body>

    <div class="container">
        <table class="email-content" cellpadding="0" cellspacing="0">
            <!-- Header -->
            <tr>
                <td class="header">
                    <h1 style="margin: 0; font-size: 24px;">Thông báo cập nhật trạng thái yêu cầu đơn hàng</h1>
                </td>
            </tr>
            
            <!-- Content -->
            <tr>
                <td class="body">
                    <p>Hello, <strong>{{ $user->email }}</strong></p>
                    <p>Cảm ơn bạn đã ủng hộ shop khi sử dụng dịch vụ mua hàng tại website!</p>
                    <p>
                        Sau khi kiểm tra kỹ càng yêu cầu về đơn hàng {{ $order->oder_number }}, chúng tôi đã quyết định:
                        @if($order->check_refund == 1)
                        <strong>Hoàn trả số tiền: {{ $order->total_money }} VNĐ</strong>
                        <br>
                        <span>{{ $order->note_admin }}</span>
                        @else
                        <strong>Từ chối hoàn số tiền: {{ $order->total_money }} VNĐ</strong>
                        <br>
                        <span>Lý do: {{ $order->note_admin ?? "Mặt hàng đã hết , rất mong quý khách thông cảm!" }}</span>
                        @endif
                    </p>
                    <p style="font-size: 14px; color: #999999; text-align: center;">
                        Xin lỗi vì những sự cố không đáng có! Cảm ơn quý khách.
                    </p>
                </td>
            </tr>
            
            <!-- Footer -->
            <tr>
                <td class="footer">
                    <p style="margin: 0;">If you did not request this, please ignore this email.</p>
                    <p style="margin: 0;">&copy; 2023 Your Company. All rights reserved.</p>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>