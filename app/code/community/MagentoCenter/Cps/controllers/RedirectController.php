<?php


class MagentoCenter_Cps_RedirectController extends Mage_Core_Controller_Front_Action {

    protected $_order;

    protected function _expireAjax() {
        if (!Mage::getSingleton('checkout/session')->getQuote()->hasItems()) {
            $this->getResponse()->setHeader('HTTP/1.1','403 Session Expired');
            exit;
        }
    }
    protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    public function indexAction() {
        $this->getResponse()
                ->setHeader('Content-type', 'text/html; charset=utf8')
                ->setBody($this->getLayout()
                ->createBlock('cps/redirect')
                ->toHtml());
    }
    

    public function successAction() {
			//get cps gateway response parameters
			$strResult = $_REQUEST['responseXml'];
			$strResult = stripslashes($strResult);

			$doc = new DOMDocument();
			$doc->loadXML($strResult);
  
			$result = $doc->getElementsByTagName( "result" );
			foreach( $result as $value )
			{
			$resultCode = $value->getElementsByTagName( "resultCode" );
			$resCode = $resultCode->item(0)->nodeValue;
  
			$resultMessage = $value->getElementsByTagName( "resultMessage" );
			$resMessage = $resultMessage->item(0)->nodeValue;
  
			$resultText = $value->getElementsByTagName( "resultText" );
			$resText = $resultText->item(0)->nodeValue;
			}
			
			//forward to failure page if payment is unsuccessful
			if($resMessage != 'Captured'){
				$this->_forward('failure');
			}
			//payment successful
			elseif($resMessage == 'Captured')
			{
			//retrieve order id
			$event = $this->getRequest()->getParams();
            $transaction_id= $event['transaction_id'];			
			
			//add note about successful payment to order in the backoffice
			$order = Mage::getModel('sales/order')->loadByIncrementId($transaction_id);
			
			$order->addStatusToHistory(
				$order->getStatus(),
				Mage::helper('cps')->__("Customer's credit card was successfuly charged by CPS.")
			);	
						
			//send email about the order to customer
			$order->sendNewOrderEmail()->setEmailSent(true)->save();
			
			//create invoice for successful payment
			if(!$order->canInvoice())
            {
            Mage::throwException(Mage::helper('core')->__('Cannot create an invoice.'));
            }			
			$invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice(); 
			if (!$invoice->getTotalQty()) {
			Mage::throwException(Mage::helper('core')->__('Cannot create an invoice without products.'));
			} 
			$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
			$invoice->register();
			$transactionSave = Mage::getModel('core/resource_transaction')
			->addObject($invoice)
			->addObject($invoice->getOrder());
			$transactionSave->save();
			
			//set order status to "Processing" and save order
			$order->setStatus(Mage_Sales_Model_Order::STATE_PROCESSING, true);
			$order->save();
			
			//redirect to success page
			$this->_getCheckout()->setLastSuccessQuoteId($order->getQuoteId());
            $this->_redirect('checkout/onepage/success', array('_secure'=>true));
			}
		
    }

    
    public function failureAction()
    {		
		//retrieve order id
		$event = $this->getRequest()->getParams();
        $transaction_id= $event['transaction_id'];
						
		//add note about unsuccessful payment to order in the backoffice
		$order = Mage::getModel('sales/order')->loadByIncrementId($transaction_id);
		$order->addStatusToHistory(
			$order->getStatus(),
			Mage::helper('cps')->__('Payment rejected by CPS.')
		);			
		$order->cancel();
		$order->save();
		
		//send email to customer that the order's been canceled
		$order->sendOrderUpdateEmail()->setEmailSent(true)->save();
		
		//keep session in order to have all the products back in the cart
		$quoteId = $this->_getCheckout()->getQuoteId();
		$quote = Mage::getModel('sales/quote')->load($quoteId);
		$quote->getId();
		$quote->setIsActive(true)->save();
		
		//redirect back to cart and display error message
		$this->_getCheckout()->addError(Mage::helper('cps')->__('Sorry, your payment was declined. Order #').$transaction_id);
		$this->_redirect('checkout/cart');

    }

}

?>
