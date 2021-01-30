<?php

class ControllerExtensionPaymentRk extends Controller
{

    private static $_domain = 'https://pay.roskassa.net/';

    private function cleanProductName($str)
    {
        return trim(preg_replace('/[^0-9a-zA-Zа-яА-ЯёЁ\-\,\.\(\)\;\_\№\/\+\& ]/ui', '', htmlspecialchars_decode($str, ENT_QUOTES)));
    }

    public function index()
    {
        $this->load->language('extension/payment/rk');
        $this->load->model('checkout/order');

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        if (isset($order_info['email']) && $order_info['email']) {
            $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('config_order_status_id'));
        }

        $data['action'] = self::$_domain;
        $data['sid'] = $this->config->get('payment_rk_account');
        $roskassa_secret = $this->config->get('payment_rk_secret');

        $data['currency_code'] = $order_info['currency_code'];
        if ($data['currency_code'] == 'RUR') {
            $data['currency_code'] = 'RUB';
        }

        $data['total'] = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
        $data['amount'] = number_format($order_info['total'], 2, '.', '');

        $data['cart_order_id'] = $this->session->data['order_id'];

        $sign_arr = array(
            'shop_id' => $data['sid'],           
            'amount' => $data['amount'], 
            'order_id' => $data['cart_order_id'],           
            'currency' => $data['currency_code']    
        );
        ksort($sign_arr);
        $str = http_build_query($sign_arr);
        $data['MNT_SIGNATURE'] = md5($str . $roskassa_secret);

        $data['btn_confirm'] = $this->language->get('text_paw_pay');

        return $this->load->view('extension/payment/rk', $data);
    }

    public function success()
    {
        $this->load->model('checkout/order');

        /* set pending status if not payed yet order will be confirmed otherway confirm will do nothing */
        $this->model_checkout_order->addOrderHistory($_REQUEST['order_id'], $this->config->get('payment_rk_order_status_id'), 'Order confirmed');

        $this->response->redirect($this->url->link('checkout/success'));
    }

    // Pay URL script
    // http://opencart-site/index.php?route=extension/payment/roskassa/callback
    public function callback()
    {
        $this->load->model('checkout/order');

        // get Pay URL data
        $MNT_ID = $this->getVar('shop_id');
        $MNT_TRANSACTION_ID = $this->getVar('order_id');
        //$MNT_OPERATION_ID = $this->getVar('MNT_OPERATION_ID');
        $MNT_AMOUNT = $this->getVar('amount');
        $MNT_CURRENCY_CODE = $this->getVar('currency');
        //$MNT_TEST_MODE = $this->getVar('MNT_TEST_MODE');
        $MNT_SIGNATURE = $this->getVar('sign');

        if ($MNT_TRANSACTION_ID && $MNT_SIGNATURE) {
            $mnt_dataintegrity_code = $this->config->get('payment_rk_secret');
            $sign_arr = array(
                'shop_id' => $MNT_ID,           
                'amount' => $MNT_TRANSACTION_ID,           
                'currency' => $MNT_AMOUNT,           
                'order_id' => $MNT_CURRENCY_CODE           
            );
            ksort($sign_arr);
            $str = http_build_query($sign_arr);
            $check_signature = md5($str . $mnt_dataintegrity_code);

            if ($MNT_SIGNATURE == $check_signature) {
                $order_id = $MNT_TRANSACTION_ID;
                $order_info = $this->model_checkout_order->getOrder($order_id);
                if ($order_info) {
                    $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_rk_order_status_id'), 'Payment complete');

                    
                    $inventoryPositions = array();
                    $this->load->model('account/order');
                    
                    $modelAccountOrder = $this->model_account_order;
                    $products = $modelAccountOrder->getOrderProducts($order_id);
                    $orderTotals = $modelAccountOrder->getOrderTotals($order_id);
                    foreach ($products as $product) {
                        $inventoryPositions[] = array(
                            'name' => $this->cleanProductName($product['name']),
                            'price' => number_format($product['price'], 2, '.', ''),
                            'quantity' => $product['quantity']
                        );
                    }
                    $kassa_delivery = null;
                    foreach ($orderTotals as $orderTotal)
                        if ($orderTotal['code'] == 'shipping') {
                            $kassa_delivery = number_format($orderTotal['value'], 2, '.', '');
                            break;
                        }
                        $kassa_inventory = json_encode($inventoryPositions);
                        self::rkURLResponse($_REQUEST['shop_id'], $_REQUEST['order_id'],
                            $mnt_dataintegrity_code, true, false, true, true, $kassa_inventory, $order_info['email'],
                            $kassa_delivery,$check_signature);

                        exit;
                    }
                }
            }

            echo 'FAIL';
        }

        private static function rkURLResponse($mnt_id, $mnt_transaction_id, $mnt_data_integrity_code, $success = false,
           $repeatRequest = false, $echo = true, $die = true, $kassa_inventory = null, $kassa_customer = null, $kassa_delivery = null,$signature)
        {
            if ($success === true)
                $resultCode = '200';
            elseif ($repeatRequest === true)
                $resultCode = '402';
            else
                $resultCode = '500';
            
            $response = '<?xml version="1.0" encoding="UTF-8" ?>' . "\n";
            $response .= "<MNT_RESPONSE>\n";
            $response .= "<MNT_ID>{$mnt_id}</MNT_ID>\n";
            $response .= "<MNT_TRANSACTION_ID>{$mnt_transaction_id}</MNT_TRANSACTION_ID>\n";
            $response .= "<MNT_RESULT_CODE>{$resultCode}</MNT_RESULT_CODE>\n";
            $response .= "<MNT_SIGNATURE>{$signature}</MNT_SIGNATURE>\n";
            $response .= "<MNT_ATTRIBUTES>\n";
            $response .= "<ATTRIBUTE>\n";
            $response .= "<KEY>cms</KEY>\n";
            $response .= "<VALUE>opencart</VALUE>\n";
            $response .= "</ATTRIBUTE>\n";
            $response .= "<ATTRIBUTE>\n";
            $response .= "<KEY>cms_m</KEY>\n";
            $response .= "<VALUE></VALUE>\n";
            $response .= "</ATTRIBUTE>\n";

            if (!empty($kassa_inventory) || !empty($kassa_customer) || !empty($kassa_delivery)) {
                foreach (array('INVENTORY' => $kassa_inventory, 'CUSTOMER' => $kassa_customer, 'DELIVERY' => $kassa_delivery) as $k => $v)
                    if (!empty($v))
                        $response .= "<ATTRIBUTE><KEY>{$k}</KEY><VALUE>{$v}</VALUE></ATTRIBUTE>\n";
                }

                $response .= "</MNT_ATTRIBUTES>\n";
                $response .= "</MNT_RESPONSE>\n";

                if ($echo === true) {
                    header("Content-type: application/xml");
                    echo $response;
                } else
                return $response;
                if ($die === true)
                    die;
                return '';
            }

            private function getVar($name)
            {
                $value = false;
                if (isset($_POST[$name])) {
                    $value = $_POST[$name];
                } else if (isset($_GET[$name])) {
                    $value = $_GET[$name];
                }

                return $value;
            }
        }