<?php
use Stripe\StripeClient;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function stripealipay_MetaData()
{
    return array(
        'DisplayName' => 'Stripe Alipay',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

function stripealipay_config($params)
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Stripe Alipay',
        ),
        'StripeSkLive' => array(
            'FriendlyName' => 'SK_LIVE 密钥',
            'Type' => 'text',
            'Size' => 30,
            'Description' => '填写从Stripe获取到的密钥（SK_LIVE）',
        ),
        'StripeWebhookKey' => array(
            'FriendlyName' => 'Webhook 密钥',
            'Type' => 'text',
            'Size' => 30,
            'Description' => '填写从Stripe获取到的Webhook密钥签名',
        ),
        'StripeCurrency' => array(
            'FriendlyName' => '发起交易货币[可留空]',
            'Type' => 'text',
            'Size' => 30,
            'Description' => '默认获取WHMCS的货币，与您设置的发起交易货币进行汇率转换，再使用转换后的价格和货币向Stripe请求',
        ),
        'RefundFixed' => array(
            'FriendlyName' => '退款扣除固定金额',
            'Type' => 'text',
            'Size' => 30,
      'Default' => '0.00',
      'Description' => '$'
        ),
        'RefundPercent' => array(
            'FriendlyName' => '退款扣除百分比金额',
            'Type' => 'text',
            'Size' => 30,
      'Default' => '0.00',
      'Description' => "% <br><br> <div class='alert alert-success' role='alert' style='margin-bottom: 0px;'>Webhook设置 <a href='https://dashboard.stripe.com/webhooks' target='_blank'><span class='glyphicon glyphicon-new-window'></span> Stripe webhooks</a> 侦听的事件:payment_intent.succeeded <br>
      Stripe webhook " .$params['systemurl']."modules/gateways/stripealipay/webhooks.php
               </div><style>* {font-family: Microsoft YaHei Light , Microsoft YaHei}</style>"
        )
    );
}

function stripealipay_link($params)
{
  global $_LANG;
  $amount = ceil($params['amount'] * 100.00);
  $setcurrency = $params['currency'];
  $Methodtype = 'alipay';
  if ($params['StripeCurrency']) {
      $exchange = stripealipay_exchange($params['currency'], strtoupper($params['StripeCurrency']));
      if (!$exchange) {
          return '<div class="alert alert-danger text-center" role="alert">支付汇率错误，请联系客服进行处理</div>';
      }
  $amount = floor($params['amount'] * $exchange * 100.00);
  $setcurrency = $params['StripeCurrency'];
  }

    try {
        $stripe = new Stripe\StripeClient($params['StripeSkLive']);
        $paymentIntent = null;
        $paymentMethod = $stripe->paymentMethods->create(['type' => $Methodtype]);
        $paymentIntentParams = [
        'amount' => $amount,
        'currency' => $setcurrency,
        'payment_method' => $paymentMethod->id,
        'payment_method_types' => [$Methodtype],
        'confirmation_method' => 'manual', // 手动确认支付
        'confirm' => true, // 直接确认支付
        'return_url' => $params['systemurl'] . 'viewinvoice.php?paymentsuccess=true&id=' . $params['invoiceid'],
        'description' => $params['companyname'] . $_LANG['invoicenumber'] . $params['invoiceid'],
        'metadata' => [
                    'invoice_id' => $params['invoiceid'],
                    'original_amount' => $params['amount']
                ],
            ];
        $paymentIntent = $stripe->paymentIntents->create($paymentIntentParams);

    } catch (Exception $e) {
        return '<div class="alert alert-danger text-center" role="alert">支付网关错误，请联系客服进行处理'. $e .'</div>';
    }

    if ($paymentIntent->status == 'requires_action') {
        $url = explode("?", $paymentIntent['next_action']['alipay_handle_redirect']['url']);
        $secret = explode("=", $url[1])[1];
        return '<form action="' . $url[0] . '" method="get"><input type="hidden" name="client_secret" value="' . $secret . '"><input type="submit" class="btn btn-primary" value="' . $params['langpaynow'] . '" /></form>';
    }

    return '<div class="alert alert-danger text-center" role="alert">发生错误，请创建工单联系客服处理</div>';
}
function stripealipay_refund($params)
{
    $stripe = new Stripe\StripeClient($params['StripeSkLive']);
    $amount = ($params['amount'] - $params['RefundFixed']) / ($params['RefundPercent'] / 100 + 1);
    try {
        $responseData = $stripe->refunds->create([
            'payment_intent' => $params['transid'],
            'amount' => $amount * 100.00,
            'metadata' => [
                'invoice_id' => $params['invoiceid'],
                'original_amount' => $params['amount'],
            ]
        ]);
        return array(
            'status' => ($responseData->status === 'succeeded' || $responseData->status === 'pending') ? 'success' : 'error',
            'rawdata' => $responseData,
            'transid' => $params['transid'],
            'fees' => $params['amount'],
        );
    } catch (Exception $e) {
        return array(
            'status' => 'error',
            'rawdata' => $e->getMessage(),
            'transid' => $params['transid'],
            'fees' => $params['amount'],
        );
    }
}
function stripealipay_exchange($from, $to)
{
    try {
        $url = 'https://raw.githubusercontent.com/DyAxy/ExchangeRatesTable/main/data.json';

        $result = file_get_contents($url, false);
        $result = json_decode($result, true);
        return $result['rates'][strtoupper($to)] / $result['rates'][strtoupper($from)];
    } catch (Exception $e) {
        echo "Exchange error: " . $e;
        return "Exchange error: " . $e;
    }
}
