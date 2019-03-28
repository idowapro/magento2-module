<?php

namespace RatePAY\Payment\Block\Form;

class Installment extends Base
{
    protected $allowedMonths = null;

    /**
     * @return bool
     */
    public function isSepaBlockVisible()
    {
        $validPaymentFirstdays = $this->getValidPaymentFirstdays();
        if (is_array($validPaymentFirstdays) || $validPaymentFirstdays == '2') {
            return true;
        }
        return false;
    }

    /**
     * @return array|string
     */
    protected function getValidPaymentFirstdays()
    {
        $validPaymentFirstdays = $this->rpDataHelper->getRpConfigData($this->getMethodCode(), 'valid_payment_firstday');
        if(strpos($validPaymentFirstdays, ',') !== false) {
            $validPaymentFirstdays = explode(',', $validPaymentFirstdays);
        }
        return $validPaymentFirstdays;
    }

    /**
     * @return array
     */
    public function getAllowedMonths()
    {
        if ($this->allowedMonths === null) {
            $allowedMonths = [];
            if ($this->getMethod() instanceof \RatePAY\Payment\Model\Method\AbstractMethod) {
                $allowedMonths = $this->getMethod()->getAllowedMonths($this->getCreateOrderModel()->getQuote()->getGrandTotal());
            }
            $this->allowedMonths = $allowedMonths;
        }
        return $this->allowedMonths;
    }

    /**
     * @return bool
     */
    public function hasAllowedMonths()
    {
        if (!empty($this->getAllowedMonths())) {
            return true;
        }
        return false;
    }

    /**
     * @return string
     */
    public function getRestUrl()
    {
        return $this->_urlBuilder->getDirectUrl('rest/default/V1/carts/mine/ratepay-installmentPlan?isAjax=1');
    }

    /**
     * @return float
     */
    public function getQuoteGrandTotal()
    {
        return $this->getCreateOrderModel()->getQuote()->getGrandTotal();
    }
}
