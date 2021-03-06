<?php

class ControllerPaymentsveainvoice extends Controller {

    /**
     * Returns the currency used for an invoice country.
     */
    protected function getInvoiceCurrency( $countryCode ) {
        $country_currencies = array(
            'SE' => 'SEK',
            'NO' => 'NOK',
            'FI' => 'EUR',
            'DK' => 'DKK',
            'NL' => 'EUR',
            'DE' => 'EUR'
        );
        return $country_currencies[$countryCode];
    }

    public function index() {
        $this->load->language('payment/svea_invoice');
        $this->load->model('checkout/order');
        //Definitions
        $data['text_private_or_company'] = $this->language->get("text_private_or_company");
        $data['text_company'] = $this->language->get("text_company");
        $data['text_private'] = $this->language->get("text_private");
        $data['text_ssn'] = $this->language->get("text_ssn");
        $data['text_vat_no'] = $this->language->get("text_vat_no");
        $data['text_get_address'] = $this->language->get("text_get_address");
        $data['text_invoice_address'] = $this->language->get('text_invoice_address');
        $data['text_shipping_address'] = $this->language->get('text_shipping_address');
        $data['text_birthdate'] = $this->language->get("text_birthdate");
        $data['text_initials'] = $this->language->get("text_initials");
        $data['text_vat_no'] = $this->language->get("text_vat_no");
        $data['svea_invoice_shipping_billing'] = $this->config->get('svea_invoice_shipping_billing');


        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['button_back'] = $this->language->get('button_back');

        $data['continue'] = 'index.php?route=checkout/success';

        if ($this->request->get['route'] != 'checkout/guest_step_3') {
            $data['back'] = 'index.php?route=checkout/payment';
        } else {
            $data['back'] = 'index.php?rout=checkout/guest_step_2';
        }

        //Get the country
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $data['countryCode'] = $order_info['payment_iso_code_2'];

        $data['logo'] = "<img src='admin/view/image/payment/".$this->getLogo($order_info['payment_iso_code_2'])."/svea_invoice.png'>";


//        $this->id = 'payment';

        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/svea_invoice.tpl')) {
                return $this->load->view($this->config->get('config_template') . '/template/payment/svea_invoice.tpl', $data);
        } else {
                return $this->load->view('default/template/payment/svea_invoice.tpl', $data);
        }
    }

    private function responseCodes($err,$msg = "") {
        $this->load->language('payment/svea_invoice');

        $definition = $this->language->get("response_$err");
        if (preg_match("/^response/", $definition))
             $definition = $this->language->get("response_error"). " $msg";

        return $definition;
    }

    public function confirm() {

        $this->load->language('payment/svea_invoice');
        $this->load->language('total/svea_fee');

        //Load models
        $this->load->model('checkout/order');
        $this->load->model('payment/svea_invoice');
        $this->load->model('checkout/coupon');
        $this->load->model('account/address');

        if (floatval(VERSION) >= 1.5) {
            $this->load->model('checkout/voucher');
        }
        //Load SVEA includes
        include(DIR_APPLICATION.'../svea/Includes.php');

        //Get order information
        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $countryCode = $order['payment_iso_code_2'];
        //Testmode
        if($this->config->get('svea_invoice_testmode_'.$countryCode) !== NULL){
            $conf = ( $this->config->get('svea_invoice_testmode_'.$countryCode) == "1" )
                    ? new OpencartSveaConfigTest($this->config) : new OpencartSveaConfig($this->config);
        }
        else {
            $response = array("error" => $this->responseCodes(40001,"The country is not supported for this paymentmethod"));
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($response));

        }

        $svea = WebPay::createOrder($conf);

        //Check if company or private
        $company = ($_GET['company'] == 'true') ? true : false;

        // Get the products in the cart
        $products = $this->cart->getProducts();

        // make sure we use the currency matching the invoice clientno
        $this->load->model('localisation/currency');
        $currency_info = $this->model_localisation_currency->getCurrencyByCode( $this->getInvoiceCurrency($countryCode) );
        $currencyValue = $currency_info['value'];

        //Products
        $svea = $this->formatOrderRows($svea,$products,$currencyValue);
        //get all addons
        $addons = $this->formatAddons();

        //extra charge addons like shipping and invoice fee
        foreach ($addons as $addon) {

            if($addon['value'] >= 0){
                $vat = floatval($addon['value'] * $currencyValue) * (intval($addon['tax_rate']) / 100 );
                $svea = $svea
                    ->addOrderRow(WebPayItem::orderRow()
                    ->setQuantity(1)
                    ->setAmountIncVat(floatval($addon['value'] * $currencyValue) + $vat)
                    ->setVatPercent(intval($addon['tax_rate']))
                    ->setName(isset($addon['title']) ? $addon['title'] : "")
                    ->setUnit($this->language->get('unit'))
                    ->setArticleNumber($addon['code'])
                    ->setDescription(isset($addon['text']) ? $addon['text'] : "")
                );

            //voucher(-)
            } elseif ($addon['value'] < 0 && $addon['code'] == 'voucher') {
                $svea = $svea
                    ->addDiscount(WebPayItem::fixedDiscount()
                        ->setDiscountId($addon['code'])
                        ->setAmountIncVat(floatval(abs($addon['value']) * $currencyValue))
                        ->setVatPercent(0)//no vat when using a voucher
                        ->setName(isset($addon['title']) ? $addon['title'] : "")
                        ->setUnit($this->language->get('unit'))
                        ->setDescription(isset($addon['text']) ? $addon['text'] : "")
                );
            }
            //discounts (-)
            else {
                $taxRates = $this->getTaxRatesInOrder($svea);
                $discountRows = Svea\Helper::splitMeanToTwoTaxRates( (abs($addon['value']) * $currencyValue), $addon['tax_rate'], $addon['title'], $addon['text'], $taxRates );
                foreach($discountRows as $row) {
                    $svea = $svea->addDiscount( $row );
                }
            }
         }

        //Seperates the street from the housenumber according to testcases for NL and DE
        if($order["payment_iso_code_2"] == "DE" || $order["payment_iso_code_2"] == "NL") {
            $addressArr = Svea\Helper::splitStreetAddress( $order['payment_address_1'] );
        }  else {
            $addressArr[1] =  $order['payment_address_1'];
            $addressArr[2] =  "";
        }

        if ($company == TRUE){  // company customer

            $item = Item::companyCustomer();

            $item = $item->setEmail($order['email'])
                         ->setCompanyName($order['payment_company'])
                         ->setStreetAddress($addressArr[1],$addressArr[2])
                         ->setCoAddress($order['payment_address_2'])
                         ->setZipCode($order['payment_postcode'])
                         ->setLocality($order['payment_city'])
                         ->setIpAddress($order['ip'])
                         ->setPhoneNumber($order['telephone']);
            if($order["payment_iso_code_2"] == "DE" || $order["payment_iso_code_2"] == "NL") {
                $item = $item->setVatNumber(isset($_GET['vatno']) ? $_GET['vatno'] : $_GET['ssn'] );
            }
            else{
                $item = $item->setNationalIdNumber($_GET['ssn']);
            }
            //only for SE, NO, DK where getAddress has been performed
            if($order["payment_iso_code_2"] == "SE" || $order["payment_iso_code_2"] == "NO" || $order["payment_iso_code_2"] == "DK") {
                $item = $item->setAddressSelector($_GET['addSel']);
            }
            $svea = $svea->addCustomerDetails($item);

            // set customer reference to stored customer firstname + lastname, from before getaddresses
            $svea = $svea->setCustomerReference($this->session->data['svea_reference']);

        }
        else {  // private customer

            $ssn = (isset($_GET['ssn'])) ? $_GET['ssn'] : 0;

            $item = Item::individualCustomer();
            //send customer filled address to svea. Svea will use address from getAddress for the invoice.
            $item = $item
                ->setNationalIdNumber($ssn)
                ->setEmail($order['email'])
                ->setName($order['payment_firstname'],$order['payment_lastname'])
                ->setStreetAddress($addressArr[1],$addressArr[2])
                ->setCoAddress($order['payment_address_2'])
                ->setZipCode($order['payment_postcode'])
                ->setLocality($order['payment_city'])
                ->setIpAddress($order['ip'])
                ->setPhoneNumber($order['telephone']);

            if($order["payment_iso_code_2"] == "DE" || $order["payment_iso_code_2"] == "NL"){
                $item = $item->setBirthDate($_GET['birthYear'], $_GET['birthMonth'], $_GET['birthDay']);
            }
            if($order["payment_iso_code_2"] == "NL"){
                $item = $item->setInitials($_GET['initials']);
            }
            $svea = $svea->addCustomerDetails($item);
        }

        try {
            $svea = $svea
                ->setCountryCode($countryCode)
                ->setCurrency($this->session->data['currency'])
                ->setClientOrderNumber($this->session->data['order_id'])
                ->setOrderDate(date('c'))
                ->useInvoicePayment()
                ->doRequest();
        }
        catch (Exception $e){
            $this->log->write($e->getMessage());
            $response = array("error" => $this->responseCodes(0,$e->getMessage()));
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($response));

        }

        //If CreateOrder accepted redirect to thankyou page
        if ($svea->accepted == 1) {
            $sveaOrderAddress = $this->buildPaymentAddressQuery($svea,$countryCode,$order['comment']);

            // if set to enforce shipping = billing, fetch billing address
            if($this->config->get('svea_invoice_shipping_billing') == '1') {
                $sveaOrderAddress = $this->buildShippingAddressQuery($svea,$sveaOrderAddress,$countryCode);
            }
            $this->model_payment_svea_invoice->updateAddressField($this->session->data['order_id'],$sveaOrderAddress);

            $response = array();

            //If Auto deliver order is set, DeliverOrder
            if($this->config->get('svea_invoice_auto_deliver') === '1') {
                $deliverObj = WebPay::deliverOrder($conf);
                //Product rows
                $deliverObj = $this->formatOrderRows($deliverObj, $products,$currencyValue);

                // no need to do formatAddons again

                //extra charge addons like shipping and invoice fee
                foreach ($addons as $addon) {
                    if($addon['value'] >= 0){
                        $vat = floatval($addon['value'] * $currencyValue) * (intval($addon['tax_rate']) / 100 );
                        $deliverObj = $deliverObj
                            ->addOrderRow(WebPayItem::orderRow()
                            ->setQuantity(1)
                            ->setAmountIncVat(floatval($addon['value'] * $currencyValue) + $vat)
                            ->setVatPercent(intval($addon['tax_rate']))
                            ->setName(isset($addon['title']) ? $addon['title'] : "")
                            ->setUnit($this->language->get('unit'))
                            ->setArticleNumber($addon['code'])
                            ->setDescription(isset($addon['text']) ? $addon['text'] : "")
                        );

                        //voucher(-)
                        } elseif ($addon['value'] < 0 && $addon['code'] == 'voucher') {
                            $deliverObj = $deliverObj
                                ->addDiscount(WebPayItem::fixedDiscount()
                                    ->setDiscountId($addon['code'])
                                    ->setAmountIncVat(floatval(abs($addon['value']) * $currencyValue))
                                    ->setVatPercent(0)//no vat when using a voucher
                                    ->setName(isset($addon['title']) ? $addon['title'] : "")
                                    ->setUnit($this->language->get('unit'))
                                    ->setDescription(isset($addon['text']) ? $addon['text'] : "")
                            );
                        }
                        //discounts (-)
                        else {
                            $taxRates = $this->getTaxRatesInOrder($deliverObj);
                            $discountRows = Svea\Helper::splitMeanToTwoTaxRates( (abs($addon['value']) * $currencyValue), $addon['tax_rate'], $addon['title'], $addon['text'], $taxRates );
                            foreach($discountRows as $row) {
                                $deliverObj = $deliverObj->addDiscount( $row );
                        }
                    }
                }

                // try to do deliverOrder request
                try {
                    $deliverObj = $deliverObj
                        ->setCountryCode($countryCode)
                        ->setOrderId($svea->sveaOrderId)    // match doRequest orderId
                        ->setInvoiceDistributionType($this->config->get('svea_invoice_distribution_type'))
                        ->deliverInvoiceOrder()
                        ->doRequest();
                }
                catch (Exception $e) {
                    $this->log->write($e->getMessage());
                    $response = array("error" => $this->responseCodes(0,$e->getMessage()));
                    $this->response->addHeader('Content-Type: application/json');
                    $this->response->setOutput(json_encode($response));

                }

                //if DeliverOrder returns true, send true to view
                if($deliverObj->accepted == 1){
                    $response = array("success" => true);
                    //update order status for delivered
                    $this->db->query("UPDATE `" . DB_PREFIX . "order` SET date_modified = NOW(), comment = '".$sveaOrderAddress['comment']." | Order delivered. Svea InvoiceId: ".$deliverObj->invoiceId."' WHERE order_id = '" . (int)$this->session->data['order_id'] . "'");

                    $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('svea_invoice_deliver_status_id'), 'Svea InvoiceId '.$deliverObj->invoiceId);
                }
                //if not, send error codes
                else {
                    $response = array("error" => $this->responseCodes($deliverObj->resultcode,$deliverObj->errormessage));
                }

            }
            //if auto deliver not set, send true to view
            else {
                $response = array("success" => true);
                //update order status for created
                $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('svea_invoice_order_status_id'),'Svea order id '. $svea->sveaOrderId);
            }

        // not accepted, send errors to view
        }  else {
            $response = array("error" => $this->responseCodes($svea->resultcode,$svea->errormessage));
        }


        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($response));
    }

    public function getAddress() {

        include(DIR_APPLICATION.'../svea/Includes.php');

        $this->load->model('payment/svea_invoice');
        $this->load->model('checkout/order');
        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        // store customer firstname + lastname in session as svea_reference
        $this->session->data['svea_reference'] = $order['payment_firstname'] ." ". $order['payment_lastname'];

        $countryCode = $order['payment_iso_code_2'];
        //Testmode
        $conf = ( $this->config->get('svea_invoice_testmode_'.$countryCode) == '1' )
                ? new OpencartSveaConfigTest($this->config) : new OpencartSveaConfig($this->config);

        $svea = WebPay::getAddresses($conf)
            ->setOrderTypeInvoice()
            ->setCountryCode($countryCode);

        if($this->request->post['company'] == 'true') {
            $svea = $svea->setCompany($this->request->post['ssn']);
        }
        else {
            $svea = $svea->setIndividual($this->request->post['ssn']);
        }

        try{
            $svea = $svea->doRequest();
        }
        catch (Exception $e){
            $response = array("error" => $this->responseCodes(0,$e->getMessage()));
            $this->log->write($e->getMessage());
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($response));
        }

        $result = array();
        if ($svea->accepted != TRUE) {
            $result = array("error" => $svea->errormessage);
        }
        else {
            foreach ($svea->customerIdentity as $ci)
            {
                $name = ($ci->fullName) ? $ci->fullName : $ci->legalName;

                $result[] = array(  "fullName"  => $name,
                                    "street"    => $ci->street,
                                    "address_2" => $ci->coAddress,
                                    "zipCode"  =>  $ci->zipCode,
                                    "locality"  => $ci->locality,
                                    "addressSelector" => $ci->addressSelector);
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($result));
    }

    private function formatOrderRows($svea,$products,$currencyValue){
        $this->load->language('payment/svea_invoice');

        //Product rows
        foreach ($products as $product) {
              $item = Item::orderRow()
                ->setQuantity($product['quantity'])
                ->setName($product['name'])
                ->setUnit($this->language->get('unit'))
                ->setArticleNumber($product['model']);
//                ->setDescription($product['model'])//should be used for $product['option'] wich is array, but to risky because limit is String(40)

            $tax = $this->tax->getRates($product['price'], $product['tax_class_id']);
            $taxPercent = 0;
            $taxAmount = 0;
            foreach ($tax as $key => $value) {
                $taxPercent = $value['rate'];
                $taxAmount = $value['amount'];
            }
            $item = $item->setAmountIncVat(($product['price'] + $taxAmount) * $currencyValue)
                        ->setVatPercent(intval($taxPercent));//set amount inc vat is used for precision

          $svea = $svea->addOrderRow($item);
        }
        return $svea;
    }

    public function formatAddons() {
        //Get all addons
       $this->load->model('extension/extension');
        $total_data = array();
        $total = 0;
        $svea_tax = array();
        $cartTax = $this->cart->getTaxes();

        $results = $this->model_extension_extension->getExtensions('total');
        foreach ($results as $result) {
           //if this result is activated
            if($this->config->get($result['code'] . '_status')){
                $amount = 0;
                $taxes = array();
                foreach ($cartTax as $key => $value) {
                    $taxes[$key] = 0;
                }
                $this->load->model('total/' . $result['code']);

                $this->{'model_total_' . $result['code']}->getTotal($total_data, $total, $taxes);

                foreach ($taxes as $tax_id => $value) {
                    $amount += $value;
                }

                $svea_tax[$result['code']] = $amount;
            }
        }

        foreach ($total_data as $key => $value) {
            if (isset($svea_tax[$value['code']])) {
                if ($svea_tax[$value['code']]) {
                    $total_data[$key]['tax_rate'] = (int)round( $svea_tax[$value['code']] / $value['value'] * 100 ); // round and cast, or may get i.e. 24.9999, which shows up as 25f in debugger & written to screen, but converts to 24i
                } else {
                    $total_data[$key]['tax_rate'] = 0;
                }
            }
            else {
                $total_data[$key]['tax_rate'] = '0';
            }
        }

        $ignoredTotals = 'sub_total, total, taxes';
        $ignoredOrderTotals = array_map('trim', explode(',', $ignoredTotals));
        foreach ($total_data as $key => $orderTotal) {
            if (in_array($orderTotal['code'], $ignoredOrderTotals)) {
                unset($total_data[$key]);
            }
        }

        return $total_data;
    }

    private function getLogo($countryCode){
        switch ($countryCode){
            case "SE": $country = "swedish";    break;
            case "NO": $country = "norwegian";  break;
            case "DK": $country = "danish";     break;
            case "FI": $country = "finnish";    break;
            case "NL": $country = "dutch";      break;
            case "DE": $country = "german";     break;
            default:   $country = "english";    break;
        }
        return $country;
    }

    /**
     * TODO replace these with the one in php integration package Helper class in next release
     *
     * Takes a createOrderBuilder object, iterates over its orderRows, and
     * returns an array containing the distinct taxrates present in the order
     */
    private function getTaxRatesInOrder($order) {
        $taxRates = array();

        foreach( $order->orderRows as $orderRow ) {

            if( isset($orderRow->vatPercent) ) {
                $seenRate = $orderRow->vatPercent; //count
            }
            elseif( isset($orderRow->amountIncVat) && isset($orderRow->amountExVat) ) {
                $seenRate = Svea\Helper::bround( (($orderRow->amountIncVat - $orderRow->amountExVat) / $orderRow->amountExVat) ,2) *100;
            }

            if(isset($seenRate)) {
                isset($taxRates[$seenRate]) ? $taxRates[$seenRate] +=1 : $taxRates[$seenRate] =1;   // increase count of seen rate
            }
        }
        return array_keys($taxRates);   //we want the keys
    }

    // update order billingaddress
    private function buildPaymentAddressQuery($svea,$countryCode,$order_comment) {
         $countryId = $this->model_payment_svea_invoice->getCountryIdFromCountryCode(strtoupper($countryCode));
         $paymentAddress = array();

        if ($svea->customerIdentity->customerType == 'Company'){
            if( isset($svea->customerIdentity->firstName)){ $paymentAddress["payment_firstname"] = $svea->customerIdentity->firstName; }
            if( isset($svea->customerIdentity->lastName)){ $paymentAddress["payment_lastname"] = $svea->customerIdentity->lastName; }
            if( isset($svea->customerIdentity->fullName)){ $paymentAddress["payment_company"] = $svea->customerIdentity->fullName; }

            if( isset($svea->customerIdentity->street)){ $paymentAddress["payment_address_1"] = $svea->customerIdentity->street; }
            if( isset($svea->customerIdentity->coAddress)){ $paymentAddress["payment_address_2"] = $svea->customerIdentity->coAddress; }
            if( isset($svea->customerIdentity->locality)){ $paymentAddress["payment_city"] = $svea->customerIdentity->locality; }
            if( isset($svea->customerIdentity->zipCode)){ $paymentAddress["payment_postcode"] = $svea->customerIdentity->zipCode; }

            $paymentAddress["payment_country_id"] = $countryId['country_id'];
            $paymentAddress["payment_country"] = $countryId['country_name'];
            $paymentAddress["payment_method"] = $this->language->get('text_title');
        }

        else {  // private customer

            if( isset($svea->customerIdentity->firstName)){ $paymentAddress["payment_firstname"] = $svea->customerIdentity->firstName; }
            if( isset($svea->customerIdentity->lastName)){ $paymentAddress["payment_lastname"] = $svea->customerIdentity->lastName; }
            // for private individuals, if firstName, lastName is not set in GetAddresses response, we put the entire getAddress LegalName in lastName
            if( isset($svea->customerIdentity->fullName)){ $paymentAddress["payment_lastname"] = $svea->customerIdentity->fullName; }

            if( isset($svea->customerIdentity->street)){ $paymentAddress["payment_address_1"] = $svea->customerIdentity->street; }
            if( isset($svea->customerIdentity->coAddress)){ $paymentAddress["payment_address_2"] = $svea->customerIdentity->coAddress; }
            if( isset($svea->customerIdentity->locality)){ $paymentAddress["payment_city"] = $svea->customerIdentity->locality; }
            if( isset($svea->customerIdentity->zipCode)){ $paymentAddress["payment_postcode"] = $svea->customerIdentity->zipCode; }

            $paymentAddress["payment_country_id"] = $countryId['country_id'];
            $paymentAddress["payment_country"] = $countryId['country_name'];
            $paymentAddress["payment_method"] = $this->language->get('text_title');
        }

        $paymentAddress["comment"] = $order_comment . "\nSvea order id: ".$svea->sveaOrderId;

        return $paymentAddress;
    }

    // update shipping address
    private function buildShippingAddressQuery($svea,$shippingAddress,$countryCode) {
        $countryId = $this->model_payment_svea_invoice->getCountryIdFromCountryCode(strtoupper($countryCode));

        if ($svea->customerIdentity->customerType == 'Company'){
            if( isset($svea->customerIdentity->firstName)){ $shippingAddress["shipping_firstname"] = $svea->customerIdentity->firstName; }
            if( isset($svea->customerIdentity->lastName)){ $shippingAddress["shipping_lastname"] = $svea->customerIdentity->lastName; }
            if( isset($svea->customerIdentity->fullName)){ $shippingAddress["shipping_company"] = $svea->customerIdentity->fullName; }

            if( isset($svea->customerIdentity->street)){ $shippingAddress["shipping_address_1"] = $svea->customerIdentity->street; }
            if( isset($svea->customerIdentity->coAddress)){ $shippingAddress["shipping_address_2"] = $svea->customerIdentity->coAddress; }
            if( isset($svea->customerIdentity->locality)){ $shippingAddress["shipping_city"] = $svea->customerIdentity->locality; }
            if( isset($svea->customerIdentity->zipCode)){ $shippingAddress["shipping_postcode"] = $svea->customerIdentity->zipCode; }

            $shippingAddress["shipping_country_id"] = $countryId['country_id'];
            $shippingAddress["shipping_country"] = $countryId['country_name'];
        }
        else {  // private customer
            if( isset($svea->customerIdentity->firstName)){ $shippingAddress["shipping_firstname"] = $svea->customerIdentity->firstName; }
            if( isset($svea->customerIdentity->lastName)){ $shippingAddress["shipping_lastname"] = $svea->customerIdentity->lastName; }
            if( isset($svea->customerIdentity->fullName)){ $shippingAddress["shipping_lastname"] = $svea->customerIdentity->fullName; }

            if( isset($svea->customerIdentity->street)){ $shippingAddress["shipping_address_1"] = $svea->customerIdentity->street; }
            if( isset($svea->customerIdentity->coAddress)){ $shippingAddress["shipping_address_2"] = $svea->customerIdentity->coAddress; }
            if( isset($svea->customerIdentity->locality)){ $shippingAddress["shipping_city"] = $svea->customerIdentity->locality; }
            if( isset($svea->customerIdentity->zipCode)){ $shippingAddress["shipping_postcode"] = $svea->customerIdentity->zipCode; }
            $shippingAddress["shipping_country_id"] = $countryId['country_id'];
            $shippingAddress["shipping_country"] = $countryId['country_name'];
        }

        return $shippingAddress;
    }
}
?>
