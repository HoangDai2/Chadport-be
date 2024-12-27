<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông báo đơn hàng bị huỷ {{ $order->oder_number }}</title>
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
                    <h1 style="margin: 0; font-size: 24px;">Thông báo đơn hàng bị huỷ {{ $order->oder_number }}</h1>
                </td>
            </tr>
            
            <!-- Content -->
            <tr>
                <td class="body">
                    <p>
                        Đơn hàng có mã đơn : {{ $order->oder_number}} vừa được khách hàng có email: {{ $user->email}} yêu cầu huỷ. Vui lòng kiểm tra lại!
                    </p>
                    <br>
                    @php
                        $data = json_decode($order->note_user, true);
                    @endphp

                    <p><strong>Lý do:</strong> {{ $data['reason'] }}</p>
                    <p>Yêu cầu hoàn về :</p>
                    <p><strong>Số tài khoản:</strong> {{ $data['account_info']['account_number'] }}</p>
                    <p><strong>Tên ngân hàng:</strong> {{ $data['account_info']['bank_name'] }}</p>
                    <p><strong>Chủ tài khoản:</strong> {{ $data['account_info']['account_holder'] }}</p>
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