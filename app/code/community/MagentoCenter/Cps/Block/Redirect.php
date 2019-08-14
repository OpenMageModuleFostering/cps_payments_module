<?php


class MagentoCenter_Cps_Block_Redirect extends Mage_Core_Block_Abstract
{
    protected function _toHtml()
    {
        $cps = Mage::getModel('cps/checkout');

        $form = new Varien_Data_Form();
        $form->setAction($cps->getCpsUrl())
            ->setId('pay')
            ->setName('pay')
            ->setMethod('POST')
            ->setUseContainer(true);
        foreach ($cps->getCpsCheckoutFormFields() as $field=>$value) {
            $form->addField($field, 'hidden', array('name'=>$field, 'value'=>$value));
        }

        $html = '<html><body>';
        $html.= $this->__('Redirecting to checkout page ...');
        $html.= '<hr>';
        $html.= $form->toHtml();
        $html.= '<script type="text/javascript">document.getElementById("pay").submit();</script>';
        $html.= '</body></html>';
        

        return $html;
    }
}
