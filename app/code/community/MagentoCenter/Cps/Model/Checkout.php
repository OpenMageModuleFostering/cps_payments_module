<?php


class MagentoCenter_Cps_Model_Checkout extends Mage_Payment_Model_Method_Abstract {

    protected $_code          = 'cps';
    protected $_formBlockType = 'cps/form';
    protected $_infoBlockType = 'cps/info';
    protected $_order;
    
    const     USERNAME       = 'payment/cps/cps_username';
	const     KEYFILE        = 'payment/cps/cps_keyfile';
	const     KEYPASS        = 'payment/cps/cps_keypass';


    

    public function getCheckout() {
        return Mage::getSingleton('checkout/session');
    }

    public function getOrderPlaceRedirectUrl() {
        return Mage::getUrl('cps/redirect', array('_secure' => true));
    }

    public function getCpsUrl() {
        $url = 'https://3ds.cps.lv/GatorServo/request';
        return $url;
    }

    public function getLocale()
    {
        return Mage::app()->getLocale()->getLocaleCode();
    }
    
    public function getCpsCheckoutFormFields() {

        //set parameters
		$order_id = $this->getCheckout()->getLastRealOrderId();
        $order    = Mage::getModel('sales/order')->loadByIncrementId($order_id);
        if ($order->getBillingAddress()->getEmail()) {
            $email = $order->getBillingAddress()->getEmail();
        } else {
            $email = $order->getCustomerEmail();
        }
		$user = Mage::getStoreConfig(MagentoCenter_Cps_Model_Checkout::USERNAME);
		$key = Mage::getStoreConfig(MagentoCenter_Cps_Model_Checkout::KEYFILE);
		$kpass = Mage::getStoreConfig(MagentoCenter_Cps_Model_Checkout::KEYPASS);
		$redirectUrl = Mage::getUrl('cps/redirect/success', array('transaction_id' => $order_id));
		$firstName = $order->getBillingAddress()->getFirstname();
		$lastName = $order->getBillingAddress()->getLastname();
		$street = $order->getBillingAddress()->getStreet();
		$zip = $order->getBillingAddress()->getPostcode();
		$city = $order->getBillingAddress()->getCity();
		$country = $order->getBillingAddress()->getCountry();
		$value = trim(round($order->getGrandTotal(), 2))*100;
		$currency = $order->getOrderCurrencyCode();
		$productName = Mage::helper('cps')->__('Payment for order #').$order_id;
		$ip = $_SERVER["REMOTE_ADDR"];
		$product_url = 'http://'.$_SERVER['HTTP_HOST'];
		
		//create digital signature		
		$signature = null;
		$pemkey = null;
		$toSign = "sendForAuth" . $user . $order_id . $value . $currency . $productName;
		$p12cert = array();
		$file = 'app/code/community/MagentoCenter/Cps/Model/'.$key;
		$fd = fopen($file, 'rb');
		$p12buf = fread($fd, filesize($file));
		fclose($fd);
		openssl_pkcs12_read($p12buf, $p12cert, $kpass);
		openssl_pkey_export($p12cert['pkey'], $pemkey); 
		openssl_sign($toSign, $signature, $pemkey, OPENSSL_ALGO_SHA1);
		openssl_free_key($pemkey);
		$base64_sig = base64_encode($signature);
		
		//prepare xml
		$send_xml = "<?xml version='1.0' encoding='UTF-8'?>
		<cpsxml xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance'
        xsi:schemaLocation='http://www.cps.lv/xml/ns/cpsxml
        https://3ds.cps.lv/GatorServo/Gator_SendForAuth.xsd'
        xmlns='http://www.cps.lv/xml/ns/cpsxml'>
		<header xmlns=''>
			<responsetype>direct</responsetype>
			<user>$user</user>
			<type>sendForAuth</type>
			<transType>DB</transType>
			<digiSignature>$base64_sig</digiSignature>
			<redirectUrl>$redirectUrl</redirectUrl>
		</header>
		<request xmlns=''>
			<orderNumber>$order_id</orderNumber>
			<cardholder>
				<firstName>$firstName</firstName>
				<lastName>$lastName</lastName>
				<street>$street</street>
				<zip>$zip</zip>
				<city>$city</city>
				<country>$country</country>
				<email>$email</email>
				<ip>$ip</ip>
			</cardholder>
			<amount>
				<value>$value</value>
				<currency>$currency</currency>
			</amount>
			<product>
				<productName>$productName</productName>
				<productUrl>$product_url</productUrl>
			</product>
		</request>
		</cpsxml>";

		//load request paramaters in an array
        $params = array(
			  'type'				  => 'sendForAuth',
			  'xml'					  => $send_xml,
        );
        return $params;
        
    }

}
