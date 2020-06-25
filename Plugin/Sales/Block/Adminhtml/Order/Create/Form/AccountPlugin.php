<?php namespace Forter\Forter\Plugin\Sales\Block\Adminhtml\Order\Create\Form;

class AccountPlugin
{
    public function afterToHtml(\Magento\Sales\Block\Adminhtml\Order\Create\Form\Account $subject, $html)
    {
        $newBlockHtml = $subject->getLayout()->createBlock('\Magento\Framework\View\Element\Template')->setTemplate('Forter_Forter::order/view/account.phtml')->toHtml();

        return $html.$newBlockHtml;
    }
}