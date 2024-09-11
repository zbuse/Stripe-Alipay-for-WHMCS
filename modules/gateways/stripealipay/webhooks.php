<?php
use Stripe\StripeClient;
use Stripe\Webhook;

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

$gatewayName = 'stripealipay';
$Params = getGatewayVariables($gatewayName);
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
if (!$Params['type']) {
    die("Module Not Activated");
}
if (!isset($_SERVER['HTTP_STRIPE_SIGNATURE'])) {
    die("错误请求");
}

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = null;

try {
    $event = Webhook::constructEvent(
        $payload, $sig_header, $Params['StripeWebhookKey']
    );
} catch(\UnexpectedValueException $e) {
    logTransaction($Params['paymentmethod'], $e, $gatewayName.': Invalid payload');
    http_response_code(400);
    exit();
} catch(Stripe\Exception\SignatureVerificationException $e) {
    logTransaction($Params['paymentmethod'], $e, $gatewayName.': Invalid signature');
    http_response_code(400);
    exit();
}

try {
    if ($event->type == 'payment_intent.succeeded') {
        $stripe = new Stripe\StripeClient($Params['StripeSkLive']);
        $paymentId = $event->data->object->id;

        $paymentIntent = $stripe->paymentIntents->retrieve($paymentId,[]);

        if ($paymentIntent->status == 'succeeded') {
            $invoiceId = checkCbInvoiceID($paymentIntent['metadata']['invoice_id'], $Params['paymentmethod']);
	    checkCbTransID($paymentId);
		
        //Get Transactions fee
        $charge = $stripe->charges->retrieve($paymentIntent->latest_charge, []);
        $balanceTransaction = $stripe->balanceTransactions->retrieve($charge->balance_transaction, []);
        $fee = $balanceTransaction->fee / 100.00;
if ( strtoupper($params['currency']) != strtoupper($balanceTransaction->currency )) {
        $feeexchange = stripealipay_exchange($params['currency'], strtoupper($balanceTransaction->currency ));
        $fee = floor($balanceTransaction->fee * $feeexchange / 100.00);
}
		
            logTransaction($Params['paymentmethod'], $paymentIntent, $gatewayName.': Callback successful');
             addInvoicePayment($invoiceId, $paymentId,$paymentIntent['metadata']['original_amount'],$fee,$params['paymentmethod']);
		}
            echo json_encode(['status' => $paymentIntent->status ]);
    }
    
} catch (Exception $e) {
    logTransaction($Params['paymentmethod'], $e, 'error-callback');
    http_response_code(400);
    echo $e;
}
