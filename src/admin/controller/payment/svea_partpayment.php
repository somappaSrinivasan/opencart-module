<?php
class ControllerPaymentsveapartpayment extends Controller {
	private $error = array();
        protected $svea_version = '3.0.12';

	public function index() {
		$this->load->language('payment/svea_partpayment');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && ($this->validate())) {

                    $this->model_setting_setting->editSetting('svea_partpayment', $this->request->post);
                      //load latest PaymentPlan params from Svea api on save
                    $this->loadPaymentPlanParams();

                    $this->session->data['success'] = $this->language->get('text_success');
                    $this->response->redirect($this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL'));
//                    $this->redirect(HTTPS_SERVER . 'index.php?route=extension/payment&token=' . $this->session->data['token']);
		}
                $data['svea_version'] = $this->getSveaVersion();

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_all_zones'] = $this->language->get('text_all_zones');
                //order status
		$data['entry_order_status'] = $this->language->get('entry_order_status');
		$data['entry_order_status_text'] = $this->language->get('entry_order_status_text');
                $data['entry_status_order'] = $this->language->get('entry_status_order');
                $data['entry_status_canceled'] = $this->language->get('entry_status_canceled');
                $data['entry_status_canceled_text'] = $this->language->get('entry_status_canceled_text');
                $data['entry_status_delivered'] = $this->language->get('entry_status_delivered');
                $data['entry_status_delivered_text'] = $this->language->get('entry_status_delivered_text');

		$data['entry_shipping_billing'] = $this->language->get('entry_shipping_billing');
		$data['entry_shipping_billing_text'] = $this->language->get('entry_shipping_billing_text');
		$data['entry_geo_zone'] = $this->language->get('entry_geo_zone');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');
                $data['entry_payment_description']   = $this->language->get('entry_payment_description');
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['tab_general'] = $this->language->get('tab_general');
                //Credentials
                $data['entry_username']      = $this->language->get('entry_username');
                $data['entry_password']      = $this->language->get('entry_password');
                $data['entry_clientno']      = $this->language->get('entry_clientno');
                $data['entry_product']      = $this->language->get('entry_product');
                $data['entry_product_text'] = $this->language->get('entry_product_text');

                $data['entry_sweden']        = $this->language->get('entry_sweden');
                $data['entry_finland']       = $this->language->get('entry_finland');
                $data['entry_denmark']       = $this->language->get('entry_denmark');
                $data['entry_norway']        = $this->language->get('entry_norway');
                $data['entry_germany']       = $this->language->get('entry_germany');
                $data['entry_netherlands']   = $this->language->get('entry_netherlands');

                $data['entry_testmode']      = $this->language->get('entry_testmode');
                $data['entry_auto_deliver']  = $this->language->get('entry_auto_deliver');
                $data['entry_auto_deliver_text'] = $this->language->get('entry_auto_deliver_text');
                $data['entry_post']           = $this->language->get('entry_post');
                $data['entry_email']          = $this->language->get('entry_email');
                $data['entry_yes']           = $this->language->get('entry_yes');
                $data['entry_no']            = $this->language->get('entry_no');
                $data['entry_min_amount']    = $this->language->get('entry_min_amount');

                $data['version']             = floatval(VERSION);

                //As you might notice the word we're really looking for is "country" not "lang". Leaving it like that so it won't ruin anything though.
                $cred = array();
                $cred[] = array("lang" => "SE","value_username" => $this->config->get('svea_partpayment_username_SE'),"name_username" => 'svea_partpayment_username_SE',"value_password" => $this->config->get('svea_partpayment_password_SE'),"name_password" => 'svea_partpayment_password_SE',"value_clientno" => $this->config->get('svea_partpayment_clientno_SE'),"name_clientno" => 'svea_partpayment_clientno_SE',"min_amount_name" => 'svea_partpayment_min_amount_SE',"min_amount_value" => $this->config->get('svea_partpayment_min_amount_SE'),"value_testmode" => $this->config->get('svea_partpayment_testmode_SE'),"name_testmode" => 'svea_partpayment_testmode_SE');
                $cred[] = array("lang" => "NO","value_username" => $this->config->get('svea_partpayment_username_NO'),"name_username" => 'svea_partpayment_username_NO',"value_password" => $this->config->get('svea_partpayment_password_NO'),"name_password" => 'svea_partpayment_password_NO',"value_clientno" => $this->config->get('svea_partpayment_clientno_NO'),"name_clientno" => 'svea_partpayment_clientno_NO',"min_amount_name" => 'svea_partpayment_min_amount_NO',"min_amount_value" => $this->config->get('svea_partpayment_min_amount_NO'),"value_testmode" => $this->config->get('svea_partpayment_testmode_NO'),"name_testmode" => 'svea_partpayment_testmode_NO');
                $cred[] = array("lang" => "FI","value_username" => $this->config->get('svea_partpayment_username_FI'),"name_username" => 'svea_partpayment_username_FI',"value_password" => $this->config->get('svea_partpayment_password_FI'),"name_password" => 'svea_partpayment_password_FI',"value_clientno" => $this->config->get('svea_partpayment_clientno_FI'),"name_clientno" => 'svea_partpayment_clientno_FI',"min_amount_name" => 'svea_partpayment_min_amount_FI',"min_amount_value" => $this->config->get('svea_partpayment_min_amount_FI'),"value_testmode" => $this->config->get('svea_partpayment_testmode_FI'),"name_testmode" => 'svea_partpayment_testmode_FI');
                $cred[] = array("lang" => "DK","value_username" => $this->config->get('svea_partpayment_username_DK'),"name_username" => 'svea_partpayment_username_DK',"value_password" => $this->config->get('svea_partpayment_password_DK'),"name_password" => 'svea_partpayment_password_DK',"value_clientno" => $this->config->get('svea_partpayment_clientno_DK'),"name_clientno" => 'svea_partpayment_clientno_DK',"min_amount_name" => 'svea_partpayment_min_amount_DK',"min_amount_value" => $this->config->get('svea_partpayment_min_amount_DK'),"value_testmode" => $this->config->get('svea_partpayment_testmode_DK'),"name_testmode" => 'svea_partpayment_testmode_DK');
                $cred[] = array("lang" => "NL","value_username" => $this->config->get('svea_partpayment_username_NL'),"name_username" => 'svea_partpayment_username_NL',"value_password" => $this->config->get('svea_partpayment_password_NL'),"name_password" => 'svea_partpayment_password_NL',"value_clientno" => $this->config->get('svea_partpayment_clientno_NL'),"name_clientno" => 'svea_partpayment_clientno_NL',"min_amount_name" => 'svea_partpayment_min_amount_NL',"min_amount_value" => $this->config->get('svea_partpayment_min_amount_NL'),"value_testmode" => $this->config->get('svea_partpayment_testmode_NL'),"name_testmode" => 'svea_partpayment_testmode_NL');
                $cred[] = array("lang" => "DE","value_username" => $this->config->get('svea_partpayment_username_DE'),"name_username" => 'svea_partpayment_username_DE',"value_password" => $this->config->get('svea_partpayment_password_DE'),"name_password" => 'svea_partpayment_password_DE',"value_clientno" => $this->config->get('svea_partpayment_clientno_DE'),"name_clientno" => 'svea_partpayment_clientno_DE',"min_amount_name" => 'svea_partpayment_min_amount_DE',"min_amount_value" => $this->config->get('svea_partpayment_min_amount_DE'),"value_testmode" => $this->config->get('svea_partpayment_testmode_DE'),"name_testmode" => 'svea_partpayment_testmode_DE');

                $data['credentials'] = $cred;

                $data['svea_partpayment_sort_order']    = $this->config->get('svea_partpayment_sort_order');
                $data['svea_partpayment_auto_deliver']      = $this->config->get('svea_partpayment_auto_deliver');

 		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

                    $data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], 'SSL')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_payment'),
			'href' => $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('payment/svea_partpayment', 'token=' . $this->session->data['token'], 'SSL')
		);

                $data['action'] = $this->url->link('payment/svea_partpayment', 'token=' . $this->session->data['token'], 'SSL');
		$data['cancel'] = $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL');

		if (isset($this->request->post['svea_partpayment_geo_zone_id'])) {
			$data['svea_partpayment_geo_zone_id'] = $this->request->post['svea_partpayment_geo_zone_id'];
		} else {
			$data['svea_partpayment_geo_zone_id'] = $this->config->get('svea_partpayment_geo_zone_id');
		}

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

                //order status
		if (isset($this->request->post['svea_partpayment_status'])) {
			$data['svea_partpayment_status'] = $this->request->post['svea_partpayment_status'];
		} else {
			$data['svea_partpayment_status'] = $this->config->get('svea_partpayment_status');
		}
                //sort order
		if (isset($this->request->post['svea_partpayment_sort_order'])) {
			$data['svea_partpayment_sort_order'] = $this->request->post['svea_partpayment_sort_order'];
		} else {
			$data['svea_partpayment_sort_order'] = $this->config->get('svea_partpayment_sort_order');
		}
                   //payment info
		if (isset($this->request->post['svea_partpayment_payment_description'])) {
			$data['svea_partpayment_payment_description'] = $this->request->post['svea_partpayment_payment_description'];
		} else {
			$data['svea_partpayment_payment_description'] = $this->config->get('svea_partpayment_payment_description');
		}
                //auto deliver
                if (isset($this->request->post['svea_partpayment_auto_deliver'])) {
			$data['svea_partpayment_auto_deliver'] = $this->request->post['svea_partpayment_auto_deliver'];
		} else {
			$data['svea_partpayment_auto_deliver'] = $this->config->get('svea_partpayment_auto_deliver');
		}
                 //shipping billing
		if (isset($this->request->post['svea_partpayment_shipping_billing'])) {
			$data['svea_partpayment_shipping_billing'] = $this->request->post['svea_partpayment_shipping_billing'];
		} else {
			$data['svea_partpayment_shipping_billing'] = $this->config->get('svea_partpayment_shipping_billing');
		}
                 //show price on product
		if (isset($this->request->post['svea_partpayment_product_price'])) {
			$data['svea_partpayment_product_price'] = $this->request->post['svea_partpayment_product_price'];
		} else {
			$data['svea_partpayment_product_price'] = $this->config->get('svea_partpayment_product_price');
		}
                //order statuses
                $this->load->model('localisation/order_status');
                $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
                if (isset($this->request->post['svea_partpayment_order_status_id'])) {
                    $data['svea_partpayment_order_status_id'] = $this->request->post['svea_partpayment_order_status_id'];
		} else {
                    $data['svea_partpayment_order_status_id'] = $this->config->get('svea_partpayment_order_status_id');
		}
                if (isset($this->request->post['svea_partpayment_canceled_status_id'])) {
                    $data['svea_partpayment_canceled_status_id'] = $this->request->post['svea_partpayment_canceled_status_id'];
                } else {
                    $data['svea_partpayment_canceled_status_id'] = $this->config->get('svea_partpayment_canceled_status_id');
                }
                if (isset($this->request->post['svea_partpayment_deliver_status_id'])) {
			$data['svea_partpayment_deliver_status_id'] = $this->request->post['svea_partpayment_deliver_status_id'];
		} else {
			$data['svea_partpayment_deliver_status_id'] = $this->config->get('svea_partpayment_deliver_status_id');
		}

                $data['header'] = $this->load->controller('common/header');
                $data['column_left'] = $this->load->controller('common/column_left');
                $data['footer'] = $this->load->controller('common/footer');

                $this->response->setOutput($this->load->view('payment/svea_partpayment.tpl', $data));


//		$this->template = 'payment/svea_partpayment.tpl';
//		$this->children = array(
//			'common/header',
//			'common/footer'
//		);
//
//		$this->response->setOutput($this->render(TRUE), $this->config->get('config_compression'));


	}


        private function validate() {
		if (!$this->user->hasPermission('modify', 'payment/svea_partpayment')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->error) {
			return TRUE;
		} else {
			return FALSE;
		}
	}


    /**
     * Svea: Create table if does not exists, call Svea API, load with params values for specific countrycode
     * Called whenever saving payment plan module settings, will update stored campaigns in table svea_params_table.
     */
    private function loadPaymentPlanParams(){
        //Load SVEA includes
        include(DIR_APPLICATION . '../svea/Includes.php');
        $countryCode = array("SE","NO","FI","DK","NL","DE");
        for($i=0;$i<sizeof($countryCode);$i++){
            $username = $this->config->get('svea_partpayment_username_' . $countryCode[$i]);
            $password = $this->config->get('svea_partpayment_password_' . $countryCode[$i]);
            $client_id = $this->config->get('svea_partpayment_clientno_' . $countryCode[$i]);
            //get params if config is set

            if($username != "" && $password != "" && $client_id != ""){
                if ( $this->config->get('svea_partpayment_testmode_' . $countryCode[$i]) !== NULL){

                    $conf = $this->config->get('svea_partpayment_testmode_' . $countryCode[$i]) == "1" ? new OpencartSveaConfigTest($this->config) : new OpencartSveaConfig($this->config);
                    $svea_params = WebPay::getPaymentPlanParams($conf);

                    try {
                        $svea_params = $svea_params->setCountryCode($countryCode[$i])
                            ->doRequest();
                    }
                    catch (Exception $e) {
                         $this->log->write("Failed to update PaymentPlanParams" . $e->getMessage());
                         return; // without updating table.
                    }

                    if( isset($svea_params->accepted) && $svea_params->accepted == TRUE ) {
                        $formatted_params = $this->sveaFormatParams($svea_params);

                        if($formatted_params !=NULL){
                            $this->insertPaymentPlanParams($formatted_params,$countryCode[$i]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Create Svea params talbe if not exists
     */
    public function install() {
        $q  = ' CREATE TABLE IF NOT EXISTS `' . DB_PREFIX .'svea_params_table`
                (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `campaignCode` VARCHAR( 100 ) NOT NULL,
                `description` VARCHAR( 100 ) NOT NULL ,
                `paymentPlanType` VARCHAR( 100 ) NOT NULL ,
                `contractLengthInMonths` INT NOT NULL ,
                `monthlyAnnuityFactor` DOUBLE NOT NULL ,
                `initialFee` DOUBLE NOT NULL ,
                `notificationFee` DOUBLE NOT NULL ,
                `interestRatePercent` INT NOT NULL ,
                `numberOfInterestFreeMonths` INT NOT NULL ,
                `numberOfPaymentFreeMonths` INT NOT NULL ,
                `fromAmount` DOUBLE NOT NULL ,
                `toAmount` DOUBLE NOT NULL ,
                `timestamp` INT UNSIGNED NOT NULL,
                `countryCode` VARCHAR( 100 ) NOT NULL
            )   ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1
            ';
        $this->db->query($q);

        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('svea_partpayment', array('svea_partpayment_status'=>1));
    }

    public function uninstall() {
        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('svea_partpayment', array('svea_partpayment_status'=>0));
    }

    protected function getSveaVersion(){
        $update_url = "https://github.com/sveawebpay/opencart-module/archive/master.zip";
        $docs_url = "https://github.com/sveawebpay/opencart-module/releases";
        $url = "https://raw.githubusercontent.com/sveawebpay/opencart-module/master/docs/info.json";
        $json = file_get_contents($url);
        $data = json_decode($json);

        if($data->module_version == $this->svea_version){
            return "You have the latest ". $this->svea_version . " version.";
        }else{
         return $this->svea_version . '<br />
            There is a new version available.<br />
            <a href="'.$docs_url.'" title="Go to release notes on github">View version details</a> or <br />
            <a title="Download zip" href="'.$update_url.'"><img width="67" src="view/image/download.png"></a>';

        }

    }

      protected function sveaFormatParams($response){
            $result = array();
            if ($response == null) {
                return $result;
            } else {
                foreach ($response->campaignCodes as $responseResultItem) {
                    try {
                        $campaignCode = (isset($responseResultItem->campaignCode)) ? $responseResultItem->campaignCode : "";
                        $description = (isset($responseResultItem->description)) ? $responseResultItem->description : "";
                        $paymentplantype = (isset($responseResultItem->paymentPlanType)) ? $responseResultItem->paymentPlanType : "";
                        $contractlength = (isset($responseResultItem->contractLengthInMonths)) ? $responseResultItem->contractLengthInMonths : "";
                        $monthlyannuityfactor = (isset($responseResultItem->monthlyAnnuityFactor)) ? $responseResultItem->monthlyAnnuityFactor : "";
                        $initialfee = (isset($responseResultItem->initialFee)) ? $responseResultItem->initialFee : "";
                        $notificationfee = (isset($responseResultItem->notificationFee)) ? $responseResultItem->notificationFee : "";
                        $interestratepercentage = (isset($responseResultItem->interestRatePercent)) ? $responseResultItem->interestRatePercent : "";
                        $interestfreemonths = (isset($responseResultItem->numberOfInterestFreeMonths)) ? $responseResultItem->numberOfInterestFreeMonths : "";
                        $paymentfreemonths = (isset($responseResultItem->numberOfPaymentFreeMonths)) ? $responseResultItem->numberOfPaymentFreeMonths : "";
                        $fromamount = (isset($responseResultItem->fromAmount)) ? $responseResultItem->fromAmount : "";
                        $toamount = (isset($responseResultItem->toAmount)) ? $responseResultItem->toAmount : "";

                        $result[] = Array(
                            "campaignCode" => $campaignCode,
                            "description" => $description,
                            "paymentPlanType" => $paymentplantype,
                            "contractLengthInMonths" => $contractlength,
                            "monthlyAnnuityFactor" => $monthlyannuityfactor,
                            "initialFee" => $initialfee,
                            "notificationFee" => $notificationfee,
                            "interestRatePercent" => $interestratepercentage,
                            "numberOfInterestFreeMonths" => $interestfreemonths,
                            "numberOfPaymentFreeMonths" => $paymentfreemonths,
                            "fromAmount" => $fromamount,
                            "toAmount" => $toamount
                        );
                    } catch (Exception $e) {
                       $this->log->write($e->getMessage());
                    }
                }
            }
            return $result;
        }

    protected function insertPaymentPlanParams($params,$countryCode) {

            foreach ($params as $param) {
                //$query = $db->getQuery(true);
                $q = "INSERT INTO `" . DB_PREFIX ."svea_params_table`
                        (   `campaignCode` ,
                            `description`,
                            `paymentPlanType`,
                            `contractLengthInMonths`,
                            `monthlyAnnuityFactor`,
                            `initialFee`,
                            `notificationFee`,
                            `interestRatePercent`,
                            `numberOfInterestFreeMonths`,
                            `numberOfPaymentFreeMonths`,
                            `fromAmount`,
                            `toAmount`,
                            `timestamp`,
                            `countryCode`)
                            VALUES(";
                foreach ($param as $key => $value)
                    $q .= "'".$value."',";

                $q .= time().",";
                $q .= "'".$countryCode."'";
                $q .= ")";
                try{
                    $this->db->query($q);
                }catch (Exception $e) {
                    $this->log->write($e->getMessage());
                }

            }


    }
}
?>
